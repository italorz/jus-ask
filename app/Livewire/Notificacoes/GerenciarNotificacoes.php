<?php

namespace App\Livewire\Notificacoes;

use App\Models\Notificacao;
use App\Services\TenantManager;
use Livewire\Component;

class GerenciarNotificacoes extends Component
{
    public bool $apenasNaoLidas = false;

    public function mount(): void
    {
        if (! app(TenantManager::class)->check()) {
            redirect()->route('home');
        }
    }

    public function marcarLida(int $id): void
    {
        Notificacao::findOrFail($id)->update(['lida' => true]);
    }

    public function marcarTodasLidas(): void
    {
        Notificacao::where('lida', false)->update(['lida' => true]);
        session()->flash('status', 'Todas as notificações foram marcadas como lidas.');
    }

    public function render()
    {
        $query = Notificacao::with('processo')
            ->orderByDesc('created_at');

        if ($this->apenasNaoLidas) {
            $query->where('lida', false);
        }

        return view('livewire.notificacoes.gerenciar-notificacoes', [
            'notificacoes' => $query->paginate(20),
            'totalNaoLidas' => Notificacao::where('lida', false)->count(),
        ])->extends('layouts.app');
    }
}
