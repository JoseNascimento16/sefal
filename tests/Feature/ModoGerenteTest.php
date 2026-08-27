<?php

use App\Models\PermissaoLog;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use App\Services\PermissaoService;
use App\Support\CatalogoFuncionalidades;
use Database\Seeders\PermissoesSetorSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modo Gerente — quem entra em qual tela, e quem decide isso
|--------------------------------------------------------------------------
|
| O que se testa aqui é a REGRA DE ACESSO, não a aparência da matriz: quem
| pode ver uma tela, o que acontece com quem não pode (nunca uma tela de erro
| seca) e quem manda quando a matriz e o menu discordam — porque só um deles
| pode mandar.
|
| A régua é a spec de design (Fase 1, Modo Gerente) e a lei do projeto de que
| ninguém é barrado em silêncio.
|
*/

beforeEach(fn () => $this->seed());

/** Usuário de um setor só, do jeito que o sistema o cria. */
function usuarioDoSetor(string $slug, array $atributos = []): User
{
    $u = User::factory()->create([...$atributos, 'admin' => false]);
    $u->setores()->attach(Setor::where('slug', $slug)->firstOrFail());

    return $u->fresh();
}

/**
 * Uma tela controlável qualquer, para testar a guarda sem depender da única tela
 * real que existe hoje (que tem tratamento próprio: ver `permissao_sempre`).
 *
 * O grupo `web` na rota é essencial — é ele que carrega as guardas. Sem ele, a
 * requisição não passaria por guarda nenhuma e o teste ficaria verde por engano.
 */
function telaControlavel(string $slug): void
{
    Route::middleware(['web', 'auth'])->get("retaguarda/{$slug}", fn () => 'ok')->name("teste.{$slug}");
    Route::middleware(['web', 'auth'])->put("retaguarda/{$slug}", fn () => 'ok')->name("teste.{$slug}.update");
    Route::getRoutes()->refreshNameLookups();

    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Vistorias',
            'rota' => "teste.{$slug}",
            'icone' => 'fiscalizacoes',
            'slug' => $slug,
            'setores' => [],
        ]],
    ]]);
}

test('admin pode tudo', function () {
    $admin = User::factory()->create(['admin' => true]);

    expect(app(PermissaoService::class)->pode($admin, 'modo-gerente', 'habilitado'))->toBeTrue()
        ->and(app(PermissaoService::class)->pode($admin, 'modo-gerente', 'visivel'))->toBeTrue();
});

test('quem nao tem a tela concedida nao pode, mesmo com setor', function () {
    $fiscal = usuarioDoSetor('fiscal');

    expect(app(PermissaoService::class)->pode($fiscal, 'modo-gerente', 'visivel'))->toBeFalse();
});

test('permissao concedida a um setor vale para quem pertence a ele', function () {
    $gestor = usuarioDoSetor('gestor');

    PermissaoSetor::create([
        'setor' => 'gestor',
        'slug' => 'modo-gerente',
        'visivel' => true,
        'habilitado' => true,
    ]);

    expect(app(PermissaoService::class)->pode($gestor, 'modo-gerente', 'visivel'))->toBeTrue()
        ->and(app(PermissaoService::class)->pode($gestor, 'modo-gerente', 'incluir'))->toBeFalse();
});

test('quem tem dois setores soma o que cada um concede', function () {
    // A permissão efetiva é a UNIÃO (OR): o setor que concede ganha do que não
    // concede. Fosse interseção, acumular setores TIRARIA acesso.
    $u = User::factory()->create(['admin' => false]);
    $u->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());
    $u->setores()->attach(Setor::where('slug', 'gestor')->firstOrFail());

    PermissaoSetor::create(['setor' => 'fiscal', 'slug' => 'modo-gerente', 'visivel' => true]);
    PermissaoSetor::create(['setor' => 'gestor', 'slug' => 'modo-gerente', 'excluir' => true]);

    $servico = app(PermissaoService::class);

    expect($servico->pode($u->fresh(), 'modo-gerente', 'visivel'))->toBeTrue()
        ->and($servico->pode($u->fresh(), 'modo-gerente', 'excluir'))->toBeTrue();
});

test('fiscal sem permissao de tela e redirecionado ao inicio com mensagem, nunca 403 cru', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $fiscal = usuarioDoSetor('fiscal');

    $this->actingAs($fiscal)->get('/retaguarda/modo-gerente')
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');
});

test('HEAD e barrado como o GET — e a mesma leitura', function () {
    // Conferir só o GET deixaria a tela acessível por HEAD, e cabeçalho e código
    // de status já contam bastante sobre o que existe do outro lado.
    config(['retaguarda.permissao_enforce' => 'block']);

    $this->actingAs(usuarioDoSetor('fiscal'))->head('/retaguarda/modo-gerente')
        ->assertRedirect('/retaguarda/inicio');
});

