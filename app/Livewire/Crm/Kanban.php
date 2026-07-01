<?php

namespace App\Livewire\Crm;

use App\Models\Cliente;
use App\Models\Processo;
use App\Models\Tarefa;
use App\Services\TenantManager;
use Carbon\Carbon;
use Livewire\Component;

class Kanban extends Component
{
    public string $modoVista = 'kanban'; // kanban | agenda

    public bool $mostrarForm = false;

    public ?int   $tarefaId  = null;
    public string $titulo    = '';
    public string $descricao = '';
    public ?int   $clienteId  = null;
    public ?int   $processoId = null;
    public string $prazo      = '';
    public string $hora       = '';
    public string $statusForm = 'a_fazer';

    public string $filtroAgenda = 'todos'; // todos | hoje | semana | mes

    public function mount(): void
    {
        if (! app(TenantManager::class)->check()) {
            redirect()->route('home');
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
            'hora'       => ['nullable', 'date_format:H:i'],
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

        $this->tarefaId    = $t->id;
        $this->titulo      = (string) $t->titulo;
        $this->descricao   = (string) $t->descricao;
        $this->clienteId   = $t->cliente_id;
        $this->processoId  = $t->processo_id;
        $this->prazo       = $t->prazo?->format('Y-m-d') ?? '';
        $this->hora        = (string) ($t->hora ?? '');
        $this->statusForm  = $t->status;
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
            'hora'        => ($dados['hora'] ?? '') ?: null,
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
        $this->reset(['tarefaId', 'titulo', 'descricao', 'clienteId', 'processoId', 'prazo', 'hora', 'mostrarForm']);
        $this->statusForm = 'a_fazer';
        $this->resetValidation();
    }

    private function construirAgenda(): array
    {
        $query = Tarefa::with(['cliente:id,nome', 'processo:id,numero'])
            ->orderByRaw('CASE WHEN prazo IS NULL THEN 1 ELSE 0 END')
            ->orderBy('prazo')
            ->orderByRaw('CASE WHEN hora IS NULL THEN 1 ELSE 0 END')
            ->orderBy('hora')
            ->orderBy('id');

        $hoje = Carbon::today();

        match ($this->filtroAgenda) {
            'hoje'  => $query->where('prazo', $hoje->toDateString()),
            'semana' => $query->whereBetween('prazo', [
                $hoje->toDateString(),
                $hoje->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ]),
            'mes'   => $query->whereBetween('prazo', [
                $hoje->toDateString(),
                $hoje->copy()->endOfMonth()->toDateString(),
            ]),
            default => null,
        };

        $tarefas = $query->get();
        $grupos  = [];

        foreach ($tarefas as $tarefa) {
            if ($tarefa->prazo) {
                $chave = $tarefa->prazo->format('Y-m-d');

                if (! isset($grupos[$chave])) {
                    $grupos[$chave] = [
                        'chave'   => $chave,
                        'label'   => $this->labelDia($tarefa->prazo),
                        'data'    => ucfirst($tarefa->prazo->isoFormat('dddd, DD [de] MMMM [de] YYYY')),
                        'passado' => $tarefa->prazo->lt($hoje) && ! $tarefa->prazo->isToday(),
                        'hoje'    => $tarefa->prazo->isToday(),
                        'tarefas' => [],
                    ];
                }

                $grupos[$chave]['tarefas'][] = $tarefa;
            } else {
                if (! isset($grupos['sem_data'])) {
                    $grupos['sem_data'] = [
                        'chave'   => 'sem_data',
                        'label'   => 'Sem data',
                        'data'    => '',
                        'passado' => false,
                        'hoje'    => false,
                        'tarefas' => [],
                    ];
                }

                $grupos['sem_data']['tarefas'][] = $tarefa;
            }
        }

        // "Sem data" sempre por último
        if (isset($grupos['sem_data'])) {
            $semData = $grupos['sem_data'];
            unset($grupos['sem_data']);
            $grupos['sem_data'] = $semData;
        }

        return array_values($grupos);
    }

    private function labelDia(Carbon $data): string
    {
        if ($data->isToday())     return 'Hoje';
        if ($data->isTomorrow())  return 'Amanhã';
        if ($data->isYesterday()) return 'Ontem';

        return ucfirst($data->isoFormat('dddd'));
    }

    public function render()
    {
        $tarefas = Tarefa::with(['cliente:id,nome', 'processo:id,numero'])
            ->orderBy('ordem')
            ->orderByDesc('id')
            ->get()
            ->groupBy('status');

        $processos = $this->clienteId
            ? Processo::where('cliente_id', $this->clienteId)->orderBy('numero')->limit(200)->get(['id', 'numero'])
            : collect();

        $agenda    = $this->modoVista === 'agenda' ? $this->construirAgenda() : [];
        $hojeCount = Tarefa::where('prazo', Carbon::today()->toDateString())->count();

        return view('livewire.crm.kanban', [
            'colunas'   => Tarefa::STATUS,
            'tarefas'   => $tarefas,
            'clientes'  => Cliente::orderBy('nome')->get(['id', 'nome']),
            'processos' => $processos,
            'agenda'    => $agenda,
            'hojeCount' => $hojeCount,
        ])->extends('layouts.app');
    }
}
