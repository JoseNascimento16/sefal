<?php

use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Operacao;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

/*
 * A matriz de permissões é semeada: sem ela o Modo Gerente recusa a ação e o
 * teste falharia por acesso, não pela regra que ele quer provar — e a falha
 * apareceria como "não deu sucesso", escondendo o motivo.
 */
beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

/**
 * As Denúncias depois da consolidação: a triagem e o direcionamento gravam em
 * BANCO, e cada decisão deixa passo de trâmite.
 *
 * O que estes testes protegem é a cadeia que o dono descreveu: integração →
 * triagem do coordenador → mesa do Chefe de Setor → equipe ou operação.
 */
function comSetor(string $slug, array $atributos = []): User
{
    $usuario = User::factory()->create($atributos);
    $setor = Setor::firstOrCreate(['slug' => $slug], ['nome' => $slug]);
    $usuario->setores()->syncWithoutDetaching([$setor->id]);

    return $usuario;
}

/**
 * A estrutura mínima: uma área com uma equipe, e o LÍDER dela com conta.
 *
 * Devolve o líder no lugar em que antes vinha o chefe da área: desde 22/09/2026
 * o chefe é um só (e não é vinculado a área nenhuma); quem tem recorte é o
 * líder, pela equipe.
 */
function estrutura(): array
{
    $lider = comSetor('lider-de-equipe', ['name' => 'Líder C1']);
    $area = Area::create(['nome' => 'Área 5', 'regiao' => 'Orla']);
    AreaBairro::create(['area_id' => $area->id, 'bairro' => 'Costa Azul']);
    $equipe = Equipe::create(['codigo' => 'C1', 'nome' => 'Equipe C1', 'area_id' => $area->id, 'lider_id' => $lider->id]);

    return [$area, $equipe, $lider];
}

function denunciaRecebida(array $extra = []): Demanda
{
    static $n = 0;
    $n++;

    return Demanda::create(array_merge([
        'protocolo' => sprintf('DEN-%04d', $n),
        'canal' => Demanda::CANAL_E_SALVADOR,
        'entrada' => Demanda::ENTRADA_INTEGRACAO,
        'numero_origem' => 'ESL-2026-'.$n,
        'recebida_em' => Date::now()->subHours(3),
        'prazo_em' => Date::now()->addDays(10),
        'assunto' => 'Barraca em calçada impedindo passagem',
        'bairro' => 'Costa Azul',
        'situacao' => Demanda::RECEBIDA,
    ], $extra));
}

it('a tela do canal lista o que está em banco, com os catálogos do servidor', function () {
    estrutura();
    denunciaRecebida();
    denunciaRecebida(['canal' => Demanda::CANAL_SALVADOR_DIGITAL]);

    $this->actingAs(comSetor('chefe-de-setor', ['admin' => true]))
        ->get(route('retaguarda.denuncias.e-salvador.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Retaguarda/Denuncias/ESalvador')
            // Só o canal desta tela.
            ->has('denuncias', 1)
            ->where('canal.nome', 'e-Salvador')
            // O catálogo é o do MODEL, inteiro e na ordem do fluxo — e não um
            // primeiro item específico: fixar a posição fazia o teste quebrar
            // quando uma etapa NOVA nascia antes das outras, que é exatamente o
            // que a pré-triagem é.
            ->where('situacoes', Demanda::SITUACOES)
            ->has('lideres')
            ->has('operacoes'),
        );
});

it('o chefe de setor encaminha em lote e cada denúncia ganha passo com a equipe e o líder', function () {
    estrutura();
    $a = denunciaRecebida();
    $b = denunciaRecebida();

    $this->actingAs(comSetor('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.encaminhar'), [
            'destinos' => [
                ['id' => $a->id, 'equipe' => 'C1'],
                ['id' => $b->id, 'equipe' => 'C1'],
            ],
        ])
        ->assertSessionHas('flash.sucesso');

    expect($a->fresh()->situacao)->toBe(Demanda::ENCAMINHADA_AO_LIDER)
        ->and($a->fresh()->equipe->codigo)->toBe('C1')
        // A área vem junto: é a da equipe.
        ->and($a->fresh()->area->nome)->toBe('Área 5')
        ->and($a->fresh()->ultimoTramite()->campos)->toMatchArray(['Líder da equipe' => 'Líder C1'])
        ->and($b->fresh()->situacao)->toBe(Demanda::ENCAMINHADA_AO_LIDER);
});

it('o líder direciona aos fiscais e o caso chega à fila de campo', function () {
    [$area, $equipe, $lider] = estrutura();
    $denuncia = denunciaRecebida([
        'situacao' => Demanda::ENCAMINHADA_AO_LIDER, 'area_id' => $area->id, 'equipe_id' => $equipe->id,
    ]);

    $this->actingAs($lider)
        ->post(route('retaguarda.denuncias.direcionar'), [
            'ids' => [$denuncia->id],
            'orientacao' => 'Ir depois das 18h: as mesas só saem à noite.',
        ])
        ->assertSessionHas('flash.sucesso');

    expect($denuncia->fresh()->situacao)->toBe(Demanda::DIRECIONADA_AOS_FISCAIS)
        ->and($denuncia->fresh()->equipe->codigo)->toBe('C1')
        ->and($denuncia->fresh()->ultimoTramite()->papel)->toBe('lider-de-equipe');
});

