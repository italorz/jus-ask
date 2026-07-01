<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Notificacao;
use App\Models\Processo;
use App\Models\ProcessoConteudo;
use App\Models\ProcessoCliente;
use App\Models\ProcessoContato;
use App\Models\TokenCnj;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProcessoApiService
{
    private const API_URL = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos?numeroProcesso=';

    /**
     * Códigos CNJ/TPU de ENCERRAMENTO (último movimento) que indicam processo concluído.
     */
    private const CODIGOS_ENCERRAMENTO = [
        22 => 'Baixa Definitiva',
        246 => 'Arquivado Definitivamente',
        848 => 'Trânsito em Julgado',
        849 => 'Trânsito em Julgado',
    ];

    static function limparNumero(string $numero): string
    {
        // Mantém só os dígitos (ex.: "1038079-15.2024.8.26.0576" -> "10380791520248260576"),
        // garantindo a mesma URL que o PDPJ aceita: ?numeroProcesso=<somente dígitos>.
        return preg_replace('/\D+/', '', $numero) ?? '';
    }

    /**
     * Retorna um cliente HTTP já configurado com proxy (se PDPJ_PROXY_URL estiver definido).
     * Formato da variável: http://usuario:senha@host:porta  ou  http://host:porta
     */
    private static function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $proxyUrl = env('PDPJ_PROXY_URL');

        $client = Http::timeout(15);

        if ($proxyUrl) {
            $client = $client->withOptions(['proxy' => $proxyUrl]);
        }

        return $client;
    }

    static function resolverToken(?string $tenant = null): string
    {
        $raw = TokenCnj::query()->where('tenant', $tenant)->latest()->value('token')
            ?? env('TOKEN_API_PROCESSO', '');

        return trim(str_replace('Bearer ', '', (string) $raw));
    }

    static function consultarApi(?string $processo = null, ?string $tenant = null): ?array
    {
        if (empty($processo)) {
            throw new \RuntimeException('Número do processo é obrigatório.');
        }

        $token = self::resolverToken($tenant);

        if ($token === '') {
            throw new \RuntimeException('Nenhum token CNJ esta cadastrado para consultar o PDPJ.');
        }

        $numeroLimpo = self::limparNumero($processo);
        $tentativas  = 3;
        $response    = null;
        $backoffs    = [3, 6];

        try {
            for ($i = 1; $i <= $tentativas; $i++) {
                $response = self::httpClient()
                    ->withToken($token)
                    ->withHeaders(['User-Agent' => 'curl/8.19.0'])
                    ->withoutVerifying()
                    ->get(self::API_URL . $numeroLimpo);

                if ($response->status() !== 429) {
                    break;
                }

                // Respeita Retry-After se existir; senão usa backoff curto
                $wait = (int) ($response->header('Retry-After') ?: ($backoffs[$i - 1] ?? 10));
                logger()->info('ProcessoApiService: 429 recebido, aguardando antes de tentar novamente', [
                    'numero'    => $processo,
                    'tentativa' => $i,
                    'aguardar'  => $wait,
                ]);

                if ($i < $tentativas) {
                    sleep($wait);
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            logger()->warning('ProcessoApiService: falha de conexão (proxy ou rede)', [
                'numero' => $processo,
                'tenant' => $tenant,
                'erro'   => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            logger()->warning('ProcessoApiService: resposta não-2xx', [
                'numero' => $processo,
                'tenant' => $tenant,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;
        }

        $json    = $response->json();
        $content = $json['content'][0] ?? null;
        $tram    = $content['tramitacoes'][0] ?? null;

        if (! $content || ! $tram) {
            logger()->warning('ProcessoApiService: estrutura inesperada no JSON', [
                'numero' => $processo,
                'tenant' => $tenant,
            ]);

            return null;
        }

        return $json;
    }

    /**
     * Cadastra/atualiza um processo PELO NÚMERO, sempre considerando o tenant:
     * se o número já existir no tenant, atualiza; senão, cria. Em seguida consulta o
     * PDPJ e define ativo conforme a situação (concluído → inativo; em andamento → ativo).
     *
     * @return array{processo: Processo, novo: bool, sincronizado: bool}
     */
    static function registrarPorNumero(string $numero, string $tenant, int $empresaId, ?int $clienteId = null): array
    {
        $numeroLimpo = self::limparNumero($numero);

        $processo = Processo::withoutGlobalScopes()
            ->where('tenant', $tenant)
            ->whereRaw("regexp_replace(numero, '\\D', '', 'g') = ?", [$numeroLimpo])
            ->first();

        $novo = false;

        if (! $processo) {
            $novo = true;
            $processo = Processo::withoutGlobalScopes()->create([
                'tenant' => $tenant,
                'empresa_id' => $empresaId,
                'cliente_id' => $clienteId,
                'numero' => $numero,
                'ativo' => true,
            ]);
        } elseif ($clienteId && empty($processo->cliente_id)) {
            // vincula ao cliente informado se ainda não tinha
            $processo->update(['cliente_id' => $clienteId]);
        }

        $sincronizado = self::consultarESalvar($processo);

        return ['processo' => $processo->refresh(), 'novo' => $novo, 'sincronizado' => $sincronizado];
    }

    /**
     * Consulta e grava um snapshot (uso no CADASTRO por número): define o "ativo"
     * conforme a análise do último movimento. Retorna true se sincronizou.
     */
    static function consultarESalvar(Processo $processo): bool
    {
        $json = self::consultarApi($processo->numero, $processo->tenant);

        if ($json === null) {
            return false;
        }

        self::persistirSnapshot($processo, $json, definirAtivo: true);

        return true;
    }

    static function sincronizarComVerificacao(Processo $processo): array
    {
        $json = self::consultarApi($processo->numero, $processo->tenant);

        if ($json === null) {
            return ['atualizado' => false, 'tamanho_anterior' => 0, 'tamanho_novo' => 0];
        }

        $tamanhoNovo = strlen(json_encode($json));

        $ultimo = ProcessoConteudo::withoutGlobalScopes()
            ->where('processo_id', $processo->id)
            ->latest()
            ->first();

        if ($ultimo === null) {
            self::persistirSnapshot($processo, $json);

            return ['atualizado' => false, 'tamanho_anterior' => 0, 'tamanho_novo' => $tamanhoNovo];
        }

        $tamanhoAtual = strlen(json_encode($ultimo->conteudo_json));

        if ($tamanhoNovo <= $tamanhoAtual) {
            return ['atualizado' => false, 'tamanho_anterior' => $tamanhoAtual, 'tamanho_novo' => $tamanhoNovo];
        }

        self::persistirSnapshot($processo, $json);
        self::criarNotificacao($processo, $tamanhoAtual, $tamanhoNovo);
        self::notificarWhatsapp($processo);

        return ['atualizado' => true, 'tamanho_anterior' => $tamanhoAtual, 'tamanho_novo' => $tamanhoNovo];
    }

    /**
     * Notificação por WhatsApp (Evolution API) ao(s) número(s) de contato do processo.
     * Funciona apenas como NOTIFICAÇÃO e é global (uma instância Evolution, sem vínculo
     * de tenant). O link aponta para o WhatsApp do advogado/firma dono do processo.
     */
    static function notificarWhatsapp(Processo $processo): void
    {
        try {
            // Números cadastrados diretamente no processo (legado)
            $numerosLegado = ProcessoContato::withoutGlobalScopes()
                ->where('processo_id', $processo->id)
                ->where('tipo', 'telefone')
                ->pluck('valor');

            // Telefones dos clientes vinculados com canal whatsapp/ambos
            $numerosClientes = ProcessoCliente::withoutGlobalScopes()
                ->with('cliente')
                ->where('processo_id', $processo->id)
                ->whereIn('canal_notificacao', ['whatsapp', 'ambos'])
                ->get()
                ->map(fn ($pc) => preg_replace('/\D+/', '', (string) ($pc->cliente?->telefone ?? '')))
                ->filter();

            $numeros = $numerosLegado->merge($numerosClientes)->filter()->unique();

            if ($numeros->isEmpty()) {
                return;
            }

            $whatsAdvogado = Empresa::where('id', $processo->empresa_id)->value('whatsapp');
            $linkAdvogado = $whatsAdvogado
                ? 'https://wa.me/' . preg_replace('/\D+/', '', (string) $whatsAdvogado)
                : null;

            $mensagem = "Oba! 🎉 Seu processo {$processo->numero} foi atualizado.\n\n"
                . ($linkAdvogado
                    ? "Entre em contato com o seu advogado responsável: {$linkAdvogado}"
                    : 'Entre em contato com o seu advogado responsável.');

            $whatsapp = app(\App\Services\EvolutionWhatsappService::class);

            foreach ($numeros as $numero) {
                $whatsapp->enviarTexto((string) $numero, $mensagem);
            }
        } catch (\Throwable $e) {
            logger()->warning('Notificação WhatsApp falhou', [
                'processo_id' => $processo->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sincronização MANUAL (forçada): ignora a barreira de "sem novas atualizações"
     * (comparação de tamanho) e sempre grava o snapshot atual do PDPJ. Usado pelos
     * botões "Sincronizar" — o usuário está forçando de propósito.
     *
     * @return array{ok: bool, processo?: Processo}
     */
    static function sincronizarForcado(Processo $processo): array
    {
        $json = self::consultarApi($processo->numero, $processo->tenant);

        if ($json === null) {
            return ['ok' => false];
        }

        self::persistirSnapshot($processo, $json);

        return ['ok' => true, 'processo' => $processo->refresh()];
    }

    /**
     * Ativa um processo para monitoramento: antes, faz uma consulta ao CNJ POR NÚMERO
     * para pegar o estado mais atualizado; depois marca ativo=true.
     *
     * @return array{ok: bool, processo?: Processo}
     */
    static function ativar(Processo $processo): array
    {
        $res = self::sincronizarForcado($processo); // pega o mais atualizado por número
        $processo->update(['ativo' => true]);

        return ['ok' => $res['ok'] ?? false, 'processo' => $processo->refresh()];
    }

    static function verificarProcessosAtivos(): int
    {
        // Todas as configurações lêem env vars; os defaults são conservadores.
        $delayMs       = max(1000, (int) env('PDPJ_VERIFY_DELAY_MS',        3000));  // entre processos
        $pauseApos     = max(1,    (int) env('PDPJ_VERIFY_PAUSA_APOS',        10));  // a cada N processos
        $pauseMs       = max(5000, (int) env('PDPJ_VERIFY_PAUSA_MS',        20000)); // pausa longa
        $cooldownHoras = max(1,    (int) env('PDPJ_VERIFY_COOLDOWN_HORAS',      4)); // não reverifica em X h

        $atualizados = 0;
        $verificados = 0;
        $pulados     = 0;

        Processo::withoutGlobalScopes()
            ->where('ativo', true)
            ->chunk(50, function ($processos) use (
                &$atualizados, &$verificados, &$pulados,
                $delayMs, $pauseApos, $pauseMs, $cooldownHoras
            ) {
                foreach ($processos as $processo) {
                    // Não reverifica processo checado recentemente (cooldown por Redis).
                    $cooldownKey = 'proc_verificado:' . $processo->id;
                    if (Cache::has($cooldownKey)) {
                        $pulados++;
                        continue;
                    }

                    // Delay entre processos (com jitter ±500 ms).
                    if ($verificados > 0) {
                        $jitter = rand(-500, 500);
                        usleep(max(1000, $delayMs + $jitter) * 1000);
                    }

                    // Pausa longa a cada N processos verificados (com jitter ±2 s).
                    if ($verificados > 0 && $verificados % $pauseApos === 0) {
                        $jitterLote = rand(-2000, 2000);
                        usleep(max(5000, $pauseMs + $jitterLote) * 1000);
                        logger()->info('ProcessoApiService: pausa de lote', [
                            'verificados_ate_agora' => $verificados,
                            'pausa_ms'              => $pauseMs,
                        ]);
                    }

                    try {
                        $res = self::sincronizarComVerificacao($processo);
                        if ($res['atualizado']) {
                            $atualizados++;
                        }
                        Cache::put($cooldownKey, true, now()->addHours($cooldownHoras));
                    } catch (\Throwable $e) {
                        logger()->error('ProcessoApiService: falha ao verificar processo', [
                            'processo_id' => $processo->id,
                            'numero'      => $processo->numero,
                            'tenant'      => $processo->tenant,
                            'erro'        => $e->getMessage(),
                        ]);
                    }

                    $verificados++;
                }
            });

        logger()->info('ProcessoApiService: verificação concluída', [
            'atualizados'      => $atualizados,
            'verificados'      => $verificados,
            'pulados_cooldown' => $pulados,
        ]);

        return $atualizados;
    }

    /**
     * Deriva a situação (concluido | em_andamento) a partir do código do último movimento.
     */
    static function situacaoPorMovimento(?int $codigo): string
    {
        return $codigo !== null && isset(self::CODIGOS_ENCERRAMENTO[$codigo]) ? 'concluido' : 'em_andamento';
    }

    /**
     * Espelha campos no processo e grava um snapshot. Quando $definirAtivo=true (cadastro
     * por número), o "ativo" é definido pela situação: concluído → inativo; andamento → ativo.
     */
    static function persistirSnapshot(Processo $processo, array $json, bool $definirAtivo = false): void
    {
        $content = $json['content'][0];
        $tram    = $content['tramitacoes'][0];

        $dataAjuizamento        = isset($tram['dataHoraAjuizamento']) ? Carbon::parse($tram['dataHoraAjuizamento']) : null;
        $dataUltimaDistribuicao = isset($tram['dataHoraUltimaDistribuicao']) ? Carbon::parse($tram['dataHoraUltimaDistribuicao']) : null;
        $dataUltimoMovimento    = isset($tram['ultimoMovimento']['dataHora']) ? Carbon::parse($tram['ultimoMovimento']['dataHora']) : null;
        $valorAcao              = $tram['valorAcao'] ?? null;
        $assunto                = $tram['assunto'][0]['descricao'] ?? null;
        $tribunal               = $tram['tribunal']['sigla'] ?? ($content['siglaTribunal'] ?? null);
        $classe                 = $tram['classe'][0]['descricao'] ?? null;

        $movCodigo    = $tram['ultimoMovimento']['codigo'] ?? null;
        $movDescricao = $tram['ultimoMovimento']['descricao'] ?? null;
        $situacao     = self::situacaoPorMovimento($movCodigo !== null ? (int) $movCodigo : null);

        $dados = [
            'data_hora_ajuizamento'         => $dataAjuizamento,
            'valor_acao'                    => $valorAcao,
            'data_hora_ultima_distribuicao' => $dataUltimaDistribuicao,
            'assunto'                       => $assunto,
            'tribunal'                      => $tribunal,
            'classe'                        => $classe,
            'situacao'                      => $situacao,
            'ultimo_movimento_codigo'       => $movCodigo,
            'ultimo_movimento'              => $movCodigo ? trim($movCodigo . ' - ' . $movDescricao) : $movDescricao,
            'ultimo_movimento_em'           => $dataUltimoMovimento,
            'ultima_atualizacao'            => $dataUltimoMovimento,
        ];

        if ($definirAtivo) {
            $dados['ativo'] = $situacao === 'em_andamento';
        }

        $processo->update($dados);

        ProcessoConteudo::create([
            'processo_id'                   => $processo->id,
            'empresa_id'                    => $processo->empresa_id,
            'tenant'                        => $processo->tenant,
            'numero_processo'               => $content['numeroProcesso'] ?? null,
            'data_hora_ajuizamento'         => $dataAjuizamento,
            'valor_acao'                    => $valorAcao,
            'data_hora_ultima_distribuicao' => $dataUltimaDistribuicao,
            'assunto'                       => $assunto,
            'conteudo_json'                 => $json,
        ]);
    }

    static function criarNotificacao(Processo $processo, int $tamanhoAnterior, int $tamanhoNovo): void
    {
        Notificacao::create([
            'processo_id' => $processo->id,
            'empresa_id'  => $processo->empresa_id,
            'tenant'      => $processo->tenant,
            'titulo'      => "Processo {$processo->numero} atualizado",
            'mensagem'    => "O processo {$processo->numero} teve atualização na API PDPJ "
                . "({$tamanhoAnterior} → {$tamanhoNovo} caracteres).",
        ]);
    }
}
