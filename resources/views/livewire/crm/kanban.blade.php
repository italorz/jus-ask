<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0"><i class="bi bi-kanban me-2"></i>CRM — Tarefas</h1>
        <button class="btn btn-primary" wire:click="novo">
            <i class="bi bi-plus-lg me-1"></i> Nova tarefa
        </button>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Formulário (inline) --}}
    @if ($mostrarForm)
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ $tarefaId ? 'Editar tarefa' : 'Nova tarefa' }}</div>
            <div class="card-body">
                <form wire:submit="salvar">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('titulo') is-invalid @enderror" wire:model="titulo">
                            @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Coluna</label>
                            <select class="form-select" wire:model="statusForm">
                                @foreach ($colunas as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Prazo</label>
                            <input type="date" class="form-control" wire:model="prazo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cliente</label>
                            <select class="form-select" wire:model.live="clienteId">
                                <option value="">— nenhum —</option>
                                @foreach ($clientes as $c)
                                    <option value="{{ $c->id }}">{{ $c->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Processo {{ $clienteId ? '' : '(selecione um cliente)' }}</label>
                            <select class="form-select" wire:model="processoId" @disabled(! $clienteId)>
                                <option value="">— nenhum —</option>
                                @foreach ($processos as $p)
                                    <option value="{{ $p->id }}">{{ $p->numero }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" rows="2" wire:model="descricao"></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar</button>
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('mostrarForm', false)">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Board --}}
    <div class="row g-3 kanban-board">
        @foreach ($colunas as $valor => $rotulo)
            @php $cards = $tarefas[$valor] ?? collect(); @endphp
            <div class="col-md-4">
                <div class="kanban-col-wrap">
                    <div class="kanban-col-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ $rotulo }}</span>
                        <span class="badge bg-secondary rounded-pill">{{ $cards->count() }}</span>
                    </div>

                    <div class="kanban-col" data-kanban-col data-status="{{ $valor }}">
                        @foreach ($cards as $t)
                            <div class="kanban-card" data-id="{{ $t->id }}" wire:key="tarefa-{{ $t->id }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="fw-semibold">{{ $t->titulo }}</span>
                                    <span class="kanban-grip text-muted"><i class="bi bi-grip-vertical"></i></span>
                                </div>

                                @if ($t->descricao)
                                    <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($t->descricao, 90) }}</div>
                                @endif

                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @if ($t->cliente)
                                        <span class="badge bg-primary-subtle text-primary"><i class="bi bi-person me-1"></i>{{ $t->cliente->nome }}</span>
                                    @endif
                                    @if ($t->processo)
                                        <span class="badge bg-info-subtle text-info"><i class="bi bi-briefcase me-1"></i>{{ $t->processo->numero }}</span>
                                    @endif
                                    @if ($t->prazo)
                                        <span class="badge bg-{{ $t->prazo->isPast() && $valor !== 'concluido' ? 'danger' : 'secondary' }}">
                                            <i class="bi bi-calendar-event me-1"></i>{{ $t->prazo->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="d-flex justify-content-end gap-1 mt-2">
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-1" wire:click="editar({{ $t->id }})" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" wire:click="excluir({{ $t->id }})"
                                            wire:confirm="Remover esta tarefa?" title="Remover">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <style>
        .kanban-col-wrap { background: var(--bg-body); border: 1px solid var(--border); border-radius: .6rem; padding: .6rem; height: 100%; }
        .kanban-col-header { padding: .25rem .35rem .55rem; }
        .kanban-col { min-height: 120px; display: flex; flex-direction: column; gap: .55rem; }
        .kanban-card {
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: .55rem;
            padding: .6rem .7rem; box-shadow: var(--shadow); cursor: grab;
        }
        .kanban-card:active { cursor: grabbing; }
        .kanban-ghost { opacity: .4; }
        .kanban-grip { cursor: grab; }
    </style>

    @script
    <script>
        let kanbanSortables = [];

        function initKanban() {
            if (typeof Sortable === 'undefined') return;
            kanbanSortables.forEach(s => s.destroy());
            kanbanSortables = [];

            document.querySelectorAll('[data-kanban-col]').forEach(col => {
                kanbanSortables.push(new Sortable(col, {
                    group: 'kanban',
                    animation: 150,
                    ghostClass: 'kanban-ghost',
                    handle: '.kanban-card',
                    onEnd: function (evt) {
                        const id = evt.item.dataset.id;
                        const status = evt.to.dataset.status;
                        $wire.mover(parseInt(id), status, evt.newIndex);
                    },
                }));
            });
        }

        initKanban();
        Livewire.hook('morph.updated', () => initKanban());
    </script>
    @endscript
</div>
