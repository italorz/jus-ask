<?php

namespace App\Livewire\Processos;

use App\Models\Cliente;
use App\Models\Processo;
use App\Services\ProcessoApiService;
use App\Services\TenantManager;
use Livewire\Component;

class GerenciarProcessos extends Component
{
    public ?int $processoId = null;
    public bool $mostrarForm = false;

    public ?int $clienteId = null;
    public string $numero = '';
    public string $ultimaAtualizacao = '';
    public bool $encerrado = false;

    public function mount()
    {
        if (! app(TenantManager::class)->check()) {
            return redirect()->route('home');
        }
    }

    protected function rules(): array
    {
        return [
            'clienteId' => ['nullable', 'integer'],
            'numero' => ['required', 'string', 'max:100'],
            'ultimaAtualizacao' => ['nullable', 'date'],
            'encerrado' => ['boolean'],
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

        $this->processoId = $processo->id;
        $this->clienteId = $processo->cliente_id;
        $this->numero = $processo->numero;
        $this->ultimaAtualizacao = $processo->ultima_atualizacao?->format('Y-m-d') ?? '';
        $this->encerrado = $processo->encerrado;

        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        // Garante que o cliente pertence ao tenant ativo (escopo global) — quando informado.
        if ($this->clienteId) {
            Cliente::findOrFail($this->clienteId);
        }

        $dados = [
            'cliente_id' => $this->clienteId,
            'numero' => $this->numero,
            'ultima_atualizacao' => $this->ultimaAtualizacao ?: null,
            'encerrado' => $this->encerrado,
        ];

        if ($this->processoId) {
            Processo::findOrFail($this->processoId)->update($dados);
            session()->flash('status', 'Processo atualizado.');
        } else {
            $processo = Processo::create($dados);
            $sincronizado = app(ProcessoApiService::class)->consultarESalvar($processo);
            if ($sincronizado) {
                session()->flash('status', 'Processo cadastrado e sincronizado com a API.');
            } else {
                session()->flash('warning', 'Processo cadastrado. Não foi possível sincronizar com a API.');
            }
        }

        $this->resetForm();
        $this->mostrarForm = false;
    }

    public function sincronizar(int $id): void
    {
        $processo = Processo::findOrFail($id);
        $ok = app(ProcessoApiService::class)->consultarESalvar($processo);
        if ($ok) {
            session()->flash('status', 'Processo sincronizado com sucesso.');
        } else {
            session()->flash('warning', 'Não foi possível sincronizar o processo com a API.');
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
        $this->reset(['processoId', 'clienteId', 'numero', 'ultimaAtualizacao', 'encerrado']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.processos.gerenciar-processos', [
            'processos' => Processo::with('cliente')->orderByDesc('ultima_atualizacao')->get(),
            'clientes' => Cliente::orderBy('nome')->get(),
        ])->extends('layouts.app');
    }
}