test('a tela inicial nunca e barrada — nao ha loop de redirecionamento', function () {
    // A lei: o destino do login jamais pode ser uma tela controlada por
    // permissão. Se fosse, quem não a tivesse cairia em redirecionamento
    // infinito e o navegador morreria sem dizer o motivo.
    config(['retaguarda.permissao_enforce' => 'block']);

    $this->actingAs(usuarioDoSetor('fiscal'))->get('/retaguarda/inicio')->assertOk();
});

test('a propria conta nunca depende de permissao de gestor', function () {
    // Trocar a própria senha não é decisão de matriz: um gestor distraído
    // trancaria alguém fora da própria conta.
    config(['retaguarda.permissao_enforce' => 'block']);

    $fiscal = usuarioDoSetor('fiscal');

    $this->actingAs($fiscal)->get('/retaguarda/perfil')->assertOk();

    $this->actingAs($fiscal)
        ->patch(route('profile.update'), ['name' => 'Outro Nome', 'email' => $fiscal->email])
        ->assertRedirect(route('profile.edit'));

    expect($fiscal->fresh()->name)->toBe('Outro Nome');
});

test('mutacao sem permissao volta para a tela anterior com o motivo, e nao grava', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $gestor = usuarioDoSetor('gestor');

    $this->actingAs($gestor)
        ->from('/retaguarda/inicio')
        ->post(route('retaguarda.modo-gerente.salvar'), [
            'slug' => 'modo-gerente',
            'matriz' => [['setor' => 'fiscal', 'visivel' => true]],
        ])
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');

    // A conferência é sobre a linha que a mutação TENTOU gravar: o fiscal já tem
    // outras concessões vindas da semente, e olhar o setor inteiro faria o teste
    // passar a acusar a semeadura em vez da gravação barrada.
    expect(PermissaoSetor::where('setor', 'fiscal')->where('slug', 'modo-gerente')->exists())->toBeFalse();
});

test('o rollout observa antes de barrar: off e log passam, block barra', function () {
    /*
     * Os três modos na MESMA tela, e de propósito: se o teste só afirmasse que
     * `log` passa, ele passaria também com a guarda desligada — não provaria
     * nada. É o `block` barrando, no fim, que prova que a guarda estava lá nos
     * outros dois.
     */
    telaControlavel('vistorias');

    foreach (['off', 'log'] as $modo) {
        config(['retaguarda.permissao_enforce' => $modo]);

        $this->actingAs(usuarioDoSetor('fiscal'))->get('/retaguarda/vistorias')->assertOk();
    }

    config(['retaguarda.permissao_enforce' => 'block']);

    $this->actingAs(usuarioDoSetor('fiscal'))->get('/retaguarda/vistorias')
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');
});

test('no modo log a leitura barrada fica REGISTRADA, com o que se precisa para conferir', function () {
    /*
     * O modo `log` não vale nada se não registrar: sem este teste, apagar o
     * registro deixaria a suíte verde — e o rollout ficaria sem o rastro que é a
     * única razão de ele existir. O que se confere antes de virar a chave é
     * exatamente este log.
     */
    config(['retaguarda.permissao_enforce' => 'log']);
    telaControlavel('vistorias');

    Log::spy();

    $fiscal = usuarioDoSetor('fiscal');

    $this->actingAs($fiscal)->get('/retaguarda/vistorias')->assertOk();

    Log::shouldHaveReceived('warning')->once()->withArgs(
        function (string $mensagem, array $contexto) use ($fiscal): bool {
            expect($mensagem)->toContain('Modo Gerente')
                ->and($contexto['tela'])->toBe('vistorias')
                ->and($contexto['rota'])->toBe('teste.vistorias')
                ->and($contexto['caminho'])->toBe('retaguarda/vistorias')
                ->and($contexto['user_id'])->toBe($fiscal->id);

            return true;
        },
    );
});

test('no modo log a mutacao barrada fica REGISTRADA, com a acao inferida', function () {
    config(['retaguarda.permissao_enforce' => 'log']);
    telaControlavel('vistorias');

    Log::spy();

    $fiscal = usuarioDoSetor('fiscal');

    $this->actingAs($fiscal)->put('/retaguarda/vistorias')->assertOk();

    Log::shouldHaveReceived('warning')->once()->withArgs(
        function (string $mensagem, array $contexto) use ($fiscal): bool {
            expect($mensagem)->toContain('Modo Gerente')
                ->and($contexto['tela'])->toBe('vistorias')
                // PUT não é `.store` nem exclusão: a ação inferida é operar.
                ->and($contexto['acao'])->toBe('habilitado')
                ->and($contexto['metodo'])->toBe('PUT')
                ->and($contexto['rota'])->toBe('teste.vistorias.update')
                ->and($contexto['user_id'])->toBe($fiscal->id);

            return true;
        },
    );
});

test('quem tem a permissao nao gera registro — o log e do que SERIA barrado', function () {
    // Registrar quem passou legitimamente afogaria o rastro no próprio volume.
    config(['retaguarda.permissao_enforce' => 'log']);
    telaControlavel('vistorias');

    PermissaoSetor::create(['setor' => 'fiscal', 'slug' => 'vistorias', 'visivel' => true, 'habilitado' => true]);

    Log::spy();

    $this->actingAs(usuarioDoSetor('fiscal'))->get('/retaguarda/vistorias')->assertOk();

    Log::shouldNotHaveReceived('warning');
});

