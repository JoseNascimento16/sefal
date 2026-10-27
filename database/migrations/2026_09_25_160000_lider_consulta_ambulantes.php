<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O LÍDER DE EQUIPE passa a CONSULTAR o cadastro de ambulantes (dono,
 * 25/09/2026): o "Ver prontuário" do mapa leva para lá, e ele era barrado. Só
 * leitura, como o fiscal. Aplica a semente desta tela para o líder nos bancos que
 * já existem; idempotente.
 */
return new class extends Migration
{
    private const SETOR = 'lider-de-equipe';

    private const SLUG = 'ambulantes';

    public function up(): void
    {
        if (! in_array(self::SETOR, CatalogoFuncionalidades::setoresSemente(self::SLUG), true)) {
            return;
        }

        DB::table('permissoes_setor')->insertOrIgnore([
            'setor' => self::SETOR,
            'slug' => self::SLUG,
            ...CatalogoFuncionalidades::acoesSemente(self::SLUG, self::SETOR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissoes_setor')->where('setor', self::SETOR)->where('slug', self::SLUG)->delete();
    }
};
