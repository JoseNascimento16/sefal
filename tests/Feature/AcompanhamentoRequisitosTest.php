<?php

use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Acompanhamento de Requisitos — o que está construído bate com o escrito?
|--------------------------------------------------------------------------
|
| A tela responde uma pergunta que ninguém consegue responder de cabeça depois
| de algumas semanas: para CADA funcionalidade entregue, existe requisito
| escrito, e o comportamento de hoje ainda condiz com ele?
|
| O que se testa aqui são as leis que fazem essa resposta valer alguma coisa:
|
|   • a tela abre e mostra o que a configuração declara — nada é escrito à mão;
|   • toda linha está COMPLETA (linha pela metade mente sobre a cobertura);
|   • linha sem requisito escrito diz de ONDE veio a funcionalidade;
|   • nenhuma tela do menu fica FORA do mapa (é o esquecimento clássico);
|   • quem não tem a tela concedida é mandado de volta com o motivo;
|   • o recorte visível vira documento pelo ponto único de exportação.
|
*/

/**
 * As linhas declaradas na configuração — a fonte única da tela.
 *
 * @return list<array<string, mixed>>
 */
function linhasDoAcompanhamento(): array
{
    return array_values((array) config('acompanhamento_requisitos.telas', []));
}

/** O nome de uma linha, para a mensagem de falha apontar QUEM está errado. */
function nomeDaLinha(array $linha, int $posicao): string
{
    return (string) ($linha['tela'] ?? "linha #{$posicao}");
}

test('a tela abre para o administrador e entrega as linhas da configuracao', function () {
    $linhas = linhasDoAcompanhamento();

    expect($linhas)->not->toBeEmpty();

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.acompanhamento-de-requisitos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Retaguarda/Sistema/AcompanhamentoDeRequisitos')
            ->has('telas', count($linhas))
            ->where('telas.0.tela', $linhas[0]['tela'])
            ->where('telas.0.hu_status', $linhas[0]['hu_status'])
            ->has('totais')
            ->has('porModulo'));
});

test('toda linha esta completa — linha pela metade mente sobre a cobertura', function () {
    /*
     * Teste-LEI sobre a configuração real. Uma linha sem módulo, sem caminho no
     * menu ou com situação inventada não é "quase certa": ela entra na conta da
     * cobertura e some da leitura de quem procura o que falta.
     */
    $problemas = [];

    foreach (linhasDoAcompanhamento() as $i => $linha) {
        $nome = nomeDaLinha($linha, $i);

        foreach (['modulo', 'tela', 'origem', 'breadcrumb', 'nota'] as $campo) {
            if (trim((string) ($linha[$campo] ?? '')) === '') {
                $problemas[] = "{$nome}: {$campo} vazio";
            }
        }

        if (! in_array($linha['hu_status'] ?? null, ['sim', 'desatualizada', 'nao'], true)) {
            $problemas[] = "{$nome}: hu_status inválido";
        }

        if (! is_array($linha['hus'] ?? null)) {
            $problemas[] = "{$nome}: hus não é lista";
        }
    }

    expect($problemas)->toBe([]);
});

test('linha com requisito escrito lista a HU; linha sem ele declara a ORIGEM na nota', function () {
    /*
     * As duas pontas da mesma honestidade. Dizer "tem HU" sem apontar qual não
     * ajuda ninguém a achar o requisito; e dizer "não tem HU" sem contar de onde
     * a funcionalidade nasceu deixa a tela órfã — semanas depois ninguém sabe se
     * ela veio da spec, de um organograma ou de um pedido de corredor.
     */
    $mudas = [];

    foreach (linhasDoAcompanhamento() as $i => $linha) {
        $nome = nomeDaLinha($linha, $i);
        $status = $linha['hu_status'] ?? null;
        $hus = (array) ($linha['hus'] ?? []);
        $nota = mb_strtolower((string) ($linha['nota'] ?? ''));

        if (in_array($status, ['sim', 'desatualizada'], true) && $hus === []) {
            $mudas[] = "{$nome}: diz ter requisito escrito e não aponta nenhuma HU";
        }

        if ($status === 'nao' && $hus !== []) {
            $mudas[] = "{$nome}: diz não ter requisito escrito, mas lista HU";
        }

        if ($status === 'nao' && ! str_contains($nota, 'origem')) {
            $mudas[] = "{$nome}: sem HU e sem declarar a origem na nota";
        }
    }

    expect($mudas)->toBe([]);
});