test('a tela que distribui acesso e barrada mesmo com o rollout em observacao', function () {
    /*
     * Achado da própria revisão desta entrega: com o bloqueio em `log` — que é
     * o padrão —, a tela do Modo Gerente ficaria aberta a qualquer pessoa
     * autenticada. O rollout gradual existe para não TIRAR acesso de ninguém
     * por engano; aplicado à tela que CONCEDE acesso, ele daria acesso a todos.
     *
     * Por isso `retaguarda.permissao_sempre`: a régua continua sendo a matriz,
     * o que não vale para ela é o adiamento do bloqueio.
     */
    foreach (['off', 'log', 'block'] as $modo) {
        config(['retaguarda.permissao_enforce' => $modo]);

        $this->actingAs(usuarioDoSetor('fiscal'))->get('/retaguarda/modo-gerente')
            ->assertRedirect('/retaguarda/inicio')
            ->assertSessionHas('flash.erro');

        $this->actingAs(usuarioDoSetor('gestor'))
            ->from('/retaguarda/inicio')
            ->post(route('retaguarda.modo-gerente.salvar'), [
                'slug' => 'modo-gerente',
                'matriz' => [['setor' => 'fiscal', 'visivel' => true]],
            ])
            ->assertRedirect('/retaguarda/inicio')
            ->assertSessionHas('flash.erro');
    }

    // Nada foi gravado PARA ESTA TELA. A conta é por slug, e não a da tabela
    // inteira: o seeder semeia a concessão inicial das outras telas do menu, e
    // uma contagem global passaria a reprovar a cada tela nova — sem que nada
    // tivesse a ver com o que este teste garante.
    expect(PermissaoSetor::where('slug', 'modo-gerente')->count())->toBe(0);
});

test('quem recebe a concessao abre o painel de verdade', function () {
    /*
     * O outro lado da moeda: conceder na matriz TEM de abrir a porta, senão o
     * Modo Gerente seria decoração.
     *
     * O Modo Gerente não é página: é o painel que abre sobre a tela atual, e a
     * MESMA rota que a guarda protege é a que entrega a matriz a ele (em JSON).
     * "Abrir" é, portanto, receber a matriz.
     */
    config(['retaguarda.permissao_enforce' => 'block']);

    $gestor = usuarioDoSetor('gestor');

    PermissaoSetor::create([
        'setor' => 'gestor',
        'slug' => 'modo-gerente',
        'visivel' => true,
        'habilitado' => true,
    ]);

    $this->actingAs($gestor)->getJson('/retaguarda/modo-gerente')->assertOk();

    $this->actingAs($gestor)
        ->post(route('retaguarda.modo-gerente.salvar'), [
            'slug' => 'modo-gerente',
            'matriz' => [['setor' => 'fiscal', 'visivel' => true, 'habilitado' => true]],
        ])
        ->assertSessionHas('flash.sucesso');

    expect(PermissaoSetor::where('setor', 'fiscal')->where('slug', 'modo-gerente')->exists())->toBeTrue();
});

test('o administrador recebe a matriz completa para o painel', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $admin = User::factory()->create(['admin' => true]);

    $this->actingAs($admin)->getJson('/retaguarda/modo-gerente')
        ->assertOk()
        ->assertJsonCount(3, 'setores')
        ->assertJsonStructure(['setores', 'funcionalidades', 'matriz', 'acoes', 'historico'])
        ->assertJsonPath('enforce', 'block');
});

test('o endereco do Modo Gerente leva ao inicio PEDINDO que o painel abra la', function () {
    /*
     * Quem tem o endereço nos favoritos (ou o digita) não pode cair num vazio: o
     * Modo Gerente virou painel sobreposto, e a rota que era da página agora
     * manda a pessoa para a tela inicial com o pedido de abrir o painel ali.
     * Devolver "não encontrado" seria barrar em silêncio quem tem a permissão.
     */
    config(['retaguarda.permissao_enforce' => 'block']);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get('/retaguarda/modo-gerente')
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('abrir.painel', 'modo-gerente');

    // E o pedido chega à tela como propriedade compartilhada — é ela que o painel
    // lê para se abrir sozinho.
    $this->followingRedirects()
        ->actingAs(User::factory()->create(['admin' => true]))
        ->get('/retaguarda/modo-gerente')
        ->assertInertia(fn ($p) => $p->where('painel', 'modo-gerente'));
});

test('a leitura pedida em JSON e negada COM O MOTIVO, e nao com um redirecionamento', function () {
    /*
     * Quem pede a matriz é código (o painel), não navegador: um redirecionamento
     * devolveria a tela inicial em HTML, o painel não conseguiria ler aquilo e
     * mostraria "falha ao carregar" — que é justamente a barrada em silêncio que
     * a lei do projeto proíbe. A negativa vem com o motivo escrito.
     */
    config(['retaguarda.permissao_enforce' => 'block']);

    $this->actingAs(usuarioDoSetor('fiscal'))->getJson('/retaguarda/modo-gerente')
        ->assertForbidden()
        ->assertJsonPath('erro', 'Você não tem acesso a essa tela.');
});

