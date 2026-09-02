<?php

use App\Models\Ambulante;
use App\Models\Setor;
use App\Models\User;
use App\Support\ContadoresDoMenu;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| A casca editorial: o que o servidor entrega ao menu
|--------------------------------------------------------------------------
|
| O visual da casca (a curva do painel, a doca, o cabeçalho editorial) é
| conferência de tela, não asserção de teste de servidor. O que se testa aqui é o
| CONTRATO que a casca consome: o número vivo de cada item e o rótulo curto da
| doca — e, principalmente, que nenhum dos dois consegue derrubar o menu.
|
| Menu é navegação: ele tem de existir justamente quando algo está errado.
|
*/

beforeEach(fn () => $this->seed(SetoresSeeder::class));

/**
 * O menu como o servidor o entrega a esta pessoa — ACHATADO, com os itens de
 * dentro das pastas no mesmo saco.
 *
 * O achatamento importa: um item pode ser PASTA e a tela morar no filho. Sem
 * descer, os testes-lei desta suíte (rótulo curto, contador) deixariam de cobrir
 * justamente as telas agrupadas — que são as que ninguém lembra de conferir.
 */
function itensDoMenu(User $u): array
{
    $menu = test()->actingAs($u)->get('/retaguarda/inicio')->viewData('page')['props']['menu'];

    return collect($menu)
        ->pluck('itens')
        ->flatten(1)
        ->flatMap(fn (array $item): array => [$item, ...$item['filhos']])
        ->keyBy('rotulo')
        ->all();
}

/** Um gestor, que enxerga o cadastro de ambulante. */
function gestorDaCasca(): User
{
    $u = User::factory()->create(['admin' => false]);
    $u->setores()->attach(Setor::where('slug', 'gestor')->firstOrFail());

    return $u->fresh();
}

test('o item que declara contador chega com o numero e o tom', function () {
    $this->seed(PermissoesSetorSeeder::class);

    Ambulante::factory()->count(3)->create();

    $itens = itensDoMenu(gestorDaCasca());

    expect($itens['Ambulantes']['contador'])->toBe(['valor' => 3, 'tom' => 'neutro']);
});

test('item sem contador declarado nao inventa numero', function () {
    // YAGNI dito por teste: contador custa uma contagem por requisição, então só
    // existe onde alguém declarou que o número muda a decisão de quem olha.
    $itens = itensDoMenu(User::factory()->create(['admin' => true]));

    expect($itens['Início']['contador'])->toBeNull()
        ->and($itens['Relatórios']['contador'])->toBeNull();
});

test('o contador de FILA nao aparece em zero, e aparece quando ha o que fazer', function () {
    /*
     * Alerta é cobrança: um "0" laranja chama atenção para dizer que não há nada a
     * fazer — o pior uso possível de uma cor de alerta. Neutro é diferente: zero
     * cadastrados é um tamanho honesto, e aparece.
     */
    expect(ContadoresDoMenu::para('ambulantes-em-quarentena'))->toBeNull()
        ->and(ContadoresDoMenu::para('ambulantes'))->toBe(['valor' => 0, 'tom' => 'neutro']);

    Ambulante::factory()->create(['situacao' => Ambulante::SITUACAO_CAMPO]);

    expect(ContadoresDoMenu::para('ambulantes-em-quarentena'))
        ->toBe(['valor' => 1, 'tom' => 'alerta']);
});

test('contador desconhecido ou que falha nao derruba o menu — vem sem numero', function () {
    // Chave inventada (config errada) e contagem que estoura têm o MESMO desfecho:
    // o item aparece sem número. Menu que quebra por causa de um selo é pior que
    // menu sem selo.
    expect(ContadoresDoMenu::para('nao-existe-esse-contador'))->toBeNull();

    // A contagem estoura de verdade: a tabela é derrubada debaixo dela.
    Schema::drop('ambulantes');

    expect(ContadoresDoMenu::para('ambulantes'))->toBeNull();

    // E o menu continua de pé, com o item lá — só sem o número.
    $itens = itensDoMenu(User::factory()->create(['admin' => true]));

    expect($itens)->toHaveKey('Ambulantes')
        ->and($itens['Ambulantes']['contador'])->toBeNull();
});

test('todo item do menu chega com rotulo curto para a doca', function () {
    /*
     * Teste-LEI: a doca desenha `curto`, e um item sem ele apareceria com o rótulo
     * vazio — um ícone sem nome no menu. O servidor garante o valor, caindo na
     * primeira palavra do rótulo quando a config não declara.
     */
    $itens = itensDoMenu(User::factory()->create(['admin' => true]));

    $semCurto = collect($itens)->filter(fn (array $i): bool => trim((string) $i['curto']) === '');

    expect($semCurto->keys()->all())->toBe([])
        // Declarado na config quando a primeira palavra não serve — "Cadastro de
        // Operação" e "Mapa de Calor" viriam ambos como uma palavra ambígua.
        ->and($itens['Cadastro de Operação']['curto'])->toBe('OPERAÇÃO')
        ->and($itens['Mapa de Calor']['curto'])->toBe('CALOR')
        // …e deduzido quando serve.
        ->and($itens['Relatórios']['curto'])->toBe('RELATÓRIOS');
});
