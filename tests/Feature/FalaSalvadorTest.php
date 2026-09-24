<?php

use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\Setor;
use App\Models\User;
use App\Support\Estrutura;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O canal Fala Salvador — e o canal avulsa
|--------------------------------------------------------------------------
|
| Decisão do dono em 22/09/2026. O Fala Salvador (156) substitui o "Salvador
| Digital": não tem API e SÓ OS LÍDERES o acessam — o SEFAL é intermediário de
| registro. O líder digita aqui o que recebeu lá, o caso nasce já na mesa dele e
| segue o fluxo; a resposta ao cidadão continua no Fala Salvador.
|
| A avulsa é o outro canal novo: a ligação ou o e-mail de um superior ao Chefe
| de Setor. Entra pela Caixa, como o ofício.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

function comPapel(string $slug, array $extra = []): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true, ...$extra]);
    $u->setores()->attach(Setor::where('slug', $slug)->firstOrFail());

    return $u->fresh();
}

/** Uma área com uma equipe e o líder dela. Devolve [equipe, líder]. */
function equipeComLider(string $codigo = 'C1', string $area = 'Área 5'): array
{
    $lider = comPapel('lider-de-equipe', ['name' => 'Líder '.$codigo]);
    $a = Area::firstOrCreate(['nome' => $area], ['regiao' => 'Orla']);
    $equipe = Equipe::create(['codigo' => $codigo, 'nome' => 'Equipe '.$codigo, 'area_id' => $a->id, 'lider_id' => $lider->id]);
    Estrutura::esquecer();

    return [$equipe, $lider];
}

function ligacao(array $extra = []): array
{
    return array_merge([
        'documento_origem' => '156-2026-884120',
        'recebida_em' => now()->format('Y-m-d'),
        'anonima' => true,
        'assunto' => 'Mesas na calçada em frente ao bar',
        'endereco' => 'Rua Barão de Mauá, altura do nº 120',
        'bairro' => 'Costa Azul',
        'descricao' => 'Munícipe relata mesas ocupando toda a calçada à noite.',
    ], $extra);
}

it('o líder registra o que recebeu no Fala Salvador, e o caso nasce na mesa dele', function () {
    [$equipe, $lider] = equipeComLider();

    $this->actingAs($lider)
        ->post(route('retaguarda.denuncias.registrar', 'fala-salvador'), ligacao())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso');

    $demanda = Demanda::firstOrFail();

    expect($demanda->canal)->toBe(Demanda::CANAL_FALA_SALVADOR)
        // Digitado, não recebido por integração: o canal não tem API.
        ->and($demanda->entrada)->toBe(Demanda::ENTRADA_BALCAO)
        // Não passa pelo chefe: quem atendeu foi o líder, e é dele o próximo passo.
        ->and($demanda->situacao)->toBe(Demanda::ENCAMINHADA_AO_LIDER)
        ->and($demanda->equipe_id)->toBe($equipe->id)
        ->and($demanda->area_id)->toBe($equipe->area_id)
        ->and($demanda->anonima)->toBeTrue()
        ->and($demanda->prazo_em)->not->toBeNull();

    $passo = $demanda->ultimoTramite();

    expect($passo->papel)->toBe(DemandaTramite::PAPEL_LIDER)
        ->and($passo->campos)->toMatchArray(['Origem do documento' => 'Fala Salvador', 'Equipe' => 'C1']);
});

it('o registro aparece na tela do canal, para o próprio líder direcionar', function () {
    [, $lider] = equipeComLider();

    $this->actingAs($lider)->post(route('retaguarda.denuncias.registrar', 'fala-salvador'), ligacao());

    $this->actingAs($lider)
        ->get(route('retaguarda.denuncias.fala-salvador.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Retaguarda/Denuncias/FalaSalvador')
            // O servidor diz que ele registra — é o que faz o formulário aparecer.
            ->where('registra', true)
            ->has('bairros')
            ->has('denuncias', 1)
            ->where('denuncias.0.canal', Demanda::CANAL_FALA_SALVADOR));
});

