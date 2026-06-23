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
        return Processo::aberturasPorMes($this->tenant, $this->meses, [
            'situacao' => $this->filtroSituacao,
            'ativo' => $this->filtroAtivo,
            'tribunal' => $this->filtroTribunal,
        ]);
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
