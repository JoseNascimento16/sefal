<?php

use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\Operacao;
use App\Models\Setor;
use App\Models\User;
use App\Support\Estrutura;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Support\Facades\Date;

/*
|--------------------------------------------------------------------------
| Pedidos do dono de 25/09/2026 (segunda leva)
|--------------------------------------------------------------------------
|
| Menu (Sistema com os diagnósticos dentro, Operações como menu pai), Modo
| Gerente com chaves no menu e as duas marcas da conta, mapas para o líder com
| recorte, a coluna de área na Caixa, o cadastro de Áreas com bairros e as
| Operações com denúncias e fiscais de qualquer área.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

function contaDoLote(string $setor, array $extra = []): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true, ...$extra]);
    $u->setores()->attach(Setor::where('slug', $setor)->firstOrFail());

    return $u->fresh();
}

function adminDoLote(): User
{
    return User::factory()->create(['admin' => true, 'ativo' => true])->fresh();
}

/** @return array<int, array<string, mixed>> */
function secoesDoMenu(User $u): array
{
    return test()->actingAs($u)->get('/retaguarda/inicio')->viewData('page')['props']['menu'];
}

// ── Menu ─────────────────────────────────────────────────────────────────────

it('Monitoramento, Logs e Acompanhamento de Requisitos são filhos da pasta Sistema', function () {
    $secoes = collect(secoesDoMenu(adminDoLote()));
    $pasta = collect($secoes->firstWhere('rotulo', 'Sistema')['itens'])->firstWhere('rotulo', 'Sistema');

    expect(collect($pasta['filhos'])->pluck('rotulo')->all())
        ->toContain('Monitoramento', 'Logs', 'Acompanhamento de Requisitos', 'Áreas', 'Equipes')
        ->not->toContain('Áreas e Equipes', 'Cadastro de Operação');
});

it('Operações é menu pai próprio, logo depois dos mapas', function () {
    $rotulos = collect(secoesDoMenu(adminDoLote()))->pluck('rotulo')->all();
    $fisc = array_search('Fiscalização', $rotulos, true);

    expect($rotulos[$fisc + 1])->toBe('Operações')
        ->and(collect(secoesDoMenu(adminDoLote()))->firstWhere('rotulo', 'Operações')['itens'][0]['rotulo'])->toBe('Cadastro de Operação');
});

it('o item do menu leva o slug da tela — é o que a chave do Modo Gerente abre', function () {
    $itens = collect(secoesDoMenu(adminDoLote()))->flatMap(fn ($s) => $s['itens']);

    expect($itens->firstWhere('rotulo', 'Fiscalizações')['slug'])->toBe('fiscalizacoes')
        ->and($itens->firstWhere('rotulo', 'Modo Gerente'))->toBeNull();
});

// ── Modo Gerente e as duas marcas ────────────────────────────────────────────

it('o administrador liga e desliga o Modo Gerente, e o estado vale nas outras telas', function () {
    $admin = adminDoLote();

    $this->actingAs($admin)->get('/retaguarda/inicio')
        ->assertInertia(fn ($p) => $p->where('modoGerente.pode', true)->where('modoGerente.ativo', false));

    $this->actingAs($admin)->post(route('retaguarda.modo-gerente.alternar'))->assertSessionHas('flash.sucesso');

    $this->actingAs($admin)->get('/retaguarda/fiscalizacoes')
        ->assertInertia(fn ($p) => $p->where('modoGerente.ativo', true));

    $this->actingAs($admin)->post(route('retaguarda.modo-gerente.alternar'));

    $this->actingAs($admin)->get('/retaguarda/inicio')
        ->assertInertia(fn ($p) => $p->where('modoGerente.ativo', false));
});

it('a marca "Pode ativar o Modo Gerente" dá o Modo Gerente sem ser administrador; sem ela, não', function () {
    $gerente = contaDoLote('chefe-de-setor', ['is_gerente' => true]);
    $comum = contaDoLote('chefe-de-setor');

    $this->actingAs($gerente)->get('/retaguarda/inicio')->assertInertia(fn ($p) => $p->where('modoGerente.pode', true));
    $this->actingAs($gerente)->post(route('retaguarda.modo-gerente.alternar'))->assertSessionHas('flash.sucesso');

    $this->actingAs($comum)->get('/retaguarda/inicio')->assertInertia(fn ($p) => $p->where('modoGerente.pode', false));
    $this->actingAs($comum)->post(route('retaguarda.modo-gerente.alternar'))->assertSessionHas('flash.erro');
});

