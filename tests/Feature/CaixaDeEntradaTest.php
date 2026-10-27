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
/** O Chefe de Setor — a Caixa é a mesa dele (decisão do dono, 22/09/2026). */
function chefeDaCaixa(): User
{
    $usuario = User::factory()->create(['admin' => true]);
    $setor = Setor::firstOrCreate(['slug' => 'chefe-de-setor'], ['nome' => 'Chefe de Setor']);
    $usuario->setores()->syncWithoutDetaching([$setor->id]);

    return $usuario;
}

function areaComEquipe(string $nome = 'Área 5', string $equipe = 'C1', string $bairro = 'Costa Azul'): Area
{
    $lider = User::factory()->create(['name' => 'Líder '.$equipe]);
    $area = Area::create(['nome' => $nome, 'regiao' => 'Orla']);
    AreaBairro::create(['area_id' => $area->id, 'bairro' => $bairro]);
    // O líder é USUÁRIO ligado à equipe: é ele que recebe o encaminhamento.
    Equipe::create(['codigo' => $equipe, 'nome' => 'Equipe '.$equipe, 'area_id' => $area->id, 'encarregado' => 'José', 'lider_id' => $lider->id]);

    return $area;
}

function formulario(array $extra = []): array
{
    return array_merge([
        'destino' => 'encaminhar',
        'origem' => 'Avulsa',
        'documento_origem' => '156-2026-884120',
        'recebida_em' => now()->format('Y-m-d'),
        'anonima' => true,
        'assunto' => 'Barracas ocupando a calçada em frente à feira',
        'endereco' => 'Rua Barão de Mauá, altura do nº 120',
        'bairro' => 'Costa Azul',
        'descricao' => 'Quatro barracas fixas fechando a passagem de pedestres.',
        // O destino é a EQUIPE: o chefe escolhe, e quem recebe é o líder dela.
        'equipe' => 'C1',
    ], $extra);
}

it('grava a demanda em banco, com protocolo e o passo de recebimento', function () {
    areaComEquipe();

    $this->actingAs(chefeDaCaixa())
        ->post(route('retaguarda.caixa-de-entrada.store'), formulario())
        ->assertRedirect(route('retaguarda.caixa-de-entrada.index'));

    $demanda = Demanda::firstOrFail();

    expect($demanda->entrada)->toBe(Demanda::ENTRADA_BALCAO)
        ->and($demanda->canal)->toBe(Demanda::CANAL_AVULSA)
        ->and($demanda->protocolo)->not->toBeEmpty()
        // O prazo nasce do padrão quando o formulário não informa outro.
        ->and($demanda->prazo_em)->not->toBeNull()
        ->and($demanda->tramites)->toHaveCount(2);
});

it('encaminhar deixa o caso na mesa do LÍDER da equipe escolhida, e o trâmite diz quem', function () {
    areaComEquipe();

    $this->actingAs(chefeDaCaixa())->post(route('retaguarda.caixa-de-entrada.store'), formulario());

    $demanda = Demanda::firstOrFail();

    expect($demanda->situacao)->toBe(Demanda::ENCAMINHADA_AO_LIDER)
        // A equipe é o destino; a área vem junto porque é a dela.
        ->and($demanda->equipe->codigo)->toBe('C1')
        ->and($demanda->area->nome)->toBe('Área 5')
        // Encaminhar é entregar trabalho a ALGUÉM: o passo diz a quem.
        ->and($demanda->ultimoTramite()->campos)->toMatchArray(['Líder da equipe' => 'Líder C1']);
});

it('recusa encaminhar sem equipe, dizendo o que fazer', function () {
    areaComEquipe();

    $this->actingAs(chefeDaCaixa())
        ->post(route('retaguarda.caixa-de-entrada.store'), formulario(['equipe' => null]))
        ->assertSessionHasErrors('equipe');

    expect(Demanda::count())->toBe(0);
});

it('devolver exige justificativa escrita, porque é ato administrativo', function () {
    areaComEquipe();

    $this->actingAs(chefeDaCaixa())
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

    $this->actingAs(chefeDaCaixa())
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
    $chefe = chefeDaCaixa();

    $this->actingAs($chefe)->post(route('retaguarda.caixa-de-entrada.store'), formulario());
    $this->actingAs($chefe)->post(route('retaguarda.caixa-de-entrada.store'), formulario([
        'documento_origem' => '156-2026-884121',
    ]));

    [$principal, $agregada] = Demanda::orderBy('id')->get()->all();
    $agregada->agruparEm($principal, $chefe, 'Mesmo ponto.');

    $this->actingAs($chefe)
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Retaguarda/Fiscalizacao/CaixaDeEntrada')
            // Duas demandas existem; uma só é trabalho.
            ->has('demandas', 1)
            ->where('demandas.0.protocolo', $principal->protocolo)
            ->has('sugestoes')
            // As equipes vêm com o líder de cada uma: é a quem o chefe encaminha.
            ->has('equipes'),
        );
});
