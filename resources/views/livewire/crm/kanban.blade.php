<div class="container-fluid">

    {{-- Cabeçalho --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-kanban me-2"></i>CRM — Agenda</h1>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Toggle Kanban / Agenda --}}
            <div class="btn-group btn-group-sm" role="group">
                <button type="button"
                        class="btn {{ $modoVista === 'kanban' ? 'btn-primary' : 'btn-outline-primary' }}"
                        wire:click="$set('modoVista','kanban')">
                    <i class="bi bi-kanban me-1"></i> Kanban
                </button>
                <button type="button"
                        class="btn {{ $modoVista === 'agenda' ? 'btn-primary' : 'btn-outline-primary' }}"
                        wire:click="$set('modoVista','agenda')">
                    <i class="bi bi-calendar3 me-1"></i> Agenda
                    @if ($hojeCount > 0)
                        <span class="badge bg-danger ms-1 rounded-pill">{{ $hojeCount }}</span>
                    @endif
                </button>
            </div>
            <button class="btn btn-sm btn-primary" wire:click="novo">
                <i class="bi bi-plus-lg me-1"></i> Nova tarefa
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ═══ FORMULÁRIO ═══ --}}
    @if ($mostrarForm)
        <div class="card mb-4 shadow-sm">
            <div class="card-header fw-semibold">
                <i class="bi bi-{{ $tarefaId ? 'pencil' : 'plus-circle' }} me-1"></i>
                {{ $tarefaId ? 'Editar tarefa' : 'Nova tarefa' }}
            </div>
            <div class="card-body">
                <form wire:submit="salvar">
                    <div class="row g-3">

                        {{-- Título --}}
                        <div class="col-md-6">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('titulo') is-invalid @enderror"
                                   wire:model="titulo"
                                   placeholder="Ex.: Reunião inicial, Protocolar recurso…">
                            @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Coluna / Status --}}
                        <div class="col-md-2">
                            <label class="form-label">Coluna</label>
                            <select class="form-select" wire:model="statusForm">
                                @foreach ($colunas as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Data --}}
                        <div class="col-md-2">
                            <label class="form-label">Data</label>
                            <input type="date"
                                   class="form-control @error('prazo') is-invalid @enderror"
                                   wire:model="prazo">
                            @error('prazo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Hora --}}
                        <div class="col-md-2">
                            <label class="form-label">Hora</label>
                            <input type="time"
                                   class="form-control @error('hora') is-invalid @enderror"
                                   wire:model="hora">
                            @error('hora') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Cliente --}}
                        <div class="col-md-6">
                            <label class="form-label">Cliente</label>
                            <select class="form-select" wire:model.live="clienteId">
                                <option value="">— nenhum —</option>
                                @foreach ($clientes as $c)
                                    <option value="{{ $c->id }}">{{ $c->nome }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Processo --}}
                        <div class="col-md-6">
                            <label class="form-label">
                                Processo {{ $clienteId ? '' : '<small class="text-muted">(selecione um cliente)</small>' }}
                            </label>
                            <select class="form-select" wire:model="processoId" @disabled(! $clienteId)>
                                <option value="">— nenhum —</option>
                                @foreach ($processos as $p)
                                    <option value="{{ $p->id }}">{{ $p->numero }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Descrição --}}
                        <div class="col-12">
                            <label class="form-label">Descrição / Observações</label>
                            <textarea class="form-control" rows="2" wire:model="descricao"
                                      placeholder="Detalhes opcionais…"></textarea>
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <button type="button" class="btn btn-outline-secondary"
                                wire:click="$set('mostrarForm', false)">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════
         MODO KANBAN
    ════════════════════════════════════════════════════ --}}
    @if ($modoVista === 'kanban')
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
                                        <span class="fw-semibold flex-grow-1">{{ $t->titulo }}</span>
                                        <span class="kanban-grip text-muted ms-2 flex-shrink-0">
                                            <i class="bi bi-grip-vertical"></i>
                                        </span>
                                    </div>

                                    @if ($t->descricao)
                                        <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($t->descricao, 90) }}</div>
                                    @endif

                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @if ($t->cliente)
                                            <span class="badge badge-andamento">
                                                <i class="bi bi-person me-1"></i>{{ $t->cliente->nome }}
                                            </span>
                                        @endif
                                        @if ($t->processo)
                                            <span class="badge badge-inativo">
                                                <i class="bi bi-briefcase me-1"></i>{{ $t->processo->numero }}
                                            </span>
                                        @endif
                                        @if ($t->prazo)
                                            @php
                                                $vencida = $t->prazo->isPast() && ! $t->prazo->isToday() && $valor !== 'concluido';
                                                $hoje    = $t->prazo->isToday() && $valor !== 'concluido';
                                            @endphp
                                            <span class="badge {{ $vencida ? 'badge-vencida' : ($hoje ? 'badge-hoje' : 'badge-inativo') }}">
                                                <i class="bi bi-calendar-event me-1"></i>{{ $t->prazo->format('d/m/Y') }}
                                                @if ($t->hora) {{ $t->hora }} @endif
                                            </span>
                                        @endif
                                    </div>

                                    <div class="d-flex justify-content-end gap-1 mt-2">
                                        <button class="btn btn-ghost btn-icon btn-sm btn-action-edit"
                                                wire:click="editar({{ $t->id }})"
                                                data-bs-toggle="tooltip" data-bs-title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-icon btn-sm btn-action-delete"
                                                wire:click="excluir({{ $t->id }})"
                                                wire:confirm="Remover esta tarefa?"
                                                data-bs-toggle="tooltip" data-bs-title="Excluir">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════
         MODO AGENDA
    ════════════════════════════════════════════════════ --}}
    @if ($modoVista === 'agenda')
        {{-- Filtros de período --}}
        <div class="d-flex gap-2 mb-4 flex-wrap">
            @foreach (['todos' => 'Todos', 'hoje' => 'Hoje', 'semana' => 'Esta semana', 'mes' => 'Este mês'] as $valor => $label)
                <button type="button"
                        class="btn btn-sm {{ $filtroAgenda === $valor ? 'btn-primary' : 'btn-outline-secondary' }}"
                        wire:click="$set('filtroAgenda','{{ $valor }}')">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if (empty($agenda))
            <div class="text-center text-muted py-5">
                <i class="bi bi-calendar-x display-5 d-block mb-3 opacity-25"></i>
                Nenhuma tarefa {{ $filtroAgenda !== 'todos' ? 'neste período' : 'cadastrada' }}.
            </div>
        @else
            @foreach ($agenda as $grupo)
                <div class="agenda-grupo mb-4">
                    {{-- Cabeçalho do dia --}}
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <span class="fw-bold fs-6
                            {{ $grupo['hoje'] ? 'text-primary' : ($grupo['passado'] ? 'text-danger' : '') }}">
                            {{ $grupo['label'] }}
                        </span>
                        @if ($grupo['data'])
                            <span class="text-muted small">{{ $grupo['data'] }}</span>
                        @endif
                        @if ($grupo['passado'])
                            <span class="badge bg-danger-subtle text-danger small">Vencido</span>
                        @elseif ($grupo['hoje'])
                            <span class="badge bg-primary-subtle text-primary small">Hoje</span>
                        @endif
                    </div>

                    {{-- Itens do dia --}}
                    @foreach ($grupo['tarefas'] as $t)
                        @php
                            $statusCor = match($t->status) {
                                'a_fazer'   => 'secondary',
                                'fazendo'   => 'warning',
                                'concluido' => 'success',
                                default     => 'secondary',
                            };
                            $statusLabel = $colunas[$t->status] ?? $t->status;
                        @endphp
                        <div class="agenda-item card mb-2 border-start border-4
                            border-{{ $t->status === 'concluido' ? 'success' : ($grupo['passado'] && $t->status !== 'concluido' ? 'danger' : ($grupo['hoje'] ? 'primary' : 'secondary')) }}"
                             wire:key="agenda-{{ $t->id }}">
                            <div class="card-body py-2 px-3 d-flex gap-3 align-items-start">

                                {{-- Coluna de hora --}}
                                <div class="agenda-hora text-center flex-shrink-0 pt-1"
                                     style="min-width:52px;">
                                    @if ($t->hora)
                                        <span class="fw-bold text-primary" style="font-size:1.1rem;line-height:1;">{{ $t->hora }}</span>
                                    @else
                                        <span class="text-muted" style="font-size:.8rem;">sem hora</span>
                                    @endif
                                </div>

                                {{-- Conteúdo --}}
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <span class="fw-semibold {{ $t->status === 'concluido' ? 'text-decoration-line-through text-muted' : '' }}">
                                            {{ $t->titulo }}
                                        </span>
                                        <span class="badge flex-shrink-0 {{ $t->status === 'concluido' ? 'badge-concluido' : ($t->status === 'fazendo' ? 'badge-andamento' : 'badge-inativo') }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>

                                    @if ($t->descricao)
                                        <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($t->descricao, 120) }}</div>
                                    @endif

                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @if ($t->cliente)
                                            <span class="badge badge-andamento">
                                                <i class="bi bi-person me-1"></i>{{ $t->cliente->nome }}
                                            </span>
                                        @endif
                                        @if ($t->processo)
                                            <span class="badge badge-inativo">
                                                <i class="bi bi-briefcase me-1"></i>{{ $t->processo->numero }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Ações --}}
                                <div class="d-flex gap-1 flex-shrink-0 pt-1">
                                    <button class="btn btn-ghost btn-icon btn-sm btn-action-edit"
                                            wire:click="editar({{ $t->id }})"
                                            data-bs-toggle="tooltip" data-bs-title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-ghost btn-icon btn-sm btn-action-delete"
                                            wire:click="excluir({{ $t->id }})"
                                            wire:confirm="Remover esta tarefa?"
                                            data-bs-toggle="tooltip" data-bs-title="Excluir">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    @endif

    {{-- ═══════════════════════════════════════════════════
         ESTILOS
    ════════════════════════════════════════════════════ --}}
    <style>
        /* Kanban */
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

        /* Agenda */
        .agenda-grupo { }
        .agenda-item { border-radius: .5rem !important; }
        .agenda-hora { border-right: 1px solid var(--border); padding-right: 1rem; margin-right: .25rem; }
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
                    handle: '.kanban-grip',
                    onEnd: function (evt) {
                        const id     = evt.item.dataset.id;
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
