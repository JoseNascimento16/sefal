<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controle de usuários (pedido do dono, 25/09/2026 — a tela do Codecon trazida
 * para cá).
 *
 * Duas colunas em `users`:
 *
 *  - `deleted_at` — a EXCLUSÃO vai para uma lixeira: a conta some do sistema e
 *    do login, fica restaurável por alguns dias e só então é removida de vez
 *    (e só se não tiver histórico; ver `User::temHistorico()`);
 *  - `senha_definida_em` — o PRIMEIRO ACESSO. A senha é obrigatória na tabela
 *    (a coluna nasceu `NOT NULL`), então a conta criada pela tela nasce com uma
 *    senha aleatória que ninguém conhece; é esta data que diz se a pessoa já
 *    escolheu a dela. Afrouxar o `NOT NULL` da senha no Oracle de homologação
 *    seria mexer em coluna com dado — uma coluna nova e nula não mexe em nada.
 *
 * Toda conta que já existe TEM senha escolhida (foi criada por seeder ou por
 * comando, sempre com senha): ganha a data de criação como a da senha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }

            if (! Schema::hasColumn('users', 'senha_definida_em')) {
                $table->timestamp('senha_definida_em')->nullable();
            }
        });

        DB::table('users')->whereNull('senha_definida_em')->update([
            'senha_definida_em' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'senha_definida_em')) {
                $table->dropColumn('senha_definida_em');
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
