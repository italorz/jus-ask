<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Consulta de processos por CNPJ</h1>
    </div>

    <p class="text-muted" style="font-size:.9rem;">
        Usa a mesma lógica da tool MCP <code>consultar-processos-por-cnpj</code> e sempre o
        <strong>último token CNJ gerado</strong>. Útil para testar a consulta sem passar pelo Claude.
    </p>

    {{-- Formulário --}}
    <div class="card mb-4">
        <div class="card-body">
            <form wire:submit="consultar" class="row g-2 align-items-end">
                <div class="col-sm-8 col-md-6">
                    <label for="cnpj" class="form-label">CNPJ da parte</label>
                    <input type="text" id="cnpj" class="form-control @error('cnpj') is-invalid @enderror"
                           placeholder="Ex.: 52123916000132" wire:model="cnpj" autocomplete="off">
                    @error('cnpj') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-4 col-md-3">
                    <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="consultar">
                        <span wire:loading.remove wire:target="consultar">Consultar</span>
                        <span wire:loading wire:target="consultar">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Consultando...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Erro --}}
    @if ($erro)
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    {{-- Resultado --}}
    @if ($resultado)
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge bg-primary fs-6">{{ $resultado['total'] }}</span>
            <span class="text-muted">processo(s) encontrado(s) para o CNPJ <code>{{ $resultado['cnpj'] }}</code></span>
        </div>

        @if ($resultado['total'] === 0)
            <div class="alert alert-info">Nenhum processo retornado pelo PDPJ para este CNPJ.</div>
        @else
            {{-- Agregações --}}
            <div class="row g-3 mb-4">
                @php
                    $blocos = [
                        'Por tribunal' => $resultado['agregacoes']['por_tribunal'],
                        'Por ano'      => $resultado['agregacoes']['por_ano'],
                        'Por classe'   => $resultado['agregacoes']['por_classe'],
                    ];
                @endphp
                @foreach ($blocos as $titulo => $dados)
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">{{ $titulo }}</div>
                            <ul class="list-group list-group-flush">
                                @forelse ($dados as $rotulo => $qtd)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="text-truncate me-2">{{ $rotulo }}</span>
                                        <span class="badge bg-secondary rounded-pill">{{ $qtd }}</span>
                                    </li>
                                @empty
                                    <li class="list-group-item text-muted">—</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Tabela de processos --}}
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Tribunal</th>
                                <th>Classe</th>
                                <th>Assunto</th>
                                <th class="text-end">Valor</th>
                                <th class="text-center">Ano</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resultado['processos'] as $p)
                                <tr>
                                    <td><code>{{ $p['numero'] ?? '—' }}</code></td>
                                    <td>{{ $p['sigla'] ?? $p['tribunal'] }}</td>
                                    <td>{{ $p['classe'] }}</td>
                                    <td>{{ $p['assunto'] ?? '—' }}</td>
                                    <td class="text-end">
                                        {{ $p['valor_acao'] ? 'R$ ' . number_format((float) $p['valor_acao'], 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-center">{{ $p['ano_ajuizamento'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
