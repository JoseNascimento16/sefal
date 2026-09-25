<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As duas marcas da tela de Usuários que o Codecon tem (dono, 25/09/2026):
 *
 *  - `is_gerente` — pode ATIVAR o Modo Gerente: ligar as chaves no menu e
 *    configurar o que cada cargo vê e faz em cada tela;
 *  - `is_admin_usuarios` — ADMINISTRADOR DE USUÁRIOS: o poder da tela de
 *    Usuários (criar, editar, excluir, restaurar, convidar) sem ser
 *    administrador do sistema.
 *
 * Colunas novas, com padrão falso: nenhuma conta ganha poder que não tinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_gerente')) {
                $table->boolean('is_gerente')->default(false);
            }

            if (! Schema::hasColumn('users', 'is_admin_usuarios')) {
                $table->boolean('is_admin_usuarios')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['is_gerente', 'is_admin_usuarios'] as $coluna) {
                if (Schema::hasColumn('users', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
