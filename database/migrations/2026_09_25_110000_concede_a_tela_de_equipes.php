<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A tela Sistema › Equipes (25/09/2026) nasce concedida a quem o menu declara
 * (`config/retaguarda_menu.php`: administrador e Chefe de Setor).
 *
 * A semente da matriz (`PermissoesSetorSeeder`) só roda uma vez; nos bancos que
 * já existem, a tela nova ficaria controlável e sem concessão — ninguém a abriria
 * além do administrador. Esta migration aplica a semente SÓ desta tela.
 * Idempotente (`insertOrIgnore`): não mexe em concessão já ajustada no Modo Gerente.
 */
return new class extends Migration
{
    private const SLUG = 'equipes';

    public function up(): void
    {
        foreach (CatalogoFuncionalidades::setoresSemente(self::SLUG) as $setor) {
            // O administrador não é linha de matriz: o acesso total dele é desvio no código.
            if ($setor === 'administrador') {
                continue;
            }

            DB::table('permissoes_setor')->insertOrIgnore([
                'setor' => $setor,
                'slug' => self::SLUG,
                ...CatalogoFuncionalidades::acoesSemente(self::SLUG, $setor),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissoes_setor')->where('slug', self::SLUG)->delete();
    }
};
