<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Consulta de processos por CNPJ</h1>
    </div>

    <p class="text-muted" style="font-size:.9rem;">
        Coleta os processos do CNPJ no PDPJ/CNJ e <strong>salva no banco</strong> (cadastra a empresa como
        cliente e grava cada processo como <strong>inativo</strong>, fora do monitoramento). Consultas grandes
        rodam em segundo plano — você pode acompanhar e cancelar.
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

        {{-- Em processamento --}}
        @if ($st === 'processing')
            <div class="card" wire:poll.3s="atualizarStatus">
                <div class="card-body d-flex align-items-center gap-3 flex-wrap">
                    <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Coletando e salvando processos em segundo plano…</div>
                        <div class="text-muted" style="font-size:.9rem;">
                            {{ $resultado['coletados'] ?? 0 }} de {{ $resultado['total'] ?? '?' }} processos
                            (CNPJ <code>{{ $resultado['cnpj'] }}</code>). Atualiza sozinho.
                        </div>
                    </div>
                    <button class="btn btn-outline-danger btn-sm" wire:click="cancelar" wire:loading.attr="disabled" wire:target="cancelar">
                        Cancelar
                    </button>
                </div>
                @if (($resultado['total'] ?? 0) > 0)
                    <div class="progress rounded-0" style="height:6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             style="width: {{ min(100, (int) round(($resultado['coletados'] ?? 0) / max(1, $resultado['total']) * 100)) }}%"></div>
                    </div>
                @endif
            </div>

        @elseif ($st === 'error')
            <div class="alert alert-danger">{{ $resultado['erro'] ?? 'Falha ao consultar os processos.' }}</div>

        {{-- Concluído ou cancelado --}}
        @else
            <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                @if ($st === 'cancelado')
                    <span class="badge bg-warning text-dark">cancelada</span>
                @endif
                <span class="badge bg-primary fs-6">{{ $resultado['total_no_banco'] ?? ($resultado['coletados'] ?? 0) }}</span>
                <span class="text-muted">processo(s) salvo(s) para o CNPJ <code>{{ $resultado['cnpj'] }}</code></span>
                @if (($resultado['total'] ?? 0) > ($resultado['total_no_banco'] ?? 0))
                    <span class="text-muted" style="font-size:.85rem;">(PDPJ informa {{ $resultado['total'] }} no total)</span>
                @endif
            </div>

            @php $agg = $resultado['agregacoes'] ?? []; @endphp
            @if ($agg)
                <div class="row g-3 mb-4">
                    @php
                        $blocos = [
                            'Por tribunal' => $agg['por_tribunal'] ?? [],
                            'Por ano'      => $agg['por_ano'] ?? [],
                            'Por classe'   => $agg['por_classe'] ?? [],
                        ];
                    @endphp
                    @foreach ($blocos as $titulo => $dados)
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header fw-semibold d-flex justify-content-between">
                                    <span>{{ $titulo }}</span><span class="text-muted fw-normal">{{ count($dados) }}</span>
                                </div>
                                <ul class="list-group list-group-flush" style="max-height:300px;overflow:auto;">
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
            @endif

            {{-- Tabela (lida do banco em lotes) --}}
            @if ($lote && ($lote['total_no_banco'] ?? 0) > 0)
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="fw-semibold">Processos salvos</span>
                        <span class="text-muted" style="font-size:.85rem;">
                            página {{ $lote['pagina'] }} de {{ $lote['paginas_total'] }}
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Número</th><th>Tribunal</th><th>Classe</th><th>Assunto</th>
                                    <th class="text-end">Valor</th><th class="text-center">Ano</th><th class="text-center">Ativo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lote['processos'] as $p)
                                    <tr>
                                        <td><code>{{ $p['numero'] ?? '—' }}</code></td>
                                        <td>{{ $p['tribunal'] ?? '—' }}</td>
                                        <td>{{ $p['classe'] ?? '—' }}</td>
                                        <td>{{ $p['assunto'] ?? '—' }}</td>
                                        <td class="text-end">
                                            {{ $p['valor_acao'] ? 'R$ ' . number_format((float) $p['valor_acao'], 2, ',', '.') : '—' }}
                                        </td>
                                        <td class="text-center">{{ $p['ano'] ?? '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $p['ativo'] ? 'success' : 'secondary' }}">{{ $p['ativo'] ? 'sim' : 'não' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (($lote['paginas_total'] ?? 1) > 1)
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <button class="btn btn-sm btn-outline-secondary" wire:click="irPara({{ $lote['pagina'] - 1 }})"
                                    @disabled($lote['pagina'] <= 1)>← Anterior</button>
                            <span class="text-muted" style="font-size:.85rem;">{{ $lote['pagina'] }} / {{ $lote['paginas_total'] }}</span>
                            <button class="btn btn-sm btn-outline-secondary" wire:click="irPara({{ $lote['pagina'] + 1 }})"
                                    @disabled($lote['pagina'] >= $lote['paginas_total'])>Próxima →</button>
                        </div>
                    @endif
                </div>
            @endif
        @endif
    @endif

    {{-- Toast de conclusão (canto superior direito) --}}
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1090;">
        <div id="toastConsulta" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastConsultaBody">Concluído.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
            </div>
        </div>
    </div>

    @script
    <script>
        Livewire.on('processos-concluidos', (e) => {
            const d = Array.isArray(e) ? e[0] : e;
            const cancelado = d.status === 'cancelado';
            const el = document.getElementById('toastConsulta');
            const body = document.getElementById('toastConsultaBody');

            el.classList.toggle('text-bg-success', !cancelado);
            el.classList.toggle('text-bg-warning', cancelado);
            body.textContent = (cancelado ? 'Consulta cancelada' : 'Processos concluídos')
                + ' — ' + (d.total ?? 0) + ' processo(s) do CNPJ ' + (d.cnpj ?? '') + '.';

            new bootstrap.Toast(el, { delay: 7000 }).show();
        });
    </script>
    @endscript
</div>
