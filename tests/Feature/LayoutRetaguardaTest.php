<?php

use App\Models\Setor;
use App\Models\User;
use Database\Seeders\SetoresSeeder;

/*
|--------------------------------------------------------------------------
| A casca da Retaguarda
|--------------------------------------------------------------------------
|
| O que se testa aqui é o CONTRATO entre o servidor e a tela: quem está logado,
| o que o menu oferece a essa pessoa e o recado que atravessa o
| redirecionamento. O visual (cor, espaçamento, tema escuro) não é asserção de
| teste de servidor — é conferência de tela.
|
*/

beforeEach(fn () => $this->seed(SetoresSeeder::class));

test('a tela inicial abre com o usuario e o menu', function () {
    $u = User::factory()->create(['ativo' => true, 'login' => 'F1234']);

    $this->actingAs($u)->get('/retaguarda/inicio')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Retaguarda/Inicio')
            ->has('auth.user.login')
            // A matrícula viaja na forma canônica (minúscula); quem a mostra em
            // maiúscula é a tela.
            ->where('auth.user.login', 'f1234')
            ->has('auth.user.admin')
            ->has('auth.user.setores')
            ->has('menu'),
        );
});

test('quem nao entrou vai para o login', function () {
    $this->get('/retaguarda/inicio')->assertRedirect('/login');
});

test('a senha nao viaja para a tela', function () {
    $u = User::factory()->create();

    $this->actingAs($u)->get('/retaguarda/inicio')
        ->assertInertia(fn ($p) => $p
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token'),
        );
});

test('o menu traz o inicio, o perfil e a secao ainda em construcao', function () {
    $this->actingAs(User::factory()->create())->get('/retaguarda/inicio')
        ->assertInertia(function ($p) {
            $menu = collect($p->toArray()['props']['menu']);

            $rotulos = $menu->pluck('itens')->flatten(1)->pluck('rotulo');
            expect($rotulos)->toContain('Início')->toContain('Meu Perfil');

            // Seção sem tela pronta aparece com o recado do que vem por aí, em
            // vez de sumir — quem usa o sistema enxerga o caminho.
            $fiscalizacao = $menu->firstWhere('rotulo', 'Fiscalização');
            expect($fiscalizacao)->not->toBeNull();
            expect($fiscalizacao['itens'])->toBe([]);
            expect($fiscalizacao['vazio'])->not->toBeNull();
        });
});

test('item de menu restrito a um setor nao aparece para quem nao pertence a ele', function () {
    // O menu é montado no servidor: se a decisão de quem vê o quê morasse na
    // tela, o item apenas ficaria escondido — mas continuaria viajando.
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Só do fiscal',
            'rota' => 'retaguarda.inicio',
            'icone' => 'fiscalizacoes',
            'setores' => ['fiscal'],
        ]],
    ]]);

    $gestor = User::factory()->create();
    $gestor->setores()->attach(Setor::where('slug', 'gestor')->firstOrFail());

    $this->actingAs($gestor)->get('/retaguarda/inicio')
        ->assertInertia(fn ($p) => $p->where('menu', []));

    $fiscal = User::factory()->create();
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());

    $this->actingAs($fiscal)->get('/retaguarda/inicio')
        ->assertInertia(function ($p) {
            $itens = collect($p->toArray()['props']['menu'])->pluck('itens')->flatten(1);
            expect($itens->pluck('rotulo'))->toContain('Só do fiscal');
        });
});

test('o administrador ve o item de qualquer setor', function () {
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Só do fiscal',
            'rota' => 'retaguarda.inicio',
            'icone' => 'fiscalizacoes',
            'setores' => ['fiscal'],
        ]],
    ]]);

    $admin = User::factory()->create(['admin' => true]);

    $this->actingAs($admin)->get('/retaguarda/inicio')
        ->assertInertia(function ($p) {
            $itens = collect($p->toArray()['props']['menu'])->pluck('itens')->flatten(1);
            expect($itens->pluck('rotulo'))->toContain('Só do fiscal');
        });
});

test('item cujo destino ainda nao existe e descartado, e o menu continua de pe', function () {
    // O plano do menu anda na frente das telas. Um link para rota inexistente
    // estouraria a barra inteira: o sistema ficaria sem menu por causa de uma
    // linha de configuração.
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [
            ['rotulo' => 'Tela que ainda vem', 'rota' => 'fiscalizacao.que.nao.existe', 'icone' => 'padrao', 'setores' => []],
            ['rotulo' => 'Início', 'rota' => 'retaguarda.inicio', 'icone' => 'inicio', 'setores' => []],
        ],
    ]]);

    $this->actingAs(User::factory()->create())->get('/retaguarda/inicio')
        ->assertOk()
        ->assertInertia(function ($p) {
            $rotulos = collect($p->toArray()['props']['menu'])->pluck('itens')->flatten(1)->pluck('rotulo');
            expect($rotulos)->toContain('Início')->not->toContain('Tela que ainda vem');
        });
});

test('o recado do servidor chega a tela uma vez, com chave nova a cada resposta', function () {
    $u = User::factory()->create();

    $this->actingAs($u)
        ->patch(route('profile.update'), ['name' => 'Nome Novo', 'email' => $u->email])
        ->assertRedirect(route('profile.edit'));

    $primeira = $this->get(route('profile.edit'));
    $primeira->assertInertia(fn ($p) => $p->where('flash.sucesso', 'Dados atualizados.'));
    $chave = $primeira->viewData('page')['props']['flash']['chave'];
    expect($chave)->not->toBeNull();

    // Recado é de uma vez: na visita seguinte ele já não está lá.
    $this->get(route('profile.edit'))
        ->assertInertia(fn ($p) => $p->where('flash.sucesso', null)->where('flash.chave', null));

    // E, na próxima gravação, a chave é outra — é ela que faz o aviso reaparecer
    // quando a mesma mensagem chega duas vezes seguidas.
    $this->patch(route('profile.update'), ['name' => 'Outro Nome', 'email' => $u->email]);
    $segunda = $this->get(route('profile.edit'));
    expect($segunda->viewData('page')['props']['flash']['chave'])->not->toBe($chave);
});

test('as telas de erro falam portugues e nao mostram rastro de pilha', function () {
    // Sem depurador: é o que roda em homologação e produção.
    config()->set('app.debug', false);

    $resposta = $this->get('/rota-que-nao-existe');

    $resposta->assertNotFound()
        ->assertSee('Página não encontrada')
        ->assertSee('Ir para o início')
        ->assertDontSee('Stack trace', false);
});
