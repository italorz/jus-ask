<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'tipo')) {
                // funil: prospeccao -> prospectado -> cliente
                $table->string('tipo', 20)->default('cliente')->after('cnpj');
                $table->index(['tenant', 'tipo']);
            }
        });

        Schema::table('processos', function (Blueprint $table) {
            if (! Schema::hasColumn('processos', 'situacao')) {
                $table->string('situacao', 20)->nullable()->after('classe'); // concluido | em_andamento
            }
            if (! Schema::hasColumn('processos', 'ultimo_movimento_codigo')) {
                $table->integer('ultimo_movimento_codigo')->nullable()->after('situacao');
            }
            if (! Schema::hasColumn('processos', 'ultimo_movimento')) {
                $table->string('ultimo_movimento', 500)->nullable()->after('ultimo_movimento_codigo');
            }
            if (! Schema::hasColumn('processos', 'ultimo_movimento_em')) {
                $table->dateTime('ultimo_movimento_em')->nullable()->after('ultimo_movimento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn(['situacao', 'ultimo_movimento_codigo', 'ultimo_movimento', 'ultimo_movimento_em']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['tenant', 'tipo']);
            $table->dropColumn('tipo');
        });
    }
};
