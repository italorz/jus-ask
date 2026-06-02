<?php

namespace App\Services;

use App\Models\Notificacao;
use App\Models\Processo;
use App\Models\ProcessoContato;
use App\Models\ProcessoConteudo;
use App\Models\TokenCnj;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ProcessoApiService
{
    private const API_URL = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos?numeroProcesso=';

    static function limparNumero(string $numero): string
    {
        return preg_replace('/[.\\/\-]/', '', $numero);
    }

    /**
     * Consulta a API e persiste dados — sem comparação (uso no cadastro individual).
     * Sempre grava um novo snapshot em processos_conteudos.
     */
    static function consultarESalvar(Processo $processo): bool
    {
        try {
            $json = self::consultarApi($processo);

            if ($json === null) {
                return false;
            }

            self::persistirSnapshot($processo, $json);

            return true;
        } catch (\Throwable $e) {
            logger()->error('ProcessoApiService: exceção ao consultar API', [
                'processo_id' => $processo->id,
                'erro'        => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Consulta a API e compara com o último snapshot em processos_conteudos.
     * Só grava e notifica quando a API retorna mais dados (em caracteres) do que o armazenado.
     *
     * @return array{atualizado: bool, tamanho_anterior: int, tamanho_novo: int, erro?: string}
     */
    static function sincronizarComVerificacao(Processo $processo): array
    {
        try {
            $json = self::consultarApi($processo);

            if ($json === null) {
                return ['atualizado' => false, 'tamanho_anterior' => 0, 'tamanho_novo' => 0, 'erro' => 'Falha na API'];
            }

            $jsonString  = json_encode($json);
            $tamanhoNovo = strlen($jsonString);

            // Busca último snapshot sem escopo de tenant (sync roda fora de contexto HTTP)
            $ultimo = ProcessoConteudo::withoutGlobalScopes()
                ->where('processo_id', $processo->id)
                ->latest()
                ->first();

            $tamanhoAtual = 0;
            if ($ultimo && ! empty($ultimo->conteudo_json)) {
                $stored       = is_string($ultimo->conteudo_json)
                    ? $ultimo->conteudo_json
                    : json_encode($ultimo->conteudo_json);
                $tamanhoAtual = strlen($stored);
            }

            // Sem mudança: API não tem dados novos
            if ($tamanhoNovo <= $tamanhoAtual && $ultimo !== null) {
                return ['atualizado' => false, 'tamanho_anterior' => $tamanhoAtual, 'tamanho_novo' => $tamanhoNovo];
            }

            // Há dados novos: persiste e notifica
            self::persistirSnapshot($processo, $json, explicit: true);
            self::criarNotificacao($processo, $tamanhoAtual, $tamanhoNovo);

            return ['atualizado' => true, 'tamanho_anterior' => $tamanhoAtual, 'tamanho_novo' => $tamanhoNovo];
        } catch (\Throwable $e) {
            logger()->error('ProcessoApiService: exceção na sincronização com verificação', [
                'processo_id' => $processo->id,
                'erro'        => $e->getMessage(),
            ]);

            return ['atualizado' => false, 'tamanho_anterior' => 0, 'tamanho_novo' => 0, 'erro' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────────

    static function consultarApi(Processo $processo): ?array
    {
        $token       = self::resolverToken();
        $numeroLimpo = self::limparNumero($processo->numero);

        $response = Http::timeout(15)
            ->withToken($token)
            ->withHeaders(['User-Agent' => 'curl/8.19.0'])
            ->withoutVerifying()
            ->get(self::API_URL, ['numeroProcesso' => $numeroLimpo]);

        if (! $response->successful()) {
            logger()->warning('ProcessoApiService: resposta não-2xx', [
                'processo_id' => $processo->id,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);

            return null;
        }

        $json    = $response->json();
        $content = $json['content'][0] ?? null;
        $tram    = $content['tramitacoes'][0] ?? null;

        if (! $content || ! $tram) {
            logger()->warning('ProcessoApiService: estrutura inesperada no JSON', [
                'processo_id' => $processo->id,
            ]);

            return null;
        }

        return $json;
    }

    /**
     * Espelha campos no processo e grava novo snapshot em processos_conteudos.
     *
     * @param bool $explicit Quando true, passa empresa_id/tenant explicitamente
     *                       (necessário fora de contexto de tenant ativo).
     */
    static function persistirSnapshot(Processo $processo, array $json, bool $explicit = false): void
    {
        $content = $json['content'][0];
        $tram    = $content['tramitacoes'][0];

        $dataAjuizamento        = isset($tram['dataHoraAjuizamento'])
            ? Carbon::parse($tram['dataHoraAjuizamento'])
            : null;
        $dataUltimaDistribuicao = isset($tram['dataHoraUltimaDistribuicao'])
            ? Carbon::parse($tram['dataHoraUltimaDistribuicao'])
            : null;
        $dataUltimoMovimento    = isset($tram['ultimoMovimento']['dataHora'])
            ? Carbon::parse($tram['ultimoMovimento']['dataHora'])
            : null;
        $valorAcao  = $tram['valorAcao'] ?? null;
        $assunto    = $tram['assunto'][0]['descricao'] ?? null;
        $numeroApi  = $content['numeroProcesso'] ?? null;

        $processo->update([
            'data_hora_ajuizamento'         => $dataAjuizamento,
            'valor_acao'                    => $valorAcao,
            'data_hora_ultima_distribuicao' => $dataUltimaDistribuicao,
            'assunto'                       => $assunto,
            'ultima_atualizacao'            => $dataUltimoMovimento,
        ]);

        $dados = [
            'processo_id'                   => $processo->id,
            'numero_processo'               => $numeroApi,
            'data_hora_ajuizamento'         => $dataAjuizamento,
            'valor_acao'                    => $valorAcao,
            'data_hora_ultima_distribuicao' => $dataUltimaDistribuicao,
            'assunto'                       => $assunto,
            'conteudo_json'                 => $json,
        ];

        if ($explicit) {
            $dados['empresa_id'] = $processo->empresa_id;
            $dados['tenant']     = $processo->tenant;
        }

        ProcessoConteudo::create($dados);
    }

    static function criarNotificacao(Processo $processo, int $tamanhoAnterior, int $tamanhoNovo): void
    {
        $mensagem = "O processo {$processo->numero} foi atualizado na API PDPJ. "
            . "Dados anteriores: {$tamanhoAnterior} caracteres. "
            . "Dados atuais: {$tamanhoNovo} caracteres.";

        Notificacao::create([
            'processo_id' => $processo->id,
            'empresa_id'  => $processo->empresa_id,
            'tenant'      => $processo->tenant,
            'titulo'      => "Processo {$processo->numero} atualizado",
            'mensagem'    => $mensagem,
        ]);

        // Envia e-mails para os contatos cadastrados
        $emails = ProcessoContato::withoutGlobalScopes()
            ->where('processo_id', $processo->id)
            ->where('tipo', 'email')
            ->pluck('valor');

        foreach ($emails as $email) {
            try {
                Mail::raw($mensagem, function ($msg) use ($email, $processo) {
                    $msg->to($email)
                        ->subject("Atualização detectada — Processo {$processo->numero}");
                });
            } catch (\Throwable $e) {
                logger()->warning('ProcessoApiService: falha ao enviar e-mail de notificação', [
                    'email'       => $email,
                    'processo_id' => $processo->id,
                    'erro'        => $e->getMessage(),
                ]);
            }
        }
    }

    static function resolverToken(): string
    {
        $raw = TokenCnj::latest()->value('token')
            ?? env('TOKEN_API_PROCESSO', '');

        return trim(preg_replace('/^bearer\s+/i', '', (string) $raw));
    }
}