it('o Administrador de usuários abre a tela de Usuários, mas não dá as marcas a ninguém', function () {
    $adminDeUsuarios = contaDoLote('fiscal', ['is_admin_usuarios' => true]);
    $alvo = contaDoLote('fiscal');

    $this->actingAs($adminDeUsuarios)->get(route('retaguarda.usuarios.index'))->assertOk();

    $this->actingAs($adminDeUsuarios)->put(route('retaguarda.usuarios.update', $alvo), [
        'name' => $alvo->name, 'email' => $alvo->email, 'setores' => ['fiscal'], 'ativo' => true, 'is_gerente' => true,
    ])->assertSessionHasErrors('marcas');

    expect($alvo->fresh()->is_gerente)->toBeFalse();

    // O administrador dá.
    $this->actingAs(adminDoLote())->put(route('retaguarda.usuarios.update', $alvo), [
        'name' => $alvo->name, 'email' => $alvo->email, 'setores' => ['fiscal'], 'ativo' => true, 'is_gerente' => true, 'is_admin_usuarios' => true,
    ])->assertSessionHasNoErrors();

    expect($alvo->fresh()->is_gerente)->toBeTrue()->and($alvo->fresh()->is_admin_usuarios)->toBeTrue();
});

// ── Mapas para o líder, com recorte ──────────────────────────────────────────