test('toda tela do menu tem linha no acompanhamento — nada entregue fica fora do mapa', function () {
    /*
     * Teste-LEI. A lei do projeto manda a linha nascer junto com a
     * funcionalidade, e é justamente isso que se esquece: a tela entra no menu,
     * o acompanhamento continua com a contagem antiga e passa a mentir que a
     * cobertura está melhor do que é. A ligação é a ROTA — nome de tela muda,
     * rota não.
     */
    $mapeadas = array_filter(array_column(linhasDoAcompanhamento(), 'rota'));
    $foraDoMapa = [];

    // Percorre pelo caminhador do MENU, que desce nas pastas (`filhos`): a tela
    // agrupada dentro de uma pasta é entrega como qualquer outra, e varrer só o
    // primeiro nível deixaria a cobertura mentir exatamente sobre ela.
    foreach (CatalogoFuncionalidades::folhasDoMenu() as $folha) {
        $item = $folha['item'];
        $rota = $item['rota'] ?? null;

        // Item cujo destino ainda não existe já é descartado do próprio menu.
        if (! is_string($rota) || ! Route::has($rota)) {
            continue;
        }

        if (! in_array($rota, $mapeadas, true)) {
            $foraDoMapa[] = (string) ($item['rotulo'] ?? $rota);
        }
    }

    expect($foraDoMapa)->toBe([]);
});

test('os totais sao a conta das linhas, e a cobertura sai deles', function () {
    // Número escrito à mão envelhece na primeira linha nova — e um painel que
    // conta errado é pior que painel nenhum, porque ninguém desconfia dele.
    $linhas = linhasDoAcompanhamento();

    $sim = count(array_filter($linhas, fn (array $l): bool => $l['hu_status'] === 'sim'));
    $desatualizada = count(array_filter($linhas, fn (array $l): bool => $l['hu_status'] === 'desatualizada'));
    $nao = count(array_filter($linhas, fn (array $l): bool => $l['hu_status'] === 'nao'));
    $comHu = $sim + $desatualizada;
    $total = count($linhas);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.acompanhamento-de-requisitos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totais.sim', $sim)
            ->where('totais.desatualizada', $desatualizada)
            ->where('totais.nao', $nao)
            ->where('totais.comHu', $comHu)
            ->where('totais.total', $total)
            ->where('totais.percentComHu', $total > 0 ? (int) round($comHu / $total * 100) : 0)
            ->where('totais.percentAlinhada', $comHu > 0 ? (int) round($sim / $comHu * 100) : 0));
});

test('o resumo por modulo agrupa as linhas do proprio modulo', function () {
    $linhas = linhasDoAcompanhamento();
    $modulos = array_values(array_unique(array_column($linhas, 'modulo')));
    $primeiro = $modulos[0];
    $noPrimeiro = count(array_filter($linhas, fn (array $l): bool => $l['modulo'] === $primeiro));

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.acompanhamento-de-requisitos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('porModulo', count($modulos))
            ->where('porModulo.0.modulo', $primeiro)
            ->where('porModulo.0.total', $noPrimeiro));
});

test('quem nao tem a tela concedida e mandado de volta dizendo o porque', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $fiscal = User::factory()->create(['admin' => false]);
    $fiscal->setores()->attach(Setor::create(['slug' => 'fiscal', 'nome' => 'Fiscal']));

    $this->actingAs($fiscal->fresh())->get('/retaguarda/acompanhamento-de-requisitos')
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');
});

test('concedida na matriz, a tela abre para quem nao e administrador', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $gestor = User::factory()->create(['admin' => false]);
    $gestor->setores()->attach(Setor::create(['slug' => 'gestor', 'nome' => 'Gestor']));
    PermissaoSetor::create(['setor' => 'gestor', 'slug' => 'acompanhamento-de-requisitos', 'visivel' => true]);

    $this->actingAs($gestor->fresh())->get('/retaguarda/acompanhamento-de-requisitos')->assertOk();
});

test('a tela e SO LEITURA: nenhuma mutacao mora sob o caminho dela', function () {
    // O acompanhamento é o retrato do que o repositório declara. Um botão de
    // editar aqui criaria um segundo dono para a mesma informação — a linha
    // mudaria na tela e continuaria velha no arquivo que o time lê no MR.
    $mutacoes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_starts_with($r->uri(), 'retaguarda/acompanhamento-de-requisitos')
            && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->map(fn ($r): string => $r->uri())
        ->values()
        ->all();

    expect($mutacoes)->toBe([]);
});

test('o recorte visivel da tela vira documento pelo ponto unico de exportacao', function () {
    // A lei da exportação vale para esta listagem como para qualquer outra: o
    // documento sai do MESMO endpoint, com as colunas que a tela declara.
    $resposta = $this->actingAs(User::factory()->create(['admin' => true]))
        ->post(route('retaguarda.exportar-listagem'), [
            'formato' => 'pdf',
            'titulo' => 'Acompanhamento de Requisitos',
            'subtitulo' => 'Sistema › Acompanhamento de Requisitos',
            'contexto' => 'busca: "sem requisito"',
            'colunas' => [
                ['chave' => 'modulo', 'titulo' => 'Módulo'],
                ['chave' => 'tela', 'titulo' => 'Tela'],
                ['chave' => 'requisito', 'titulo' => 'Requisito'],
            ],
            'linhas' => [
                ['modulo' => 'Sistema', 'tela' => 'Logs de Erros', 'requisito' => 'Sem requisito escrito'],
            ],
        ]);

    $resposta->assertOk();

    expect((string) $resposta->getContent())->toStartWith('%PDF');
});
