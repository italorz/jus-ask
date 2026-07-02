<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A tabela ja existe em producao (criada antes da migration/model terem
        // sido perdidos do repositorio); evita recriar em ambientes onde ja existe.
        if (Schema::hasTable('processo_clientes')) {
            return;
        }

        Schema::create('processo_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('processos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tenant')->index();
            $table->enum('canal_notificacao', ['nenhum', 'email', 'whatsapp', 'ambos'])->default('nenhum');
            $table->timestamps();

            $table->unique(['processo_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processo_clientes');
    }
};
