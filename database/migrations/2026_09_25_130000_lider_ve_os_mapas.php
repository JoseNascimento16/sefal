<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O LÍDER DE EQUIPE passa a ver os dois mapas (dono, 25/09/2026) — recortados à
 * área dele (`MapaDaCidade::recorteDoLider`).
 *
 * A semente da matriz só roda uma vez; nos bancos que já existem, a concessão
 * nova do menu não chegaria sozinha. Esta migration aplica a semente SÓ destas
 * duas telas para o líder. Idempotente: não mexe em concessão já ajustada no Modo
 * Gerente.
 */
return new class extends Migration
{
    private const SETOR = 'lider-de-equipe';

    private const TELAS = ['mapa', 'mapa-de-calor'];

    public function up(): void
    {
        foreach (self::TELAS as $slug) {
            if (! in_array(self::SETOR, CatalogoFuncionalidades::setoresSemente($slug), true)) {
                continue;
            }

            DB::table('permissoes_setor')->insertOrIgnore([
                'setor' => self::SETOR,
                'slug' => $slug,
                ...CatalogoFuncionalidades::acoesSemente($slug, self::SETOR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissoes_setor')->where('setor', self::SETOR)->whereIn('slug', self::TELAS)->delete();
    }
};
