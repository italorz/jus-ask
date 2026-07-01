<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarefa extends Model
{
    use BelongsToTenant;

    protected $table = 'tarefas';

    protected $fillable = [
        'empresa_id',
        'tenant',
        'titulo',
        'descricao',
        'status',
        'cliente_id',
        'processo_id',
        'prazo',
        'hora',
        'ordem',
    ];

    protected function casts(): array
    {
        return ['prazo' => 'date'];
    }

    /** Colunas do kanban. */
    public const STATUS = [
        'a_fazer'   => 'A fazer',
        'fazendo'   => 'Fazendo',
        'concluido' => 'Concluído',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }
}