test('o item do Modo Gerente no menu ABRE PAINEL — nao navega', function () {
    /*
     * Teste-LEI do contrato entre o servidor e a barra lateral: é o `modal` do
     * item que faz a barra desenhar um botão em vez de um link. Sem ele, o item
     * volta a navegar para o endereço — que hoje só redireciona —, e a pessoa dá
     * uma volta inteira para chegar ao mesmo painel.
     */
    $itens = collect(
        $this->actingAs(User::factory()->create(['admin' => true]))
            ->get('/retaguarda/inicio')
            ->viewData('page')['props']['menu']
    )->pluck('itens')->flatten(1);

    expect($itens->firstWhere('rotulo', 'Modo Gerente')['modal'])->toBe('modo-gerente')
        // E o resto do menu continua navegando: `modal` é a exceção declarada.
        ->and($itens->firstWhere('rotulo', 'Permissionários')['modal'])->toBeNull();
});

test('salvar a matriz grava a concessao, normaliza as regras e deixa rastro', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $admin = User::factory()->create(['admin' => true, 'name' => 'Ana Gestora']);

    /*
     * A gravação vem de dentro do painel, que abre SOBRE uma tela qualquer — daí
     * o `from` ser a tela inicial. O que se afirma é que a resposta volta para
     * onde a pessoa estava (`back()`), e não para uma página de Modo Gerente que
     * não existe mais: o painel continua aberto por cima do que ela conferia.
     */
    $this->actingAs($admin)
        ->from('/retaguarda/inicio')
        ->post(route('retaguarda.modo-gerente.salvar'), [
            'slug' => 'modo-gerente',
            'matriz' => [
                // Apenas leitura manda: incluir/excluir caem, mesmo pedidos.
                ['setor' => 'gestor', 'visivel' => true, 'habilitado' => true, 'apenas_leitura' => true, 'incluir' => true, 'excluir' => true],
                // Sem "visível" nada mais vale: é o pré-requisito das demais.
                ['setor' => 'fiscal', 'visivel' => false, 'habilitado' => true, 'incluir' => true],
            ],
        ])
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.sucesso');

    $gestor = PermissaoSetor::where('setor', 'gestor')->where('slug', 'modo-gerente')->firstOrFail();
    expect($gestor->visivel)->toBeTrue()
        ->and($gestor->apenas_leitura)->toBeTrue()
        ->and($gestor->incluir)->toBeFalse()
        ->and($gestor->excluir)->toBeFalse();

    $fiscal = PermissaoSetor::where('setor', 'fiscal')->where('slug', 'modo-gerente')->firstOrFail();
    expect($fiscal->visivel)->toBeFalse()
        ->and($fiscal->habilitado)->toBeFalse()
        ->and($fiscal->incluir)->toBeFalse();

    // O rastro existe para se poder perguntar depois "quem abriu esta porta?".
    $log = PermissaoLog::latest('id')->firstOrFail();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->user_nome)->toBe('Ana Gestora')
        ->and($log->funcionalidade_slug)->toBe('modo-gerente');
});

test('o rastro diz o QUE mudou, por setor — concessao e revogacao', function () {
    /*
     * "Permissões alteradas" não responde a pergunta que se faz depois de um
     * incidente. O rastro tem de dizer qual porta se abriu, e para quem.
     */
    $admin = User::factory()->create(['admin' => true]);

    // Estado de partida: gestor já operava, fiscal não tinha nada.
    PermissaoSetor::create([
        'setor' => 'gestor',
        'slug' => 'modo-gerente',
        'visivel' => true,
        'habilitado' => true,
    ]);

    $this->actingAs($admin)->post(route('retaguarda.modo-gerente.salvar'), [
        'slug' => 'modo-gerente',
        'matriz' => [
            // Concessão nova.
            ['setor' => 'fiscal', 'visivel' => true, 'habilitado' => true],
            // Revogação: perde o operar, mantém o ver.
            ['setor' => 'gestor', 'visivel' => true, 'habilitado' => false],
        ],
    ]);

    $descricao = PermissaoLog::latest('id')->firstOrFail()->descricao;

    expect($descricao)->toContain('Modo Gerente')
        ->and($descricao)->toContain('fiscal: +visivel, +habilitado')
        ->and($descricao)->toContain('gestor: -habilitado');
});

test('setor que nao mudou fica FORA do rastro', function () {
    // Rastro que repete o estado inteiro a cada gravação some no próprio volume.
    $admin = User::factory()->create(['admin' => true]);

    PermissaoSetor::create([
        'setor' => 'gestor',
        'slug' => 'modo-gerente',
        'visivel' => true,
        'habilitado' => true,
    ]);

    $this->actingAs($admin)->post(route('retaguarda.modo-gerente.salvar'), [
        'slug' => 'modo-gerente',
        'matriz' => [
            ['setor' => 'gestor', 'visivel' => true, 'habilitado' => true],
            ['setor' => 'fiscal', 'visivel' => true],
        ],
    ]);

    $descricao = PermissaoLog::latest('id')->firstOrFail()->descricao;

    expect($descricao)->toContain('fiscal: +visivel')
        ->and($descricao)->not->toContain('gestor');
});

