<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'cnpj')) {
                $table->string('cnpj', 18)->nullable()->after('cpf');
                $table->index(['tenant', 'cnpj']);
            }
        });

        Schema::table('processos', function (Blueprint $table) {
            if (! Schema::hasColumn('processos', 'tribunal')) {
                $table->string('tribunal', 40)->nullable()->after('assunto');
            }
            if (! Schema::hasColumn('processos', 'classe')) {
                $table->string('classe', 255)->nullable()->after('tribunal');
            }
            // Índice para acelerar o "já existe este número neste tenant?" e as leituras.
            $table->index(['tenant', 'numero'], 'processos_tenant_numero_idx');
        });
    }

    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dropIndex('processos_tenant_numero_idx');
            $table->dropColumn(['tribunal', 'classe']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['tenant', 'cnpj']);
            $table->dropColumn('cnpj');
        });
    }
};
