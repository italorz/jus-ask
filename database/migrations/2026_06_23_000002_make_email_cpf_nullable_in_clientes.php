<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * email e cpf eram NOT NULL (cadastro de pessoa física). Para permitir cadastrar
     * clientes do tipo empresa (CNPJ) sem colidir nos uniques (tenant,email)/(tenant,cpf),
     * passam a aceitar NULL.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('cpf')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('cpf')->nullable(false)->change();
        });
    }
};
