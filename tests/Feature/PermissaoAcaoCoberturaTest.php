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
