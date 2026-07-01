<?php

namespace App\Livewire\Clientes;

use App\Models\ChaveGemini;
use App\Models\Cliente;
use App\Models\Processo;
use App\Services\McpProcessoService;
use App\Services\ProcessoApiService;
use App\Services\TenantManager;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class EditarCliente extends Component
{
    use WithPagination;

    public ?int $clienteId = null;
    public string $abaAtiva = 'dados'; // dados | processos | agente

    // Configuração do agente IA
    public ?int $chaveGeminiId = null;

    // Dados do cliente
    public string $nome = '';
    public string $tipo = 'cliente'; // cliente | prospectado | prospeccao
    public string $telefone = '';
    public string $email = '';
    public string $cpf = '';
    public string $cnpj = '';
    public string $endereco = '';
    public string $numero = '';
    public string $bairro = '';
    public string $cidade = '';
    public string $estado = '';
    public string $cep = '';

    /** @var array<string,mixed>|null  status da consulta CNJ em andamento */
    public ?array $consultaStatus = null;

    public ?string $erroConsulta = null;

    public function mount(?Cliente $cliente = null)
    {
        if (! app(TenantManager::class)->check()) {
            return redirect()->route('home');
        }

        if ($cliente && $cliente->exists) {
            $this->clienteId = $cliente->id;
            $this->nome      = (string) $cliente->nome;
            $this->tipo      = $cliente->tipo ?? 'cliente';
            $this->telefone  = (string) $cliente->telefone;
            $this->email     = (string) $cliente->email;
            $this->cpf       = (string) $cliente->cpf;
            $this->cnpj      = (string) $cliente->cnpj;
            $this->endereco  = (string) $cliente->endereco;
            $this->numero    = (string) $cliente->numero;
            $this->bairro    = (string) $cliente->bairro;
            $this->cidade    = (string) $cliente->cidade;
            $this->estado    = (string) $cliente->estado;
            $this->cep           = (string) $cliente->cep;
            $this->chaveGeminiId = $cliente->chave_gemini_id;
        }
    }

    protected function rules(): array
    {
        $tenant = app(TenantManager::class)->tenant();

        return [
            'nome'     => ['required', 'string', 'max:255'],
            'tipo'     => ['required', 'in:cliente,prospectado,prospeccao'],
            'telefone' => ['nullable', 'string', 'max:20', Rule::unique('clientes', 'telefone')->where('tenant', $tenant)->ignore($this->clienteId)],
            'email'    => ['nullable', 'email', 'max:255', Rule::unique('clientes', 'email')->where('tenant', $tenant)->ignore($this->clienteId)],
            'cpf'      => ['nullable', 'string', 'max:20', Rule::unique('clientes', 'cpf')->where('tenant', $tenant)->ignore($this->clienteId)],
            'cnpj'     => ['nullable', 'string', 'max:20', Rule::unique('clientes', 'cnpj')->where('tenant', $tenant)->ignore($this->clienteId)],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero'   => ['nullable', 'string', 'max:50'],
            'bairro'   => ['nullable', 'string', 'max:255'],
            'cidade'   => ['nullable', 'string', 'max:255'],
            'estado'   => ['nullable', 'string', 'max:2'],
            'cep'      => ['nullable', 'string', 'max:10'],
        ];
    }

    public function salvar(): void
    {
        $dados = $this->validate();

        foreach (['telefone', 'email', 'cpf', 'cnpj'] as $campo) {
            if (($dados[$campo] ?? '') === '') {
                $dados[$campo] = null;
            }
        }

        if ($this->clienteId) {
            Cliente::findOrFail($this->clienteId)->update($dados);
            session()->flash('status', 'Cliente atualizado.');
        } else {
            $cliente = Cliente::create($dados);
            $this->clienteId = $cliente->id;
            $this->abaAtiva = 'processos'; // segue a rotina: cadastra cliente -> processos
            session()->flash('status', 'Cliente cadastrado. Agora os processos.');
        }
    }

    /** Botão "Buscar processos no CNJ" (usa CPF/CNPJ do cliente). */
    public function buscarProcessosCnj(): void
    {
        $this->erroConsulta = null;

        if (! $this->clienteId) {
            $this->erroConsulta = 'Salve o cliente antes de buscar os processos.';
            return;
        }

        try {
            $this->consultaStatus = McpProcessoService::consultarParaCliente($this->clienteId, app(TenantManager::class)->tenant());
            $this->resetPage();
        } catch (\InvalidArgumentException $e) {
            $this->erroConsulta = $e->getMessage();
        } catch (RequestException $e) {
            $this->erroConsulta = 'Falha ao consultar o PDPJ (HTTP ' . $e->response->status() . ').';
        } catch (\Throwable $e) {
            $this->erroConsulta = 'Não foi possível buscar os processos: ' . $e->getMessage();
        }
    }

    /** Poll enquanto a coleta grande roda em segundo plano. */
    public function atualizarConsulta(): void
    {
        if (($this->consultaStatus['status'] ?? null) !== 'processing' || ! $this->clienteId) {
            return;
        }

        try {
            $this->consultaStatus = McpProcessoService::consultarParaCliente($this->clienteId, app(TenantManager::class)->tenant());
        } catch (\Throwable) {
            // mantém o status
        }
    }

    /** Ativa/desativa um processo. Ao ativar, busca o mais atualizado no CNJ por número. */
    public function toggleAtivo(int $processoId): void
    {
        $processo = Processo::findOrFail($processoId);

        if ($processo->ativo) {
            $processo->update(['ativo' => false]);
            session()->flash('status', "Processo {$processo->numero} desativado.");
            return;
        }

        $res = ProcessoApiService::ativar($processo);
        session()->flash('status', $res['ok']
            ? "Processo {$processo->numero} ativado e atualizado pelo CNJ."
            : "Processo {$processo->numero} ativado (não foi possível atualizar pelo CNJ agora).");
    }

    public function excluirProcesso(int $processoId): void
    {
        Processo::findOrFail($processoId)->delete();
        session()->flash('status', 'Processo removido.');
    }

    public function salvarAgente(): void
    {
        if (! $this->clienteId) {
            session()->flash('status_agente', 'Salve o cliente antes de configurar o agente.');
            return;
        }

        $this->validateOnly('chaveGeminiId', [
            'chaveGeminiId' => ['nullable', 'integer', Rule::exists('chaves_gemini', 'id')],
        ]);

        Cliente::findOrFail($this->clienteId)->update([
            'chave_gemini_id' => $this->chaveGeminiId ?: null,
        ]);

        session()->flash('status_agente', 'Configuração do agente salva.');
    }

    public function render()
    {
        $processos = $this->clienteId
            ? Processo::where('cliente_id', $this->clienteId)->orderByDesc('ultima_atualizacao')->paginate(10)
            : null;

        $chavesGemini = ChaveGemini::orderBy('apelido')->get();

        return view('livewire.clientes.editar-cliente', [
            'processos'    => $processos,
            'chavesGemini' => $chavesGemini,
        ])->extends('layouts.app');
    }
}
