<?php

namespace App\Services;

use App\Jobs\ConsultarProcessosCnpjJob;
use App\Models\TokenCnj;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class McpProcessoService
{
    /** Até este nº de páginas (×100 processos) a consulta roda de forma síncrona. */
    public const MAX_PAGINAS_SYNC = 3;

    /** Teto duro de páginas percorridas (trava de segurança). 200 = ~20.000 processos. */
    public const MAX_PAGINAS_TOTAL = 200;

    /** Quantos processos detalhados são devolvidos na amostra (o resto vira só agregação). */
    public const AMOSTRA_MAX = 200;

    private const PAGINA_TIMEOUT = 20;

    /** Máximo de retentativas por página em caso de 429/5xx. */
    private const MAX_RETRY = 5;

    /** Acima deste total de processos, aplica intervalo entre páginas para não tomar 429. */
    public const THROTTLE_ACIMA_DE = 500;

    /**
     * Ponto de entrada único (tool MCP, página web e endpoint REST).
     *
     * - Resultado já em cache  -> devolve na hora (done/processing/error).
     * - Consulta pequena        -> roda síncrono e devolve "done".
     * - Consulta grande         -> dispara job na fila e devolve "processing".
     *
     * @return array<string, mixed>
     */
    public static function consultar(string $cnpj, ?string $tenant = null, bool $atualizar = false): array
    {
        $cnpjLimpo = preg_replace('/\D+/', '', $cnpj) ?? '';

        if (strlen($cnpjLimpo) !== 14) {
            throw new \InvalidArgumentException('Informe um CNPJ valido com 14 digitos.');
        }

        $key = self::cacheKey($cnpjLimpo, $tenant);

        if (! $atualizar && ($cache = Cache::get($key))) {
            return $cache;
        }

        $token = self::tokenPara($tenant);

        if (empty($token)) {
            throw new \RuntimeException('Nenhum token CNJ esta cadastrado para consultar o PDPJ.');
        }

        // Primeira página: descobre o total para decidir síncrono x assíncrono.
        $primeira = self::buscarPagina($cnpjLimpo, $token, null);
        $total = (int) ($primeira['total'] ?? count($primeira['content'] ?? []));

        // Pequeno: coleta tudo agora mesmo.
        if ($total <= self::MAX_PAGINAS_SYNC * 100) {
            $resultado = self::coletarTudo($cnpjLimpo, $token, $primeira, self::MAX_PAGINAS_TOTAL);
            $resultado['status'] = 'done';
            Cache::put($key, $resultado, now()->addMinutes(60));

            return $resultado;
        }

        // Grande: processa em segundo plano.
        $minutos = max(1, (int) ceil($total / 100 * 5 / 60)); // ~5s por página de 100.

        $processing = [
            'status' => 'processing',
            'cnpj' => $cnpjLimpo,
            'tenant' => $tenant,
            'total' => $total,
            'coletados' => 0,
            'paginas' => 0,
            'mensagem' => "Consulta grande: {$total} processos. Buscando em segundo plano (~{$minutos} min). "
                . 'Consulte novamente para ver o andamento e o resultado.',
            'atualizado_em' => now()->toIso8601String(),
        ];

        Cache::put($key, $processing, now()->addMinutes(30));
        ConsultarProcessosCnpjJob::dispatch($cnpjLimpo, $tenant);

        return $processing;
    }

    /**
     * Percorre todas as páginas (a partir de uma já buscada), agregando de forma
     * incremental para não acumular milhares de registros em memória.
     *
     * @param  array<string, mixed>  $primeira  JSON da primeira página (já obtida).
     * @return array<string, mixed>
     */
    public static function coletarTudo(string $cnpj, string $token, array $primeira, int $maxPaginas, ?string $progressKey = null): array
    {
        $amostra = [];
        $porTribunal = [];
        $porAno = [];
        $porClasse = [];

        $total = $primeira['total'] ?? null;
        $coletados = 0;
        $pagina = 0;
        $json = $primeira;

        do {
            $itens = $json['content'] ?? [];

            if (empty($itens)) {
                break;
            }

            foreach ($itens as $item) {
                $p = self::mapProcesso($item);

                if (count($amostra) < self::AMOSTRA_MAX) {
                    $amostra[] = $p;
                }

                $chaveTrib = $p['sigla'] ?? $p['tribunal'];
                $porTribunal[$chaveTrib] = ($porTribunal[$chaveTrib] ?? 0) + 1;
                $porAno[$p['ano_ajuizamento']] = ($porAno[$p['ano_ajuizamento']] ?? 0) + 1;
                $porClasse[$p['classe']] = ($porClasse[$p['classe']] ?? 0) + 1;
            }

            $coletados += count($itens);
            $searchAfter = $json['searchAfter'] ?? null;
            $pagina++;

            // Atualiza o progresso no cache (útil quando rodando dentro do job).
            if ($progressKey && $pagina % 5 === 0) {
                Cache::put($progressKey, [
                    'status' => 'processing',
                    'cnpj' => $cnpj,
                    'total' => $total,
                    'coletados' => $coletados,
                    'paginas' => $pagina,
                    'mensagem' => "Coletando... {$coletados} de " . ($total ?? '?') . ' processos.',
                    'atualizado_em' => now()->toIso8601String(),
                ], now()->addMinutes(30));
            }

            if (empty($searchAfter) || $coletados >= ($total ?? PHP_INT_MAX) || $pagina >= $maxPaginas) {
                break;
            }

            // Throttle: consultas grandes (> 500) espaçam as chamadas para não tomar 429.
            if (($ms = self::throttleMs($total)) > 0) {
                usleep($ms * 1000);
            }

            $json = self::buscarPagina($cnpj, $token, $searchAfter);
        } while (true);

        ksort($porAno);
        arsort($porTribunal);
        arsort($porClasse);

        return [
            'cnpj' => $cnpj,
            'total' => $total ?? $coletados,
            'coletados' => $coletados,
            'paginas' => $pagina,
            'truncado' => $coletados < ($total ?? 0),          // não buscou tudo (atingiu o teto)
            'amostra_truncada' => $coletados > count($amostra), // a lista de processos é só uma amostra
            'processos' => $amostra,
            'agregacoes' => [
                'por_tribunal' => $porTribunal,
                'por_ano' => $porAno,
                'por_classe' => $porClasse,
            ],
        ];
    }

    /**
     * Busca uma única página do PDPJ (100 processos), com retry/backoff em 429 e 5xx.
     *
     * O PDPJ limita a taxa de requisições; em consultas grandes (dezenas de páginas)
     * é normal receber 429. Respeitamos o header Retry-After (ou backoff exponencial).
     */
    public static function buscarPagina(string $cnpj, string $token, ?array $searchAfter): array
    {
        $rota = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos?cpfCnpjParte=' . $cnpj;

        if (! empty($searchAfter)) {
            $rota .= '&searchAfter=' . implode(',', $searchAfter);
        }

        $tentativa = 0;

        do {
            $tentativa++;

            // User-Agent + withoutVerifying evitam o 403 do WAF do PDPJ.
            $response = Http::timeout(self::PAGINA_TIMEOUT)
                ->withToken($token)
                ->withHeaders(['User-Agent' => 'curl/8.19.0'])
                ->withoutVerifying()
                ->get($rota);

            $status = $response->status();

            // 429 (rate limit) ou 5xx temporário: espera e tenta de novo.
            if (($status === 429 || $status >= 500) && $tentativa <= self::MAX_RETRY) {
                $retryAfter = (int) $response->header('Retry-After');
                $espera = $retryAfter > 0 ? $retryAfter : min(30, 2 ** $tentativa); // 2,4,8,16,30s
                sleep(max(1, $espera));
                continue;
            }

            break;
        } while (true);

        if (! $response->successful()) {
            throw new RequestException($response);
        }

        return $response->json() ?? [];
    }

    /**
     * Intervalo (ms) entre páginas conforme o volume. Quanto mais processos,
     * mais devagar para respeitar o limite de taxa do PDPJ e não tomar 429.
     */
    public static function throttleMs(?int $total): int
    {
        if ($total === null || $total <= self::THROTTLE_ACIMA_DE) {
            return 0;        // <= 500: sem espera (consulta rápida).
        }

        if ($total > 5000) {
            return 5000;     // > 5.000: 5s entre páginas.
        }

        if ($total > 2000) {
            return 4000;     // 2.001–5.000: 4s.
        }

        return 3000;         // 501–2.000: 3s.
    }

    /** Resolve o token CNJ: o último do tenant; ou o último geral quando sem tenant. */
    public static function tokenPara(?string $tenant): ?string
    {
        $query = TokenCnj::query();

        if (! empty($tenant)) {
            $query->where('tenant', $tenant);
        }

        $token = $query->latest()->value('token');

        return $token ? trim(str_replace('Bearer ', '', (string) $token)) : null;
    }

    public static function cacheKey(string $cnpj, ?string $tenant): string
    {
        return 'consulta_cnpj:' . ($tenant ?: 'default') . ':' . $cnpj;
    }

    /**
     * Extrai os campos de interesse de um item cru do PDPJ.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function mapProcesso(array $item): array
    {
        $tram = $item['tramitacoes'][0] ?? [];

        $tribunal = $tram['tribunal']['nome'] ?? ($item['siglaTribunal'] ?? 'Não informado');
        $sigla = $tram['tribunal']['sigla'] ?? ($item['siglaTribunal'] ?? null);
        $classe = $tram['classe'][0]['descricao'] ?? 'Não informada';
        $assunto = $tram['assunto'][0]['descricao'] ?? null;
        $ano = isset($tram['dataHoraAjuizamento'])
            ? substr((string) $tram['dataHoraAjuizamento'], 0, 4)
            : 'Não informado';

        return [
            'numero' => $item['numeroProcesso'] ?? null,
            'tribunal' => $tribunal,
            'sigla' => $sigla,
            'classe' => $classe,
            'assunto' => $assunto,
            'valor_acao' => $tram['valorAcao'] ?? null,
            'ano_ajuizamento' => $ano,
            'orgao_julgador' => $tram['orgaoJulgador']['nome'] ?? null,
        ];
    }
}
