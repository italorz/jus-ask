<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Processo extends Model
{
    use BelongsToTenant;

    protected $table = 'processos';

    protected $attributes = ['ativo' => true];

    protected $fillable = [
        'cliente_id',
        'empresa_id',
        'tenant',
        'numero',
        'ultima_atualizacao',
        'ativo',
        'data_hora_ajuizamento',
        'valor_acao',
        'data_hora_ultima_distribuicao',
        'assunto',
        'tribunal',
        'classe',
        'situacao',
        'ultimo_movimento_codigo',
        'ultimo_movimento',
        'ultimo_movimento_em',
    ];

    protected function casts(): array
    {
        return [
            'ultima_atualizacao'            => 'datetime',
            'ativo'                         => 'boolean',
            'data_hora_ajuizamento'         => 'datetime',
            'data_hora_ultima_distribuicao' => 'datetime',
            'valor_acao'                    => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function conteudos(): HasMany
    {
        return $this->hasMany(ConteudoProcesso::class);
    }

    public function processosConteudos(): HasMany
    {
        return $this->hasMany(ProcessoConteudo::class);
    }

    public function contatos(): HasMany
    {
        return $this->hasMany(ProcessoContato::class);
    }

    public function notificacoes(): HasMany
    {
        return $this->hasMany(Notificacao::class);
    }

    /**
     * Processos ABERTOS (por data de ajuizamento) por mês, nos últimos N meses.
     * Retorna labels (ex.: "Jun/26"), valores e total. Filtros opcionais:
     * situacao ('em_andamento'|'concluido'), ativo ('1'|'0'), tribunal.
     *
     * @return array{labels: array<int,string>, valores: array<int,int>, total: int}
     */
    public static function aberturasPorMes(string $tenant, int $meses = 12, array $filtros = []): array
    {
        $meses = max(1, $meses);
        $inicio = now()->startOfMonth()->subMonths($meses - 1);

        $contagem = static::query()
            ->withoutGlobalScopes()
            ->where('tenant', $tenant)
            ->whereNotNull('data_hora_ajuizamento')
            ->where('data_hora_ajuizamento', '>=', $inicio)
            ->when(($filtros['situacao'] ?? '') !== '', fn ($q) => $q->where('situacao', $filtros['situacao']))
            ->when(($filtros['ativo'] ?? '') !== '', fn ($q) => $q->where('ativo', $filtros['ativo'] === '1'))
            ->when(($filtros['tribunal'] ?? '') !== '', fn ($q) => $q->where('tribunal', $filtros['tribunal']))
            ->selectRaw("to_char(data_hora_ajuizamento, 'YYYY-MM') as mes, count(*) as c")
            ->groupBy('mes')
            ->pluck('c', 'mes');

        $labels = [];
        $valores = [];

        for ($i = 0; $i < $meses; $i++) {
            $mes = now()->startOfMonth()->subMonths($meses - 1 - $i);
            $labels[] = ucfirst($mes->locale('pt_BR')->isoFormat('MMM/YY'));
            $valores[] = (int) ($contagem[$mes->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'valores' => $valores, 'total' => array_sum($valores)];
    }
}
