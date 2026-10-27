<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A tela Sistema › Áreas (25/09/2026) nasce concedida a quem o menu declara
 * (administrador e Chefe de Setor). Nos bancos que já existem, a semente da
 * matriz não roda de novo — esta migration a aplica SÓ desta tela. Idempotente.
 */
return new class extends Migration
{
    private const SLUG = 'areas';

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