test('gravar sem mexer em nada diz isso, em vez de fingir alteracao', function () {
    $admin = User::factory()->create(['admin' => true]);

    $this->actingAs($admin)->post(route('retaguarda.modo-gerente.salvar'), [
        'slug' => 'modo-gerente',
        'matriz' => [['setor' => 'fiscal', 'visivel' => false]],
    ]);

    expect(PermissaoLog::latest('id')->firstOrFail()->descricao)->toContain('nada mudou');
});

test('so consulta e operar nao convivem — a linha contraditoria e normalizada', function () {
    /*
     * A coluna promete "abre para olhar"; deixar `habilitado` de pé junto com
     * `apenas_leitura` permitia gravar por PUT/PATCH. A promessa da tela e o que
     * o servidor faz têm de ser a mesma coisa.
     */
    $admin = User::factory()->create(['admin' => true]);

    $this->actingAs($admin)->post(route('retaguarda.modo-gerente.salvar'), [
        'slug' => 'modo-gerente',
        'matriz' => [[
            'setor' => 'gestor',
            'visivel' => true,
            'habilitado' => true,
            'apenas_leitura' => true,
            'incluir' => true,
            'excluir' => true,
        ]],
    ]);

    $linha = PermissaoSetor::where('setor', 'gestor')->where('slug', 'modo-gerente')->firstOrFail();

    expect($linha->visivel)->toBeTrue()
        ->and($linha->apenas_leitura)->toBeTrue()
        ->and($linha->habilitado)->toBeFalse()
        ->and($linha->incluir)->toBeFalse()
        ->and($linha->excluir)->toBeFalse();

    // E o efeito prático: quem só consulta não opera.
    $gestor = usuarioDoSetor('gestor');
    $servico = app(PermissaoService::class);

    expect($servico->pode($gestor, 'modo-gerente', 'visivel'))->toBeTrue()
        ->and($servico->pode($gestor, 'modo-gerente', 'habilitado'))->toBeFalse();
});

test('a matriz recusa setor de fora do catalogo e tela fora do catalogo', function () {
    $admin = User::factory()->create(['admin' => true]);

    $this->actingAs($admin)
        ->post(route('retaguarda.modo-gerente.salvar'), [
            'slug' => 'modo-gerente',
            'matriz' => [['setor' => 'inventado', 'visivel' => true]],
        ]);

    expect(PermissaoSetor::where('setor', 'inventado')->exists())->toBeFalse();

    $this->actingAs($admin)
        ->post(route('retaguarda.modo-gerente.salvar'), [
            'slug' => 'tela-que-nao-existe',
            'matriz' => [['setor' => 'gestor', 'visivel' => true]],
        ])
        ->assertSessionHasErrors('slug');
});

test('o administrador nao aparece na matriz como editavel — ele e desvio, nao concessao', function () {
    $admin = User::factory()->create(['admin' => true]);

    $this->actingAs($admin)
        ->post(route('retaguarda.modo-gerente.salvar'), [
            'slug' => 'modo-gerente',
            'matriz' => [['setor' => 'administrador', 'visivel' => false, 'habilitado' => false]],
        ]);

    // Gravar "não" para o administrador não pode tirar o acesso dele: o bypass
    // vive no código, não numa linha que alguém possa desmarcar por engano.
    expect(app(PermissaoService::class)->pode(User::factory()->create(['admin' => true]), 'modo-gerente'))->toBeTrue()
        ->and(PermissaoSetor::where('setor', 'administrador')->exists())->toBeFalse();
});

test('o menu obedece o rollout: fora do modo block, o item continua a vista', function () {
    /*
     * Some do menu sem recado é a barrada em silêncio que o `log` existe para
     * evitar: o item desapareceria, ninguém saberia por quê, nada seria
     * registrado — e a tela abriria pelo endereço. Enquanto o bloqueio não está
     * ligado, o item fica; quem visita sem permissão passa e vira registro.
     */
    telaControlavel('vistorias');

    $fiscal = usuarioDoSetor('fiscal');

    // Uma asserção só, com os três modos lado a lado: assim a falha diz QUAL
    // modo saiu do combinado, em vez de apontar para uma volta de laço.
    $apareceNoMenu = [];

    foreach (['off', 'log', 'block'] as $modo) {
        config(['retaguarda.permissao_enforce' => $modo]);

        $menu = $this->actingAs($fiscal)->get('/retaguarda/inicio')->viewData('page')['props']['menu'];

        $apareceNoMenu[$modo] = collect($menu)->pluck('itens')->flatten(1)
            ->pluck('rotulo')->contains('Vistorias');
    }

    expect($apareceNoMenu)->toBe(['off' => true, 'log' => true, 'block' => false]);
});

