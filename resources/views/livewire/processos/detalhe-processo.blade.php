<div class="container">

    {{-- Breadcrumb --}}
    <nav class="mb-3" aria-label="breadcrumb">
        <a href="{{ route('processos', ['tenant' => app(\App\Services\TenantManager::class)->tenant()]) }}"
           class="text-decoration-none text-muted small">
            <i class="bi bi-arrow-left me-1"></i> Processos
        </a>
    </nav>

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

    {{-- === Formulário de novo processo === --}}
    @if (! $processoId)
        <div class="card">
            <div class="card-header">Cadastrar processo</div>
            <div class="card-body">
                <form wire:submit="salvar" style="max-width:520px">
                    <label class="form-label">Número CNJ <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace @error('numero') is-invalid @enderror"
                               wire:model="numero" placeholder="0000000-00.0000.0.00.0000">
                        <button type="submit" class="btn btn-primary"
                                wire:loading.attr="disabled" wire:target="salvar">
                            <span wire:loading.remove wire:target="salvar">
                                <i class="bi bi-check-lg"></i> Cadastrar
                            </span>
                            <span wire:loading wire:target="salvar">
                                <span class="spinner-border spinner-border-sm"></span> Buscando…
                            </span>
                        </button>
                    </div>
                    @error('numero') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </form>
            </div>
        </div>

    {{-- === Detalhe do processo === --}}
    @else

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="h4 mb-0 font-monospace">{{ $processo->numero }}</h1>
                    @if ($processo->ativo)
                        <span class="badge badge-ativo"><i class="bi bi-broadcast me-1"></i>Monitorado</span>
                    @else
                        <span class="badge badge-inativo">Inativo</span>
                    @endif
                    @if ($processo->situacao)
                        <span class="badge {{ $processo->situacao === 'concluido' ? 'badge-concluido' : 'badge-andamento' }}">
                            {{ $processo->situacao === 'concluido' ? 'Concluído' : 'Em andamento' }}
                        </span>
                    @endif
                </div>
                @if ($processo->cliente)
                    <p class="text-muted small mb-0 mt-1">
                        <i class="bi bi-person me-1"></i>{{ $processo->cliente->nome }}
                    </p>
                @endif
            </div>
            <button class="btn btn-outline-secondary btn-sm" wire:click="sincronizar"
                    wire:loading.attr="disabled" wire:target="sincronizar">
                <span wire:loading.remove wire:target="sincronizar">
                    <i class="bi bi-arrow-clockwise"></i> Sincronizar
                </span>
                <span wire:loading wire:target="sincronizar">
                    <span class="spinner-border spinner-border-sm"></span> Sincronizando…
                </span>
            </button>
        </div>

        {{-- Abas --}}
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button class="nav-link {{ $abaAtiva === 'movimentacoes' ? 'active' : '' }}"
                        wire:click="$set('abaAtiva','movimentacoes')">
                    <i class="bi bi-list-ul me-1"></i> Movimentações
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link {{ $abaAtiva === 'clientes' ? 'active' : '' }}"
                        wire:click="$set('abaAtiva','clientes')">
                    <i class="bi bi-people me-1"></i> Clientes
                    @if ($vinculados->isNotEmpty())
                        <span class="badge bg-secondary ms-1">{{ $vinculados->count() }}</span>
                    @endif
                </button>
            </li>
        </ul>

        {{-- === Aba Movimentações === --}}
        @if ($abaAtiva === 'movimentacoes')
            @if ($snap)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-database me-1 text-muted"></i> Dados do processo</span>
                        <small class="text-muted fw-normal">
                            Sync {{ \Carbon\Carbon::parse($snap['sincronizado_em'])->diffForHumans() }}
                        </small>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-4">
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Tribunal</div>
                                <div class="fw-medium small">{{ $snap['tribunal_nome'] ?? $snap['tribunal_sigla'] ?? '—' }}</div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Classe</div>
                                <div class="fw-medium small">{{ $snap['classe'] ?? '—' }}</div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Valor da ação</div>
                                <div class="fw-medium small">
                                    {{ $snap['valor_acao'] !== null ? 'R$ ' . number_format($snap['valor_acao'], 2, ',', '.') : '—' }}
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Ajuizamento</div>
                                <div class="fw-medium small">
                                    {{ $snap['data_ajuizamento'] ? \Carbon\Carbon::parse($snap['data_ajuizamento'])->format('d/m/Y') : '—' }}
                                </div>
                            </div>
                            <div class="col-12 col-md-8">
                                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Assunto</div>
                                <div class="fw-medium small">{{ $snap['assunto'] ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($movimentos->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-clock-history me-1 text-muted"></i>
                            Movimentos
                            <span class="badge bg-secondary ms-1">{{ $movimentos->count() }}</span>
                        </div>
                        <div style="max-height:420px;overflow-y:auto">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:120px">Data</th>
                                        <th>Descrição</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($movimentos as $mov)
                                        <tr>
                                            <td class="text-muted small">
                                                {{ isset($mov['dataHora']) ? \Carbon\Carbon::parse($mov['dataHora'])->format('d/m/Y') : '—' }}
                                            </td>
                                            <td class="small">{{ $mov['descricao'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state py-4">
                            <i class="bi bi-cloud-slash empty-icon"></i>
                            <div class="empty-title">Sem dados sincronizados</div>
                            <div class="empty-sub">Use o botão Sincronizar para buscar dados do PDPJ.</div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        {{-- === Aba Clientes === --}}
        @if ($abaAtiva === 'clientes')
            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-person-plus me-1 text-muted"></i> Vincular cliente
                </div>
                <div class="card-body">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control"
                                       wire:model.live.debounce.300ms="buscaCliente"
                                       placeholder="Nome, CPF ou e-mail…">
                            </div>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-outline-secondary btn-sm"
                                    wire:click="abrirModalNovoCliente">
                                <i class="bi bi-person-plus"></i> Novo cliente
                            </button>
                        </div>
                    </div>

                    @if ($clientesDisponiveis->isNotEmpty())
                        <ul class="list-group mt-3">
                            @foreach ($clientesDisponiveis as $cl)
                                <li class="list-group-item d-flex align-items-center justify-content-between py-2"
                                    wire:key="disp-{{ $cl->id }}">
                                    <div>
                                        <span class="fw-semibold small">{{ $cl->nome }}</span>
                                        @if ($cl->cpf)
                                            <span class="text-muted small ms-2">CPF {{ $cl->cpf }}</span>
                                        @endif
                                        @if ($cl->email)
                                            <span class="text-muted small ms-2">{{ $cl->email }}</span>
                                        @endif
                                    </div>
                                    <button class="btn btn-primary btn-sm"
                                            wire:click="vincularCliente({{ $cl->id }})">
                                        <i class="bi bi-link-45deg"></i> Vincular
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif (strlen($buscaCliente) >= 2)
                        <p class="text-muted small mt-2 mb-0">Nenhum cliente encontrado.</p>
                    @endif
                </div>
            </div>

            @if ($vinculados->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-people me-1 text-muted"></i> Vinculados
                    </div>
                    <ul class="list-group list-group-flush">
                        @foreach ($vinculados as $pc)
                            <li class="list-group-item py-3" wire:key="vinc-{{ $pc->id }}">
                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                    <div>
                                        <span class="fw-semibold">{{ $pc->cliente?->nome ?? '—' }}</span>
                                        @if ($pc->cliente?->email)
                                            <span class="text-muted small ms-2">{{ $pc->cliente->email }}</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <select class="form-select form-select-sm" style="width:auto"
                                                wire:change="alterarCanal({{ $pc->id }}, $event.target.value)">
                                            <option value="nenhum" {{ $pc->canal_notificacao === 'nenhum' ? 'selected' : '' }}>
                                                Sem notificação
                                            </option>
                                            <option value="email" {{ $pc->canal_notificacao === 'email' ? 'selected' : '' }}>
                                                E-mail
                                            </option>
                                            <option value="whatsapp" {{ $pc->canal_notificacao === 'whatsapp' ? 'selected' : '' }}>
                                                WhatsApp
                                            </option>
                                            <option value="ambos" {{ $pc->canal_notificacao === 'ambos' ? 'selected' : '' }}>
                                                Ambos
                                            </option>
                                        </select>
                                        <button class="btn btn-ghost btn-icon btn-sm btn-action-delete"
                                                wire:click="desvincularCliente({{ $pc->id }})"
                                                wire:confirm="Desvincular este cliente?"
                                                data-bs-toggle="tooltip" data-bs-title="Desvincular">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state py-3">
                            <i class="bi bi-people empty-icon"></i>
                            <div class="empty-title">Sem clientes vinculados</div>
                            <div class="empty-sub">Busque e vincule clientes acima.</div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

    @endif

    {{-- === Modal novo cliente === --}}
    @if ($modalNovoCliente)
        <div class="modal-backdrop fade show" style="z-index:1990"></div>
        <div class="modal fade show d-block" tabindex="-1" style="z-index:2000"
             wire:click.self="fecharModalNovoCliente">
            <div class="modal-dialog modal-dialog-centered" style="z-index:2001">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Novo cliente</h5>
                        <button type="button" class="btn-close" wire:click="fecharModalNovoCliente"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('novoClienteNome') is-invalid @enderror"
                                       wire:model="novoClienteNome">
                                @error('novoClienteNome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" class="form-control @error('novoClienteCpf') is-invalid @enderror"
                                       wire:model="novoClienteCpf">
                                @error('novoClienteCpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control @error('novoClienteTel') is-invalid @enderror"
                                       wire:model="novoClienteTel">
                                @error('novoClienteTel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control @error('novoClienteEmail') is-invalid @enderror"
                                       wire:model="novoClienteEmail">
                                @error('novoClienteEmail') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                                wire:click="fecharModalNovoCliente">Cancelar</button>
                        <button type="button" class="btn btn-primary"
                                wire:click="salvarNovoCliente"
                                wire:loading.attr="disabled" wire:target="salvarNovoCliente">
                            <span wire:loading.remove wire:target="salvarNovoCliente">
                                <i class="bi bi-check-lg"></i> Criar e vincular
                            </span>
                            <span wire:loading wire:target="salvarNovoCliente">
                                <span class="spinner-border spinner-border-sm"></span> Salvando…
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
