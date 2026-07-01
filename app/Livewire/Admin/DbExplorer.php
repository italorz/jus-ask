<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class DbExplorer extends Component
{
    public string  $tabelaSelecionada = '';
    public string  $sql               = '';
    public ?array  $colunas           = null;
    public ?array  $linhas            = null;
    public ?string $erro              = null;
    public ?string $info              = null;
    public int     $limite            = 100;

    public function mount(): void
    {
        if (! auth()->user()?->can('super-admin')) {
            abort(403);
        }
    }

    public function selecionarTabela(string $tabela): void
    {
        $this->tabelaSelecionada = $tabela;
        $this->sql               = "SELECT * FROM \"{$tabela}\" LIMIT {$this->limite};";
        $this->colunas           = null;
        $this->linhas            = null;
        $this->erro              = null;
        $this->info              = null;
    }

    public function executar(): void
    {
        $this->colunas = null;
        $this->linhas  = null;
        $this->erro    = null;
        $this->info    = null;

        $sql = trim($this->sql);

        if ($sql === '') {
            $this->erro = 'Digite uma query SQL.';
            return;
        }

        // Só permite SELECT
        if (! preg_match('/^\s*SELECT\s/i', $sql)) {
            $this->erro = 'Apenas instruções SELECT são permitidas.';
            return;
        }

        // Bloqueia substrings perigosas mesmo dentro de um SELECT
        $proibidas = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'EXEC', 'EXECUTE', 'GRANT', 'REVOKE'];
        foreach ($proibidas as $p) {
            if (preg_match('/\b' . $p . '\b/i', $sql)) {
                $this->erro = "Instrução não permitida detectada: {$p}.";
                return;
            }
        }

        try {
            $inicio     = microtime(true);
            $resultados = DB::select($sql);
            $ms         = round((microtime(true) - $inicio) * 1000, 1);

            if (empty($resultados)) {
                $this->colunas = [];
                $this->linhas  = [];
                $this->info    = "Nenhum resultado. ({$ms} ms)";
                return;
            }

            $this->colunas = array_keys((array) $resultados[0]);
            $this->linhas  = array_map(fn ($row) => array_values((array) $row), $resultados);
            $this->info    = count($resultados) . ' linha(s) — ' . $ms . ' ms';
        } catch (\Throwable $e) {
            $this->erro = $e->getMessage();
        }
    }

    public function render()
    {
        $tabelas = collect(DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_type = 'BASE TABLE'
            ORDER BY table_name
        "))->pluck('table_name')->toArray();

        $estrutura = [];

        if ($this->tabelaSelecionada) {
            $estrutura = collect(DB::select("
                SELECT column_name, data_type, is_nullable, column_default
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = ?
                ORDER BY ordinal_position
            ", [$this->tabelaSelecionada]))->toArray();
        }

        return view('livewire.admin.db-explorer', [
            'tabelas'   => $tabelas,
            'estrutura' => $estrutura,
        ])->extends('layouts.app');
    }
}
