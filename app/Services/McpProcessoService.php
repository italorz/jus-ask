<?php

namespace App\Services;

use App\Jobs\ConsultarProcessosCnpjJob;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Processo;
use App\Models\TokenCnj;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class McpProcessoService
{
    /** Até este nº de páginas (×100) a consulta roda síncrona; acima vai para a fila. */
    public const MAX_PAGINAS_SYNC = 3;

    /** Teto duro de páginas (trava de segurança). 200 = ~20.000 processos. */
    public const MAX_PAGINAS_TOTAL = 200;

    private const PAGINA_TIMEOUT = 20;

    /** Máximo de retentativas por página em caso de 429/5xx. */
    private const MAX_RETRY = 5;

    /** Acima deste total, espaça as páginas para não tomar 429. */
    public const THROTTLE_ACIMA_DE = 500;

    /** Tamanho do lote ao ler os processos do banco. */
    public const POR_PAGINA = 50;

    // ───────────────────────────────────────────── Orquestração ──

    /**
     * Ponto de entrada. Garante o Cliente (por CNPJ) no tenant, coleta os processos
     * do PDPJ salvando-os no banco (ativo=false) e devolve o status + agregações.
     *
     * @return array<string, mixed>
     */
    public static function consultar(string $cnpj, ?string $tenant, bool $atualizar = false): array
    {
        $cnpjLimpo = self::limparCnpj($cnpj);
        $tenant = $tenant ?: null;

        if (empty($tenant)) {
            throw new \InvalidArgumentException('Tenant (empresa) é necessário para salvar a consulta.');
        }

        $key = self::statusKey($cnpjLimpo, $tenant);

        if (! $atualizar && ($cache = Cache::get($key))) {
            // Já temos status; se concluído, devolve com as agregações atualizadas do banco.
            if (in_array($cache['status'] ?? '', ['done', 'cancelado'], true)) {
                return self::respostaConcluida($cnpjLimpo, $tenant, $cache);
            }

            return $cache;
        }

        $token = self::tokenPara($tenant);
        if (empty($token)) {
            throw new \RuntimeException('Nenhum token CNJ está cadastrado para consultar o PDPJ.');
        }

        $empresaId = self::empresaIdDoTenant($tenant);
        if (empty($empresaId)) {
            throw new \RuntimeException("Empresa (tenant) '{$tenant}' não encontrada.");
        }

        // Primeira página: total + nome da parte (para cadastrar o Cliente).
        $primeira = self::buscarPagina($cnpjLimpo, $token, null);
        $total = (int) ($primeira['total'] ?? count($primeira['content'] ?? []));
        $nome = self::nomeParteDoCnpj($primeira['content'] ?? [], $cnpjLimpo);

        $cliente = self::ensureCliente($cnpjLimpo, $tenant, $empresaId, $nome);

        Cache::forget(self::cancelKey($cnpjLimpo, $tenant));

        // Pequeno: coleta e salva agora mesmo.
        if ($total <= self::MAX_PAGINAS_SYNC * 100) {
            $res = self::coletarESalvar($cnpjLimpo, $tenant, $token, $primeira, self::MAX_PAGINAS_TOTAL, $cliente->id, $empresaId, $key);

            $status = ! empty($res['cancelado']) ? 'cancelado' : 'done';
            $st = self::montarStatus($status, $cnpjLimpo, $tenant, $cliente->id, $total, $res['coletados'], $res['paginas']);
            Cache::put($key, $st, now()->addMinutes(60));

            return self::respostaConcluida($cnpjLimpo, $tenant, $st);
        }

        // Grande: processa em segundo plano.
        $minutos = max(1, (int) ceil($total / 100 * 6 / 60));
        $st = self::montarStatus('processing', $cnpjLimpo, $tenant, $cliente->id, $total, 0, 0);
        $st['mensagem'] = "Consulta grande: {$total} processos. Coletando em segundo plano (~{$minutos} min) e salvando no banco. "
            . 'Consulte de novo para ver o andamento; use o parâmetro "pagina" para ler os processos já salvos.';
        Cache::put($key, $st, now()->addMinutes(30));

        ConsultarProcessosCnpjJob::dispatch($cnpjLimpo, $tenant);

        return $st;
    }

    /** Marca a consulta para cancelamento (o job para no próximo lote, mantendo o que já salvou). */
    public static function cancelar(string $cnpj, string $tenant): array
    {
        $cnpjLimpo = self::limparCnpj($cnpj);
        Cache::put(self::cancelKey($cnpjLimpo, $tenant), true, now()->addMinutes(30));

        $st = Cache::get(self::statusKey($cnpjLimpo, $tenant));
        if ($st && ($st['status'] ?? '') === 'processing') {
            $st['mensagem'] = 'Cancelamento solicitado; o job vai parar no próximo lote (o que já foi salvo é mantido).';
            Cache::put(self::statusKey($cnpjLimpo, $tenant), $st, now()->addMinutes(30));
        }

        return ['status' => 'cancelando', 'cnpj' => $cnpjLimpo, 'tenant' => $tenant];
    }

    /**
     * Lê os processos JÁ SALVOS no banco para o CNPJ, em lotes (paginado).
     *
     * @return array<string, mixed>
     */
    public static function lerDoBanco(string $cnpj, string $tenant, int $pagina = 1, ?int $porPagina = null): array
    {
        $cnpjLimpo = self::limparCnpj($cnpj);
        $porPagina = $porPagina ?: self::POR_PAGINA;
        $pagina = max(1, $pagina);

        $cliente = Cliente::withoutGlobalScopes()
            ->where('tenant', $tenant)->where('cnpj', $cnpjLimpo)->first();

        if (! $cliente) {
            return ['cnpj' => $cnpjLimpo, 'total_no_banco' => 0, 'pagina' => $pagina, 'paginas_total' => 0, 'processos' => []];
        }

        $base = Processo::withoutGlobalScopes()
            ->where('tenant', $tenant)->where('cliente_id', $cliente->id);

        $totalBanco = (clone $base)->count();

        $processos = (clone $base)
            ->orderBy('id')
            ->forPage($pagina, $porPagina)
            ->get(['numero', 'tribunal', 'classe', 'assunto', 'valor_acao', 'data_hora_ajuizamento', 'ativo'])
            ->map(fn ($p) => [
                'numero' => $p->numero,
                'tribunal' => $p->tribunal,
                'classe' => $p->classe,
                'assunto' => $p->assunto,
                'valor_acao' => $p->valor_acao,
                'ano' => $p->data_hora_ajuizamento?->format('Y'),
                'ativo' => $p->ativo,
            ])->all();

        return [
            'cnpj' => $cnpjLimpo,
            'cliente' => $cliente->nome,
            'total_no_banco' => $totalBanco,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'paginas_total' => (int) ceil($totalBanco / $porPagina),
            'processos' => $processos,
        ];
    }

    // ───────────────────────────────────────────── Coleta + persistência ──

    /**
     * Fluxo completo usado pelo Job (segundo plano): resolve token/empresa/cliente,
     * percorre todas as páginas salvando no banco e grava o status final no cache.
     *
     * @return array<string, mixed>
     */
    public static function coletarCompleto(string $cnpj, string $tenant): array
    {
        $cnpjLimpo = self::limparCnpj($cnpj);
        $key = self::statusKey($cnpjLimpo, $tenant);

        $token = self::tokenPara($tenant);
        if (empty($token)) {
            throw new \RuntimeException('Nenhum token CNJ está cadastrado para consultar o PDPJ.');
        }

        $empresaId = self::empresaIdDoTenant($tenant);
        if (empty($empresaId)) {
            throw new \RuntimeException("Empresa (tenant) '{$tenant}' não encontrada.");
        }

        $primeira = self::buscarPagina($cnpjLimpo, $token, null);
        $total = (int) ($primeira['total'] ?? count($primeira['content'] ?? []));
        $nome = self::nomeParteDoCnpj($primeira['content'] ?? [], $cnpjLimpo);

        $cliente = self::ensureCliente($cnpjLimpo, $tenant, $empresaId, $nome);

        $res = self::coletarESalvar($cnpjLimpo, $tenant, $token, $primeira, self::MAX_PAGINAS_TOTAL, $cliente->id, $empresaId, $key);

        $status = ! empty($res['cancelado']) ? 'cancelado' : 'done';
        $st = self::montarStatus($status, $cnpjLimpo, $tenant, $cliente->id, $total, $res['coletados'], $res['paginas']);
        Cache::put($key, $st, now()->addMinutes(60));

        return $st;
    }

    /**
     * Percorre as páginas (a partir de uma já obtida) salvando cada processo no banco.
     * Atualiza o progresso no cache e respeita o pedido de cancelamento.
     *
     * @return array{coletados:int, paginas:int, total:?int, cancelado:bool}
     */
    public static function coletarESalvar(string $cnpj, string $tenant, string $token, array $primeira, int $maxPaginas, int $clienteId, int $empresaId, string $statusKey): array
    {
        $total = $primeira['total'] ?? null;
        $coletados = 0;
        $pagina = 0;
        $cancelado = false;
        $json = $primeira;

        do {
            if (Cache::get(self::cancelKey($cnpj, $tenant))) {
                $cancelado = true;
                break;
            }

            $itens = $json['content'] ?? [];
            if (empty($itens)) {
                break;
            }

            self::salvarPagina($itens, $clienteId, $empresaId, $tenant);

            $coletados += count($itens);
            $searchAfter = $json['searchAfter'] ?? null;
            $pagina++;

            Cache::put($statusKey, self::montarStatus('processing', $cnpj, $tenant, $clienteId, $total, $coletados, $pagina), now()->addMinutes(30));

            if (empty($searchAfter) || $coletados >= ($total ?? PHP_INT_MAX) || $pagina >= $maxPaginas) {
                break;
            }

            if (($ms = self::throttleMs($total)) > 0) {
                usleep($ms * 1000);
            }

            $json = self::buscarPagina($cnpj, $token, $searchAfter);
        } while (true);

        return ['coletados' => $coletados, 'paginas' => $pagina, 'total' => $total, 'cancelado' => $cancelado];
    }

    /**
     * Salva (ou atualiza) os processos de uma página. NUNCA altera o "ativo" nem o
     * "cliente_id" de um processo já existente — só preenche metadados. Novos entram
     * com ativo=false (para não cair no monitoramento).
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    private static function salvarPagina(array $itens, int $clienteId, int $empresaId, string $tenant): void
    {
        foreach ($itens as $item) {
            $p = self::mapProcesso($item);

            if (empty($p['numero'])) {
                continue;
            }

            $meta = [
                'data_hora_ajuizamento' => self::data($p['data_hora_ajuizamento']),
                'data_hora_ultima_distribuicao' => self::data($p['data_hora_ultima_distribuicao']),
                'valor_acao' => $p['valor_acao'],
                'assunto' => $p['assunto'],
                'tribunal' => $p['tribunal'],
                'classe' => $p['classe'],
            ];

            $existente = Processo::withoutGlobalScopes()
                ->where('tenant', $tenant)->where('numero', $p['numero'])->first();

            if ($existente) {
                // Mantém ativo e cliente_id originais; só atualiza os metadados.
                $existente->fill($meta)->save();

                continue;
            }

            Processo::withoutGlobalScopes()->create(array_merge($meta, [
                'cliente_id' => $clienteId,
                'empresa_id' => $empresaId,
                'tenant' => $tenant,
                'numero' => $p['numero'],
                'ativo' => false,
                'ultima_atualizacao' => now(),
            ]));
        }
    }

    private static function ensureCliente(string $cnpj, string $tenant, int $empresaId, ?string $nome): Cliente
    {
        $cliente = Cliente::withoutGlobalScopes()
            ->where('tenant', $tenant)->where('cnpj', $cnpj)->first();

        if ($cliente) {
            if ($nome && $cliente->nome !== $nome) {
                $cliente->update(['nome' => $nome]);
            }

            return $cliente;
        }

        return Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $empresaId,
            'tenant' => $tenant,
            'cnpj' => $cnpj,
            'nome' => $nome ?: ('CNPJ ' . self::formatarCnpj($cnpj)),
            // consulta por CNPJ entra no funil como "prospecção" (só monitorando).
            'tipo' => 'prospeccao',
            // email/cpf/telefone têm unique (tenant, col); como o cliente é uma
            // empresa (CNPJ), ficam NULL (no Postgres, múltiplos NULL não colidem).
            'email' => null,
            'cpf' => null,
        ]);
    }

    /** Agregações (tribunal/ano/classe) calculadas direto no banco. */
    public static function agregacoesDoBanco(string $tenant, int $clienteId): array
    {
        $base = fn () => Processo::withoutGlobalScopes()->where('tenant', $tenant)->where('cliente_id', $clienteId);

        $porTribunal = (clone $base())->selectRaw("COALESCE(tribunal,'Não informado') t, count(*) c")
            ->groupBy('t')->orderByDesc('c')->pluck('c', 't')->all();

        $porClasse = (clone $base())->selectRaw("COALESCE(classe,'Não informada') c2, count(*) c")
            ->groupBy('c2')->orderByDesc('c')->pluck('c', 'c2')->all();

        $porAno = (clone $base())->selectRaw("COALESCE(to_char(data_hora_ajuizamento,'YYYY'),'Não informado') a, count(*) c")
            ->groupBy('a')->orderBy('a')->pluck('c', 'a')->all();

        return ['por_tribunal' => $porTribunal, 'por_ano' => $porAno, 'por_classe' => $porClasse];
    }

    /** Monta a resposta de uma consulta concluída: status + agregações + 1º lote. */
    private static function respostaConcluida(string $cnpj, string $tenant, array $status): array
    {
        $clienteId = $status['cliente_id'] ?? null;
        $lote = self::lerDoBanco($cnpj, $tenant, 1);

        return array_merge($status, [
            'total_no_banco' => $lote['total_no_banco'],
            'paginas_total' => $lote['paginas_total'],
            'agregacoes' => $clienteId ? self::agregacoesDoBanco($tenant, $clienteId) : [],
            'processos' => $lote['processos'],
            'dica' => 'Use o parâmetro "pagina" (1.. ' . $lote['paginas_total'] . ') para ler os demais processos em lotes.',
        ]);
    }

    // ───────────────────────────────────────────── HTTP / PDPJ ──

    /** Busca uma página do PDPJ, com retry/backoff em 429 e 5xx. */
    public static function buscarPagina(string $cnpj, string $token, ?array $searchAfter): array
    {
        $rota = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos?cpfCnpjParte=' . $cnpj;
        if (! empty($searchAfter)) {
            $rota .= '&searchAfter=' . implode(',', $searchAfter);
        }

        $tentativa = 0;
        do {
            $tentativa++;
            $response = Http::timeout(self::PAGINA_TIMEOUT)
                ->withToken($token)
                ->withHeaders(['User-Agent' => 'curl/8.19.0'])
                ->withoutVerifying()
                ->get($rota);

            $status = $response->status();
            if (($status === 429 || $status >= 500) && $tentativa <= self::MAX_RETRY) {
                $retryAfter = (int) $response->header('Retry-After');
                sleep(max(1, $retryAfter > 0 ? $retryAfter : min(30, 2 ** $tentativa)));
                continue;
            }
            break;
        } while (true);

        if (! $response->successful()) {
            throw new RequestException($response);
        }

        return $response->json() ?? [];
    }

    /** Intervalo (ms) entre páginas conforme o volume, para não tomar 429. */
    public static function throttleMs(?int $total): int
    {
        if ($total === null || $total <= self::THROTTLE_ACIMA_DE) {
            return 0;
        }
        if ($total > 5000) {
            return 5000;
        }
        if ($total > 2000) {
            return 4000;
        }

        return 3000;
    }

    /** Token CNJ: o último do tenant; se não houver, o último geral (fallback). */
    public static function tokenPara(?string $tenant): ?string
    {
        $token = null;
        if (! empty($tenant)) {
            $token = TokenCnj::query()->where('tenant', $tenant)->latest()->value('token');
        }
        if (empty($token)) {
            $token = TokenCnj::query()->latest()->value('token');
        }

        return $token ? trim(str_replace('Bearer ', '', (string) $token)) : null;
    }

    // ───────────────────────────────────────────── Helpers ──

    private static function empresaIdDoTenant(string $tenant): ?int
    {
        return Empresa::where('tenant', $tenant)->value('id');
    }

    /** Acha, na lista de processos, o nome da parte cujo documento é o CNPJ buscado. */
    private static function nomeParteDoCnpj(array $content, string $cnpj): ?string
    {
        foreach ($content as $item) {
            foreach (($item['tramitacoes'][0]['partes'] ?? []) as $parte) {
                foreach (($parte['documentosPrincipais'] ?? []) as $doc) {
                    if (preg_replace('/\D+/', '', (string) ($doc['numero'] ?? '')) === $cnpj) {
                        return $parte['nome'] ?? null;
                    }
                }
            }
        }

        return null;
    }

    private static function mapProcesso(array $item): array
    {
        $tram = $item['tramitacoes'][0] ?? [];

        return [
            'numero' => $item['numeroProcesso'] ?? null,
            'tribunal' => $tram['tribunal']['sigla'] ?? ($item['siglaTribunal'] ?? null),
            'classe' => $tram['classe'][0]['descricao'] ?? null,
            'assunto' => $tram['assunto'][0]['descricao'] ?? null,
            'valor_acao' => $tram['valorAcao'] ?? null,
            'data_hora_ajuizamento' => $tram['dataHoraAjuizamento'] ?? null,
            'data_hora_ultima_distribuicao' => $tram['dataHoraUltimaDistribuicao'] ?? null,
        ];
    }

    private static function montarStatus(string $status, string $cnpj, string $tenant, ?int $clienteId, ?int $total, int $coletados, int $paginas): array
    {
        return [
            'status' => $status,
            'cnpj' => $cnpj,
            'tenant' => $tenant,
            'cliente_id' => $clienteId,
            'total' => $total,
            'coletados' => $coletados,
            'paginas' => $paginas,
            'atualizado_em' => now()->toIso8601String(),
        ];
    }

    private static function data(?string $iso): ?Carbon
    {
        if (empty($iso)) {
            return null;
        }
        try {
            return Carbon::parse($iso);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function limparCnpj(string $cnpj): string
    {
        $limpo = preg_replace('/\D+/', '', $cnpj) ?? '';
        if (strlen($limpo) !== 14) {
            throw new \InvalidArgumentException('Informe um CNPJ valido com 14 digitos.');
        }

        return $limpo;
    }

    private static function formatarCnpj(string $c): string
    {
        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $c) ?: $c;
    }

    public static function statusKey(string $cnpj, ?string $tenant): string
    {
        return 'consulta_cnpj:' . ($tenant ?: 'default') . ':' . $cnpj;
    }

    private static function cancelKey(string $cnpj, string $tenant): string
    {
        return 'consulta_cnpj_cancel:' . $tenant . ':' . $cnpj;
    }
}
