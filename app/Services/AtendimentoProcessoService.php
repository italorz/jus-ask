<?php

namespace App\Services;

use App\Models\ChaveGemini;
use App\Models\Cliente;
use App\Models\Empresa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Orquestra o atendimento via WhatsApp:
 *
 * Prioridade 1 — cliente identificado pelo telefone no banco:
 *   Carrega os processos dele como contexto via GeminiService::buildSystemPrompt()
 *   e conversa usando GeminiService::chat(). Histórico mantido no Redis por sessão.
 *
 * Prioridade 2 — número não cadastrado (fallback):
 *   Pede o número CNJ do processo, consulta o PDPJ e responde sobre aquele processo.
 */
class AtendimentoProcessoService
{
    private const API_URL           = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos';
    private const SESSION_TTL_HOURS = 7;
    private const MAX_HISTORY_TURNS = 10; // pares user/model

    public function __construct(private GeminiService $gemini) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Ponto de entrada
    // ─────────────────────────────────────────────────────────────────────────

    public function responder(string $mensagem, ?string $tenant = null, ?string $sessionId = null): string
    {
        $cacheKey = $this->cacheKey($tenant, $sessionId);
        $state    = $cacheKey ? Cache::get($cacheKey, []) : [];

        // ── Tenta identificar o cliente pelo telefone (remoteJid) ─────────────
        // Identifica pelo telefone na primeira mensagem da sessão
        if (empty($state['cliente_id']) && $sessionId) {
            $telefone = $this->extrairTelefone($sessionId);

            if ($telefone) {
                $cliente = $this->buscarClientePorTelefone($telefone, $tenant);

                if ($cliente) {
                    $systemPrompt = GeminiService::buildSystemPrompt($cliente->empresa_id, $cliente->id)
                        . $this->instrucoesPadrao();

                    $state = [
                        'cliente_id'    => $cliente->id,
                        'empresa_id'    => $cliente->empresa_id,
                        'system_prompt' => $systemPrompt,
                        'prompt_em'     => now()->timestamp,
                        'history'       => [],
                    ];

                    if ($cacheKey) {
                        Cache::put($cacheKey, $state, now()->addHours(self::SESSION_TTL_HOURS));
                    }
                }
            }
        }

        // Regenera o system prompt após 1 hora para refletir novos processos cadastrados
        if (! empty($state['cliente_id'])) {
            $promptIdade = now()->timestamp - ($state['prompt_em'] ?? 0);

            if ($promptIdade > 3600) {
                $state['system_prompt'] = GeminiService::buildSystemPrompt($state['empresa_id'], $state['cliente_id'])
                    . $this->instrucoesPadrao();
                $state['prompt_em'] = now()->timestamp;

                if ($cacheKey) {
                    Cache::put($cacheKey, $state, now()->addHours(self::SESSION_TTL_HOURS));
                }
            }
        }

        // ── Modo 1: cliente do banco — processos já carregados como contexto ──
        if (! empty($state['cliente_id'])) {
            return $this->responderComCliente($mensagem, $state, $cacheKey);
        }

        // ── Modo 2: fallback — pede número do processo e consulta PDPJ ────────
        return $this->responderPorNumero($mensagem, $tenant, $sessionId, $state, $cacheKey);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Modo 1: cliente identificado
    // ─────────────────────────────────────────────────────────────────────────

    private function responderComCliente(string $mensagem, array $state, ?string $cacheKey): string
    {
        $history = $state['history'] ?? [];

        try {
            $resposta = $this->gemini->chat(
                empresaId:    $state['empresa_id'],
                clienteId:    $state['cliente_id'],
                systemPrompt: $state['system_prompt'],
                history:      $history,
                userMessage:  $mensagem,
            );

            $history[] = ['role' => 'user',  'text' => $mensagem];
            $history[] = ['role' => 'model', 'text' => $resposta];
            $state['history'] = array_slice($history, -(self::MAX_HISTORY_TURNS * 2));

            if ($cacheKey) {
                Cache::put($cacheKey, $state, now()->addHours(self::SESSION_TTL_HOURS));
            }

            return $resposta;
        } catch (\Throwable $e) {
            logger()->error('AtendimentoProcessoService: falha ao gerar resposta (cliente do banco)', [
                'cliente_id' => $state['cliente_id'],
                'empresa_id' => $state['empresa_id'],
                'erro'       => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'quota')) {
                return 'O assistente está temporariamente indisponível por limite de uso. '
                    . 'Por favor, tente novamente mais tarde. 🙏';
            }

            return 'Tive um problema ao gerar a resposta. Tente novamente em instantes.';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Modo 2: fallback por número de processo (PDPJ)
    // ─────────────────────────────────────────────────────────────────────────

    private function responderPorNumero(
        string  $mensagem,
        ?string $tenant,
        ?string $sessionId,
        array   $state,
        ?string $cacheKey,
    ): string {
        $numero = $this->extrairNumeroProcesso($mensagem);

        if ($numero === null && empty($state['contexto'])) {
            return 'Olá! Não encontrei seu número cadastrado no sistema. '
                . 'Para consultar um processo, envie o *número no formato CNJ* '
                . '(ex.: 0011632-42.2024.5.15.0033) e responderei com base nos dados do CNJ.';
        }

        if ($numero !== null) {
            $json = $this->consultarPdpj($numero, $tenant);

            if ($json === null) {
                return "Não consegui localizar o processo *{$numero}* na base do CNJ agora. "
                    . 'Confira o número e tente novamente em alguns instantes.';
            }

            $state = [
                'numero'   => $numero,
                'contexto' => $this->formatarProcesso($json),
                'history'  => [],
            ];
        }

        $systemPrompt = $this->montarSystemPromptPdpj($state['numero'], $state['contexto']);
        $history      = $state['history'] ?? [];

        try {
            $resposta = $this->gemini->responder(
                $this->resolverChaveGemini($tenant),
                $systemPrompt,
                $mensagem,
                $history,
            );

            $history[] = ['role' => 'user',  'text' => $mensagem];
            $history[] = ['role' => 'model', 'text' => $resposta];
            $state['history'] = array_slice($history, -(self::MAX_HISTORY_TURNS * 2));

            if ($cacheKey) {
                Cache::put($cacheKey, $state, now()->addHours(self::SESSION_TTL_HOURS));
            }

            return $resposta;
        } catch (\Throwable $e) {
            logger()->error('AtendimentoProcessoService: falha ao gerar resposta (PDPJ)', [
                'numero' => $state['numero'] ?? null,
                'tenant' => $tenant,
                'erro'   => $e->getMessage(),
            ]);

            return 'Localizei o processo, mas tive um problema ao gerar a resposta agora. '
                . 'Tente novamente em instantes.';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Identificação pelo telefone do WhatsApp
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extrai dígitos do JID e remove DDI 55.
     * "5514996440809@s.whatsapp.net" → "14996440809"
     */
    private function extrairTelefone(string $sessionId): ?string
    {
        $digits = preg_replace('/\D+/', '', explode('@', $sessionId)[0]) ?? '';

        if ($digits === '') {
            return null;
        }

        return $this->normalizarDigitos($digits);
    }

    /**
     * Busca cliente no banco pelo telefone (comparação só de dígitos).
     * Quando $tenant é nulo, busca em todos os tenants.
     */
    private function buscarClientePorTelefone(string $telefone, ?string $tenant): ?Cliente
    {
        $query = Cliente::withoutGlobalScopes()->whereNotNull('telefone');

        if ($tenant) {
            $query->where('tenant', $tenant);
        }

        return $query->get(['id', 'empresa_id', 'telefone', 'nome'])
            ->first(function (Cliente $c) use ($telefone) {
                $stored = $this->normalizarDigitos(
                    preg_replace('/\D+/', '', (string) $c->telefone) ?? ''
                );

                return $stored === $telefone;
            });
    }

    private function normalizarDigitos(string $digits): string
    {
        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            return substr($digits, 2);
        }

        return $digits;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function instrucoesPadrao(): string
    {
        return <<<'TXT'


INSTRUÇÕES DE FORMATO — WHATSAPP:
10. Você está atendendo via WhatsApp. Use parágrafos curtos (máx. 3–4 linhas cada).
11. Para negrito use *asterisco*. Não use ##, **, [], listas excessivas nem <br>.
12. Seja direto. O cliente prefere respostas concisas e em linguagem simples.
TXT;
    }

    public function extrairNumeroProcesso(string $texto): ?string
    {
        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $texto, $m)) {
            return $m[0];
        }

        $digits = preg_replace('/\D+/', '', $texto);

        if (strlen($digits) >= 20) {
            return substr($digits, 0, 20);
        }

        return null;
    }

    private function consultarPdpj(string $numero, ?string $tenant): ?array
    {
        $token = ProcessoApiService::resolverToken($tenant);

        if ($token === '') {
            return null;
        }

        $numeroLimpo = preg_replace('/\D+/', '', $numero);

        try {
            $response = Http::timeout(20)
                ->withToken($token)
                ->withHeaders(['User-Agent' => 'Jus-Ask-WhatsApp'])
                ->withoutVerifying()
                ->get(self::API_URL, ['numeroProcesso' => $numeroLimpo]);

            if (! $response->successful() || empty($response->json('content.0.tramitacoes.0'))) {
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            logger()->error('AtendimentoProcessoService: exceção ao consultar PDPJ', [
                'numero' => $numero,
                'erro'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function formatarProcesso(array $json): string
    {
        $content = $json['content'][0];
        $tram    = $content['tramitacoes'][0];

        $text = 'NUMERO: ' . ($content['numeroProcesso'] ?? '?') . "\n";

        if (! empty($tram['tribunal']['nome'])) {
            $sigla = ! empty($tram['tribunal']['sigla']) ? " ({$tram['tribunal']['sigla']})" : '';
            $text .= "Tribunal: {$tram['tribunal']['nome']}{$sigla}\n";
        }
        if (! empty($tram['classe'][0]['descricao'])) {
            $text .= "Classe: {$tram['classe'][0]['descricao']}\n";
        }
        if (! empty($tram['assunto'])) {
            $text .= "Assuntos:\n";
            foreach ($tram['assunto'] as $a) {
                $text .= '  - ' . ($a['descricao'] ?? '?') . "\n";
            }
        }
        if (! empty($tram['valorAcao'])) {
            $text .= 'Valor da ação: R$ ' . number_format((float) $tram['valorAcao'], 2, ',', '.') . "\n";
        }
        if (! empty($tram['dataHoraAjuizamento'])) {
            $text .= 'Ajuizamento: ' . Carbon::parse($tram['dataHoraAjuizamento'])->format('d/m/Y') . "\n";
        }
        if (! empty($tram['orgaoJulgador']['nome'])) {
            $text .= "Órgão julgador: {$tram['orgaoJulgador']['nome']}\n";
        }
        if (! empty($tram['partes'])) {
            $text .= "\nPARTES:\n";
            foreach ($tram['partes'] as $p) {
                $text .= "  [{$p['polo']} / {$p['tipoParte']}] {$p['nome']}\n";
            }
        }
        if (! empty($tram['movimentos'])) {
            $text .= "\nMOVIMENTOS (mais recentes primeiro):\n";
            foreach (array_reverse(array_slice($tram['movimentos'], -30)) as $mov) {
                $data = ! empty($mov['dataHora']) ? Carbon::parse($mov['dataHora'])->format('d/m/Y') : '?';
                $desc = $mov['descricao'] ?? ($mov['tipo']['nome'] ?? 'sem descrição');
                $text .= "  [{$data}] {$desc}\n";
            }
        }

        return $text;
    }

    private function montarSystemPromptPdpj(string $numero, string $contexto): string
    {
        return <<<PROMPT
Você é um assistente jurídico virtual que atende clientes pelo WhatsApp.
Você tem acesso exclusivamente às informações do processo abaixo, consultadas na base oficial do CNJ.

DADOS DO PROCESSO {$numero}:
{$contexto}

REGRAS:
1. Responda apenas com base nos dados acima. Nunca invente informações.
2. Se a informação não estiver nos dados, diga que não consta.
3. Seja conciso e use linguagem simples; o cliente é leigo.
4. Ao citar termos jurídicos, explique brevemente entre parênteses.
5. Use *negrito* com asterisco. Não use ##, **, [], <br> nem listas excessivas.
6. Não responda sobre assuntos fora deste processo.
PROMPT;
    }

    private function resolverChaveGemini(?string $tenant): string
    {
        $empresaId = null;

        if ($tenant !== null && trim($tenant) !== '') {
            $empresaId = Empresa::where('tenant', $tenant)->value('id');
        }

        $query = ChaveGemini::withoutGlobalScopes();

        if ($empresaId !== null) {
            $query->where('empresa_id', $empresaId);
        }

        $chave = $query->value('chave') ?? env('GEMINI_API_KEY');

        if (! $chave) {
            throw new \RuntimeException('Nenhuma chave Gemini configurada.');
        }

        return $chave;
    }

    private function cacheKey(?string $tenant, ?string $sessionId): ?string
    {
        if ($sessionId === null || trim($sessionId) === '') {
            return null;
        }

        return 'whatsapp_atendimento:' . sha1(($tenant ?? 'global') . '|' . $sessionId);
    }
}
