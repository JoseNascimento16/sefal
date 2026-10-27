<?php

use App\Models\Area;
use App\Models\Equipe;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;

/*
|--------------------------------------------------------------------------
| A geometria das áreas (dono, 13/10/2026)
|--------------------------------------------------------------------------
|
| O desenho da área mora no banco (`areas.poligono`, GeoJSON), é editado na tela
| de Áreas e chega ao aplicativo do fiscal — que antes trazia o contorno
| desenhado à mão no próprio código.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

function chefeDaGeometria(): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    return $u->fresh();
}

/** @return array<string, mixed> */
function areaComDesenho(array $extra = []): array
{
    return [
        'nome' => 'Área G', 'regiao' => 'Orla', 'recorte' => 'bairros', 'turno' => 'Diurno', 'ativa' => true,
        'bairros' => ['Pituba'],
        'contorno' => [[-12.99, -38.46], [-12.98, -38.44], [-13.00, -38.43]],
        ...$extra,
    ];
}

it('o desenho vai e volta: contorno em lat/lng na tela, GeoJSON fechado no banco', function () {
    $geojson = Area::geoJson([[-12.99, -38.46], [-12.98, -38.44], [-13.00, -38.43]], null);
    $dado = json_decode((string) $geojson, true);

    expect($dado['features'][0]['geometry']['type'])->toBe('Polygon')
        // GeoJSON é [lng, lat] e fecha o anel repetindo o primeiro ponto.
        ->and($dado['features'][0]['geometry']['coordinates'][0][0])->toBe([-38.46, -12.99])
        ->and($dado['features'][0]['geometry']['coordinates'][0])->toHaveCount(4);

    $area = new Area(['poligono' => $geojson]);
    expect($area->geometria())->toBe([
        'contorno' => [[-12.99, -38.46], [-12.98, -38.44], [-13.0, -38.43]],
        'corredores' => null,
    ]);
});

it('a tela de Áreas grava o contorno do bloco, e a área de corredores grava as linhas com nome', function () {
    $chefe = chefeDaGeometria();

    $this->actingAs($chefe)->post(route('retaguarda.areas.store'), areaComDesenho())->assertSessionHasNoErrors();
    expect(Area::where('nome', 'Área G')->firstOrFail()->geometria()['contorno'])->toHaveCount(3);

    $this->actingAs($chefe)->post(route('retaguarda.areas.store'), areaComDesenho([
        'nome' => 'Área Corredor', 'recorte' => 'corredores', 'contorno' => null,
        'corredores' => [['nome' => 'Avenida Sete de Setembro', 'linha' => [[-12.98, -38.51], [-12.97, -38.51]]]],
    ]))->assertSessionHasNoErrors();

    expect(Area::where('nome', 'Área Corredor')->firstOrFail()->geometria())
        ->contorno->toBeNull()
        ->corredores->toBe([['nome' => 'Avenida Sete de Setembro', 'linha' => [[-12.98, -38.51], [-12.97, -38.51]]]]);
});

it('ponto fora de Salvador é recusado dizendo o que fazer', function () {
    $this->actingAs(chefeDaGeometria())->post(route('retaguarda.areas.store'), areaComDesenho([
        'contorno' => [[-12.99, -38.46], [-12.98, -38.44], [-23.55, -46.63]],
    ]))->assertSessionHasErrors(['contorno.2.0' => 'Um ponto do contorno ficou fora de Salvador. Desfaça o último ponto e desenhe de novo.']);
});

it('passar a área para cidade inteira apaga o desenho, e gravar sem mandar desenho não o apaga', function () {
    $chefe = chefeDaGeometria();
    $this->actingAs($chefe)->post(route('retaguarda.areas.store'), areaComDesenho());
    $area = Area::where('nome', 'Área G')->firstOrFail();

    // Outra tela (ou um cliente antigo) grava sem o desenho: ele fica.
    $semDesenho = areaComDesenho();
    unset($semDesenho['contorno']);
    $this->actingAs($chefe)->put(route('retaguarda.areas.update', $area), $semDesenho)->assertSessionHasNoErrors();
    expect($area->fresh()->poligono)->not->toBeNull();

    $this->actingAs($chefe)->put(route('retaguarda.areas.update', $area), areaComDesenho(['recorte' => 'cidade']))->assertSessionHasNoErrors();
    expect($area->fresh()->poligono)->toBeNull();
});

it('o aplicativo recebe o contorno da área da equipe no login', function () {
    $area = Area::create(['nome' => 'Área App', 'regiao' => 'Orla', 'recorte' => 'bairros', 'ativa' => true,
        'poligono' => Area::geoJson([[-12.99, -38.46], [-12.98, -38.44], [-13.00, -38.43]], null)]);
    $equipe = Equipe::create(['codigo' => 'G1', 'nome' => 'Equipe G1', 'area_id' => $area->id, 'ativa' => true]);
    $fiscal = User::factory()->create(['login' => 'fiscal-g1', 'ativo' => true]);
    $equipe->fiscais()->attach($fiscal->id);

    $this->postJson(route('aplicativo.entrar'), ['login' => 'fiscal-g1', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('equipe.contorno', [[-12.99, -38.46], [-12.98, -38.44], [-13, -38.43]])
        ->assertJsonPath('equipe.corredores', null);
});
