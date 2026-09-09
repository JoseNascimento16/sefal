<?php

use App\Http\Middleware\HandleAppearance;
use App\Models\Setor;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| O caminho do trabalho visível antes de o conteúdo existir
|--------------------------------------------------------------------------
|
| Duas mudanças de produto, opostas e do mesmo dia:
|
|  · as seis telas de Parametrização SAÍRAM do menu sem nada ser desligado —
|    rota, tela e permissão continuam vivas;
|  · as quatro telas da fiscalização ENTRARAM no menu antes de existirem, como
|    stub que abria e explicava a espera.
|
| O que se testa aqui é justamente o que a pressa costuma quebrar: esconder que
| desliga por acidente, e item de menu que promete link sem endereço.
|
| ⚠️ O segundo caso ACABOU: as quatro telas passaram a existir (as de mapa em
| 02/09/2026; Cadastro de Operação e Fiscalizações em 09/09/2026), e o andaime foi
| removido por ter ficado sem morador. A lei correspondente mudou de forma — em vez
| de abrir as quatro do catálogo, ela varre o MENU inteiro e exige que cada tela
| tenha conteúdo próprio. O motivo está escrito nela: laço sobre catálogo vazio
| passa sem verificar nada, e é assim que uma lei morre sem ninguém notar.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

/** Os rótulos que o menu entrega a esta pessoa. */
function rotulosDoMenu(User $u): array
{
    $menu = test()->actingAs($u)->get('/retaguarda/inicio')->viewData('page')['props']['menu'];

    return collect($menu)->pluck('itens')->flatten(1)->pluck('rotulo')->all();
}

/** Um usuário de um setor só, do jeito que o sistema o cria. */
function usuarioDaFase(string $slug): User
{
    $u = User::factory()->create(['admin' => false]);
    $u->setores()->attach(Setor::where('slug', $slug)->firstOrFail());

    return $u->fresh();
}

test('as telas de Parametrizacao saem do MENU sem nada ser desligado', function () {
    /*
     * "Esconder" tem de ser só isto: tirar o atalho. O risco da pressa é esconder
     * apagando — e aí a volta atrás é refazer, não descomentar uma linha.
     *
     * As três coisas que continuam vivas: a rota responde pelo endereço, a tela
     * renderiza, e a permissão segue no catálogo do Modo Gerente (senão o chefe de setor
     * perderia a linha da matriz e, com ela, a concessão já feita).
     */
    $admin = User::factory()->create(['admin' => true]);

    $seis = [
        'Tipos de Infração',
        'Atividades do Ambulante',
        'Unidades de Medida',
        'Tipos de Operação',
        'Origens de Operação',
        'Motivos de Recusa',
    ];

    // (a) saíram do menu — e a seção inteira sumiu com elas, por não sobrar item.
    $rotulos = rotulosDoMenu($admin);

    foreach ($seis as $rotulo) {
        expect($rotulos)->not->toContain($rotulo);
    }

    $secoes = collect(
        $this->actingAs($admin)->get('/retaguarda/inicio')->viewData('page')['props']['menu']
    )->pluck('rotulo');

    expect($secoes)->not->toContain('Parametrização');

    // (b) as telas continuam abrindo pelo endereço.
    $this->actingAs($admin)->get('/retaguarda/parametrizacao/tipos-de-infracao')->assertOk();
    $this->actingAs($admin)->get('/retaguarda/parametrizacao/motivos-de-recusa')->assertOk();

    // (c) a permissão continua no catálogo — a linha da matriz não foi embora.
    expect(collect(CatalogoFuncionalidades::itens())->pluck('slug'))->toContain('parametrizacao');
});

