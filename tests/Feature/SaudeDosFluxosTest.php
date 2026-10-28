<?php

use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use App\Support\SaudeFluxos\CatalogoFluxos;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Saúde dos Fluxos (dono, 27/10/2026)
|--------------------------------------------------------------------------
|
| O fluxograma do trabalho do SEFAL de ponta a ponta — da chegada da demanda a
| cada destino final, incluindo o aplicativo do fiscal —, só para o
| administrador. A tela veio do CODECON com a mesma estrutura.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    config(['retaguarda.permissao_enforce' => 'block']);
});

function administradorDosFluxos(): User
{
    return User::factory()->create(['admin' => true, 'ativo' => true]);
}

it('abre na Visão geral para o administrador, com um fluxo e a sua aba', function () {
    $this->actingAs(administradorDosFluxos())
        ->get(route('retaguarda.saude-dos-fluxos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $tela) => $tela
            ->component('Retaguarda/Sistema/SaudeDosFluxos')
            ->where('fluxoAtual', null)
            ->has('abas', 1)
            ->has('visaoGeral', 1)
            ->where('visaoGeral.0.chave', 'fiscalizacao'));
});

it('abre o fluxo com o fluxograma, o passo a passo e as conclusões', function () {
    $this->actingAs(administradorDosFluxos())
        ->get(route('retaguarda.saude-dos-fluxos.index', ['fluxo' => 'fiscalizacao']))
        ->assertOk()
        ->assertInertia(fn (Assert $tela) => $tela
            ->where('fluxoAtual', 'fiscalizacao')
            ->has('fluxo.nos')
            ->has('fluxo.arestas')
            ->has('fluxo.etapas')
            ->has('fluxo.conclusoes'));
});

it('fluxo que não existe cai na Visão geral, sem erro', function () {
    $this->actingAs(administradorDosFluxos())
        ->get(route('retaguarda.saude-dos-fluxos.index', ['fluxo' => 'inventado']))
        ->assertOk()
        ->assertInertia(fn (Assert $tela) => $tela->where('fluxoAtual', null));
});

it('só o administrador vê a tela: os outros cargos são mandados de volta dizendo o porquê', function () {
    $cargos = array_diff(array_keys((array) config('retaguarda.setores')), ['administrador']);
    expect($cargos)->not->toBeEmpty();

    foreach ($cargos as $cargo) {
        $pessoa = User::factory()->create(['admin' => false, 'ativo' => true]);
        $pessoa->setores()->attach(Setor::where('slug', $cargo)->firstOrFail());

        $this->actingAs($pessoa->fresh())->get(route('retaguarda.saude-dos-fluxos.index'))
            ->assertRedirect('/retaguarda/inicio')
            ->assertSessionHas('flash.erro');

        $this->actingAs($pessoa->fresh())->get(route('retaguarda.saude-dos-fluxos.exportar', 'fiscalizacao'))
            ->assertRedirect('/retaguarda/inicio');

        expect(PermissaoSetor::where('slug', 'saude-dos-fluxos')->where('setor', $cargo)->exists())
            ->toBeFalse("o cargo {$cargo} não pode ter a tela concedida");
    }
});

it('exporta o passo a passo em texto, com os trechos previstos à parte', function () {
    $resposta = $this->actingAs(administradorDosFluxos())
        ->get(route('retaguarda.saude-dos-fluxos.exportar', 'fiscalizacao'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    $texto = $resposta->getContent();
    $fluxo = CatalogoFluxos::definicao('fiscalizacao');

    expect($texto)->toContain('# '.$fluxo['titulo'])
        ->toContain('## Passo a passo')
        ->toContain('## Todas as formas de conclusão');

    if (collect($fluxo['nos'])->contains('situacao', CatalogoFluxos::PREVISTO)) {
        expect($texto)->toContain('## Trechos previstos');
    }
});

// ── Leis do catálogo: o desenho não pode mentir nem quebrar ─────────────────

it('lei: todo quadradinho tem id único, posição única na grade, tipo e situação conhecidos', function (string $chave) {
    $nos = collect(CatalogoFluxos::definicao($chave)['nos']);

    expect($nos->pluck('id')->duplicates()->all())->toBe([])
        ->and($nos->map(fn ($n) => $n['col'].':'.$n['lin'])->duplicates()->all())->toBe([])
        ->and($nos->pluck('tipo')->diff(['entrada', 'etapa', 'decisao', 'fim', 'trava'])->all())->toBe([])
        ->and($nos->pluck('situacao')->diff([CatalogoFluxos::ATENDIDO, CatalogoFluxos::PREVISTO])->all())->toBe([]);

    $nos->each(fn ($n) => expect(trim($n['descricao']))->not->toBe('', "quadradinho sem descrição: {$n['id']}"));
})->with(fn () => CatalogoFluxos::chaves());

it('lei: toda seta liga dois quadradinhos que existem, e nenhum fica solto', function (string $chave) {
    $fluxo = CatalogoFluxos::definicao($chave);
    $ids = array_column($fluxo['nos'], 'id');
    $ligados = [];

    foreach ($fluxo['arestas'] as $a) {
        expect($ids)->toContain($a['de'])->toContain($a['para']);
        $ligados[$a['de']] = true;
        $ligados[$a['para']] = true;
    }

    expect(array_values(array_diff($ids, array_keys($ligados))))->toBe([]);
})->with(fn () => CatalogoFluxos::chaves());

it('lei: toda entrada leva a algum lugar e todo fim é chegada, nunca saída', function (string $chave) {
    $fluxo = CatalogoFluxos::definicao($chave);
    $saem = array_column($fluxo['arestas'], 'de');
    $chegam = array_column($fluxo['arestas'], 'para');

    foreach ($fluxo['nos'] as $no) {
        if ($no['tipo'] === 'entrada') {
            expect($saem)->toContain($no['id']);
        }
        if ($no['tipo'] === 'fim') {
            expect($saem)->not->toContain($no['id'])
                ->and($chegam)->toContain($no['id']);
        }
    }
})->with(fn () => CatalogoFluxos::chaves());

it('lei: o passo a passo cobre todos os quadradinhos, e só os que existem', function (string $chave) {
    $fluxo = CatalogoFluxos::definicao($chave);
    $ids = array_column($fluxo['nos'], 'id');
    $citados = array_merge(...array_column($fluxo['etapas'], 'nos'));

    expect(array_values(array_diff($citados, $ids)))->toBe([])
        ->and(array_values(array_diff($ids, $citados)))->toBe([]);
})->with(fn () => CatalogoFluxos::chaves());
