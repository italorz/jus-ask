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

    public ?string $expiraEm = null;

    public bool $expirado = false;

    /** Validade do token CNJ em horas a partir da criação (informativo). */
    private const HORAS_VALIDADE = 7;

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
        $this->criadoEm = null;
        $this->expiraEm = null;
        $this->expirado = false;

        if ($registro?->created_at) {
            $criado = $registro->created_at;
            $expira = $criado->copy()->addHours(self::HORAS_VALIDADE);

            $this->criadoEm = $criado->format('d/m/Y H:i');
            $this->expiraEm = $expira->format('d/m/Y H:i');
            $this->expirado = now()->greaterThan($expira);
        }
    }

    public function render()
    {
        return view('livewire.tokens.token-cnj-atual')->extends('layouts.app');
    }
}
