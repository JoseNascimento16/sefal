<?php

use App\Http\Middleware\PermissaoAcao;
use App\Services\PermissaoService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Teste-LEI: nenhuma mutação da Retaguarda nasce desprotegida
|--------------------------------------------------------------------------
|
| A guarda de ações não é uma lista do que está protegido — é uma lista de
| EXCEÇÕES: toda mutação sob `/retaguarda` é atribuída à tela do seu caminho
| automaticamente. Quem foge disso (ação diferente da inferida, caminho que
| não é o slug, rota que reconhecidamente está fora do alcance do Modo
| Gerente) tem de estar DECLARADA em `config/permissao_acoes.php`, com o
| motivo escrito ao lado.
|
| Este teste é o que impede a lista de virar allowlist de novo: rota nova que
| ninguém classificou reprova aqui, e não em produção.
|
*/

/*
 * Mutações que legitimamente NÃO moram sob `/retaguarda`, com o motivo.
 *
 * O controle de acesso deduz a tela pelo caminho, então uma rota de escrita fora
 * do prefixo não pertence a tela nenhuma: ela nasce fora do alcance do Modo
 * Gerente, e a cobertura abaixo não a veria. Por isso a lista é FECHADA — e cada
 * entrada é uma rota que, por natureza, não pode depender de permissão de tela.
 *
 * A API do PWA do fiscal (Fase 4) virá sob o prefixo `api/`, com guarda própria
 * (token Sanctum + regra de fiscalização). Quando ela chegar, o prefixo entra
 * aqui como grupo — não rota por rota.
 */
const FORA_DO_ALCANCE = [
    // Autenticação: quem ainda não entrou não tem permissão a conferir; quem sai
    // não pode ser barrado de sair.
    'login.store',
    'logout',
    'password.email',
    'password.update',
    'password.confirm.store',
    // Redirecionamento da raiz para a entrada. O `Route::redirect` responde a
    // todos os verbos, e não há tela por trás.
    'home',
    // Rota que o próprio framework registra para servir o disco local em
    // desenvolvimento — não é tela do sistema.
    'storage.local.upload',
];

test('nenhuma mutacao mora fora do alcance do controle de acesso sem estar declarada', function () {
    /*
     * Teste-LEI complementar, e o mais importante dos dois: a cobertura abaixo
     * varre o que está sob `/retaguarda`, então uma rota de escrita registrada
     * FORA do prefixo nasceria desprotegida com o gate verde — o furo mais fácil
     * de abrir sem perceber.
     *
     * Aqui a pergunta se inverte: toda mutação da aplicação está sob
     * `/retaguarda` (e portanto sob o controle de acesso) ou consta da lista
     * fechada acima, com o motivo escrito?
     */
    $foraDoPrefixo = [];

    foreach (Route::getRoutes()->getRoutes() as $r) {
        if (array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) === []) {
            continue;
        }

        if (str_starts_with($r->uri(), 'retaguarda')) {
            continue;
        }

        if (in_array($r->getName(), FORA_DO_ALCANCE, true)) {
            continue;
        }

        $foraDoPrefixo[] = ($r->getName() ?? '(sem nome)').' → '.$r->uri();
    }

    expect($foraDoPrefixo)->toBe([]);
});

test('a lista de fora do alcance nao guarda rota que deixou de existir', function () {
    // Entrada morta na lista fechada é a próxima brecha: alguém renomeia a rota,
    // a antiga fica na lista e a nova passa a escapar sem ninguém notar.
    $nomes = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName())->filter()->all();

    $mortas = array_values(array_diff(FORA_DO_ALCANCE, $nomes));

    expect($mortas)->toBe([]);
});

test('toda mutacao da retaguarda e derivavel ou mapeada em permissao_acoes', function () {
    $rotas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'retaguarda')
            && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []);

    expect($rotas)->not->toBeEmpty('Nenhuma mutação encontrada: o filtro do teste parou de casar com as rotas.');

    $mapa = config('permissao_acoes');

    foreach ($rotas as $r) {
        $derivavel = PermissaoAcao::derivar($r) !== null;
        $mapeada = array_key_exists($r->getName() ?? $r->uri(), $mapa);

        expect($derivavel || $mapeada)
            ->toBeTrue("Mutação sem permissão derivável nem mapeada: {$r->uri()}");
    }
});

test('a derivacao le a acao do verbo e do nome da rota', function () {
    // A convenção que faz a rota nova nascer protegida sem ninguém declarar
    // nada: `.store` inclui, DELETE exclui, o resto opera.
    Route::post('retaguarda/modo-gerente/coisas', fn () => null)->name('teste.coisas.store');
    Route::delete('retaguarda/modo-gerente/coisas/{c}', fn () => null)->name('teste.coisas.destroy');
    Route::put('retaguarda/modo-gerente/coisas/{c}', fn () => null)->name('teste.coisas.update');
    Route::get('retaguarda/modo-gerente/coisas', fn () => null)->name('teste.coisas.index');
    Route::post('retaguarda/perfil/coisas', fn () => null)->name('teste.perfil.store');

    // Rota registrada com a aplicação já de pé não entra sozinha no índice de
    // nomes — sem isto, a busca por nome devolve nulo e o teste mente.
    Route::getRoutes()->refreshNameLookups();

    $rota = fn (string $nome) => Route::getRoutes()->getByName($nome);

    expect(PermissaoAcao::derivar($rota('teste.coisas.store')))->toBe(['slug' => 'modo-gerente', 'acao' => 'incluir'])
        ->and(PermissaoAcao::derivar($rota('teste.coisas.destroy')))->toBe(['slug' => 'modo-gerente', 'acao' => 'excluir'])
        ->and(PermissaoAcao::derivar($rota('teste.coisas.update')))->toBe(['slug' => 'modo-gerente', 'acao' => 'habilitado'])
        // Leitura é da outra guarda (`Permissao`), que checa `visivel`.
        ->and(PermissaoAcao::derivar($rota('teste.coisas.index')))->toBeNull()
        // Tela fora do catálogo não é controlável: a derivação não inventa dono.
        ->and(PermissaoAcao::derivar($rota('teste.perfil.store')))->toBeNull();
});

test('toda excecao declarada explica o motivo dela', function () {
    /*
     * Uma exceção sem justificativa escrita é uma brecha esperando para ser
     * copiada: o próximo dev vê a linha, acha que é o padrão e adiciona a dele.
     * O motivo é campo obrigatório da declaração.
     */
    $semMotivo = [];

    foreach (config('permissao_acoes', []) as $chave => $regra) {
        if (trim((string) ($regra['motivo'] ?? '')) === '') {
            $semMotivo[] = $chave;
        }
    }

    expect($semMotivo)->toBe([]);
});

test('excecao declarada aponta para tela do catalogo ou se declara livre', function () {
    // Slug digitado errado numa exceção viraria uma permissão que ninguém pode
    // conceder — a ação ficaria barrada para sempre, sem aparecer na matriz.
    $invalidas = [];

    foreach (config('permissao_acoes', []) as $chave => $regra) {
        if (($regra['livre'] ?? false) === true) {
            continue;
        }

        foreach ((array) ($regra['slugs'] ?? [$regra['slug'] ?? null]) as $slug) {
            if (! PermissaoService::ehControlavel((string) $slug)) {
                $invalidas[] = "{$chave} → {$slug}";
            }
        }
    }

    expect($invalidas)->toBe([]);
});
