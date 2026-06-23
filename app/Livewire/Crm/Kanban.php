<?php

namespace App\Livewire\Crm;

use App\Models\Cliente;
use App\Models\Processo;
use App\Models\Tarefa;
use App\Services\TenantManager;
use Livewire\Component;

class Kanban extends Component
{
    public bool $mostrarForm = false;

    public ?int $tarefaId = null;

    public string $titulo = '';
    public string $descricao = '';
    public ?int $clienteId = null;
    public ?int $processoId = null;
    public string $prazo = '';
    public string $statusForm = 'a_fazer';

    public function mount()
    {
        if (! app(TenantManager::class)->check()) {
            return redirect()->route('home');
        }
    }

    protected function rules(): array
    {
        return [
            'titulo'     => ['required', 'string', 'max:255'],
            'descricao'  => ['nullable', 'string'],
            'clienteId'  => ['nullable', 'integer', 'exists:clientes,id'],
            'processoId' => ['nullable', 'integer', 'exists:processos,id'],
            'prazo'      => ['nullable', 'date'],
            'statusForm' => ['required', 'in:a_fazer,fazendo,concluido'],
        ];
    }

    public function novo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $t = Tarefa::findOrFail($id);

        $this->tarefaId   = $t->id;
        $this->titulo     = (string) $t->titulo;
        $this->descricao  = (string) $t->descricao;
        $this->clienteId  = $t->cliente_id;
        $this->processoId = $t->processo_id;
        $this->prazo      = $t->prazo?->format('Y-m-d') ?? '';
        $this->statusForm = $t->status;
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $dados = $this->validate();

        $payload = [
            'titulo'      => $dados['titulo'],
            'descricao'   => $dados['descricao'] ?: null,
            'cliente_id'  => $dados['clienteId'] ?: null,
            'processo_id' => $dados['processoId'] ?: null,
            'prazo'       => $dados['prazo'] ?: null,
            'status'      => $dados['statusForm'],
        ];

        if ($this->tarefaId) {
            Tarefa::findOrFail($this->tarefaId)->update($payload);
            session()->flash('status', 'Tarefa atualizada.');
        } else {
            Tarefa::create($payload);
            session()->flash('status', 'Tarefa criada.');
        }

        $this->resetForm();
    }

    public function excluir(int $id): void
    {
        Tarefa::findOrFail($id)->delete();
        session()->flash('status', 'Tarefa removida.');
    }

    /** Chamado pelo drag-and-drop: move a tarefa para outra coluna/posição. */
    public function mover(int $id, string $status, int $ordem = 0): void
    {
        if (! array_key_exists($status, Tarefa::STATUS)) {
            return;
        }

        $tarefa = Tarefa::find($id);

        if ($tarefa) {
            $tarefa->update(['status' => $status, 'ordem' => $ordem]);
        }
    }

    private function resetForm(): void
    {
        $this->reset(['tarefaId', 'titulo', 'descricao', 'clienteId', 'processoId', 'prazo', 'mostrarForm']);
        $this->statusForm = 'a_fazer';
        $this->resetValidation();
    }

    public function render()
    {
        $tarefas = Tarefa::with(['cliente:id,nome', 'processo:id,numero'])
            ->orderBy('ordem')
            ->orderByDesc('id')
            ->get()
            ->groupBy('status');

        // Processos do cliente selecionado (para o select do formulário).
        $processos = $this->clienteId
            ? Processo::where('cliente_id', $this->clienteId)->orderBy('numero')->limit(200)->get(['id', 'numero'])
            : collect();

        return view('livewire.crm.kanban', [
            'colunas'   => Tarefa::STATUS,
            'tarefas'   => $tarefas,
            'clientes'  => Cliente::orderBy('nome')->get(['id', 'nome']),
            'processos' => $processos,
        ])->extends('layouts.app');
    }
}
