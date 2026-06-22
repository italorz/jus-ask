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

        $rotaBase = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos?cpfCnpjParte='.$cnpjLimpo;

        // O PDPJ pagina via "searchAfter": cada resposta traz, no topo, os marcadores
        // (data + id do último processo) que devem ser repassados na próxima chamada.
        // Percorremos todas as páginas até esgotar os resultados, acumulando o "content".
        $conteudo = [];
        $searchAfter = null;
        $total = null;
        $pagina = 0;
        $maxPaginas = 100; // trava de segurança contra loop infinito.

        do {
            $rota = $rotaBase;

            if (! empty($searchAfter)) {
                // Vai como "&searchAfter=1739462225999,50069451620248080021".
                $rota .= '&searchAfter='.implode(',', $searchAfter);
            }

            // User-Agent + withoutVerifying evitam o 403 do WAF do PDPJ (mesmo padrão do ProcessoApiService).
            $response = Http::timeout(20)
                ->withToken($token)
                ->withHeaders(['User-Agent' => 'curl/8.19.0'])
                ->withoutVerifying()
                ->get($rota);

            if (! $response->successful()) {
                throw new RequestException($response);
            }

            $json = $response->json();
            $itens = $json['content'] ?? [];
            $total = $json['total'] ?? $total;

            if (empty($itens)) {
                break;
            }

            $conteudo = array_merge($conteudo, $itens);
            $searchAfter = $json['searchAfter'] ?? null;
            $pagina++;
        } while (
            ! empty($searchAfter)
            && count($conteudo) < ($total ?? PHP_INT_MAX)
            && $pagina < $maxPaginas
        );

        return [
            'cnpj' => $cnpjLimpo,
            'tenant' => $tenant,
            'data' => [
                'total' => $total ?? count($conteudo),
                'numberOfElements' => count($conteudo),
                'paginas' => $pagina,
                'content' => $conteudo,
            ],
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
