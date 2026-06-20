<?php

namespace App\Livewire\Tokens;

use App\Models\TokenCnj;
use App\Services\TenantManager;
use Livewire\Component;

class TokenCnjAtual extends Component
{
    public ?string $tenant = null;

    public ?string $token = null;

    public ?string $criadoEm = null;

    public function mount()
    {
        $tm = app(TenantManager::class);

        if (! $tm->check()) {
            return redirect()->route('home');
        }

        $this->tenant = $tm->tenant();
        $this->carregar();
    }

    /**
     * Carrega sempre o ÚLTIMO token CNJ cadastrado para o tenant ativo.
     */
    public function carregar(): void
    {
        $registro = TokenCnj::where('tenant', $this->tenant)->latest()->first();

        $this->token = $registro?->token;
        $this->criadoEm = $registro?->created_at?->format('d/m/Y H:i');
    }

    public function render()
    {
        return view('livewire.tokens.token-cnj-atual')->extends('layouts.app');
    }
}
