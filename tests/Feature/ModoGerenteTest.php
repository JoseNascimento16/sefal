<?php

use App\Models\PermissaoLog;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use App\Services\PermissaoService;
use Database\Seeders\PermissoesSetorSeeder;
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

    expect(PermissaoSetor::where('setor', 'fiscal')->exists())->toBeFalse();
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

    expect(PermissaoSetor::count())->toBe(0);
});

test('quem recebe a concessao abre a tela de verdade', function () {
    // O outro lado da moeda: conceder na matriz TEM de abrir a porta, senão a
    // tela do Modo Gerente seria decoração.
    config(['retaguarda.permissao_enforce' => 'block']);

    $gestor = usuarioDoSetor('gestor');

    PermissaoSetor::create([
        'setor' => 'gestor',
        'slug' => 'modo-gerente',
        'visivel' => true,
        'habilitado' => true,
    ]);

    $this->actingAs($gestor)->get('/retaguarda/modo-gerente')->assertOk();

    $this->actingAs($gestor)
        ->post(route('retaguarda.modo-gerente.salvar'), [
            'slug' => 'modo-gerente',
            'matriz' => [['setor' => 'fiscal', 'visivel' => true, 'habilitado' => true]],
        ])
        ->assertSessionHas('flash.sucesso');

    expect(PermissaoSetor::where('setor', 'fiscal')->where('slug', 'modo-gerente')->exists())->toBeTrue();
});

test('o administrador abre a tela e recebe a matriz completa', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $admin = User::factory()->create(['admin' => true]);

    $this->actingAs($admin)->get('/retaguarda/modo-gerente')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Retaguarda/Sistema/ModoGerente')
            ->has('setores', 3)
            ->has('funcionalidades')
            ->has('matriz')
            ->where('enforce', 'block'),
        );
});

test('salvar a matriz grava a concessao, normaliza as regras e deixa rastro', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $admin = User::factory()->create(['admin' => true, 'name' => 'Ana Gestora']);

    $this->actingAs($admin)
        ->from(route('retaguarda.modo-gerente.index'))
        ->post(route('retaguarda.modo-gerente.salvar'), [
            'slug' => 'modo-gerente',
            'matriz' => [
                // Apenas leitura manda: incluir/excluir caem, mesmo pedidos.
                ['setor' => 'gestor', 'visivel' => true, 'habilitado' => true, 'apenas_leitura' => true, 'incluir' => true, 'excluir' => true],
                // Sem "visível" nada mais vale: é o pré-requisito das demais.
                ['setor' => 'fiscal', 'visivel' => false, 'habilitado' => true, 'incluir' => true],
            ],
        ])
        ->assertRedirect(route('retaguarda.modo-gerente.index'))
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

test('item de menu que o usuario nao pode ver nao aparece no menu', function () {
    // Menu e guarda de acesso leem a MESMA regra: se cada um tivesse a sua, um
    // dia o menu ofereceria uma tela que a guarda barra.
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
