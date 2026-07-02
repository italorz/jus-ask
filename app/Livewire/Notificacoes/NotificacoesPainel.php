<?php

namespace App\Livewire\Notificacoes;

use App\Models\Notificacao;
use Livewire\Component;

class NotificacoesPainel extends Component
{
    public function marcarLida(int $id): void
    {
        Notificacao::findOrFail($id)->update(['lida' => true]);
    }

    public function render()
    {
        return view('livewire.notificacoes.notificacoes-painel', [
            'totalNaoLidas' => Notificacao::where('lida', false)->count(),
            'recentes' => Notificacao::where('lida', false)
                ->with('processo')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