it('o líder abre os dois mapas e vê só as fiscalizações da área dele', function () {
    $lider = contaDoLote('lider-de-equipe', ['login' => 'lider-mapa']);
    $fiscal = contaDoLote('fiscal');
    $minha = Area::create(['nome' => 'Área Minha', 'regiao' => 'Orla']);
    $outra = Area::create(['nome' => 'Área Outra', 'regiao' => 'Centro']);
    AreaBairro::create(['area_id' => $minha->id, 'bairro' => 'Barra']);
    $eqMinha = Equipe::create(['codigo' => 'M1', 'area_id' => $minha->id, 'lider_id' => $lider->id]);
    $eqOutra = Equipe::create(['codigo' => 'O1', 'area_id' => $outra->id]);
    Estrutura::esquecer();

    foreach ([[$eqMinha, 'Barra', 'VST-MINHA'], [$eqOutra, 'Comércio', 'VST-OUTRA']] as [$eq, $bairro, $prot]) {
        Fiscalizacao::create([
            'protocolo' => $prot, 'origem' => Fiscalizacao::ORIGEM_AVULSA, 'equipe_id' => $eq->id, 'fiscal_id' => $fiscal->id,
            'bairro' => $bairro, 'latitude' => -12.9, 'longitude' => -38.5,
            'aberta_em' => Date::now()->subHour(), 'concluida_em' => Date::now(), 'situacao' => Fiscalizacao::AGUARDANDO_LEITURA,
        ]);
    }

    $this->actingAs($lider)->get(route('retaguarda.mapa.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('registros', fn ($r) => collect($r)->pluck('protocolo')->all() === ['VST-MINHA']));

    $this->actingAs($lider)->get(route('retaguarda.mapa-de-calor.index'))->assertOk();

    // O chefe continua vendo a cidade inteira.
    $this->actingAs(contaDoLote('chefe-de-setor'))->get(route('retaguarda.mapa.index'))
        ->assertInertia(fn ($p) => $p->where('registros', fn ($r) => count($r) === 2));
});

// ── Caixa: a coluna de área para quem encaminha ──────────────────────────────

it('a Caixa mostra a coluna "Área (sugerida)" a quem encaminha, e não ao líder', function () {
    $chaves = fn (User $u) => collect(test()->actingAs($u)->get(route('retaguarda.denuncias.e-salvador.index'))
        ->viewData('page')['props']['listagens']['denuncias.todas']['grade'])->pluck('chave')->all();

    expect($chaves(contaDoLote('chefe-de-setor')))->toContain('area')
        ->and($chaves(contaDoLote('lider-de-equipe')))->not->toContain('area');
});

// ── Áreas ────────────────────────────────────────────────────────────────────

it('o chefe cadastra a área com bairros, tira e põe bairros, e a coordenada do bairro que fica é mantida', function () {
    $chefe = contaDoLote('chefe-de-setor');

    $this->actingAs($chefe)->post(route('retaguarda.areas.store'), [
        'nome' => 'Área Nova', 'regiao' => 'Subúrbio', 'recorte' => 'bairros', 'turno' => 'Diurno', 'ativa' => true,
        'bairros' => ['Periperi', 'Paripe'],
    ])->assertSessionHasNoErrors();

    $area = Area::where('nome', 'Área Nova')->firstOrFail();
    AreaBairro::where('area_id', $area->id)->where('bairro', 'Periperi')->update(['latitude' => -12.8, 'longitude' => -38.4]);

    $this->actingAs($chefe)->put(route('retaguarda.areas.update', $area), [
        'nome' => 'Área Nova', 'regiao' => 'Subúrbio', 'recorte' => 'bairros', 'turno' => 'Diurno', 'ativa' => true,
        'bairros' => ['Periperi', 'Coutos'],
    ])->assertSessionHasNoErrors();

    $bairros = AreaBairro::where('area_id', $area->id)->get()->keyBy('bairro');

    expect($bairros->keys()->sort()->values()->all())->toBe(['Coutos', 'Periperi'])
        ->and((float) $bairros['Periperi']->latitude)->toBe(-12.8);
});

it('a tela de Áreas abre com áreas cadastradas e lista os bairros de cada uma', function () {
    $area = Area::create(['nome' => 'Área Listada', 'regiao' => 'Orla']);
    AreaBairro::create(['area_id' => $area->id, 'bairro' => 'Ondina']);

    $this->actingAs(contaDoLote('chefe-de-setor'))->get(route('retaguarda.areas.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('Retaguarda/Sistema/Areas')
            ->where('areas.0.nome', 'Área Listada')
            ->where('areas.0.bairros', ['Ondina']));
});

it('área com equipe não se exclui — a recusa manda inativar', function () {
    $area = Area::create(['nome' => 'Área Com Equipe', 'regiao' => 'Orla']);
    Equipe::create(['codigo' => 'E1', 'area_id' => $area->id]);

    $this->actingAs(contaDoLote('chefe-de-setor'))->delete(route('retaguarda.areas.destroy', $area))
        ->assertSessionHas('flash.erro', fn (string $m) => str_contains($m, 'Ativa'));

    expect(Area::find($area->id))->not->toBeNull();
});

// ── Operações: denúncias e fiscais de qualquer área ─────────────────────────

it('a operação recebe denúncias no cadastro e fiscais de outra área; tirar a denúncia a devolve à mesa', function () {
    $chefe = contaDoLote('chefe-de-setor');
    $area = Area::create(['nome' => 'Área Op', 'regiao' => 'Orla']);
    AreaBairro::create(['area_id' => $area->id, 'bairro' => 'Pituba']);
    $fiscalDaEquipe = contaDoLote('fiscal');
    $fiscalDeFora = contaDoLote('fiscal');
    $equipe = Equipe::create(['codigo' => 'P1', 'area_id' => $area->id]);
    $equipe->fiscais()->attach($fiscalDaEquipe->id);
    Estrutura::esquecer();

    $demanda = Demanda::create([
        'protocolo' => 'DEM-OP1', 'canal' => Demanda::CANAL_AVULSA, 'tipo_avulsa' => Demanda::AVULSA_SUPERIOR,
        'entrada' => Demanda::ENTRADA_BALCAO, 'recebida_em' => Date::now(), 'assunto' => 'Barracas', 'bairro' => 'Pituba',
        'situacao' => Demanda::RECEBIDA, 'area_id' => $area->id,
    ]);

    $dados = [
        'nome' => 'Operação Pituba Limpa', 'area' => 'Área Op', 'equipes' => ['P1'], 'bairros' => ['Pituba'],
        'inicio' => Date::now()->format('Y-m-d'), 'situacao' => Operacao::SITUACOES[0],
        'fiscais' => [$fiscalDeFora->id], 'demandas' => [$demanda->id],
    ];

    $this->actingAs($chefe)->post(route('retaguarda.operacoes.store'), $dados)->assertSessionHasNoErrors();

    $operacao = Operacao::where('nome', 'Operação Pituba Limpa')->firstOrFail();

    expect($demanda->fresh()->operacao_id)->toBe($operacao->id)
        ->and($demanda->fresh()->situacao)->toBe(Demanda::EM_OPERACAO)
        ->and($operacao->fiscais->pluck('id')->all())->toBe([$fiscalDeFora->id])
        ->and($operacao->fiscaisQueRecebem())->toEqualCanonicalizing([$fiscalDaEquipe->id, $fiscalDeFora->id]);

    $this->actingAs($chefe)->put(route('retaguarda.operacoes.update', $operacao->id), [...$dados, 'demandas' => []])
        ->assertSessionHasNoErrors();

    expect($demanda->fresh()->operacao_id)->toBeNull()
        ->and($demanda->fresh()->situacao)->not->toBe(Demanda::EM_OPERACAO);
});
