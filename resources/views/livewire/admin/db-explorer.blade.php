<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">
            <i class="bi bi-database me-2"></i> Explorador de Banco
        </h1>
        <span class="badge bg-warning text-dark">
            <i class="bi bi-shield-lock me-1"></i> Somente leitura (SELECT)
        </span>
    </div>

    <div class="row g-3" style="min-height: calc(100vh - 140px);">

        {{-- ─── Painel esquerdo: tabelas ───────────────────────────── --}}
        <div class="col-md-3 col-lg-2">
            <div class="card h-100 shadow-sm">
                <div class="card-header py-2 fw-semibold small d-flex align-items-center gap-1">
                    <i class="bi bi-table text-primary"></i> Tabelas
                    <span class="badge bg-secondary ms-auto rounded-pill">{{ count($tabelas) }}</span>
                </div>
                <div class="card-body p-0" style="overflow-y:auto;max-height:calc(100vh - 200px);">
                    <ul class="list-group list-group-flush">
                        @foreach ($tabelas as $tabela)
                            <li class="list-group-item list-group-item-action py-1 px-2 small
                                        {{ $tabelaSelecionada === $tabela ? 'active' : '' }}"
                                style="cursor:pointer;font-size:.8rem;font-family:monospace;"
                                wire:click="selecionarTabela('{{ $tabela }}')">
                                {{ $tabela }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- ─── Painel direito: estrutura + query ─────────────────── --}}
        <div class="col-md-9 col-lg-10 d-flex flex-column gap-3">

            {{-- Estrutura da tabela --}}
            @if ($tabelaSelecionada && ! empty($estrutura))
                <div class="card shadow-sm">
                    <div class="card-header py-2 d-flex align-items-center gap-2">
                        <i class="bi bi-layout-three-columns text-secondary"></i>
                        <span class="fw-semibold small font-monospace">{{ $tabelaSelecionada }}</span>
                        <span class="text-muted small ms-1">({{ count($estrutura) }} colunas)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height:180px;overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.78rem;">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Coluna</th>
                                        <th>Tipo</th>
                                        <th>Nullable</th>
                                        <th>Default</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($estrutura as $col)
                                        <tr>
                                            <td class="font-monospace fw-semibold text-primary">{{ $col->column_name }}</td>
                                            <td class="text-muted">{{ $col->data_type }}</td>
                                            <td>
                                                @if ($col->is_nullable === 'YES')
                                                    <span class="badge bg-secondary-subtle text-secondary">null</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger">not null</span>
                                                @endif
                                            </td>
                                            <td class="text-muted font-monospace small">{{ $col->column_default ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Editor SQL --}}
            <div class="card shadow-sm">
                <div class="card-header py-2 fw-semibold small d-flex align-items-center gap-2">
                    <i class="bi bi-terminal text-success"></i> Query SQL
                </div>
                <div class="card-body pb-2">
                    <textarea class="form-control font-monospace"
                              rows="5"
                              wire:model="sql"
                              placeholder="SELECT * FROM &quot;clientes&quot; WHERE tenant = 'sp123456' LIMIT 50;"
                              style="font-size:.85rem;resize:vertical;"></textarea>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <button class="btn btn-success btn-sm"
                                wire:click="executar"
                                wire:loading.attr="disabled"
                                wire:target="executar">
                            <span wire:loading.remove wire:target="executar">
                                <i class="bi bi-play-fill me-1"></i> Executar
                            </span>
                            <span wire:loading wire:target="executar">
                                <i class="bi bi-hourglass-split me-1"></i> Executando…
                            </span>
                        </button>
                        <div class="form-check form-check-inline mb-0 ms-2">
                            <label class="form-check-label small text-muted">Limite</label>
                        </div>
                        <select class="form-select form-select-sm" style="width:90px;" wire:model="limite">
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                        </select>
                        @if ($info)
                            <span class="text-success small ms-auto">
                                <i class="bi bi-check-circle me-1"></i>{{ $info }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Resultado --}}
            @if ($erro)
                <div class="alert alert-danger py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <code>{{ $erro }}</code>
                </div>
            @endif

            @if ($colunas !== null)
                @if (empty($colunas))
                    <div class="alert alert-secondary py-2 mb-0">
                        Nenhum resultado retornado.
                    </div>
                @else
                    <div class="card shadow-sm flex-grow-1">
                        <div class="card-header py-2 small fw-semibold d-flex align-items-center gap-2">
                            <i class="bi bi-grid-3x3 text-primary"></i> Resultado
                            <span class="text-muted ms-1">{{ count($linhas) }} linha(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height:420px;overflow:auto;">
                                <table class="table table-sm table-hover table-bordered mb-0 align-middle"
                                       style="font-size:.78rem;white-space:nowrap;">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th class="text-muted" style="width:40px;">#</th>
                                            @foreach ($colunas as $col)
                                                <th class="font-monospace">{{ $col }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($linhas as $i => $linha)
                                            <tr>
                                                <td class="text-muted text-end">{{ $i + 1 }}</td>
                                                @foreach ($linha as $celula)
                                                    <td class="{{ is_null($celula) ? 'text-muted fst-italic' : '' }}">
                                                        @if (is_null($celula))
                                                            null
                                                        @elseif (strlen((string)$celula) > 120)
                                                            <span title="{{ e($celula) }}">
                                                                {{ mb_substr($celula, 0, 120) }}…
                                                            </span>
                                                        @else
                                                            {{ $celula }}
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

        </div>
    </div>
</div>
