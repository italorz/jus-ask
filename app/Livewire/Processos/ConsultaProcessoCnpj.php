<?php

namespace App\Livewire\Processos;

use App\Services\McpProcessoService;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

class ConsultaProcessoCnpj extends Component
{
    public string $cnpj = '';

    /** @var array<string, mixed>|null */
    public ?array $resultado = null;

    public ?string $erro = null;

    public function consultar(): void
    {
        $this->validate(
            ['cnpj' => ['required', 'string']],
            ['cnpj.required' => 'Informe o CNPJ da parte (14 dígitos).'],
        );

        $this->erro = null;
        $this->buscar(true);
    }

    /** Chamado pelo wire:poll enquanto a consulta grande roda em segundo plano (lê do cache). */
    public function atualizarStatus(): void
    {
        if (($this->resultado['status'] ?? null) === 'processing') {
            $this->buscar(false);
        }
    }

    private function buscar(bool $atualizar): void
    {
        try {
            // tenant = null  =>  usa sempre o último token CNJ gerado (mesma lógica da tool MCP).
            $this->resultado = McpProcessoService::consultar($this->cnpj, null, $atualizar);
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
