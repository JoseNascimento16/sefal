<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O setor `lider-de-equipe` ganha as telas que o menu declara para ele.
 *
 * Concessão NOVA, e por isso precisa de migration: a lista `setores` do menu é a
 * SEMENTE da matriz (`permissoes_setor`), aplicada uma vez pelo
 * `PermissoesSetorSeeder`. Num banco já semeado, um setor novo na config não
 * cria linha nenhuma — e o líder entraria, veria só "Início" e "Meu Perfil", e
 * seria mandado de volta de toda tela por uma decisão que já havia sido tomada a
 * favor dele. Foi exatamente o que a verificação local mostrou em 23/09/2026.
 *
 * As flags saem do MESMO lugar que a semente usaria
 * ({@see CatalogoFuncionalidades::acoesSemente}), e não escritas à mão: com duas
 * listas, um ajuste na declaração da tela deixaria esta migration concedendo o
 * pacote antigo. `insertOrIgnore` respeita a unicidade do par (setor, slug), então
 * o que já foi decidido na tela do Modo Gerente não é sobrescrito — e a migration
 * é idempotente, porque no OKD o `migrate` é passo manual.
 */
return new class extends Migration
{
    private const SETOR = 'lider-de-equipe';

    public function up(): void
    {
        foreach (CatalogoFuncionalidades::slugs() as $slug) {
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
        // A volta tira o que esta migration deu; o que foi ajustado à mão na tela
        // do Modo Gerente vai junto, porque não há como distinguir — quem reverter
        // semeia de novo.
        DB::table('permissoes_setor')->where('setor', self::SETOR)->delete();
    }
};
