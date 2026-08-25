<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A matriz do Modo Gerente e o rastro de quem a alterou.
 *
 * A tabela nasce VAZIA de propósito: a concessão inicial é semeada pelo
 * `PermissoesSetorSeeder`, que a deriva do menu. Semear dentro da migração
 * pareceria mais direto, mas daria dois donos à mesma regra — o dia em que uma
 * tela nova entrasse no menu, a migração já teria rodado e ninguém saberia por
 * qual dos dois caminhos a concessão devia vir. Com um dono só, semear de novo é
 * seguro (é idempotente) e não desfaz o que o gerente decidiu na tela.
 *
 * Nada de chave estrangeira para `setores`/`users`: `setor` é o slug (o texto que
 * a matriz mostra) e `user_id` é histórico — a linha de log tem de sobreviver ao
 * desligamento da conta que a gerou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissoes_setor', function (Blueprint $table) {
            $table->id();
            $table->string('setor', 30);
            $table->string('slug', 80);
            $table->boolean('visivel')->default(false);
            $table->boolean('habilitado')->default(false);
            $table->boolean('apenas_leitura')->default(false);
            $table->boolean('incluir')->default(false);
            $table->boolean('excluir')->default(false);
            $table->timestamps();

            // Um setor tem UMA linha por tela — o par é a chave de negócio. Sem
            // isto, duas linhas do mesmo par se contradiriam e a união (OR)
            // esconderia a contradição concedendo o mais permissivo.
            $table->unique(['setor', 'slug'], 'permissoes_setor_par_unique');
        });

        Schema::create('permissoes_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nome', 160)->nullable();
            $table->string('funcionalidade_slug', 80);
            $table->string('descricao', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissoes_log');
        Schema::dropIfExists('permissoes_setor');
    }
};
