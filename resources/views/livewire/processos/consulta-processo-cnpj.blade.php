<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Consulta de processos por CNPJ</h1>
    </div>

    <p class="text-muted" style="font-size:.9rem;">
        Usa a mesma lógica da tool MCP <code>consultar-processos-por-cnpj</code> e sempre o
        <strong>último token CNJ gerado</strong>. Consultas grandes (milhares de processos) são
        processadas em segundo plano.
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

    @if ($erro)
        <div class="alert alert-danger">{{ $erro }}</div>
    @endif

    @if ($resultado)
        @php $st = $resultado['status'] ?? 'done'; @endphp

        {{-- Em processamento (consulta grande em segundo plano) --}}
        @if ($st === 'processing')
            <div class="card" wire:poll.3s="atualizarStatus">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
                    <div>
                        <div class="fw-semibold">Buscando processos em segundo plano…</div>
                        <div class="text-muted" style="font-size:.9rem;">
                            {{ $resultado['coletados'] ?? 0 }} de {{ $resultado['total'] ?? '?' }} processos
                            (CNPJ <code>{{ $resultado['cnpj'] }}</code>). Esta tela atualiza sozinha.
                        </div>
                    </div>
                </div>
                @if (($resultado['total'] ?? 0) > 0)
                    <div class="progress rounded-0" style="height:6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             style="width: {{ min(100, (int) round(($resultado['coletados'] ?? 0) / max(1, $resultado['total']) * 100)) }}%"></div>
                    </div>
                @endif
            </div>

        {{-- Erro reportado pelo job --}}
        @elseif ($st === 'error')
            <div class="alert alert-danger">{{ $resultado['erro'] ?? 'Falha ao consultar os processos.' }}</div>

        {{-- Concluído --}}
        @else
            <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                <span class="badge bg-primary fs-6">{{ $resultado['total'] }}</span>
                <span class="text-muted">processo(s) para o CNPJ <code>{{ $resultado['cnpj'] }}</code></span>
                @if (! empty($resultado['truncado']))
                    <span class="badge bg-warning text-dark">coletados {{ $resultado['coletados'] }} (limite atingido)</span>
                @endif
            </div>

            @if (($resultado['total'] ?? 0) === 0)
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
                                <div class="card-header fw-semibold d-flex justify-content-between">
                                    <span>{{ $titulo }}</span>
                                    <span class="text-muted fw-normal">{{ count($dados) }}</span>
                                </div>
                                <ul class="list-group list-group-flush" style="max-height:320px;overflow:auto;">
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

                {{-- Tabela (amostra) --}}
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Processos</span>
                        @if (! empty($resultado['amostra_truncada']))
                            <span class="text-muted" style="font-size:.85rem;">
                                mostrando os primeiros {{ count($resultado['processos']) }} de {{ $resultado['coletados'] }}
                            </span>
                        @endif
                    </div>
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
    @endif
</div>
