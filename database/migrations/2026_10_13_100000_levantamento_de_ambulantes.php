<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O LEVANTAMENTO de ambulantes — o censo por rua que o setor faz em planilha —
 * passa a ser gravado pelo aplicativo do fiscal (dono, 13/10/2026).
 *
 * O cabeçalho é da força-tarefa (rua, bairro, equipes e encarregados); cada
 * linha é um ambulante encontrado, com a foto do equipamento e o ponto de GPS
 * de onde o fiscal estava ao somá-lo. Idempotente pelo `client_id`, como o
 * registro de vistoria: a fila do aparelho reenvia quando a resposta se perde.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('levantamentos')) {
            Schema::create('levantamentos', function (Blueprint $table) {
                $table->id();
                $table->string('protocolo', 20)->unique();
                $table->string('client_id', 40)->unique();
                $table->foreignId('user_id')->constrained('users');
                $table->string('rua', 200);
                $table->string('bairro', 120);
                // Os códigos das equipes e os nomes dos encarregados, como saem no papel.
                $table->text('equipes');
                $table->text('encarregados');
                $table->timestamp('iniciado_em');
                $table->timestamp('concluido_em');
                $table->timestamps();

                $table->index(['user_id', 'concluido_em']);
            });
        }

        if (! Schema::hasTable('levantamento_linhas')) {
            Schema::create('levantamento_linhas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('levantamento_id')->constrained('levantamentos')->cascadeOnDelete();
                $table->string('item', 5);
                $table->string('nome', 150);
                $table->string('documento', 20)->nullable();
                $table->string('contato', 40)->nullable();
                $table->string('referencia', 150)->nullable();
                $table->string('atividade', 80)->nullable();
                // A foto do equipamento, no disco privado.
                $table->string('foto', 255)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('levantamento_linhas');
        Schema::dropIfExists('levantamentos');
    }
};
