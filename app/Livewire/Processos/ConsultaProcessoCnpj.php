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

        $this->resultado = null;
        $this->erro = null;

        try {
            // tenant = null  =>  usa sempre o ÚLTIMO token CNJ gerado (de qualquer tenant).
            // É a mesma lógica/serviço usados pela tool MCP, garantindo paridade no teste.
            $this->resultado = McpProcessoService::consultarResumoPorCnpj($this->cnpj, null);
        } catch (\InvalidArgumentException $e) {
            $this->erro = $e->getMessage();
        } catch (RequestException $e) {
            $this->erro = 'Falha ao consultar a API do PDPJ (HTTP ' . $e->response->status() . ').';
        } catch (\Throwable $e) {
            $this->erro = 'Não foi possível consultar os processos: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.processos.consulta-processo-cnpj')->extends('layouts.app');
    }
}
