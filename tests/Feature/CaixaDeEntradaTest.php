<?php

use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A Caixa de Entrada depois da consolidação: ela grava em BANCO.
 *
 * O que estes testes protegem é o que o protótipo não conseguia prometer — que a
 * decisão do coordenador sobrevive ao logout, e que ela deixa trilha de quem,
 * quando e por quê.
 */
function coordenador(): User
{
    $usuario = User::factory()->create(['admin' => true]);
    $setor = Setor::firstOrCreate(['slug' => 'coordenador'], ['nome' => 'Coordenador']);
    $usuario->setores()->syncWithoutDetaching([$setor->id]);

    return $usuario;
}

function areaComEquipe(string $nome = 'Área 5', string $equipe = 'C1', string $bairro = 'Costa Azul'): Area
{
    $chefe = User::factory()->create(['name' => 'Gestor 1']);
    $area = Area::create(['nome' => $nome, 'regiao' => 'Orla', 'chefe_de_setor_id' => $chefe->id]);
    AreaBairro::create(['area_id' => $area->id, 'bairro' => $bairro]);
    Equipe::create(['codigo' => $equipe, 'nome' => 'Equipe '.$equipe, 'area_id' => $area->id, 'encarregado' => 'José']);

    return $area;
}

function formulario(array $extra = []): array
{
    return array_merge([
        'destino' => 'encaminhar',
        'origem' => 'Salvador Digital',
        'documento_origem' => '156-2026-884120',
        'recebida_em' => now()->format('Y-m-d'),
        'anonima' => true,
        'assunto' => 'Barracas ocupando a calçada em frente à feira',
        'endereco' => 'Rua Barão de Mauá, altura do nº 120',
        'bairro' => 'Costa Azul',
        'descricao' => 'Quatro barracas fixas fechando a passagem de pedestres.',
        'area' => 'Área 5',
    ], $extra);
}

it('grava a demanda em banco, com protocolo e o passo de recebimento', function () {
    areaComEquipe();

    $this->actingAs(coordenador())
        ->post(route('retaguarda.caixa-de-entrada.store'), formulario())
        ->assertRedirect(route('retaguarda.caixa-de-entrada.index'));

    $demanda = Demanda::firstOrFail();

    expect($demanda->entrada)->toBe(Demanda::ENTRADA_BALCAO)
        ->and($demanda->canal)->toBe(Demanda::CANAL_SALVADOR_DIGITAL)
        ->and($demanda->protocolo)->not->toBeEmpty()
        // O prazo nasce do padrão quando o formulário não informa outro.
        ->and($demanda->prazo_em)->not->toBeNull()
        ->and($demanda->tramites)->toHaveCount(2);
});

it('encaminhar à área deixa o caso na mesa do Chefe de Setor, não na da equipe', function () {
    areaComEquipe();

    $this->actingAs(coordenador())->post(route('retaguarda.caixa-de-entrada.store'), formulario());

    $demanda = Demanda::firstOrFail();

    expect($demanda->situacao)->toBe(Demanda::ENCAMINHADA_A_AREA)
        ->and($demanda->area->nome)->toBe('Área 5')
        // Ninguém escolheu equipe ainda — e é isso que o estado tem de dizer.
        ->and($demanda->equipe_id)->toBeNull()
        ->and($demanda->ultimoTramite()->campos)->toMatchArray(['Chefe de Setor' => 'Gestor 1']);
});

it('direcionar já à equipe é outro estado, e o trâmite diz qual', function () {
    areaComEquipe();

    $this->actingAs(coordenador())
        ->post(route('retaguarda.caixa-de-entrada.store'), formulario(['equipe' => 'C1']));

    $demanda = Demanda::firstOrFail();

    expect($demanda->situacao)->toBe(Demanda::DIRECIONADA_A_EQUIPE)
        ->and($demanda->equipe->codigo)->toBe('C1')
        // A área vem junto mesmo sem ter sido escolhida: ela é a da equipe.
        ->and($demanda->area->nome)->toBe('Área 5');
});

it('recusa encaminhar sem destino, dizendo o que fazer', function () {
    areaComEquipe();

    $this->actingAs(coordenador())
        ->post(route('retaguarda.caixa-de-entrada.store'), formulario(['area' => null]))
        ->assertSessionHasErrors('area');

    expect(Demanda::count())->toBe(0);
});

it('devolver exige justificativa escrita, porque é ato administrativo', function () {
    areaComEquipe();

    $this->actingAs(coordenador())
        ->post(route('retaguarda.caixa-de-entrada.store'), formulario([
            'destino' => 'devolver',
            'motivo' => 'Fora da competência da SEFAL',
            'justificativa' => 'curta',
            'destino_retorno' => 'Devolvida ao remetente',
        ]))
        ->assertSessionHasErrors('justificativa');
});

it('arquiva com o motivo e o destino gravados no passo', function () {
    areaComEquipe();

    $this->actingAs(coordenador())
        ->post(route('retaguarda.caixa-de-entrada.store'), formulario([
            'destino' => 'devolver',
            'motivo' => 'Objeto já regularizado',
            'justificativa' => 'A equipe passou no ponto e o ambulante já havia desmontado a barraca.',
            'destino_retorno' => 'Arquivada',
        ]));

    $demanda = Demanda::firstOrFail();

    expect($demanda->situacao)->toBe(Demanda::ARQUIVADA)
        ->and($demanda->concluida_em)->not->toBeNull()
        ->and($demanda->ultimoTramite()->campos)->toMatchArray([
            'Motivo' => 'Objeto já regularizado',
            'Destino' => 'Arquivada',
        ]);
});

it('a tela lista o que está em banco e não mostra as agregadas', function () {
    areaComEquipe();
    $coordenador = coordenador();

    $this->actingAs($coordenador)->post(route('retaguarda.caixa-de-entrada.store'), formulario());
    $this->actingAs($coordenador)->post(route('retaguarda.caixa-de-entrada.store'), formulario([
        'documento_origem' => '156-2026-884121',
    ]));

    [$principal, $agregada] = Demanda::orderBy('id')->get()->all();
    $agregada->agruparEm($principal, $coordenador, 'Mesmo ponto.');

    $this->actingAs($coordenador)
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Retaguarda/Fiscalizacao/CaixaDeEntrada')
            // Duas demandas existem; uma só é trabalho.
            ->has('demandas', 1)
            ->where('demandas.0.protocolo', $principal->protocolo)
            ->has('sugestoes')
            ->has('chefias'),
        );
});
