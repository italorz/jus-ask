<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->renameColumn('encerrado', 'ativo');
        });

        // Inverte os valores: encerrado=0(ativo) → ativo=1; encerrado=1(fechado) → ativo=0
        DB::statement('UPDATE processos SET ativo = CASE WHEN ativo = 1 THEN 0 ELSE 1 END');
    }

    public function down(): void
    {
        DB::statement('UPDATE processos SET ativo = CASE WHEN ativo = 1 THEN 0 ELSE 1 END');

        Schema::table('processos', function (Blueprint $table) {
            $table->renameColumn('ativo', 'encerrado');
        });
    }
};
