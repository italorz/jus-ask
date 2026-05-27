<?php

namespace App\Services;

use App\Models\Empresa;

/**
 * Mantem a empresa (tenant) ativa do request atual.
 * Registrado como singleton no container.
 */
class TenantManager
{
    protected ?Empresa $empresa = null;

    public function set(Empresa $empresa): void
    {
        $this->empresa = $empresa;
    }

    public function forget(): void
    {
        $this->empresa = null;
    }

    public function empresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function id(): ?int
    {
        return $this->empresa?->id;
    }

    public function tenant(): ?string
    {
        return $this->empresa?->tenant;
    }

    public function check(): bool
    {
        return $this->empresa !== null;
    }
}
