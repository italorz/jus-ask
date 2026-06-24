<div class="container">
    <div class="mb-3">
        <a href="{{ route('clientes', ['tenant' => app(\App\Services\TenantManager::class)->tenant()]) }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Voltar aos clientes
        </a>
    </div>

    <h1 class="h3 mb-3">{{ $clienteId ? $nome : 'Novo cliente' }}</h1>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Abas --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link {{ $abaAtiva === 'dados' ? 'active' : '' }}" wire:click="$set('abaAtiva','dados')">
                <i class="bi bi-person-vcard me-1"></i> Dados
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $abaAtiva === 'processos' ? 'active' : '' }}"
                    wire:click="$set('abaAtiva','processos')" @disabled(! $clienteId)>
                <i class="bi bi-folder me-1"></i> Processos
            </button>
        </li>
    </ul>

    {{-- ===== Aba DADOS ===== --}}
    @if ($abaAtiva === 'dados')
        <div class="card">
            <div class="card-body">
                <form wire:submit="salvar">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select @error('tipo') is-invalid @enderror" wire:model="tipo">
                                <option value="prospeccao">Prospecção (só monitorando)</option>
                                <option value="prospectado">Prospectado (já abordado)</option>
                                <option value="cliente">Cliente (fechado)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('nome') is-invalid @enderror" wire:model="nome">
                            @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CNPJ</label>
                            <input type="text" class="form-control @error('cnpj') is-invalid @enderror" wire:model="cnpj" placeholder="só números ou com máscara">
                            @error('cnpj') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CPF</label>
                            <input type="text" class="form-control @error('cpf') is-invalid @enderror" wire:model="cpf">
                            @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Telefone</label>
                            <input type="text" class="form-control @error('telefone') is-invalid @enderror" wire:model="telefone">
                            @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Endereço</label>
                            <input type="text" class="form-control" wire:model="endereco">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Número</label>
                            <input type="text" class="form-control" wire:model="numero">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bairro</label>
                            <input type="text" class="form-control" wire:model="bairro">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cidade</label>
                            <input type="text" class="form-control" wire:model="cidade">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">UF</label>
                            <input type="text" class="form-control" wire:model="estado" maxlength="2">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">CEP</label>
                            <input type="text" class="form-control" wire:model="cep">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="salvar">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                            @if (! $clienteId) <span class="ms-1">e ir para Processos</span> @endif
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Aba PROCESSOS ===== --}}
    @if ($abaAtiva === 'processos' && $clienteId)
        @php $doc = $cnpj ?: $cpf; @endphp

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-primary" wire:click="buscarProcessosCnj" wire:loading.attr="disabled" wire:target="buscarProcessosCnj"
                        @disabled(! $doc)>
                    <span wire:loading.remove wire:target="buscarProcessosCnj"><i class="bi bi-cloud-download me-1"></i> Buscar processos no CNJ</span>
                    <span wire:loading wire:target="buscarProcessosCnj">
                        <span class="spinner-border spinner-border-sm me-1"></span> Buscando...
                    </span>
                </button>
                <span class="text-muted small">
                    @if ($doc)
                        usando {{ $cnpj ? 'CNPJ' : 'CPF' }} <code>{{ $doc }}</code> — os processos entram <strong>inativos</strong> (você ativa o que quer monitorar).
                    @else
                        Cadastre um CPF ou CNPJ no cliente (aba Dados) para buscar no CNJ.
                    @endif
                </span>
            </div>

            @if ($erroConsulta)
                <div class="card-footer text-danger small">{{ $erroConsulta }}</div>
            @endif

            @if (($consultaStatus['status'] ?? null) === 'processing')
                <div class="card-footer" wire:poll.3s="atualizarConsulta">
                    <span class="spinner-border spinner-border-sm text-primary me-1"></span>
                    Coletando em segundo plano: {{ $consultaStatus['coletados'] ?? 0 }} de {{ $consultaStatus['total'] ?? '?' }}…
                </div>
            @elseif (in_array($consultaStatus['status'] ?? '', ['done', 'cancelado'], true))
                <div class="card-footer text-success small">
                    Coleta concluída: {{ $consultaStatus['coletados'] ?? 0 }} processo(s) salvos.
                </div>
            @endif
        </div>

        {{-- Lista de processos do cliente (paginada) --}}
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Tribunal</th>
                            <th>Classe</th>
                            <th>Situação</th>
                            <th class="text-center">Monitorar</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($processos as $p)
                            <tr wire:key="proc-{{ $p->id }}">
                                <td><code>{{ $p->numero }}</code></td>
                                <td>{{ $p->tribunal ?? '—' }}</td>
                                <td>{{ $p->classe ?? '—' }}</td>
                                <td>
                                    @if ($p->situacao === 'concluido')
                                        <span class="badge bg-secondary">Concluído</span>
                                    @elseif ($p->situacao === 'em_andamento')
                                        <span class="badge bg-primary">Em andamento</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm {{ $p->ativo ? 'btn-success' : 'btn-outline-secondary' }}"
                                            wire:click="toggleAtivo({{ $p->id }})"
                                            wire:loading.attr="disabled" wire:target="toggleAtivo({{ $p->id }})"
                                            title="{{ $p->ativo ? 'Monitorando — clique para desativar' : 'Ativar monitoramento (busca o mais atual no CNJ)' }}">
                                        <span wire:loading.remove wire:target="toggleAtivo({{ $p->id }})">
                                            <i class="bi {{ $p->ativo ? 'bi-bell-fill' : 'bi-bell-slash' }}"></i>
                                            {{ $p->ativo ? 'Ativo' : 'Inativo' }}
                                        </span>
                                        <span wire:loading wire:target="toggleAtivo({{ $p->id }})">
                                            <span class="spinner-border spinner-border-sm"></span>
                                        </span>
                                    </button>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger" wire:click="excluirProcesso({{ $p->id }})"
                                            wire:confirm="Remover este processo?"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum processo. Use "Buscar processos no CNJ".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($processos && $processos->hasPages())
                <div class="card-footer">{{ $processos->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    @endif
</div>
