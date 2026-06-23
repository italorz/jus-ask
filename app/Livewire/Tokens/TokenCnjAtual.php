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
        $this->criadoEm = $registro?->created_at?->format('d/m/Y H:i');
        $this->expiraEm = null;
        $this->expirado = false;

        if (! $registro) {
            return;
        }

        // Expiração REAL vem do "exp" do JWT; se não der pra decodificar, cai no
        // fallback de "cadastrado + 7h".
        $expira = $this->expDoJwt($registro->token)
            ?? $registro->created_at?->copy()->addHours(self::HORAS_VALIDADE);

        if ($expira) {
            $this->expiraEm = $expira->format('d/m/Y H:i');
            $this->expirado = now()->greaterThan($expira);
        }
    }

    /** Lê o "exp" (validade real) de um token JWT. Retorna null se não for um JWT válido. */
    private function expDoJwt(?string $token): ?\Illuminate\Support\Carbon
    {
        if (! $token) {
            return null;
        }

        $partes = explode('.', trim(str_replace('Bearer ', '', $token)));

        if (count($partes) < 2) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')) ?: '', true);

        if (! is_array($payload) || empty($payload['exp'])) {
            return null;
        }

        return \Illuminate\Support\Carbon::createFromTimestamp((int) $payload['exp']);
    }

    public function render()
    {
        return view('livewire.tokens.token-cnj-atual')->extends('layouts.app');
    }
}