it('o Chefe de Setor não registra o Fala Salvador — o canal é dos líderes', function () {
    equipeComLider();
    $chefe = comPapel('chefe-de-setor');

    $this->actingAs($chefe)
        ->get(route('retaguarda.denuncias.fala-salvador.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('registra', false));

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.registrar', 'fala-salvador'), ligacao())
        ->assertRedirect()
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'líder'));

    expect(Demanda::count())->toBe(0);
});

it('quem lidera mais de uma equipe escolhe para qual é — e só entre as suas', function () {
    [, $lider] = equipeComLider('C1');
    $outraArea = Area::create(['nome' => 'Área 1', 'regiao' => 'Centro']);
    $c2 = Equipe::create(['codigo' => 'C2', 'nome' => 'Equipe C2', 'area_id' => $outraArea->id, 'lider_id' => $lider->id]);
    Equipe::create(['codigo' => 'A1', 'nome' => 'Equipe A1', 'area_id' => $outraArea->id]);
    Estrutura::esquecer();

    // Sem dizer qual: recusado com o motivo no campo.
    $this->actingAs($lider->fresh())
        ->post(route('retaguarda.denuncias.registrar', 'fala-salvador'), ligacao())
        ->assertSessionHasErrors('equipe');

    // Equipe que não é dele: recusada.
    $this->actingAs($lider->fresh())
        ->post(route('retaguarda.denuncias.registrar', 'fala-salvador'), ligacao(['equipe' => 'A1']))
        ->assertSessionHasErrors('equipe');

    $this->actingAs($lider->fresh())
        ->post(route('retaguarda.denuncias.registrar', 'fala-salvador'), ligacao(['equipe' => 'C2']))
        ->assertSessionHasNoErrors();

    expect(Demanda::firstOrFail()->equipe_id)->toBe($c2->id);
});

it('denúncia identificada exige quem ligou; anônima é escolha explícita', function () {
    [, $lider] = equipeComLider();

    $this->actingAs($lider)
        ->post(route('retaguarda.denuncias.registrar', 'fala-salvador'), ligacao(['anonima' => false]))
        ->assertSessionHasErrors('requerente');

    expect(Demanda::count())->toBe(0);
});

it('a Caixa de Entrada oferece a AVULSA ao chefe, e não oferece o Fala Salvador', function () {
    $chefe = comPapel('chefe-de-setor');

    $this->actingAs($chefe)
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertOk()
        ->assertInertia(function ($p) {
            $origens = $p->toArray()['props']['origens'];

            expect($origens)->toContain('Avulsa')
                ->not->toContain('Fala Salvador');

            return $p;
        });
});

it('o canal já gravado como salvador-digital vira fala-salvador pela migration, e a volta é possível', function () {
    [$equipe] = equipeComLider();

    DB::table('demandas')->insert([
        'protocolo' => 'DEM-TESTE', 'canal' => 'salvador-digital', 'entrada' => 'integracao',
        'recebida_em' => now(), 'assunto' => 'x', 'situacao' => Demanda::RECEBIDA,
        'equipe_id' => $equipe->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_09_23_090000_canal_fala_salvador.php');
    $migration->up();

    expect(DB::table('demandas')->where('protocolo', 'DEM-TESTE')->value('canal'))->toBe('fala-salvador');

    // Idempotente: rodar de novo não muda nada.
    $migration->up();
    expect(DB::table('demandas')->where('canal', 'fala-salvador')->count())->toBe(1);

    $migration->down();
    expect(DB::table('demandas')->where('protocolo', 'DEM-TESTE')->value('canal'))->toBe('salvador-digital');
});

it('lei: o catálogo de canais do model e o da config são o mesmo', function () {
    expect(array_keys((array) config('demandas.canais')))->toBe(Demanda::CANAIS)
        ->and(Demanda::CANAIS)->toContain('fala-salvador', 'avulsa')
        ->and(Demanda::CANAIS)->not->toContain('salvador-digital');
});
