<?php

use App\Models\Area;
use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use App\Support\Papel;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Support\Facades\Date;

/*
|--------------------------------------------------------------------------
| Sistema › Equipes (dono, 25/09/2026: "preciso de um cadastro de equipes")
|--------------------------------------------------------------------------
|
| O cadastro diz quem está em cada equipe: o LÍDER (a conta que recebe o
| trabalho) e os FISCAIS. O que só o servidor garante: líder e fiscal têm de ser
| contas ativas do setor certo; o recorte do líder passa a seguir o cadastro; a
| equipe com histórico não se exclui; e a tela nasce concedida ao Chefe de Setor
| e fechada ao líder.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

function contaDaEquipe(string $setor, array $extra = []): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true, ...$extra]);
    $u->setores()->attach(Setor::where('slug', $setor)->firstOrFail());

    return $u->fresh();
}

/** @return array<string, mixed> */
function dadosDaEquipe(Area $area, array $extra = []): array
{
    return [
        'codigo' => 'z9',
        'nome' => '',
        'area_id' => $area->id,
        'turno' => 'Diurno',
        'lider_id' => null,
        'fiscais' => [],
        'ativa' => true,
        ...$extra,
    ];
}

it('o Chefe de Setor cadastra a equipe com líder e fiscais, e o líder passa a ter a equipe no recorte', function () {
    $area = Area::create(['nome' => 'Área Z', 'regiao' => 'Orla']);
    $lider = contaDaEquipe('lider-de-equipe', ['login' => 'lider-z9-eq']);
    $fiscal = contaDaEquipe('fiscal');

    $this->actingAs(contaDaEquipe('chefe-de-setor'))
        ->post(route('retaguarda.equipes.store'), dadosDaEquipe($area, ['lider_id' => $lider->id, 'fiscais' => [$fiscal->id]]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso');

    $equipe = Equipe::where('codigo', 'Z9')->firstOrFail();

    expect($equipe->nome)->toBe('Equipe Z9')
        ->and($equipe->lider_id)->toBe($lider->id)
        ->and($equipe->encarregado)->toBe($lider->name)
        ->and($equipe->fiscais->pluck('id')->all())->toBe([$fiscal->id])
        ->and(Papel::equipes($lider))->toContain('Z9');
});

it('líder e fiscal têm de ser contas ativas do setor certo; código é único', function () {
    $area = Area::create(['nome' => 'Área Z', 'regiao' => 'Orla']);
    Equipe::create(['codigo' => 'Z9', 'area_id' => $area->id]);
    $fiscal = contaDaEquipe('fiscal');
    $chefe = contaDaEquipe('chefe-de-setor');

    $this->actingAs($chefe)
        ->post(route('retaguarda.equipes.store'), dadosDaEquipe($area, ['codigo' => 'Z8', 'lider_id' => $fiscal->id, 'fiscais' => [$chefe->id]]))
        ->assertSessionHasErrors(['lider_id', 'fiscais.0']);

    $this->actingAs($chefe)
        ->post(route('retaguarda.equipes.store'), dadosDaEquipe($area))
        ->assertSessionHasErrors('codigo');
});

it('a equipe sem histórico se exclui; a que já recebeu demanda é recusada, com o caminho de inativar', function () {
    $area = Area::create(['nome' => 'Área Z', 'regiao' => 'Orla']);
    $vazia = Equipe::create(['codigo' => 'Z1', 'area_id' => $area->id]);
    $comHistorico = Equipe::create(['codigo' => 'Z2', 'area_id' => $area->id]);
    Demanda::create([
        'protocolo' => 'DEM-EQ1', 'canal' => Demanda::CANAL_AVULSA, 'entrada' => Demanda::ENTRADA_BALCAO,
        'recebida_em' => Date::now(), 'assunto' => 'x', 'situacao' => Demanda::RECEBIDA, 'equipe_id' => $comHistorico->id,
    ]);
    $chefe = contaDaEquipe('chefe-de-setor');

    $this->actingAs($chefe)->delete(route('retaguarda.equipes.destroy', $vazia))->assertSessionHas('flash.sucesso');
    $this->actingAs($chefe)->delete(route('retaguarda.equipes.destroy', $comHistorico))
        ->assertSessionHas('flash.erro', fn (string $m) => str_contains($m, 'Ativa'));

    expect(Equipe::find($vazia->id))->toBeNull()
        ->and(Equipe::find($comHistorico->id))->not->toBeNull();
});

it('a tela é do administrador e do Chefe de Setor; o líder é barrado com o motivo', function () {
    $this->actingAs(contaDaEquipe('chefe-de-setor'))->get(route('retaguarda.equipes.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('Retaguarda/Sistema/Equipes')->has('lideres')->has('fiscais'));

    $this->actingAs(contaDaEquipe('lider-de-equipe'))->get(route('retaguarda.equipes.index'))
        ->assertRedirect(route('retaguarda.inicio'))
        ->assertSessionHas('flash.erro');
});

it('a migration dá a tela ao Chefe de Setor nos bancos que já existiam', function () {
    PermissaoSetor::where('slug', 'equipes')->delete();

    (require database_path('migrations/2026_09_25_110000_concede_a_tela_de_equipes.php'))->up();

    expect(PermissaoSetor::where('slug', 'equipes')->pluck('setor')->all())->toBe(['chefe-de-setor']);
});