it('recusa o direcionamento de denúncia que não é da equipe do líder, sem alterar nada', function () {
    [, , $lider] = estrutura();
    $outra = Area::create(['nome' => 'Área 1', 'regiao' => 'Centro']);
    $outraEquipe = Equipe::create(['codigo' => 'C2', 'nome' => 'Equipe C2', 'area_id' => $outra->id]);
    $denuncia = denunciaRecebida([
        'situacao' => Demanda::ENCAMINHADA_AO_LIDER, 'area_id' => $outra->id, 'equipe_id' => $outraEquipe->id,
    ]);

    $this->actingAs($lider)
        ->post(route('retaguarda.denuncias.direcionar'), ['ids' => [$denuncia->id]])
        ->assertSessionHas('flash.erro');

    expect($denuncia->fresh()->situacao)->toBe(Demanda::ENCAMINHADA_AO_LIDER);
});

it('o líder anexa a denúncia a uma operação aberta', function () {
    [$area, $equipe, $chefe] = estrutura();
    $operacao = Operacao::create([
        'codigo' => 'OP-1', 'nome' => 'Operação Verão — Orla', 'area_id' => $area->id,
        'inicio' => Date::now()->subDays(5), 'fim' => Date::now()->addDays(20),
        'situacao' => Operacao::EM_ANDAMENTO,
    ]);
    $operacao->equipes()->attach($equipe->id);

    $denuncia = denunciaRecebida([
        'situacao' => Demanda::ENCAMINHADA_AO_LIDER, 'area_id' => $area->id, 'equipe_id' => $equipe->id,
    ]);

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.operacao'), [
            'ids' => [$denuncia->id],
            'nova' => false,
            'operacao' => 'Operação Verão — Orla',
        ])
        ->assertSessionHas('flash.sucesso');

    expect($denuncia->fresh()->situacao)->toBe(Demanda::EM_OPERACAO)
        ->and($denuncia->fresh()->operacao->nome)->toBe('Operação Verão — Orla')
        // A equipe da operação vem junto: é ela que vai à rua.
        ->and($denuncia->fresh()->equipe->codigo)->toBe('C1');
});

it('recusa anexar a operação encerrada, dizendo o porquê e o que fazer', function () {
    [$area, $equipe, $chefe] = estrutura();
    Operacao::create([
        'codigo' => 'OP-2', 'nome' => 'Operação Réveillon', 'area_id' => $area->id,
        'inicio' => Date::now()->subDays(60), 'fim' => Date::now()->subDays(30),
        'situacao' => Operacao::ENCERRADA,
    ]);

    $denuncia = denunciaRecebida([
        'situacao' => Demanda::ENCAMINHADA_AO_LIDER, 'area_id' => $area->id, 'equipe_id' => $equipe->id,
    ]);

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.operacao'), [
            'ids' => [$denuncia->id],
            'nova' => false,
            'operacao' => 'Operação Réveillon',
        ])
        ->assertSessionHas('flash.erro');

    expect($denuncia->fresh()->situacao)->toBe(Demanda::ENCAMINHADA_AO_LIDER);
});

it('abre operação nova no próprio direcionamento e anexa a denúncia a ela', function () {
    [$area, $equipe, $chefe] = estrutura();
    $denuncia = denunciaRecebida([
        'situacao' => Demanda::ENCAMINHADA_AO_LIDER, 'area_id' => $area->id, 'equipe_id' => $equipe->id,
    ]);

    $this->actingAs($chefe)
        ->post(route('retaguarda.denuncias.operacao'), [
            'ids' => [$denuncia->id],
            'nova' => true,
            'nome' => 'Operação Costa Azul',
            'area' => 'Área 5',
            'equipe' => 'C1',
            'foco' => 'Barracas na faixa de areia liberada.',
        ])
        ->assertSessionHas('flash.sucesso');

    $operacao = Operacao::where('nome', 'Operação Costa Azul')->firstOrFail();

    expect($operacao->situacao)->toBe(Operacao::EM_ANDAMENTO)
        ->and($operacao->codigo)->not->toBeEmpty()
        // Rotina aberta na hora não tem fim: inventar um mostraria prazo onde não há.
        ->and($operacao->fim)->toBeNull()
        ->and($denuncia->fresh()->operacao_id)->toBe($operacao->id);
});

it('devolver ao canal exige justificativa e registra motivo e destino', function () {
    estrutura();
    $denuncia = denunciaRecebida();

    $this->actingAs(comSetor('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.devolver'), [
            'ids' => [$denuncia->id],
            'motivo' => 'Endereço insuficiente para localizar o ponto',
            'justificativa' => 'O relato não traz número nem ponto de referência, e a rua tem dois quilômetros.',
            'destino' => 'Devolvida ao remetente',
        ])
        ->assertSessionHas('flash.sucesso');

    expect($denuncia->fresh()->situacao)->toBe(Demanda::DEVOLVIDA)
        ->and($denuncia->fresh()->ultimoTramite()->campos)
        ->toMatchArray(['Motivo' => 'Endereço insuficiente para localizar o ponto']);
});

it('avisa quando nada mudou, em vez de fingir sucesso', function () {
    estrutura();
    $denuncia = denunciaRecebida(['situacao' => Demanda::ARQUIVADA]);

    $this->actingAs(comSetor('chefe-de-setor'))
        ->post(route('retaguarda.denuncias.encaminhar'), [
            'destinos' => [['id' => $denuncia->id, 'equipe' => 'C1']],
        ])
        ->assertSessionHas('flash.erro');
});
