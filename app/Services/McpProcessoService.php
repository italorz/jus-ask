<?php

namespace App\Services;

use App\Models\TokenCnj;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class McpProcessoService
{
    static function consultarPorCnpj($cnpj = NULL, $tenant = NULL)
    {
        $cnpjLimpo = preg_replace('/\D+/', '', $cnpj) ?? '';

        // Sempre usa o ÚLTIMO token CNJ gerado: filtra pelo tenant quando informado;
        // quando não há tenant (ex.: MCP/rota sem tenant) usa o último token de qualquer tenant.
        $query = TokenCnj::query();

        if (! empty($tenant)) {
            $query->where('tenant', $tenant);
        }

        $token = $query->latest()->value('token');

        if (empty($cnpjLimpo) || strlen($cnpjLimpo) !== 14) {
            throw new \InvalidArgumentException('Informe um CNPJ valido com 14 digitos.');
        }

        if (empty($token)) {
            throw new \RuntimeException('Nenhum token CNJ esta cadastrado para consultar o PDPJ.');
        }

        $token = trim(str_replace('Bearer ', '', (string) $token));

        $rota = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos?cpfCnpjParte='.$cnpjLimpo;

        // User-Agent + withoutVerifying evitam o 403 do WAF do PDPJ (mesmo padrão do ProcessoApiService).
        $response = Http::timeout(20)
            ->withToken($token)
            ->withHeaders(['User-Agent' => 'curl/8.19.0'])
            ->withoutVerifying()
            ->get($rota);

        if (! $response->successful()) {
            throw new RequestException($response);
        }

        return [
            'cnpj' => $cnpjLimpo,
            'tenant' => $tenant,
            'data' => $response->json(),
        ];
    }

    /**
     * Consulta + resumo num passo só. Usado tanto pela tool MCP quanto pela tela web,
     * garantindo que os dois retornem exatamente a mesma estrutura.
     *
     * @return array<string, mixed>
     */
    static function consultarResumoPorCnpj($cnpj = null, $tenant = null): array
    {
        $resultado = self::consultarPorCnpj($cnpj, $tenant);

        return self::resumir($resultado['cnpj'], $resultado['data']['content'] ?? []);
    }

    /**
     * Transforma a lista crua do PDPJ em resumo por processo + agregações para gráficos.
     *
     * @param array<int, array<string, mixed>> $content
     * @return array<string, mixed>
     */
    static function resumir(string $cnpj, array $content): array
    {
        $processos = [];
        $porTribunal = [];
        $porAno = [];
        $porClasse = [];

        foreach ($content as $item) {
            $tram = $item['tramitacoes'][0] ?? [];

            $tribunal = $tram['tribunal']['nome'] ?? ($item['siglaTribunal'] ?? 'Não informado');
            $sigla = $tram['tribunal']['sigla'] ?? ($item['siglaTribunal'] ?? null);
            $classe = $tram['classe'][0]['descricao'] ?? 'Não informada';
            $assunto = $tram['assunto'][0]['descricao'] ?? null;
            $ano = isset($tram['dataHoraAjuizamento'])
                ? substr((string) $tram['dataHoraAjuizamento'], 0, 4)
                : 'Não informado';

            $processos[] = [
                'numero' => $item['numeroProcesso'] ?? null,
                'tribunal' => $tribunal,
                'sigla' => $sigla,
                'classe' => $classe,
                'assunto' => $assunto,
                'valor_acao' => $tram['valorAcao'] ?? null,
                'ano_ajuizamento' => $ano,
                'orgao_julgador' => $tram['orgaoJulgador']['nome'] ?? null,
            ];

            $porTribunal[$sigla ?? $tribunal] = ($porTribunal[$sigla ?? $tribunal] ?? 0) + 1;
            $porAno[$ano] = ($porAno[$ano] ?? 0) + 1;
            $porClasse[$classe] = ($porClasse[$classe] ?? 0) + 1;
        }

        ksort($porAno);
        arsort($porTribunal);
        arsort($porClasse);

        return [
            'cnpj' => $cnpj,
            'total' => count($processos),
            'processos' => $processos,
            'agregacoes' => [
                'por_tribunal' => $porTribunal,
                'por_ano' => $porAno,
                'por_classe' => $porClasse,
            ],
        ];
    }
}
