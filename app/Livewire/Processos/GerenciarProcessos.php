<?php

namespace App\Livewire\Processos;

use App\Models\Cliente;
use App\Models\Processo;
use App\Services\ProcessoApiService;
use App\Services\TenantManager;
use Livewire\Component;
use Livewire\WithPagination;

class GerenciarProcessos extends Component
{
    use WithPagination;

    public ?int   $processoId        = null;
    public bool   $mostrarForm       = false;

    public ?int   $clienteId         = null;
    public string $numero            = '';
    public string $ultimaAtualizacao = '';
    public bool   $ativo             = true;

    public function mount()
    {
        if (! app(TenantManager::class)->check()) {
            return redirect()->route('home');
        }
    }

    protected function rules(): array
    {
        return [
            'clienteId'         => ['nullable', 'integer'],
            'numero'            => ['required', 'string', 'max:100'],
            'ultimaAtualizacao' => ['nullable', 'date'],
            'ativo'             => ['boolean'],
        ];
    }

    public function novo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $processo = Processo::findOrFail($id);

        $this->processoId        = $processo->id;
        $this->clienteId         = $processo->cliente_id;
        $this->numero            = $processo->numero;
        $this->ultimaAtualizacao = $processo->ultima_atualizacao?->format('Y-m-d') ?? '';
        $this->ativo             = $processo->ativo;

        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        if ($this->clienteId) {
            Cliente::findOrFail($this->clienteId);
        }

        $dados = [
            'cliente_id'         => $this->clienteId,
            'numero'             => $this->numero,
            'ultima_atualizacao' => $this->ultimaAtualizacao ?: null,
            'ativo'              => $this->ativo,
        ];

        if ($this->processoId) {
            Processo::findOrFail($this->processoId)->update($dados);
            session()->flash('status', 'Processo atualizado.');
        } else {
            // Cadastro POR NÚMERO: upsert por tenant+número e ativo definido pela
            // análise do último movimento (concluído → inativo; em andamento → ativo).
            $tm = app(TenantManager::class);
            $res = ProcessoApiService::registrarPorNumero($this->numero, $tm->tenant(), $tm->id(), $this->clienteId);

            if ($res['sincronizado']) {
                $situacao = $res['processo']->situacao === 'concluido' ? 'concluído (inativo)' : 'em andamento (ativo)';
                session()->flash('status', ($res['novo'] ? 'Processo cadastrado' : 'Processo já existia e foi atualizado')
                    . " e sincronizado — {$situacao}.");
            } else {
                session()->flash('warning', 'Processo salvo. Não foi possível sincronizar com a API (PDPJ).');
            }
        }

        $this->resetForm();
        $this->mostrarForm = false;
    }

    public function sincronizar(int $id): void
    {
        $processo = Processo::findOrFail($id);

        // Sincronização manual = forçada: ignora a barreira de "sem novas atualizações".
        $res = ProcessoApiService::sincronizarForcado($processo);

        if ($res['ok']) {
            session()->flash('status', 'Processo sincronizado (forçado) com os dados atuais do PDPJ.');
        } else {
            session()->flash('warning', 'Não foi possível consultar o PDPJ agora. Tente novamente.');
        }
    }

    public function excluir(int $id): void
    {
        Processo::findOrFail($id)->delete();
        session()->flash('status', 'Processo removido.');
    }

    public function cancelar(): void
    {
        $this->resetForm();
        $this->mostrarForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['processoId', 'clienteId', 'numero', 'ultimaAtualizacao']);
        $this->ativo = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.processos.gerenciar-processos', [
            'processos' => Processo::with('cliente')->orderByDesc('ultima_atualizacao')->paginate(15),
            'clientes'  => Cliente::orderBy('nome')->get(),
        ])->extends('layouts.app');
    }
}
