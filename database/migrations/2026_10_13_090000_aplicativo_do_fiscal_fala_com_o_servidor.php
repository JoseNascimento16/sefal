<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O aplicativo do fiscal passa a gravar no servidor (dono, 13/10/2026).
 *
 * Duas coisas que o registro de campo já levava e o banco não tinha onde pôr:
 *
 * - na VISTORIA, o relato que o fiscal faz no ponto (diferente das
 *   considerações do despacho), as ocorrências marcadas em chips (chaves) e a
 *   data combinada para o retorno;
 * - a RESERVA de números oficiais de Notificação e Auto: o número sai impresso
 *   na rua, muitas vezes sem sinal, então ele é reservado ANTES, para o fiscal,
 *   e só depois chega ao servidor junto com o documento. Sem a reserva, dois
 *   aparelhos imprimiriam o mesmo número.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscalizacoes', function (Blueprint $table) {
            if (! Schema::hasColumn('fiscalizacoes', 'relato')) {
                $table->text('relato')->nullable();
            }

            if (! Schema::hasColumn('fiscalizacoes', 'ocorrencias')) {
                $table->text('ocorrencias')->nullable();
            }

            if (! Schema::hasColumn('fiscalizacoes', 'retorno_em')) {
                $table->date('retorno_em')->nullable();
            }
        });

        if (! Schema::hasTable('numeros_reservados')) {
            Schema::create('numeros_reservados', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 10);
                $table->string('numero', 20);
                $table->foreignId('user_id')->constrained('users');
                $table->timestamp('reservado_em');
                $table->timestamp('usado_em')->nullable();
                $table->foreignId('fiscalizacao_id')->nullable()->constrained('fiscalizacoes')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tipo', 'numero']);
                $table->index(['user_id', 'tipo', 'usado_em']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('numeros_reservados');

        Schema::table('fiscalizacoes', function (Blueprint $table) {
            foreach (['relato', 'ocorrencias', 'retorno_em'] as $coluna) {
                if (Schema::hasColumn('fiscalizacoes', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
