<?php

namespace App\Livewire\Processos;

use App\Models\Processo;
use App\Services\TenantManager;
use Illuminate\Support\Carbon;
use Livewire\Component;

class GraficosProcessos extends Component
{
    public int $meses = 12;

    public string $filtroSituacao = ''; // '' | em_andamento | concluido

    public string $filtroAtivo = '';    // '' | 1 | 0

    public string $filtroTribunal = '';

    public ?string $tenant = null;

    public function mount()
    {
        $tm = app(TenantManager::class);

        if (! $tm->check()) {
            return redirect()->route('home');
        }

        $this->tenant = $tm->tenant();
    }

    /** Processos ABERTOS (data de ajuizamento) por mês, nos últimos N meses, com filtros. */
    private function dados(): array
    {
        $inicio = now()->startOfMonth()->subMonths(max(1, $this->meses) - 1);

        $contagem = Processo::query()
            ->where('tenant', $this->tenant)
            ->whereNotNull('data_hora_ajuizamento')
            ->where('data_hora_ajuizamento', '>=', $inicio)
            ->when($this->filtroSituacao !== '', fn ($q) => $q->where('situacao', $this->filtroSituacao))
            ->when($this->filtroAtivo !== '', fn ($q) => $q->where('ativo', $this->filtroAtivo === '1'))
            ->when($this->filtroTribunal !== '', fn ($q) => $q->where('tribunal', $this->filtroTribunal))
            ->selectRaw("to_char(data_hora_ajuizamento, 'YYYY-MM') as mes, count(*) as c")
            ->groupBy('mes')
            ->pluck('c', 'mes');

        $labels = [];
        $valores = [];

        for ($i = 0; $i < $this->meses; $i++) {
            $mes = now()->startOfMonth()->subMonths($this->meses - 1 - $i);
            $labels[] = ucfirst($mes->locale('pt_BR')->isoFormat('MMM/YY'));
            $valores[] = (int) ($contagem[$mes->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'valores' => $valores, 'total' => array_sum($valores)];
    }

    private function tribunais()
    {
        return Processo::query()
            ->where('tenant', $this->tenant)
            ->whereNotNull('tribunal')
            ->distinct()
            ->orderBy('tribunal')
            ->pluck('tribunal');
    }

    public function render()
    {
        return view('livewire.processos.graficos-processos', [
            'dados' => $this->dados(),
            'tribunais' => $this->tribunais(),
        ])->extends('layouts.app');
    }
}
