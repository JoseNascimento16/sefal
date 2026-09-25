<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FISCAIS DA OPERAÇÃO (dono, 25/09/2026): fiscais de qualquer área podem ser
 * postos numa operação, além dos fiscais das equipes que a executam. A
 * fiscalização da operação chega a todos eles — os da equipe e os postos aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operacao_fiscais')) {
            return;
        }

        Schema::create('operacao_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['operacao_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacao_fiscais');
    }
};
