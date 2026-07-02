<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessoCliente extends Model
{
    use BelongsToTenant;

    protected $table = 'processo_clientes';

    protected $fillable = [
        'processo_id',
        'cliente_id',
        'empresa_id',
        'tenant',
        'canal_notificacao',
    ];

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
