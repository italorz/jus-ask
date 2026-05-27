<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoConteudo;
use App\Models\TokenCnj;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class ProcessoApiService
{
    private const API_URL = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos';

    /**
     * Remove pontos, barras e traços do número do processo
     * para compatibilidade com a query string da API PDPJ.
     *
     * Ex: "0011632-42.2024.5.15.0033" → "001163242202451500033"
     */
    public function limparNumero(string $numero): string
    {
        return preg_replace('/[.\\/\-]/', '', $numero);
    }

    /**
     * Consulta a API PDPJ e persiste os dados retornados.
     *
     * Salva o JSON bruto + campos extraídos em `processos_conteudos`
     * e espelha os campos-chave diretamente em `processos`.
     *
     * Nunca lança exceção — falhas são logadas silenciosamente
     * para não bloquear o cadastro do processo.
     *
     * @return bool true em sucesso, false em falha
     */
    public function consultarESalvar(Processo $processo): bool
    {
        try {
            $token = $this->resolverToken();
            $numeroLimpo = $this->limparNumero($processo->numero);

            $response = Http::timeout(15)
                ->withToken($token)
                ->withHeaders(['User-Agent' => 'curl/8.19.0'])
                ->withoutVerifying()
                ->get(self::API_URL, [
                    'numeroProcesso' => $numeroLimpo,
                ]);

            if (! $response->successful()) {
                logger()->warning('ProcessoApiService: resposta não-2xx', [
                    'processo_id' => $processo->id,
                    'status'      => $response->status(),
                    'body'        => $response->body(),
                ]);
                return false;
            }

            $json    = $response->json();
            $content = $json['content'][0] ?? null;
            $tram    = $content['tramitacoes'][0] ?? null;

            if (! $content || ! $tram) {
                logger()->warning('ProcessoApiService: estrutura inesperada no JSON', [
                    'processo_id' => $processo->id,
                ]);
                return false;
            }

            $dataAjuizamento       = isset($tram['dataHoraAjuizamento'])
                ? Carbon::parse($tram['dataHoraAjuizamento'])
                : null;

            $dataUltimaDistribuicao = isset($tram['dataHoraUltimaDistribuicao'])
                ? Carbon::parse($tram['dataHoraUltimaDistribuicao'])
                : null;

            $valorAcao  = $tram['valorAcao'] ?? null;
            $assunto    = $tram['assunto'][0]['descricao'] ?? null;
            $numeroApi  = $content['numeroProcesso'] ?? null;

            // Espelha campos-chave na tabela processos
            $processo->update([
                'data_hora_ajuizamento'        => $dataAjuizamento,
                'valor_acao'                   => $valorAcao,
                'data_hora_ultima_distribuicao' => $dataUltimaDistribuicao,
                'assunto'                      => $assunto,
            ]);

            // Grava snapshot completo em processos_conteudos
            // empresa_id e tenant são preenchidos automaticamente pelo BelongsToTenant
            ProcessoConteudo::create([
                'processo_id'                   => $processo->id,
                'numero_processo'               => $numeroApi,
                'data_hora_ajuizamento'         => $dataAjuizamento,
                'valor_acao'                    => $valorAcao,
                'data_hora_ultima_distribuicao' => $dataUltimaDistribuicao,
                'assunto'                       => $assunto,
                'conteudo_json'                 => $json,
            ]);

            return true;
        } catch (\Throwable $e) {
            logger()->error('ProcessoApiService: exceção ao consultar API', [
                'processo_id' => $processo->id,
                'erro'        => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    private function resolverToken(): string
    {
        $raw = TokenCnj::latest()->value('token')
            ?? env('TOKEN_API_PROCESSO', '');

        return trim(preg_replace('/^bearer\s+/i', '', (string) $raw));
    }
}