test('lei: toda tela do menu tem CONTEUDO — nenhuma se limita a anunciar o que vai ser', function () {
    /*
     * Teste-LEI, e o sucessor do que existia aqui.
     *
     * O antigo abria as QUATRO telas do catálogo de andaime e exigia que cada uma
     * dissesse a fase e a frase da espera. Ele fez o trabalho dele: em 02/09 as
     * duas de mapa passaram a existir, e em 09/09 o Cadastro de Operação e as
     * Fiscalizações — e o catálogo ficou vazio. Andaime sem morador é a próxima
     * armadilha, e pior que a primeira: um laço sobre lista vazia PASSA sem
     * verificar nada, e ninguém percebe que a lei morreu.
     *
     * Então a lei mudou de forma e ficou mais larga: ela varre o MENU inteiro
     * (descendo nas pastas) e exige que cada tela abra com conteúdo próprio. É a
     * mesma decisão de produto de antes — "não prometa link e não tenha endereço"
     * —, agora cobrada em toda tela, e não só nas quatro que alguém listou.
     *
     * Como se prova que a tela não é um anúncio: o componente que ela renderiza não
     * é o do andaime, e o andaime não existe mais em disco. As duas coisas, porque
     * a primeira sozinha não impede alguém de recriar o andaime com outro nome, e a
     * segunda sozinha não impede uma tela nova de nascer só com um cartão de espera.
     */
    $admin = User::factory()->create(['admin' => true]);

    // O andaime foi REMOVIDO junto com o último morador (o `EmPreparacao` e o
    // controller que o alimentava). Recriá-lo é decisão de produto, e tem de ser
    // consciente: este teste é o lugar em que a conversa acontece.
    expect(file_exists(app_path('Http/Controllers/Retaguarda/TelasEmPreparacaoController.php')))
        ->toBeFalse('o andaime das telas em preparação voltou — se é de propósito, atualize esta lei')
        ->and(file_exists(resource_path('js/pages/Retaguarda/EmPreparacao.tsx')))
        ->toBeFalse('a tela de espera voltou — se é de propósito, atualize esta lei');

    $folhas = CatalogoFuncionalidades::folhasDoMenu();

    // Guarda contra o teste tautológico: sem itens, o laço abaixo passaria sem
    // conferir nada — que é exatamente como a lei anterior morreu.
    expect($folhas)->not->toBeEmpty();

    $anunciam = [];
    $quebram = [];
    $conferidas = 0;

    foreach ($folhas as $folha) {
        $item = $folha['item'];
        $rota = $item['rota'] ?? null;
        $nome = (string) ($item['rotulo'] ?? $rota);

        if (! is_string($rota) || ! Route::has($rota)) {
            continue;
        }

        $resposta = $this->actingAs($admin)->get(route($rota, absolute: false));

        // Erro de servidor é falha de outra natureza, e vale ser acusada aqui: um
        // item de menu que estoura é pior que um que anuncia a espera.
        if ($resposta->getStatusCode() >= 500) {
            $quebram[] = "{$nome}: HTTP {$resposta->getStatusCode()}";

            continue;
        }

        /*
         * Item de menu que ABRE PAINEL sobre a tela atual (hoje o Modo Gerente)
         * responde com redirecionamento, e não com página própria: não há
         * componente a conferir, e exigir um faria a lei acusar um desenho que é de
         * propósito.
         */
        if (! $resposta->isOk()) {
            continue;
        }

        $conferidas++;
        $componente = $resposta->viewData('page')['props']['component'] ?? null;

        // O componente vem do próprio payload do Inertia; tela que renderize o
        // andaime (ou qualquer coisa chamada "EmPreparacao") é acusada por nome.
        if (is_string($componente) && str_contains($componente, 'EmPreparacao')) {
            $anunciam[] = $nome;
        }
    }

    // Segunda guarda contra a lei vazia: se nada foi conferido, o laço acima
    // "passou" sem olhar tela nenhuma.
    expect($conferidas)->toBeGreaterThan(0)
        ->and($quebram)->toBe([], 'Tela do menu que estoura ao abrir.
')
        ->and($anunciam)->toBe([], 'Tela do menu que só anuncia o que vai ser.
');
});

test('o fiscal entra no que e do trabalho dele, e nao no que e de gestao', function () {
    /*
     * A concessão inicial do caminho da fiscalização, registrada em voz alta: o
     * fiscal CONSULTA o que registrou em campo (Fiscalizações) e onde a cidade está
     * agora (Mapa ao Vivo); planejar operação e analisar concentração são leitura de
     * gestão.
     *
     * ⚠️ A entrada dele em Fiscalizações é decisão do dono (09/09/2026) COM
     * ressalva: ele é usuário do APLICATIVO, e o acesso à Retaguarda existe por
     * completude. O que ele NÃO pode é decidir sobre o retorno — isso é provado em
     * `FiscalizacoesTest`, e não aqui: aqui a régua é quem ENTRA.
     *
     * Barrado não é 403 seco: volta para a tela inicial com o motivo.
     */
    config(['retaguarda.permissao_enforce' => 'block']);

    $fiscal = usuarioDaFase('fiscal');

    $this->actingAs($fiscal)->get('/retaguarda/fiscalizacoes')->assertOk();
    $this->actingAs($fiscal)->get('/retaguarda/mapa')->assertOk();

    foreach (['/retaguarda/operacoes', '/retaguarda/mapa-de-calor'] as $fechada) {
        $this->actingAs($fiscal)->get($fechada)
            ->assertRedirect('/retaguarda/inicio')
            ->assertSessionHas('flash.erro');
    }

    // E o chefe de setor entra nas quatro: o caminho é dele.
    $chefe = usuarioDaFase('chefe-de-setor');

    foreach (['fiscalizacoes', 'operacoes', 'mapa', 'mapa-de-calor'] as $slug) {
        $this->actingAs($chefe)->get("/retaguarda/{$slug}")->assertOk();
    }
});

test('o endereco aposentado do Retorno de Campo redireciona para Fiscalizacoes', function () {
    /*
     * A tela virou a aba "A decidir" de Fiscalizações. Quem trabalhava nela tem o
     * endereço no favorito, no e-mail de aviso, na conversa de ontem — devolver
     * "não encontrado" a esse link transformaria uma melhoria em falha, e a pessoa
     * concluiria que perdeu a fila dela.
     *
     * É 301 (permanente) porque o endereço não volta.
     */
    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get('/retaguarda/retorno-de-campo')
        ->assertStatus(301)
        ->assertRedirect('/retaguarda/fiscalizacoes');

    // E o slug antigo saiu do CATÁLOGO: linha morta na matriz do Modo Gerente é
    // linha que alguém tenta conceder para uma tela que não existe.
    expect(collect(CatalogoFuncionalidades::itens())->pluck('slug'))
        ->not->toContain('retorno-de-campo')
        ->and(collect(CatalogoFuncionalidades::itens())->pluck('slug'))
        ->toContain('fiscalizacoes');
});

test('lei: o tema PADRAO e o claro nos quatro lugares que o decidem', function () {
    /*
     * Teste-LEI de fonte única. O tema de quem nunca escolheu é decidido em quatro
     * pontos — o middleware do cookie, a classe do <html>, o script de pré-pintura e
     * o hook do cliente. Se um discordar, a página nasce de um tema e passa para o
     * outro na hidratação: o lampejo que a pré-pintura existe justamente para
     * evitar.
     *
     * Antes o padrão era `system`, e o efeito era quem tem o sistema operacional no
     * escuro abrir a Retaguarda em navy sem nunca ter pedido.
     */
    $blade = (string) file_get_contents(resource_path('views/app.blade.php'));
    $hook = (string) file_get_contents(resource_path('js/hooks/use-appearance.tsx'));

    expect((new HandleAppearance)->handle(
        request(),
        fn () => new Response,
    ))->toBeInstanceOf(Response::class)
        ->and(view()->shared('appearance'))->toBe('light');

    expect($blade)->toContain("\$appearance ?? 'light'")
        ->and($blade)->toContain('$appearance ?? "light"')
        ->and($hook)->toContain("const PADRAO: Appearance = 'light';")
        // E o escuro segue disponível como ESCOLHA: o padrão mudou, a opção não saiu.
        ->and((string) file_get_contents(resource_path('js/components/appearance-tabs.tsx')))
        ->toContain("valor: 'system'");
});

test('quem escolheu tema mantem a escolha', function () {
    /*
     * O padrão vale para a AUSÊNCIA de escolha. Cookie gravado manda — senão a
     * mudança de padrão passaria por cima de quem já decidiu.
     *
     * `withUnencryptedCookie` porque é assim que o cookie chega de verdade: quem o
     * grava é o JavaScript do cliente (`setCookie` no `use-appearance`), em texto
     * claro, e `appearance` está fora da criptografia de cookies justamente para o
     * script de pré-pintura poder lê-lo antes de qualquer coisa carregar.
     */
    $this->withUnencryptedCookie('appearance', 'dark')->get('/login')->assertOk();

    expect(view()->shared('appearance'))->toBe('dark');
});
