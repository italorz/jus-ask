<?php

namespace App\Jobs;

use App\Services\McpProcessoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Coleta TODOS os processos de um CNPJ no PDPJ em segundo plano (consultas grandes,
 * que levam minutos por causa da paginação sequencial via searchAfter) e guarda o
 * resultado agregado em cache para a tool MCP / página / endpoint REST consumirem.
 */
class ConsultarProcessosCnpjJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tempo máximo do job. Com o throttle anti-429, consultas grandes ficam mais
     * lentas (ex.: ~9.000 processos ≈ 15-20 min), então damos margem folgada.
     */
    public int $timeout = 2400;

    public int $tries = 1;

    public function __construct(
        public string $cnpj,
        public ?string $tenant = null,
    ) {
    }

    public function handle(): void
    {
        $key = McpProcessoService::cacheKey($this->cnpj, $this->tenant);

        try {
            $token = McpProcessoService::tokenPara($this->tenant);

            if (empty($token)) {
                throw new \RuntimeException('Nenhum token CNJ cadastrado para consultar o PDPJ.');
            }

            $primeira = McpProcessoService::buscarPagina($this->cnpj, $token, null);

            $resultado = McpProcessoService::coletarTudo(
                $this->cnpj,
                $token,
                $primeira,
                McpProcessoService::MAX_PAGINAS_TOTAL,
                $key, // grava progresso no próprio cache
            );

            $resultado['status'] = 'done';
            $resultado['tenant'] = $this->tenant;
            $resultado['atualizado_em'] = now()->toIso8601String();

            Cache::put($key, $resultado, now()->addMinutes(60));
        } catch (\Throwable $e) {
            Cache::put($key, [
                'status' => 'error',
                'cnpj' => $this->cnpj,
                'tenant' => $this->tenant,
                'erro' => $e->getMessage(),
                'atualizado_em' => now()->toIso8601String(),
            ], now()->addMinutes(10));
        }
    }
}
