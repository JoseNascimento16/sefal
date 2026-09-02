<?php

use App\Http\Controllers\Retaguarda\TelasEmPreparacaoController;
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
|    stub que abre e explica a espera.
|
| O que se testa aqui é justamente o que a pressa costuma quebrar: esconder que
| desliga por acidente, e item de menu que promete link sem endereço.
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
     * renderiza, e a permissão segue no catálogo do Modo Gerente (senão o gestor
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

test('as quatro telas em preparacao abrem e dizem o que vao ser', function () {
    $admin = User::factory()->create(['admin' => true]);

    foreach (TelasEmPreparacaoController::slugs() as $slug) {
        // O nome de rota segue o padrão das telas de verdade: é o que permite trocar
        // o andaime pela tela sem mexer no menu.
        expect(Route::has("retaguarda.{$slug}.index"))->toBeTrue();

        $this->actingAs($admin)->get("/retaguarda/{$slug}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Retaguarda/EmPreparacao')
                // A espera é DITA: a fase e a frase são o conteúdo da tela.
                ->has('fase')
                ->has('frase')
                ->has('itens'),
            );
    }
});

test('o fiscal entra no que e do trabalho dele, e nao no que e de gestao', function () {
    /*
     * A concessão inicial das quatro, registrada em voz alta: o fiscal CONSULTA o
     * que registrou em campo (Fiscalizações) e onde a cidade está agora (Mapa ao
     * Vivo); planejar operação e analisar concentração são leitura de gestão.
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

    // E o gestor entra nas quatro: o caminho é dele.
    $gestor = usuarioDaFase('gestor');

    foreach (TelasEmPreparacaoController::slugs() as $slug) {
        $this->actingAs($gestor)->get("/retaguarda/{$slug}")->assertOk();
    }
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
