<?php

use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| A entidade virou AMBULANTE — e o dado gravado veio junto
|--------------------------------------------------------------------------
|
| A tabela já estava aplicada em banco real quando a área de negócio decidiu o
| nome novo (02/09/2026). Renomear é a operação certa justamente porque não toca
| nas linhas — mas "não toca" é afirmação, e afirmação sem asserção é esperança.
|
| O que se prova aqui:
|
|   • a renomeação PRESERVA os cadastros, com índice único e chave estrangeira
|     ainda de pé (é o que separa `rename` de "criar tabela nova e copiar");
|   • a coluna nova nasce dizendo "não tem permissão" — a resposta honesta para
|     quem ninguém conferiu;
|   • quem JÁ TINHA número de permissão é reconhecido como permissionário. Sem
|     isso, o rename marcaria como sem-permissão justamente quem o gestor
|     cadastrou lendo o documento da permissão na tela.
|
*/

beforeEach(function () {
    $this->seed();
});

/** A migration da renomeação, carregada como a `migrate` a carrega. */
function migrationDoRename(): object
{
    return require database_path('migrations/2026_09_02_090000_renomeia_permissionarios_para_ambulantes.php');
}

test('a renomeacao preserva os cadastros gravados', function () {
    /*
     * O caminho é o de ida e volta: as migrations já rodaram (a tabela chama-se
     * `ambulantes`), então o cenário "antes do rename" se monta com o `down()`.
     * É o mesmo par de operações que roda no banco real, na mesma ordem.
     */
    $atividade = AtividadeAmbulante::firstOrFail();

    $antes = Ambulante::factory()->create([
        'nome' => 'Quem Estava Na Base',
        'apelido' => 'Seu Antigo',
        'atividade_id' => $atividade->id,
    ]);

    $comPermissao = Ambulante::factory()->permissionario('SEMOP-00042')->create([
        'nome' => 'Quem Tinha Permissao',
        'atividade_id' => $atividade->id,
    ]);

    $migration = migrationDoRename();

    $migration->down();

    // Volta a se chamar como antes, e sem o atributo novo.
    expect(Schema::hasTable('permissionarios'))->toBeTrue()
        ->and(Schema::hasTable('ambulantes'))->toBeFalse()
        ->and(Schema::hasColumn('permissionarios', 'permissionario'))->toBeFalse()
        ->and(DB::table('permissionarios')->count())->toBe(2);

    $migration->up();

    expect(Schema::hasTable('ambulantes'))->toBeTrue()
        ->and(Schema::hasTable('permissionarios'))->toBeFalse();

    // As DUAS linhas continuam lá, com o mesmo id e o mesmo conteúdo.
    $recuperado = Ambulante::query()->findOrFail($antes->getKey());

    expect($recuperado->nome)->toBe('Quem Estava Na Base')
        ->and($recuperado->apelido)->toBe('Seu Antigo')
        ->and($recuperado->codigo)->toBe($antes->codigo)
        ->and($recuperado->atividade->getKey())->toBe($atividade->getKey())
        ->and(Ambulante::query()->count())->toBe(2);

    // E o índice único sobreviveu ao rename: dois códigos iguais continuam
    // impossíveis. É a diferença entre renomear e recriar a tabela na mão.
    expect(fn () => DB::table('ambulantes')->insert([
        'codigo' => $antes->codigo,
        'nome' => 'Codigo repetido',
        'atividade_id' => $atividade->id,
        'situacao' => Ambulante::SITUACAO_REGULAR,
        'permissionario' => false,
    ]))->toThrow(QueryException::class);

    // O que TINHA número de permissão é reconhecido como permissionário; o
    // resto, não.
    expect(Ambulante::query()->findOrFail($comPermissao->getKey())->permissionario)->toBeTrue()
        ->and(Ambulante::query()->findOrFail($antes->getKey())->permissionario)->toBeFalse();
});

test('a coluna nova nasce dizendo NAO — afirmar permissao que ninguem conferiu e pior que nao afirmar', function () {
    $atividade = AtividadeAmbulante::firstOrFail();

    // Gravando pela porta de baixo, sem passar pela factory nem pelo controller:
    // o que se prova é o padrão da COLUNA, que é quem responde pela fila do PWA
    // amanhã.
    DB::table('ambulantes')->insert([
        'codigo' => 'AMB'.now()->format('Ymd').'999',
        'nome' => 'Sem Ninguem Dizer Nada',
        'atividade_id' => $atividade->id,
        'situacao' => Ambulante::SITUACAO_CAMPO,
    ]);

    expect(Ambulante::query()->where('nome', 'Sem Ninguem Dizer Nada')->firstOrFail()->permissionario)
        ->toBeFalse();
});
