<?php

namespace App\Livewire\Processos;

use App\Services\McpProcessoService;
use App\Services\TenantManager;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

class ConsultaProcessoCnpj extends Component
{
    public string $cnpj = '';

    /** @var array<string, mixed>|null  Status + agregações da consulta. */
    public ?array $resultado = null;

    /** @var array<string, mixed>|null  Lote atual de processos lido do banco. */
    public ?array $lote = null;

    public int $pagina = 1;

    public ?string $erro = null;

    public ?string $tenant = null;

    public function mount()
    {
        $tm = app(TenantManager::class);

        if (! $tm->check()) {
            return redirect()->route('home');
        }

        $this->tenant = $tm->tenant();
    }

    public function consultar(): void
    {
        $this->validate(
            ['cnpj' => ['required', 'string']],
            ['cnpj.required' => 'Informe o CNPJ da parte (14 dígitos).'],
        );

        $this->erro = null;
        $this->pagina = 1;
        $this->lote = null;
        $this->buscar(true);
    }

    /** Poll enquanto a coleta grande roda em segundo plano. */
    public function atualizarStatus(): void
    {
        if (($this->resultado['status'] ?? null) === 'processing') {
            $this->buscar(false);
        }
    }

    public function cancelar(): void
    {
        try {
            McpProcessoService::cancelar($this->cnpj, $this->tenant);
        } catch (\Throwable) {
            // ignora
        }

        $this->buscar(false);
    }

    public function irPara(int $p): void
    {
        $this->pagina = max(1, $p);
        $this->lote = McpProcessoService::lerDoBanco($this->cnpj, $this->tenant, $this->pagina);
    }

    private function buscar(bool $atualizar): void
    {
        try {
            $this->resultado = McpProcessoService::consultar($this->cnpj, $this->tenant, $atualizar);

            if (in_array($this->resultado['status'] ?? '', ['done', 'cancelado'], true)) {
                $this->irPara(1);
            }
        } catch (\InvalidArgumentException $e) {
            $this->erro = $e->getMessage();
            $this->resultado = null;
        } catch (RequestException $e) {
            $this->erro = 'Falha ao consultar a API do PDPJ (HTTP ' . $e->response->status() . ').';
            $this->resultado = null;
        } catch (\Throwable $e) {
            $this->erro = 'Não foi possível consultar os processos: ' . $e->getMessage();
            $this->resultado = null;
        }
    }

    public function render()
    {
        return view('livewire.processos.consulta-processo-cnpj')->extends('layouts.app');
    }
}
