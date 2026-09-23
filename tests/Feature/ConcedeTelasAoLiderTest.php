<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A migration que concede as telas ao líder de equipe
|--------------------------------------------------------------------------
|
| O setor nasceu em 22/09/2026 e a semente da matriz roda uma vez: num banco já
| semeado, o líder entraria e veria só "Início" e "Meu Perfil". A migration
| repõe o que a semente daria — e é isso que se prova aqui, a partir de um banco
| SEM as linhas dele.
|
*/

/** A migration desta entrega, instanciada para ser reexecutada. */
function migrationDoLider(): object
{
    return require database_path('migrations/2026_09_23_090100_concede_telas_ao_lider_de_equipe.php');
}

test('o lider ganha, pela migration, exatamente as telas que o menu declara para ele', function () {
    DB::table('permissoes_setor')->where('setor', 'lider-de-equipe')->delete();

    migrationDoLider()->up();

    $esperadas = array_values(array_filter(
        CatalogoFuncionalidades::slugs(),
        static fn (string $slug): bool => in_array('lider-de-equipe', CatalogoFuncionalidades::setoresSemente($slug), true),
    ));

    $gravadas = DB::table('permissoes_setor')->where('setor', 'lider-de-equipe')->orderBy('slug')->pluck('slug')->all();
    sort($esperadas);

    expect($gravadas)->toBe($esperadas)
        // As telas do fluxo dele — sem elas o papel não existe na prática.
        ->and($gravadas)->toContain('denuncias', 'fiscalizacoes', 'operacoes')
        // A Caixa é do chefe: o líder não a recebe.
        ->and($gravadas)->not->toContain('caixa-de-entrada');

    // As flags são as da semente, não escritas à mão.
    $operacoes = (array) DB::table('permissoes_setor')->where('setor', 'lider-de-equipe')->where('slug', 'operacoes')->first();

    expect(array_map(
        static fn (mixed $v): bool => (bool) $v,
        array_intersect_key($operacoes, CatalogoFuncionalidades::acoesSemente('operacoes', 'lider-de-equipe')),
    ))->toBe(CatalogoFuncionalidades::acoesSemente('operacoes', 'lider-de-equipe'));
});

test('a migration e idempotente e nao sobrescreve o que foi decidido na tela do Modo Gerente', function () {
    // Alguém, na tela, tirou o cadastro de operação do líder: só consulta.
    DB::table('permissoes_setor')->where('setor', 'lider-de-equipe')->where('slug', 'operacoes')
        ->update(['apenas_leitura' => true, 'incluir' => false, 'excluir' => false]);

    $antes = DB::table('permissoes_setor')->where('setor', 'lider-de-equipe')->count();

    migrationDoLider()->up();
    migrationDoLider()->up();

    $operacoes = DB::table('permissoes_setor')->where('setor', 'lider-de-equipe')->where('slug', 'operacoes')->first();

    expect(DB::table('permissoes_setor')->where('setor', 'lider-de-equipe')->count())->toBe($antes)
        ->and((bool) $operacoes->apenas_leitura)->toBeTrue('a migration sobrescreveu uma decisão tomada na tela');
});
