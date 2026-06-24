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
 * Coleta TODOS os processos de um CNPJ no PDPJ em segundo plano e salva no banco
 * (Cliente por CNPJ + Processos com ativo=false), respeitando throttle/retry e o
 * pedido de cancelamento. O andamento e o resultado ficam no cache de status.
 */
class ConsultarProcessosCnpjJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Com o throttle anti-429, ~9.000 processos podem levar ~15-20 min. */
    public int $timeout = 2400;

    public int $tries = 1;

    public function __construct(
        public string $cnpj,
        public ?string $tenant = null,
        public ?int $clienteId = null,
    ) {
    }

    public function handle(): void
    {
        try {
            if ($this->clienteId) {
                McpProcessoService::coletarCompletoParaCliente($this->cnpj, (string) $this->tenant, $this->clienteId);
            } else {
                McpProcessoService::coletarCompleto($this->cnpj, (string) $this->tenant);
            }
        } catch (\Throwable $e) {
            Cache::put(
                McpProcessoService::statusKey($this->cnpj, $this->tenant),
                [
                    'status' => 'error',
                    'cnpj' => $this->cnpj,
                    'tenant' => $this->tenant,
                    'erro' => $e->getMessage(),
                    'atualizado_em' => now()->toIso8601String(),
                ],
                now()->addMinutes(10),
            );
        }
    }
}