test('item de menu que o usuario nao pode ver nao aparece no menu', function () {
    // Menu e guarda de acesso leem a MESMA regra: se cada um tivesse a sua, um
    // dia o menu ofereceria uma tela que a guarda barra. A tela do Modo Gerente
    // não espera o rollout, então some do menu em qualquer modo.
    $gestor = usuarioDoSetor('gestor');

    $this->actingAs($gestor)->get('/retaguarda/inicio')
        ->assertInertia(function ($p) {
            $rotulos = collect($p->toArray()['props']['menu'])->pluck('itens')->flatten(1)->pluck('rotulo');
            expect($rotulos)->not->toContain('Modo Gerente');
        });

    PermissaoSetor::create(['setor' => 'gestor', 'slug' => 'modo-gerente', 'visivel' => true, 'habilitado' => true]);

    $this->actingAs($gestor)->get('/retaguarda/inicio')
        ->assertInertia(function ($p) {
            $rotulos = collect($p->toArray()['props']['menu'])->pluck('itens')->flatten(1)->pluck('rotulo');
            expect($rotulos)->toContain('Modo Gerente');
        });
});

test('o administrador ve o Modo Gerente no menu sem precisar de concessao', function () {
    $this->actingAs(User::factory()->create(['admin' => true]))->get('/retaguarda/inicio')
        ->assertInertia(function ($p) {
            $rotulos = collect($p->toArray()['props']['menu'])->pluck('itens')->flatten(1)->pluck('rotulo');
            expect($rotulos)->toContain('Modo Gerente');
        });
});

test('a semeadura nasce do menu — tela restrita a setor nasce concedida a ele', function () {
    // A lista `setores` do menu é a SEMENTE da matriz. Depois de semeada, quem
    // manda é a matriz (editável no Modo Gerente) — a config não decide mais.
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Vistorias',
            'rota' => 'retaguarda.inicio',
            'icone' => 'fiscalizacoes',
            'slug' => 'vistorias',
            'setores' => ['fiscal', 'administrador'],
        ]],
    ]]);

    $this->seed(PermissoesSetorSeeder::class);

    $semeadas = PermissaoSetor::where('slug', 'vistorias')->get();

    expect($semeadas->pluck('setor')->all())->toBe(['fiscal'])
        ->and($semeadas->first()->visivel)->toBeTrue()
        ->and($semeadas->first()->habilitado)->toBeTrue();
});

test('a semente pode AJUSTAR o pacote de um setor — e o fiscal apenas CONSULTA na Retaguarda', function () {
    /*
     * O fiscal enxerga o cadastro de permissionário (chegar na calçada sem saber
     * quem está cadastrado é trabalhar às cegas) e não grava nada por lá: ele
     * cadastra em RUA, pelo aplicativo, e o que nasce em rua entra em quarentena
     * até o gestor conferir.
     *
     * ⚠️ O ajuste é `apenas_leitura`, e não "incluir e excluir desligados". A
     * diferença decide se a quarentena existe: com `habilitado` ligado o fiscal
     * ALTERAVA o cadastro, e a situação é campo do mesmo formulário — ele tirava
     * da fila o registro que ele mesmo acabara de criar em rua.
     *
     * A conferência é sobre a configuração REAL do menu, e não sobre um exemplo
     * montado no teste: é a concessão inicial entregue que se quer travar.
     */
    $fiscal = PermissaoSetor::where('setor', 'fiscal')->where('slug', 'permissionarios')->firstOrFail();

    expect($fiscal->visivel)->toBeTrue()
        ->and($fiscal->apenas_leitura)->toBeTrue()
        ->and($fiscal->habilitado)->toBeFalse()
        ->and($fiscal->incluir)->toBeFalse()
        ->and($fiscal->excluir)->toBeFalse();

    // O gestor continua com o pacote inteiro: validar e corrigir cadastro de
    // campo é o trabalho dele.
    $gestor = PermissaoSetor::where('setor', 'gestor')->where('slug', 'permissionarios')->firstOrFail();

    expect($gestor->incluir)->toBeTrue()
        ->and($gestor->excluir)->toBeTrue();
});

test('a forma curta e a forma longa da semente convivem', function () {
    // Teste-LEI do formato. A config aceita `'gestor'` (pacote inteiro) e
    // `'fiscal' => [...]` (pacote com ajuste) na MESMA lista; se a leitura
    // quebrasse com a mistura, a tela nasceria sem concessão nenhuma — e tela
    // controlável sem concessão é tela que ninguém abre.
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Vistorias',
            'rota' => 'retaguarda.inicio',
            'icone' => 'fiscalizacoes',
            'slug' => 'vistorias',
            'setores' => ['gestor', 'fiscal' => ['excluir' => false]],
        ]],
    ]]);

    $this->seed(PermissoesSetorSeeder::class);

    $semeadas = PermissaoSetor::where('slug', 'vistorias')->get()->keyBy('setor');

    expect($semeadas->keys()->sort()->values()->all())->toBe(['fiscal', 'gestor'])
        ->and($semeadas['gestor']->excluir)->toBeTrue()
        ->and($semeadas['fiscal']->excluir)->toBeFalse()
        // O ajuste é PONTUAL: o que não foi declarado continua vindo do pacote.
        ->and($semeadas['fiscal']->visivel)->toBeTrue()
        ->and($semeadas['fiscal']->incluir)->toBeTrue();
});

