<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Processos</h1>
        <a href="{{ route('processos.novo', ['tenant' => app(\App\Services\TenantManager::class)->tenant()]) }}"
           class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Novo
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-3">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning alert-dismissible fade show py-2 mb-3">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filtros --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control"
                               placeholder="Número, assunto, tribunal, cliente…"
                               wire:model.live.debounce.400ms="busca">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="filtroSituacao">
                        <option value="">Situação: todas</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="concluido">Concluído</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="filtroAtivo">
                        <option value="">Monitoramento: todos</option>
                        <option value="1">Monitorado</option>
                        <option value="0">Inativo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" wire:click="limparFiltros">
                        <i class="bi bi-x-lg"></i> Limpar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Situação</th>
                        <th>Atualizado</th>
                        <th class="text-end" style="width:110px">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($processos as $processo)
                        <tr wire:key="proc-{{ $processo->id }}">
                            <td class="font-monospace small">{{ $processo->numero }}</td>
                            <td class="text-muted small">{{ $processo->cliente?->nome ?? '—' }}</td>
                            <td>
                                @if ($processo->ativo)
                                    <span class="badge badge-ativo"><i class="bi bi-broadcast me-1"></i>Ativo</span>
                                @else
                                    <span class="badge badge-inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $processo->ultima_atualizacao?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('processos.detalhe', ['tenant' => app(\App\Services\TenantManager::class)->tenant(), 'processo' => $processo]) }}"
                                       class="btn btn-ghost btn-icon btn-sm"
                                       data-bs-toggle="tooltip" data-bs-title="Ver detalhes">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button class="btn btn-ghost btn-icon btn-sm"
                                            wire:click="sincronizar({{ $processo->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="sincronizar({{ $processo->id }})"
                                            data-bs-toggle="tooltip" data-bs-title="Sincronizar com PDPJ">
                                        <span wire:loading.remove wire:target="sincronizar({{ $processo->id }})">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </span>
                                        <span wire:loading wire:target="sincronizar({{ $processo->id }})">
                                            <span class="spinner-border spinner-border-sm"></span>
                                        </span>
                                    </button>
                                    <button class="btn btn-ghost btn-icon btn-sm btn-action-delete"
                                            wire:click="excluir({{ $processo->id }})"
                                            wire:confirm="Remover este processo?"
                                            data-bs-toggle="tooltip" data-bs-title="Excluir">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state py-4">
                                    <i class="bi bi-briefcase empty-icon"></i>
                                    <div class="empty-title">Nenhum processo</div>
                                    <div class="empty-sub">Clique em "Novo" para cadastrar.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($processos->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-muted small">{{ $processos->total() }} resultado(s)</span>
                {{ $processos->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
