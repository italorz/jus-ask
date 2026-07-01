<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Clientes</h1>
        <a class="btn btn-primary btn-sm"
           href="{{ route('clientes.novo', ['tenant' => app(\App\Services\TenantManager::class)->tenant()]) }}">
            <i class="bi bi-person-plus"></i> Novo cliente
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

    {{-- ═══ MODAL EDITAR / CRIAR CLIENTE ════════════════════════════ --}}
    <div id="clienteModal" class="modal fade" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header border-bottom-0 pb-0">
                    <div class="w-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="modal-title">
                                {{ $criandoNovo ? 'Novo cliente' : ($nome ?: 'Editar cliente') }}
                            </h5>
                            <button type="button" class="btn-close" wire:click="cancelar"></button>
                        </div>

                        {{-- Stepper (criação) --}}
                        @if ($criandoNovo)
                            @php
                                $passos     = ['dados' => 'Dados', 'processos' => 'Processos', 'chave' => 'Chave IA'];
                                $ordemAbas  = array_keys($passos);
                                $indiceAtual = array_search($abaAtiva, $ordemAbas);
                            @endphp
                            <div class="d-flex align-items-center mt-3 mb-1 px-1">
                                @foreach ($passos as $key => $label)
                                    @php
                                        $idx   = array_search($key, $ordemAbas);
                                        $state = $idx < $indiceAtual ? 'done' : ($key === $abaAtiva ? 'active' : 'future');
                                    @endphp
                                    <div class="d-flex flex-column align-items-center" style="min-width:52px">
                                        <div class="stepper-circle {{ $state }}">
                                            @if ($state === 'done')
                                                <i class="bi bi-check-lg"></i>
                                            @else
                                                {{ $idx + 1 }}
                                            @endif
                                        </div>
                                        <small class="stepper-label {{ $state }}">{{ $label }}</small>
                                    </div>
                                    @if (! $loop->last)
                                        <div class="stepper-line {{ $state === 'done' ? 'done' : 'pending' }}"></div>
                                    @endif
                                @endforeach
                            </div>

                        {{-- Nav-tabs (edição) --}}
                        @else
                            <ul class="nav nav-tabs mt-2 border-bottom-0">
                                <li class="nav-item">
                                    <button wire:click="$set('abaAtiva','dados')"
                                            class="nav-link {{ $abaAtiva === 'dados' ? 'active' : '' }}">Dados</button>
                                </li>
                                <li class="nav-item">
                                    <button wire:click="$set('abaAtiva','processos')"
                                            class="nav-link {{ $abaAtiva === 'processos' ? 'active' : '' }}">
                                        Processos
                                        @if ($processos->isNotEmpty())
                                            <span class="badge bg-secondary ms-1">{{ $processos->count() }}</span>
                                        @endif
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button wire:click="$set('abaAtiva','chave')"
                                            class="nav-link {{ $abaAtiva === 'chave' ? 'active' : '' }}">Chave IA</button>
                                </li>
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="modal-body pt-2">

                    {{-- ── ABA DADOS ────────────────────────────────── --}}
                    @if ($abaAtiva === 'dados')
                        <form wire:submit="salvar" id="formDados">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                                    <select class="form-select @error('tipo') is-invalid @enderror" wire:model="tipo">
                                        <option value="prospeccao">Prospecção</option>
                                        <option value="prospectado">Prospectado</option>
                                        <option value="cliente">Cliente</option>
                                    </select>
                                    @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('nome') is-invalid @enderror"
                                           wire:model="nome" placeholder="Nome completo">
                                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                                           wire:model="telefone" placeholder="(00) 00000-0000">
                                    @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">E-mail</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                           wire:model="email" placeholder="email@exemplo.com">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CPF</label>
                                    <input type="text" class="form-control @error('cpf') is-invalid @enderror"
                                           wire:model="cpf" placeholder="000.000.000-00">
                                    @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12"><hr class="my-1"><small class="text-muted">Endereço (opcional)</small></div>
                                <div class="col-md-6">
                                    <label class="form-label">Logradouro</label>
                                    <input type="text" class="form-control" wire:model="endereco" placeholder="Rua, Av…">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control" wire:model="numero">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Bairro</label>
                                    <input type="text" class="form-control" wire:model="bairro">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Cidade</label>
                                    <input type="text" class="form-control" wire:model="cidade">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">UF</label>
                                    <input type="text" class="form-control text-uppercase" wire:model="estado" maxlength="2" placeholder="SP">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">CEP</label>
                                    <input type="text" class="form-control" wire:model="cep" placeholder="00000-000">
                                </div>
                            </div>
                        </form>
                    @endif

                    {{-- ── ABA PROCESSOS ─────────────────────────────── --}}
                    @if ($abaAtiva === 'processos')
                        @if (! $clienteId)
                            <div class="alert alert-warning mb-0">
                                Salve os dados do cliente antes de adicionar processos.
                            </div>
                        @else
                            @unless ($mostrarFormProcesso || $modoVincular)
                                <div class="d-flex gap-2 mb-3">
                                    <button class="btn btn-sm btn-primary" wire:click="novoProcesso">
                                        <i class="bi bi-plus-lg"></i> Novo processo
                                    </button>
                                    @if ($processosDisponiveis->isNotEmpty())
                                        <button class="btn btn-sm btn-outline-secondary" wire:click="ativarVincular">
                                            <i class="bi bi-link-45deg"></i> Vincular existente
                                        </button>
                                    @endif
                                </div>
                            @endunless

                            @if ($mostrarFormProcesso)
                                <div class="card mb-3">
                                    <div class="card-header">
                                        {{ $processoId ? 'Editar processo' : 'Novo processo' }}
                                    </div>
                                    <div class="card-body">
                                        <form wire:submit="salvarProcesso">
                                            <div class="row g-3">
                                                <div class="col-md-8">
                                                    <label class="form-label fw-semibold">Número CNJ <span class="text-danger">*</span></label>
                                                    <input type="text"
                                                           class="form-control font-monospace @error('processoNumero') is-invalid @enderror"
                                                           wire:model="processoNumero"
                                                           placeholder="0000000-00.0000.0.00.0000">
                                                    @error('processoNumero') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Última atualização</label>
                                                    <input type="date" class="form-control"
                                                           wire:model="processoUltimaAtualizacao">
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                               id="processoAtivo" wire:model="processoAtivo">
                                                        <label class="form-check-label" for="processoAtivo">Processo ativo</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-3 d-flex gap-2">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-check-lg"></i> Salvar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        wire:click="cancelarProcesso">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            @if ($modoVincular)
                                <div class="card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-link-45deg me-1 text-muted"></i> Vincular processo existente</span>
                                        <button type="button" class="btn-close btn-sm" wire:click="cancelarProcesso"></button>
                                    </div>
                                    <div class="card-body p-0">
                                        @if ($processosDisponiveis->isEmpty())
                                            <p class="text-muted text-center py-4 mb-0">Nenhum processo disponível.</p>
                                        @else
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0 align-middle">
                                                    <thead>
                                                        <tr>
                                                            <th>Número</th>
                                                            <th>Assunto</th>
                                                            <th class="text-end">Ação</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($processosDisponiveis as $pd)
                                                            <tr wire:key="disp-{{ $pd->id }}">
                                                                <td class="font-monospace small">{{ $pd->numero }}</td>
                                                                <td class="text-muted small">{{ $pd->assunto ?? '—' }}</td>
                                                                <td class="text-end">
                                                                    <button class="btn btn-sm btn-primary"
                                                                            wire:click="vincularProcesso({{ $pd->id }})"
                                                                            wire:confirm="Vincular este processo ao cliente?">
                                                                        <i class="bi bi-link-45deg"></i> Vincular
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Número</th>
                                            <th>Atualizado</th>
                                            <th>Situação</th>
                                            <th class="text-end" style="width:120px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($processos as $processo)
                                            <tr wire:key="processo-{{ $processo->id }}">
                                                <td class="font-monospace small">{{ $processo->numero }}</td>
                                                <td class="text-muted small">{{ $processo->ultima_atualizacao?->format('d/m/Y') ?? '—' }}</td>
                                                <td>
                                                    @if ($processo->ativo)
                                                        <span class="badge badge-ativo"><i class="bi bi-broadcast me-1"></i>Ativo</span>
                                                    @else
                                                        <span class="badge badge-inativo">Inativo</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex gap-1 justify-content-end">
                                                        <a href="{{ route('processos.detalhe', ['tenant' => app(\App\Services\TenantManager::class)->tenant(), 'processo' => $processo]) }}"
                                                           class="btn btn-ghost btn-icon btn-sm btn-action-view"
                                                           data-bs-toggle="tooltip" data-bs-title="Ver detalhes">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button class="btn btn-ghost btn-icon btn-sm btn-action-edit"
                                                                wire:click="editarProcesso({{ $processo->id }})"
                                                                data-bs-toggle="tooltip" data-bs-title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-ghost btn-icon btn-sm btn-action-sync"
                                                                wire:click="sincronizarProcesso({{ $processo->id }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="sincronizarProcesso({{ $processo->id }})"
                                                                data-bs-toggle="tooltip" data-bs-title="Sincronizar">
                                                            <span wire:loading.remove wire:target="sincronizarProcesso({{ $processo->id }})">
                                                                <i class="bi bi-arrow-clockwise"></i>
                                                            </span>
                                                            <span wire:loading wire:target="sincronizarProcesso({{ $processo->id }})">
                                                                <span class="spinner-border spinner-border-sm"></span>
                                                            </span>
                                                        </button>
                                                        <button class="btn btn-ghost btn-icon btn-sm btn-action-delete"
                                                                wire:click="excluirProcesso({{ $processo->id }})"
                                                                wire:confirm="Remover este processo?"
                                                                data-bs-toggle="tooltip" data-bs-title="Excluir">
                                                            <i class="bi bi-x-circle-fill"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4">
                                                    <div class="empty-state py-3">
                                                        <i class="bi bi-briefcase empty-icon"></i>
                                                        <div class="empty-title">Nenhum processo</div>
                                                        <div class="empty-sub">Clique em "+ Novo processo" para começar.</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif

                    {{-- ── ABA CHAVE IA ──────────────────────────────── --}}
                    @if ($abaAtiva === 'chave')
                        @if (! $clienteId)
                            <div class="alert alert-warning mb-0">
                                Salve os dados do cliente antes de configurar a chave de IA.
                            </div>
                        @else
                            <div class="row g-3 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">
                                        Chave Gemini
                                        <button class="btn-help"
                                                data-bs-toggle="tooltip"
                                                data-bs-html="true"
                                                data-bs-title="Define qual chave Gemini é usada quando este cliente enviar mensagem via WhatsApp.<br><strong>Prioridade:</strong> chave do cliente → chave do escritório → .env">
                                            <i class="bi bi-question"></i>
                                        </button>
                                    </label>
                                    <select class="form-select @error('chaveGeminiId') is-invalid @enderror"
                                            wire:model="chaveGeminiId">
                                        <option value="">— Usar padrão do escritório —</option>
                                        @foreach ($chavesGemini as $chave)
                                            <option value="{{ $chave->id }}">{{ $chave->apelido }}</option>
                                        @endforeach
                                    </select>
                                    @error('chaveGeminiId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <a href="{{ route('chaves-gemini', ['tenant' => app(\App\Services\TenantManager::class)->tenant()]) }}"
                                       class="btn btn-outline-secondary w-100" target="_blank">
                                        <i class="bi bi-key me-1"></i> Gerenciar chaves
                                    </a>
                                </div>
                            </div>
                            @if ($chavesGemini->isEmpty())
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="bi bi-info-circle me-1"></i> Nenhuma chave cadastrada.
                                    <a href="{{ route('chaves-gemini', ['tenant' => app(\App\Services\TenantManager::class)->tenant()]) }}"
                                       target="_blank">Cadastre uma agora</a>.
                                </div>
                            @endif
                        @endif
                    @endif

                </div>{{-- /modal-body --}}

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="cancelar">Fechar</button>

                    @if ($abaAtiva === 'dados')
                        <button type="submit" form="formDados" class="btn btn-primary">
                            {{ ($criandoNovo && ! $clienteId) ? 'Salvar e continuar' : 'Salvar' }}
                        </button>
                        @if ($clienteId)
                            <button type="button" class="btn btn-primary" wire:click="$set('abaAtiva','processos')">
                                Próximo <i class="bi bi-arrow-right"></i>
                            </button>
                        @endif
                    @elseif ($abaAtiva === 'processos')
                        @if ($criandoNovo && $clienteId)
                            <button type="button" class="btn btn-primary" wire:click="$set('abaAtiva','chave')">
                                Próximo <i class="bi bi-arrow-right"></i>
                            </button>
                        @endif
                    @elseif ($abaAtiva === 'chave')
                        <button type="button" class="btn btn-primary" wire:click="salvarChaveGemini"
                                wire:loading.attr="disabled" wire:target="salvarChaveGemini">
                            <span wire:loading.remove wire:target="salvarChaveGemini">
                                <i class="bi bi-check-lg"></i> {{ $criandoNovo ? 'Concluir' : 'Salvar chave' }}
                            </span>
                            <span wire:loading wire:target="salvarChaveGemini">
                                <span class="spinner-border spinner-border-sm"></span>
                            </span>
                        </button>
                    @endif
                </div>

            </div>{{-- /modal-content --}}
        </div>
    </div>{{-- /modal --}}

    {{-- ═══ FILTROS + TABELA ════════════════════════════════════════ --}}
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group btn-group-sm">
            <button class="btn {{ $filtroTipo === '' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    wire:click="$set('filtroTipo','')">Todos</button>
            <button class="btn {{ $filtroTipo === 'cliente' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    wire:click="$set('filtroTipo','cliente')">Clientes</button>
            <button class="btn {{ $filtroTipo === 'prospectado' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    wire:click="$set('filtroTipo','prospectado')">Prospectados</button>
            <button class="btn {{ $filtroTipo === 'prospeccao' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    wire:click="$set('filtroTipo','prospeccao')">Prospecção</button>
        </div>
        <div class="input-group input-group-sm flex-grow-1" style="min-width:220px;max-width:400px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control"
                   placeholder="Nome, e-mail, CPF ou CNPJ…"
                   wire:model.live.debounce.300ms="busca">
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                @php $tiposBadge = ['cliente' => 'badge-ativo', 'prospectado' => 'badge-andamento', 'prospeccao' => 'badge-inativo']; @endphp
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Doc.</th>
                        <th>E-mail</th>
                        <th>Processos</th>
                        <th class="text-end" style="width:80px">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientes as $cliente)
                        <tr wire:key="cliente-{{ $cliente->id }}">
                            <td class="fw-medium">{{ $cliente->nome }}</td>
                            <td>
                                <span class="badge {{ $tiposBadge[$cliente->tipo] ?? 'badge-inativo' }}">
                                    {{ $cliente->tipo === 'prospeccao' ? 'prospecção' : $cliente->tipo }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $cliente->cnpj ?: ($cliente->cpf ?: '—') }}</td>
                            <td class="text-muted small">{{ $cliente->email ?: '—' }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $cliente->processos_count }}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a class="btn btn-ghost btn-icon btn-sm btn-action-edit"
                                       href="{{ route('clientes.editar', ['tenant' => app(\App\Services\TenantManager::class)->tenant(), 'cliente' => $cliente->id]) }}"
                                       data-bs-toggle="tooltip" data-bs-title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-ghost btn-icon btn-sm btn-action-delete"
                                            wire:click="pedirConfirmacao({{ $cliente->id }})"
                                            data-bs-toggle="tooltip" data-bs-title="Excluir">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state py-4">
                                    <i class="bi bi-people empty-icon"></i>
                                    <div class="empty-title">Nenhum cliente</div>
                                    <div class="empty-sub">Clique em "Novo cliente" para começar.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if ($clientes->hasPages())
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <span class="text-muted small">{{ $clientes->total() }} resultado(s)</span>
                    {{ $clientes->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ MODAL CONFIRMAR EXCLUSÃO ════════════════════════════════ --}}
    <div id="modalExcluirCliente" class="modal fade" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Excluir cliente
                    </h5>
                    <button type="button" class="btn-close" wire:click="cancelarExclusao"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir <strong>{{ $excluirNome }}</strong>?</p>
                    @if ($excluirProcessosCount > 0)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   id="chkExcluirProcessos" wire:model="excluirTambemProcessos">
                            <label class="form-check-label" for="chkExcluirProcessos">
                                Também excluir <strong>{{ $excluirProcessosCount }}
                                {{ $excluirProcessosCount === 1 ? 'processo vinculado' : 'processos vinculados' }}</strong>
                            </label>
                        </div>
                        @if ($excluirTambemProcessos)
                            <div class="alert alert-danger py-2 mb-0 small">
                                <i class="bi bi-trash3 me-1"></i>
                                Os processos serão permanentemente removidos. Esta ação não pode ser desfeita.
                            </div>
                        @else
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Os processos serão mantidos sem cliente vinculado.
                            </p>
                        @endif
                    @endif
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary"
                            wire:click="cancelarExclusao">Cancelar</button>
                    <button type="button" class="btn btn-danger"
                            wire:click="confirmarExclusao"
                            wire:loading.attr="disabled" wire:target="confirmarExclusao">
                        <span wire:loading.remove wire:target="confirmarExclusao">Confirmar exclusão</span>
                        <span wire:loading wire:target="confirmarExclusao">
                            <span class="spinner-border spinner-border-sm"></span> Removendo…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        (function () {
            const el    = document.getElementById('clienteModal');
            const modal = new bootstrap.Modal(el, { backdrop: 'static', keyboard: false });
            $wire.on('abrirModal',  () => modal.show());
            $wire.on('fecharModal', () => modal.hide());
            el.addEventListener('hidden.bs.modal', () => $wire.cancelar());

            const elEx    = document.getElementById('modalExcluirCliente');
            const modalEx = new bootstrap.Modal(elEx);
            $wire.on('abrirModalExcluir',  () => modalEx.show());
            $wire.on('fecharModalExcluir', () => modalEx.hide());
            elEx.addEventListener('hidden.bs.modal', () => $wire.cancelarExclusao());
        })();
    </script>
    @endscript
</div>