test('a semente nunca grava linha que se contradiz — "so consulta" derruba o resto', function () {
    /*
     * Teste-LEI. A resolução em tempo de execução lê as COLUNAS CRUAS da matriz:
     * uma linha com `apenas_leitura` marcado ao lado de `habilitado`, `incluir` e
     * `excluir` ainda ligados daria poder de gravar a quem a config diz que só
     * olha — e ninguém perceberia, porque a config estaria dizendo a coisa certa.
     *
     * Por isso a semente passa pela MESMA normalização que a tela do Modo Gerente
     * aplica ao gravar. Declarar o ajuste é declarar a intenção; quem a torna
     * coerente é uma regra só, em um lugar só.
     */
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Vistorias',
            'rota' => 'retaguarda.inicio',
            'icone' => 'fiscalizacoes',
            'slug' => 'vistorias',
            'setores' => ['gestor', 'fiscal' => ['apenas_leitura' => true]],
        ]],
    ]]);

    $this->seed(PermissoesSetorSeeder::class);

    $fiscal = PermissaoSetor::where('setor', 'fiscal')->where('slug', 'vistorias')->firstOrFail();

    expect($fiscal->visivel)->toBeTrue()
        ->and($fiscal->apenas_leitura)->toBeTrue()
        ->and($fiscal->habilitado)->toBeFalse()
        ->and($fiscal->incluir)->toBeFalse()
        ->and($fiscal->excluir)->toBeFalse();
});

test('a correcao da concessao do fiscal respeita o que o gestor decidiu na tela', function () {
    /*
     * A concessão nasceu larga demais (o fiscal alterava cadastro), e o seeder é
     * `firstOrCreate` — não reescreve linha existente, de propósito, para não
     * desfazer decisão tomada na tela. A migration é o que fecha essa porta em
     * quem já rodou a semente antiga.
     *
     * Mas ela só toca a linha que AINDA ESTÁ como a semente antiga a deixou. Se
     * alguém mexeu, a linha não casa e fica intacta: migration não desfaz decisão
     * de gente. É o que este teste trava — nos dois sentidos.
     */
    $migration = require database_path('migrations/2026_08_26_120000_fiscal_apenas_consulta_permissionarios.php');

    // (a) A linha intocada, com a impressão digital da semente antiga.
    PermissaoSetor::where('setor', 'fiscal')->where('slug', 'permissionarios')->update([
        'visivel' => true,
        'habilitado' => true,
        'apenas_leitura' => false,
        'incluir' => false,
        'excluir' => false,
    ]);

    $migration->up();

    $corrigida = PermissaoSetor::where('setor', 'fiscal')->where('slug', 'permissionarios')->firstOrFail();

    expect($corrigida->habilitado)->toBeFalse()
        ->and($corrigida->apenas_leitura)->toBeTrue();

    // (b) A linha que alguém ajustou à mão — o gestor concedeu a inclusão. Não
    // casa com a impressão digital, então a migration não a toca.
    PermissaoSetor::where('setor', 'fiscal')->where('slug', 'permissionarios')->update([
        'visivel' => true,
        'habilitado' => true,
        'apenas_leitura' => false,
        'incluir' => true,
        'excluir' => false,
    ]);

    $migration->up();

    $manual = PermissaoSetor::where('setor', 'fiscal')->where('slug', 'permissionarios')->firstOrFail();

    expect($manual->habilitado)->toBeTrue()
        ->and($manual->incluir)->toBeTrue()
        ->and($manual->apenas_leitura)->toBeFalse();
});

test('slug compartilhado por varias telas aparece na matriz com o nome do CONJUNTO', function () {
    /*
     * Teste-LEI do rótulo. Seis telas de Parametrização dividem o mesmo caminho,
     * logo a mesma permissão: quem concede tem de ler o nome do conjunto. Ver ali
     * o nome de uma das seis faria parecer que as outras cinco ficaram de fora.
     *
     * A regra é decidida numa passada só — apurando ANTES quantas telas declaram
     * cada slug —, e não corrigida ao encontrar a segunda tela. Emergindo da
     * ordem de iteração, ela dependia de quem viesse depois: duas telas em SEÇÕES
     * diferentes resolviam pelo nome da última, que é justamente a que ninguém
     * tem em mente ao ler a matriz.
     */
    config()->set('retaguarda_menu.secoes', [
        [
            'rotulo' => 'Parametrização',
            'itens' => [
                ['rotulo' => 'Tipos de Infração', 'rota' => 'retaguarda.inicio', 'slug' => 'parametrizacao'],
                ['rotulo' => 'Unidades de Medida', 'rota' => 'retaguarda.inicio', 'slug' => 'parametrizacao'],
            ],
        ],
        [
            'rotulo' => 'Outra seção',
            'itens' => [
                // Mesmo slug, seção diferente: resolve pela PRIMEIRA, sempre.
                ['rotulo' => 'Motivos de Recusa', 'rota' => 'retaguarda.inicio', 'slug' => 'parametrizacao'],
                // Slug de uma tela só continua com o nome dela.
                ['rotulo' => 'Vistorias', 'rota' => 'retaguarda.inicio', 'slug' => 'vistorias'],
            ],
        ],
    ]);

    $rotulos = collect(CatalogoFuncionalidades::itens())->pluck('rotulo', 'slug');

    expect($rotulos['parametrizacao'])->toBe('Parametrização')
        ->and($rotulos['vistorias'])->toBe('Vistorias');
});

