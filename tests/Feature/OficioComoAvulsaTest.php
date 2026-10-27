<?php

use App\Models\Demanda;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| O ofício é um TIPO de avulsa (dono, 25/09/2026)
|--------------------------------------------------------------------------
|
| "Ofícios, se existirem, irão se juntar ao e-Salvador para virar processo se
| for necessário pós fiscalização (similar ao que acontecerá com as avulsas).
| Pode considerar um ofício como um subtipo de avulsa."
|
| Desde 24/09 o ofício era canal sem caixa: existia no banco e não aparecia em
| tela nenhuma. Aqui se prova que ele deixou de ser canal, que entra pela caixa
| das Avulsas com o tipo dito, e que os antigos foram convertidos sem perder o
| número de origem nem colidir com uma avulsa de mesmo número.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

function chefeDoOficio(): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    return $u->fresh();
}

/** @return array<string, mixed> */
function avulsaDoOficio(array $extra = []): array
{
    return [
        'tipo_avulsa' => Demanda::AVULSA_OFICIO,
        'documento_origem' => 'OF-123/2026',
        'recebida_em' => Date::now()->format('Y-m-d'),
        'anonima' => false,
        'requerente' => 'Ministério Público da Bahia',
        'assunto' => 'Apurar ocupação de calçada por barracas',
        'endereco' => 'Rua Chile, 10',
        'bairro' => 'Centro',
        ...$extra,
    ];
}

it('o ofício não é mais canal: a lista de canais e a rota de registro não o aceitam', function () {
    expect(Demanda::CANAIS)->not->toContain(Demanda::CANAL_OFICIO)
        ->and(config('demandas.canais'))->not->toHaveKey(Demanda::CANAL_OFICIO);

    $this->actingAs(chefeDoOficio())
        ->post('/retaguarda/caixa-de-entrada/oficio/registrar', avulsaDoOficio())
        ->assertNotFound();
});

it('o ofício entra pela caixa das Avulsas, com o tipo gravado, e aparece nela com o nome do tipo', function () {
    $chefe = chefeDoOficio();

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.registrar', 'avulsa'), avulsaDoOficio())
        ->assertSessionHasNoErrors();

    $demanda = Demanda::firstOrFail();

    expect($demanda->canal)->toBe(Demanda::CANAL_AVULSA)
        ->and($demanda->tipo_avulsa)->toBe(Demanda::AVULSA_OFICIO)
        ->and($demanda->numero_origem)->toBe('OF-123/2026');

    $this->actingAs($chefe)->get(route('retaguarda.denuncias.avulsas.index'))
        ->assertInertia(fn ($p) => $p->where('denuncias.0.tipo_avulsa_nome', 'Ofício'));
});

it('a avulsa exige dizer como chegou — pedido de superior ou ofício', function () {
    $this->actingAs(chefeDoOficio())
        ->post(route('retaguarda.denuncias.registrar', 'avulsa'), avulsaDoOficio(['tipo_avulsa' => null]))
        ->assertSessionHasErrors('tipo_avulsa');

    $this->actingAs(chefeDoOficio())
        ->post(route('retaguarda.denuncias.registrar', 'avulsa'), avulsaDoOficio(['tipo_avulsa' => 'telegrama']))
        ->assertSessionHasErrors('tipo_avulsa');

    expect(Demanda::count())->toBe(0);
});

it('os outros canais não gravam tipo de avulsa, mesmo que ele venha no pedido', function () {
    $this->actingAs(chefeDoOficio())
        ->post(route('retaguarda.denuncias.registrar', 'e-protocolo'), avulsaDoOficio(['documento_origem' => 'EP-9']))
        ->assertSessionHasNoErrors();

    expect(Demanda::firstOrFail()->tipo_avulsa)->toBeNull();
});

it('a migration converte os ofícios antigos em avulsas do tipo ofício, sem colidir número com uma avulsa', function () {
    $base = [
        'entrada' => Demanda::ENTRADA_BALCAO, 'recebida_em' => Date::now(), 'assunto' => 'x',
        'situacao' => Demanda::RECEBIDA, 'created_at' => Date::now(), 'updated_at' => Date::now(),
    ];
    DB::table('demandas')->insert([
        [...$base, 'protocolo' => 'DEM-OF1', 'canal' => 'avulsa', 'numero_origem' => '77', 'tipo_avulsa' => null],
        [...$base, 'protocolo' => 'DEM-OF2', 'canal' => 'oficio', 'numero_origem' => '77', 'tipo_avulsa' => null],
        [...$base, 'protocolo' => 'DEM-OF3', 'canal' => 'oficio', 'numero_origem' => '88', 'tipo_avulsa' => null],
    ]);

    (require database_path('migrations/2026_09_25_100000_oficio_vira_tipo_de_avulsa.php'))->up();

    $porProtocolo = Demanda::all()->keyBy('protocolo');

    expect($porProtocolo['DEM-OF1']->tipo_avulsa)->toBe(Demanda::AVULSA_SUPERIOR)
        ->and($porProtocolo['DEM-OF2']->canal)->toBe(Demanda::CANAL_AVULSA)
        ->and($porProtocolo['DEM-OF2']->tipo_avulsa)->toBe(Demanda::AVULSA_OFICIO)
        ->and($porProtocolo['DEM-OF2']->numero_origem)->toBe('77 (ofício)')
        ->and($porProtocolo['DEM-OF3']->numero_origem)->toBe('88')
        ->and(Demanda::where('canal', 'oficio')->count())->toBe(0);
});
