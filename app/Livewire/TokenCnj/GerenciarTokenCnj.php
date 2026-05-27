<?php

namespace App\Livewire\TokenCnj;

use App\Models\TokenCnj;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Http\Request;

class GerenciarTokenCnj extends Component
{
    public string $tokenInput = '';
    public string $erro       = '';

    public function salvar(): void
    {
        $token = trim($this->tokenInput);

        if ($token === '') {
            $this->erro = 'Informe o token antes de salvar.';
            return;
        }

        TokenCnj::create(['token' => $token]);

        $this->tokenInput = '';
        $this->erro       = '';

        session()->flash('status', 'Token cadastrado com sucesso. Ele será usado nas próximas consultas à API PDPJ.');
    }

    public function excluir(int $id): void
    {
        TokenCnj::findOrFail($id)->delete();
        session()->flash('status', 'Token removido.');
    }

    public function render()
    {
        // Carbon::setLocale('pt_BR');
        // $hoje = Carbon::now()->startOfDay();
        // dd($hoje);
        return view('livewire.token-cnj.gerenciar-token-cnj', [
            'tokens' => TokenCnj::latest()->get(),
        ])->extends('layouts.app');
    }
}
