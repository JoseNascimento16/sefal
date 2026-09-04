<?php

use App\Models\Setor;
use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
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

test('o menu traz o inicio, o perfil e o trabalho da fiscalizacao', function () {
    /*
     * O que se afirma aqui é a MONTAGEM do menu — as seções e os itens que o
     * servidor entrega —, não o controle de acesso. Por isso o usuário é um
     * fiscal COM a concessão semeada: com o bloqueio ligado (o padrão desde o
     * fim da Fase 1), quem não tem linha na matriz não recebe item controlado
     * nenhum, e a asserção falharia por falta de permissão em vez de por menu
     * mal montado — trocando o defeito que este teste existe para pegar.
     */
    $this->seed(PermissoesSetorSeeder::class);

    $fiscal = User::factory()->create();
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());

    $this->actingAs($fiscal)->get('/retaguarda/inicio')
        ->assertInertia(function ($p) {
            $menu = collect($p->toArray()['props']['menu']);

            $rotulos = $menu->pluck('itens')->flatten(1)->pluck('rotulo');
            expect($rotulos)
                ->toContain('Início')
                ->toContain('Meu Perfil')
                ->toContain('Ambulantes');

            $fiscalizacao = $menu->firstWhere('rotulo', 'Fiscalização');
            expect($fiscalizacao)->not->toBeNull();
            expect(collect($fiscalizacao['itens'])->pluck('rotulo'))->toContain('Ambulantes');
        });
});

test('a secao que fica sem item visivel mostra o recado, em vez de sumir', function () {
    /*
     * O `vazio` da seção não é texto de placeholder que morre quando a primeira
     * tela nasce: ele é o que aparece para quem NÃO tem nenhuma das telas
     * daquela seção concedida. Some sem recado, a pessoa não sabe se a seção
     * não existe ou se ela é que não pode — e a lei do projeto é que ninguém é
     * barrado em silêncio.
     */
    config()->set('retaguarda.permissao_enforce', 'block');

    $semNada = User::factory()->create();

    $this->actingAs($semNada)->get('/retaguarda/inicio')
        ->assertInertia(function ($p) {
            $fiscalizacao = collect($p->toArray()['props']['menu'])
                ->firstWhere('rotulo', 'Fiscalização');

            expect($fiscalizacao)->not->toBeNull();
            expect($fiscalizacao['itens'])->toBe([]);
            expect($fiscalizacao['vazio'])->not->toBeNull();
        });
});

test('item de menu restrito a um setor nao aparece para quem nao pertence a ele', function () {
    // O menu é montado no servidor: se a decisão de quem vê o quê morasse na
    // tela, o item apenas ficaria escondido — mas continuaria viajando.
    //
    // Quem responde "este item aparece?" é o controle de acesso (Modo Gerente),
    // e é por isso que o item declara `slug`: a concessão é a linha da matriz,
    // semeada a partir de `setores`. A lista sozinha não filtra nada — se ela
    // também filtrasse, a mesma decisão teria dois donos.
    //
    // O modo `block` é explícito porque o menu obedece o rollout: fora dele o
    // item continua à vista de propósito (ver ModoGerenteTest).
    config()->set('retaguarda.permissao_enforce', 'block');
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Só do fiscal',
            'rota' => 'retaguarda.inicio',
            'icone' => 'fiscalizacoes',
            'slug' => 'so-do-fiscal',
            'setores' => ['fiscal'],
        ]],
    ]]);

    $this->seed(PermissoesSetorSeeder::class);

    $chefe = User::factory()->create();
    $chefe->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    $this->actingAs($chefe)->get('/retaguarda/inicio')
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
    // E sem precisar de concessão nenhuma: o acesso total do administrador é
    // desvio no código, não linha de matriz que alguém possa desmarcar. Com o
    // bloqueio ligado, para a asserção valer algo (fora dele o item apareceria
    // para qualquer um).
    config()->set('retaguarda.permissao_enforce', 'block');
    config()->set('retaguarda_menu.secoes', [[
        'rotulo' => 'Fiscalização',
        'itens' => [[
            'rotulo' => 'Só do fiscal',
            'rota' => 'retaguarda.inicio',
            'icone' => 'fiscalizacoes',
            'slug' => 'so-do-fiscal',
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

    // Autenticado, porque a saída oferecida depende de quem está lendo: para
    // quem está dentro é "Ir para o início"; para o visitante, "Entrar no
    // sistema" (ver `ObservabilidadeTest`).
    $resposta = $this->actingAs(User::factory()->create())->get('/rota-que-nao-existe');

    $resposta->assertNotFound()
        ->assertSee('Página não encontrada')
        ->assertSee('Ir para o início')
        ->assertDontSee('Stack trace', false);
});

test('lei: a marca do sistema no exemplo de ambiente e SEFAL', function () {
    /*
     * Teste-LEI de fonte única. O nome do produto aparece na aba do navegador e
     * vem de `APP_NAME`. Ele tem dois lugares onde é escrito — o default do
     * código (`SEFAL`) e o exemplo que todo ambiente novo copia —, e informação
     * com dois donos um dia diverge.
     *
     * A conferência é sobre o EXEMPLO, e não sobre o `.env` de quem roda o teste:
     * a máquina de cada um é dela. (Foi ali que a divergência apareceu — uma
     * máquina com `APP_NAME` antigo fazia a aba anunciar um nome de produto que
     * nenhuma tela usa. O código e o exemplo já estavam certos.)
     */
    expect((string) file_get_contents(base_path('.env.example')))->toContain('APP_NAME="SEFAL"');
});

test('nenhuma classe de tela depende de espaco dentro de texto de classe', function () {
    /*
     * Teste de FONTE, e não de comportamento, porque o gate não executa o
     * JavaScript — e a armadilha aqui é silenciosa.
     *
     * O formatador do projeto (prettier com o plugin de classes do Tailwind)
     * NORMALIZA o conteúdo de qualquer texto de classe, e nisso ele come o espaço
     * de início. Quem escreve
     *
     *     className={`rt-menu-item${ativo ? ' ativo' : ''}`}
     *
     * vê o formatador transformar em `'ativo'` sem o espaço, e a classe passa a
     * sair grudada ("rt-menu-itemativo"): o item ativo perde o destaque, o menu
     * some no celular, e nada quebra nem acusa. Aconteceu exatamente assim nesta
     * entrega.
     *
     * A forma que resiste é `cn('rt-menu-item', ativo && 'ativo')`, que junta as
     * classes com espaço fora do texto.
     */
    $suspeitos = [];

    $arquivos = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($arquivos as $arquivo) {
        if ($arquivo->getExtension() !== 'tsx') {
            continue;
        }

        $conteudo = file_get_contents($arquivo->getPathname());

        // Texto de classe cujo trecho condicional começa (ou termina) em espaço
        // dentro das aspas: é o padrão que o formatador desmonta.
        if (preg_match('/className=\{`[^`]*\$\{[^}]*?\'(?: [^\']*|[^\']* )\'/', $conteudo)) {
            $suspeitos[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $arquivo->getPathname());
        }
    }

    expect($suspeitos)->toBe([]);
});
