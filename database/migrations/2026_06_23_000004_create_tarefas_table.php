<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarefas', function (Blueprint $table) {
            $table->id();
            $table->string('tenant')->index();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('status', 20)->default('a_fazer'); // a_fazer | fazendo | concluido
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('processo_id')->nullable()->constrained('processos')->nullOnDelete();
            $table->date('prazo')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();

            $table->index(['tenant', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarefas');
    }
};
