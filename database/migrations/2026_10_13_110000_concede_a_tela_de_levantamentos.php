<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A tela Fiscalização › Levantamentos (dono, 13/10/2026) concedida a quem o menu
 * declara — Chefe de Setor e líder de equipe, os dois só em leitura — nos bancos
 * que já existem. O administrador vê tudo sem linha na matriz. Idempotente.
 */
return new class extends Migration
{
    private const SLUG = 'levantamentos';

    public function up(): void
    {
        foreach (CatalogoFuncionalidades::setoresSemente(self::SLUG) as $setor) {
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
