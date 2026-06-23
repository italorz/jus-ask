<div class="container">
    <h1 class="h3 mb-3"><i class="bi bi-bar-chart me-2"></i>Processos abertos por mês</h1>
    <p class="text-muted" style="font-size:.9rem;">
        Quantidade de processos por mês de ajuizamento (abertura), com filtros dinâmicos.
    </p>

    {{-- Filtros --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Período</label>
                    <select class="form-select" wire:model.live="meses">
                        <option value="6">Últimos 6 meses</option>
                        <option value="12">Últimos 12 meses</option>
                        <option value="24">Últimos 24 meses</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Situação</label>
                    <select class="form-select" wire:model.live="filtroSituacao">
                        <option value="">Todas</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="concluido">Concluído</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Monitorado</label>
                    <select class="form-select" wire:model.live="filtroAtivo">
                        <option value="">Todos</option>
                        <option value="1">Ativo</option>
                        <option value="0">Inativo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Tribunal</label>
                    <select class="form-select" wire:model.live="filtroTribunal">
                        <option value="">Todos</option>
                        @foreach ($tribunais as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="text-muted small mb-2"><strong>{{ $dados['total'] }}</strong> processo(s) no período</div>
            <div style="height:360px;">
                <canvas id="chartProcessos" wire:ignore></canvas>
            </div>
            <div id="grafico-dados" hidden
                 wire:key="gd-{{ md5(json_encode($dados)) }}"
                 data-dados='@json($dados)'></div>
        </div>
    </div>

    @script
    <script>
        let chartProcessos = null;

        const corTexto = () => getComputedStyle(document.documentElement).getPropertyValue('--text') || '#1c2b3a';
        const corBorda = () => getComputedStyle(document.documentElement).getPropertyValue('--border') || '#dce4ef';

        function renderGrafico() {
            const el = document.getElementById('grafico-dados');
            const ctx = document.getElementById('chartProcessos');
            if (!el || !ctx || typeof Chart === 'undefined') return;

            const dados = JSON.parse(el.dataset.dados);

            if (chartProcessos) {
                chartProcessos.data.labels = dados.labels;
                chartProcessos.data.datasets[0].data = dados.valores;
                chartProcessos.update();
                return;
            }

            chartProcessos = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dados.labels,
                    datasets: [{
                        label: 'Processos abertos',
                        data: dados.valores,
                        backgroundColor: '#1a56db',
                        borderRadius: 4,
                        maxBarThickness: 48,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, color: corTexto() }, grid: { color: corBorda() } },
                        x: { ticks: { color: corTexto() }, grid: { display: false } },
                    },
                },
            });
        }

        renderGrafico();
        Livewire.hook('morph.updated', () => renderGrafico());
    </script>
    @endscript
</div>