test('a semeadura e idempotente e nao desfaz o que o gerente decidiu', function () {
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Vistorias',
            'rota' => 'retaguarda.inicio',
            'icone' => 'fiscalizacoes',
            'slug' => 'vistorias',
            'setores' => ['fiscal'],
        ]],
    ]]);

    $this->seed(PermissoesSetorSeeder::class);

    PermissaoSetor::where('slug', 'vistorias')->update(['visivel' => false]);

    $this->seed(PermissoesSetorSeeder::class);

    expect(PermissaoSetor::where('slug', 'vistorias')->count())->toBe(1)
        ->and(PermissaoSetor::where('slug', 'vistorias')->first()->visivel)->toBeFalse();
});

test('todo item de menu restrito a setor declara slug — senao escapa da matriz', function () {
    /*
     * Teste-LEI sobre a configuração real do menu.
     *
     * Um item com `setores` restritos e SEM `slug` é filtrado pela config, não
     * pela matriz: a mesma decisão passaria a ter dois donos, e o Modo Gerente
     * deixaria de ser a fonte única de quem entra onde.
     */
    $escapando = [];

    foreach (config('retaguarda_menu.secoes', []) as $secao) {
        foreach ($secao['itens'] ?? [] as $item) {
            if (($item['setores'] ?? []) !== [] && ($item['slug'] ?? null) === null) {
                $escapando[] = $item['rotulo'];
            }
        }
    }

    expect($escapando)->toBe([]);
});

test('o slug declarado no menu casa com o caminho da rota', function () {
    /*
     * Teste-LEI. A guarda de leitura deduz a tela pelo CAMINHO
     * (`retaguarda/{slug}/...`), enquanto o menu e a matriz usam o `slug`
     * declarado. Se os dois discordassem, o menu ofereceria uma tela que a
     * guarda atribui a outra permissão — e o furo seria invisível.
     */
    $divergentes = [];

    foreach (config('retaguarda_menu.secoes', []) as $secao) {
        foreach ($secao['itens'] ?? [] as $item) {
            $slug = $item['slug'] ?? null;
            if ($slug === null || ! Route::has($item['rota'])) {
                continue;
            }

            $segmentos = explode('/', trim(Route::getRoutes()->getByName($item['rota'])->uri(), '/'));

            if (($segmentos[0] ?? null) !== 'retaguarda' || ($segmentos[1] ?? null) !== $slug) {
                $divergentes[] = "{$item['rotulo']}: slug={$slug}, caminho=".implode('/', $segmentos);
            }
        }
    }

    expect($divergentes)->toBe([]);
});

test('o sistema entregue BARRA de verdade — o rollout em observacao nao e o padrao', function () {
    /*
     * Teste-LEI sobre a configuração entregue.
     *
     * O modo `log` existiu para o rollout: enquanto o catálogo de telas crescia,
     * ele registrava quem SERIA barrado sem barrar ninguém. Terminada a Fase 1, a
     * chave virou. Se o padrão voltar a `log`/`off` por descuido — um merge, um
     * `.env.example` desatualizado copiado para produção —, o controle de acesso
     * deixa de existir sem que nada quebre nem apareça em tela: o pior modo de
     * falhar. Este teste é o que faz esse descuido aparecer no gate.
     *
     * A config é lida sem `env()` de propósito: o que se afirma é o DEFAULT do
     * código, não o que a máquina de quem roda o teste tem no `.env`.
     */
    $arquivo = require config_path('retaguarda.php');

    expect($arquivo['permissao_enforce'])->toBe('block');
});

test('o .env.example nao oferece um modo mais fraco do que o codigo entrega', function () {
    /*
     * Teste-LEI de fonte única. O modo tem dois lugares onde é escrito — o
     * default do código e o exemplo que todo ambiente novo copia — e informação
     * com dois donos um dia diverge. Se o exemplo ficasse em `log`, cada máquina
     * e cada deploy montado a partir dele nasceria SEM controle de acesso, com o
     * código jurando que o padrão é barrar.
     */
    $exemplo = (string) file_get_contents(base_path('.env.example'));

    expect($exemplo)->toContain('PERMISSAO_ENFORCE=block');
});
