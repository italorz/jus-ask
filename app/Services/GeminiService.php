<?php

namespace App\Services;

use App\Models\ChaveGemini;
use App\Models\Cliente;
use App\Models\ConteudoProcesso;
use App\Models\Processo;
use App\Models\ProcessoConteudo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    private const MODEL = 'gemini-2.5-flash-lite';
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    // ─────────────────────────────────────────────────────────────────────
    // System Prompt
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Monta o system prompt com os dados dos processos do cliente.
     * Usa withoutGlobalScopes() porque TenantManager pode não estar ativo.
     */
    public function buildSystemPrompt(int $empresaId, int $clienteId): string
    {
        $cliente = Cliente::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->findOrFail($clienteId);

        $processos = Processo::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('cliente_id', $clienteId)
            ->get();

        if ($processos->isEmpty()) {
            return <<<PROMPT
Você é um assistente jurídico virtual. O cliente {$cliente->nome} não possui processos cadastrados no sistema.
Informe educadamente que nenhum processo foi encontrado e oriente-o a entrar em contato com o escritório.
Recuse qualquer pergunta sobre outros assuntos.
PROMPT;
        }

        $processosText = $processos
            ->map(fn (Processo $p) => $this->buildProcessoText($p, $empresaId))
            ->implode("\n\n---\n\n");

        return <<<PROMPT
Você é um assistente jurídico virtual. Você tem acesso exclusivamente às informações dos processos jurídicos do cliente abaixo.

CLIENTE: {$cliente->nome}

PROCESSOS DO CLIENTE:
{$processosText}

REGRAS ESTRITAS QUE VOCÊ DEVE SEGUIR:
1. Responda APENAS perguntas relacionadas aos processos listados acima.
2. Se o usuário perguntar sobre qualquer outro assunto (clima, receitas, política, outros casos, etc.), recuse educadamente e redirecione ao tema dos processos.
3. Nunca invente informações. Use apenas o que está documentado acima.
4. Se não souber a resposta com base nas informações disponíveis, diga isso claramente.
5. Seja conciso, claro e profissional.
6. Você está conversando com o próprio cliente {$cliente->nome}.
7. Nunca revele dados de outros clientes ou processos de outras pessoas.
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Chat
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Envia uma mensagem ao Gemini usando a chave vinculada ao cliente
     * (ou à empresa como fallback). Lança exceção em caso de falha.
     *
     * @param  int    $empresaId
     * @param  int    $clienteId   ID do cliente para buscar a chave vinculada
     * @param  string $systemPrompt
     * @param  array  $history     [['role' => 'user'|'model', 'text' => string], ...]
     * @param  string $userMessage
     * @return string
     *
     * @throws \RuntimeException|\Throwable
     */
    public function chat(
        int    $empresaId,
        int    $clienteId,
        string $systemPrompt,
        array  $history,
        string $userMessage,
    ): string {
        $apiKey = $this->resolverChave($empresaId, $clienteId);

        $contents = array_map(
            fn ($turn) => [
                'role'  => $turn['role'],
                'parts' => [['text' => $turn['text']]],
            ],
            $history,
        );

        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        $url = self::API_BASE . self::MODEL . ':generateContent?key=' . $apiKey;

        try {
            $response = Http::timeout(30)->post($url, [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents'           => $contents,
                'generationConfig'   => ['thinkingConfig' => ['thinkingBudget' => 0]],
            ]);

            if ($response->failed()) {
                logger()->error('GeminiService: falha na API', [
                    'empresa_id' => $empresaId,
                    'cliente_id' => $clienteId,
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                ]);
                throw new \RuntimeException('Erro na API Gemini: ' . $response->status());
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            if (! is_string($text) || $text === '') {
                throw new \RuntimeException('Resposta vazia recebida da API Gemini.');
            }

            return $text;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error('GeminiService: exceção inesperada', [
                'empresa_id' => $empresaId,
                'cliente_id' => $clienteId,
                'erro'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resolve a chave da API: primeiro tenta a chave vinculada ao cliente,
     * depois qualquer chave da empresa.
     */
    private function resolverChave(int $empresaId, int $clienteId): string
    {
        // 1. Chave específica do cliente
        if ($clienteId > 0) {
            $cliente = Cliente::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->select(['chave_gemini_id'])
                ->find($clienteId);

            if ($cliente?->chave_gemini_id) {
                $chave = ChaveGemini::withoutGlobalScopes()
                    ->where('empresa_id', $empresaId)
                    ->where('id', $cliente->chave_gemini_id)
                    ->value('chave');
                    // dd($chave);
                if ($chave) {
                    
                    return $chave;
                }
            }
        }

        // 2. Fallback: qualquer chave da empresa
        $chave = ChaveGemini::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->value('chave');

        if ($chave) {
            return $chave;
        }

        // 3. Fallback: variável de ambiente
        $chave = env('GEMINI_API_KEY');

        if ($chave) {
            return $chave;
        }

        throw new \RuntimeException(
            'Nenhuma chave Gemini configurada para este escritório. ' .
            'Acesse o menu "Chaves Gemini" para adicionar uma chave.'
        );
    }

    private function buildProcessoText(Processo $p, int $empresaId): string
    {
        $text  = "PROCESSO: {$p->numero}\n";
        $text .= 'Situação: ' . ($p->encerrado ? 'Encerrado' : 'Em andamento') . "\n";

        if ($p->assunto)               $text .= "Assunto: {$p->assunto}\n";
        if ($p->valor_acao)            $text .= 'Valor da ação: R$ ' . number_format((float) $p->valor_acao, 2, ',', '.') . "\n";
        if ($p->data_hora_ajuizamento) $text .= 'Data de ajuizamento: ' . $p->data_hora_ajuizamento->format('d/m/Y') . "\n";
        if ($p->ultima_atualizacao)    $text .= 'Última atualização: ' . $p->ultima_atualizacao->format('d/m/Y') . "\n";

        // Anotações manuais (ConteudoProcesso)
        $anotacoes = ConteudoProcesso::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('processo_id', $p->id)
            ->get();

        if ($anotacoes->isNotEmpty()) {
            $text .= "\nANOTAÇÕES DO ESCRITÓRIO:\n";
            foreach ($anotacoes as $a) {
                $text .= "- {$a->conteudo}\n";
            }
        }

        // Snapshot mais recente da API (ProcessoConteudo)
        $snapshot = ProcessoConteudo::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('processo_id', $p->id)
            ->latest()
            ->first();

        if ($snapshot) {
            $raw = $snapshot->conteudo_json;
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            $content = $raw['content'][0] ?? null;
            $tram    = $content['tramitacoes'][0] ?? null;

            if ($tram) {
                if (! empty($tram['tribunal']['nome'])) {
                    $text .= "Tribunal: {$tram['tribunal']['nome']}\n";
                }
                if (! empty($tram['classe'][0]['descricao'])) {
                    $text .= "Classe: {$tram['classe'][0]['descricao']}\n";
                }
                if (! empty($tram['movimentos'])) {
                    $text .= "\nÚLTIMOS MOVIMENTOS (até 5):\n";
                    foreach (array_slice($tram['movimentos'], 0, 5) as $mov) {
                        $data      = isset($mov['dataHora'])
                            ? Carbon::parse($mov['dataHora'])->format('d/m/Y')
                            : '?';
                        $descricao = $mov['descricao'] ?? ($mov['nome'] ?? 'sem descrição');
                        $text     .= "- [{$data}] {$descricao}\n";
                    }
                }
            }
        }

        return $text;
    }
}
