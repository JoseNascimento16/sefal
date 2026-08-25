<?php

use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
|--------------------------------------------------------------------------
| Relatórios — catálogo, emissão e as guardas do documento
|--------------------------------------------------------------------------
|
| Relatório é documento OFICIAL: sai do sistema, é arquivado e lido meses
| depois. O que se testa aqui é o que faz dele confiável — o recorte impresso, a
| data em BR, o período coerente e o filtro que de fato filtra —, além de a tela
| descrever o catálogo sem conhecer relatório nenhum por dentro.
|
*/

beforeEach(fn () => $this->seed());

function usuarioComSetor(string $slug): User
{
    $u = User::factory()->create(['admin' => false]);
    $u->setores()->attach(Setor::where('slug', $slug)->firstOrFail());

    return $u->fresh();
}

test('a tela lista o catalogo com filtros e modos de cada relatorio', function () {
    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.relatorios.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->component('Retaguarda/Sistema/Relatorios')
                ->has('relatorios', 1)
                ->where('relatorios.0.chave', 'usuarios-do-sistema')
                ->where('relatorios.0.formatos', ['pdf', 'xlsx', 'docx'])
                // A tela desenha o formulário a partir daqui: sem os filtros
                // descritos, ela teria de conhecer cada relatório por dentro.
                ->has('relatorios.0.filtros', 4);
        });
});

test('emite o relatorio nos tres formatos', function () {
    $esperado = ['pdf' => 'application/pdf', 'xlsx' => 'spreadsheetml', 'docx' => 'wordprocessingml'];

    foreach ($esperado as $formato => $tipo) {
        $r = $this->actingAs(User::factory()->create(['admin' => true]))->post(route('retaguarda.relatorios.gerar'), [
            'chave' => 'usuarios-do-sistema',
            'formato' => $formato,
            'modo' => 'analitico',
            'filtros' => [],
        ]);

        $r->assertOk();
        expect((string) $r->headers->get('content-type'))->toContain($tipo);
        expect((string) $r->headers->get('content-disposition'))->toContain(".{$formato}");
    }
});

test('o documento traz o recorte, a data em BR e as contas do periodo', function () {
    $admin = User::factory()->create(['name' => 'Ana Admin', 'admin' => true]);
    usuarioComSetor('fiscal');

    $r = $this->actingAs($admin)->post(route('retaguarda.relatorios.gerar'), [
        'chave' => 'usuarios-do-sistema',
        'formato' => 'xlsx',
        'modo' => 'analitico',
        'filtros' => ['setor' => 'fiscal'],
    ]);

    $r->assertOk();
    $texto = textoDaAba(
        IOFactory::createReader('Xlsx')
            ->load(arquivoTemporarioXlsx($r->streamedContent()))
            ->getActiveSheet(),
    );

    expect($texto)
        ->toContain('USUÁRIOS DO SISTEMA')
        ->toContain('Setor: Fiscal')      // o recorte por escrito
        ->toContain('1 conta(s)')
        ->toContain('Fiscal')
        // Data SEMPRE em BR: forma ISO em documento gerado é inaceitável.
        ->toContain(now()->format('d/m/Y'))
        ->not->toContain(now()->format('Y-m-d'));

    // O filtro filtra de verdade: o administrador não é do setor fiscal.
    expect($texto)->not->toContain('Ana Admin');
});

test('periodo invertido nao gera documento vazio, e a recusa diz o porque', function () {
    // Sem esta guarda o documento sai VAZIO, e quem pediu lê "não houve
    // movimento" em vez de "você trocou as datas".
    $this->actingAs(User::factory()->create(['admin' => true]))
        ->post(route('retaguarda.relatorios.gerar'), [
            'chave' => 'usuarios-do-sistema',
            'formato' => 'pdf',
            'filtros' => ['data_inicial' => '2026-08-20', 'data_final' => '2026-08-01'],
        ])
        ->assertSessionHasErrors('filtros');

    expect(session('errors')->first('filtros'))->toContain('não pode ser posterior');
});

test('a recusa chega em JSON para quem baixa pela tela', function () {
    /*
     * A tela baixa por `fetch`, e não pelo Inertia: um redirecionamento seria
     * SEGUIDO, e o navegador acabaria salvando a página de erro como se fosse o
     * documento. Então a recusa tem de chegar como JSON com a mensagem.
     */
    $r = $this->actingAs(User::factory()->create(['admin' => true]))
        ->postJson(route('retaguarda.relatorios.gerar'), [
            'chave' => 'usuarios-do-sistema',
            'formato' => 'pdf',
            'filtros' => ['data_inicial' => '2026-08-20', 'data_final' => '2026-08-01'],
        ]);

    $r->assertStatus(422);
    expect((string) $r->json('message'))->toContain('não pode ser posterior');
});

test('relatorio inexistente responde dizendo o motivo', function () {
    $r = $this->actingAs(User::factory()->create(['admin' => true]))
        ->postJson(route('retaguarda.relatorios.gerar'), ['chave' => 'nao-existe', 'formato' => 'pdf']);

    $r->assertStatus(422);
    expect((string) $r->json('message'))->toContain('não está disponível');
});

test('o modo gerencial troca a relacao nominal pelo quadro de totais', function () {
    $admin = User::factory()->create(['name' => 'Ana Admin', 'admin' => true]);

    $r = $this->actingAs($admin)->post(route('retaguarda.relatorios.gerar'), [
        'chave' => 'usuarios-do-sistema',
        'formato' => 'xlsx',
        'modo' => 'gerencial',
        'filtros' => [],
    ]);

    $texto = textoDaAba(
        IOFactory::createReader('Xlsx')
            ->load(arquivoTemporarioXlsx($r->streamedContent()))
            ->getActiveSheet(),
    );

    // No gerencial a pergunta é "quanto": a lista nominal afogaria o número.
    expect($texto)->toContain('Contas por setor')->not->toContain('Ana Admin');
});

test('exige autenticacao para abrir e para emitir', function () {
    $this->get(route('retaguarda.relatorios.index'))->assertRedirect(route('login'));
    $this->post(route('retaguarda.relatorios.gerar'), ['chave' => 'usuarios-do-sistema', 'formato' => 'pdf'])
        ->assertRedirect(route('login'));
});

test('quem so consulta a tela ainda consegue emitir o documento', function () {
    /*
     * Emitir é LEITURA. Se a ação fosse tratada como "opera", um setor com
     * "Vê" + "Só consulta" abriria a tela de Relatórios e seria recusado no único
     * botão que ela tem — tela que abre para não fazer nada.
     */
    config(['retaguarda.permissao_enforce' => 'block']);

    PermissaoSetor::updateOrCreate(
        ['setor' => 'gestor', 'slug' => 'relatorios'],
        ['visivel' => true, 'habilitado' => false, 'apenas_leitura' => true, 'incluir' => false, 'excluir' => false],
    );

    $gestor = usuarioComSetor('gestor');

    $this->actingAs($gestor)->get(route('retaguarda.relatorios.index'))->assertOk();

    $this->actingAs($gestor)->post(route('retaguarda.relatorios.gerar'), [
        'chave' => 'usuarios-do-sistema',
        'formato' => 'pdf',
        'filtros' => [],
    ])->assertOk();
});

test('quem nao tem a tela nao emite o documento, e sabe por que', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    // O fiscal trabalha em rua pelo aplicativo: a tela não é semeada para ele.
    $this->actingAs(usuarioComSetor('fiscal'))
        ->from(route('retaguarda.inicio'))
        ->post(route('retaguarda.relatorios.gerar'), [
            'chave' => 'usuarios-do-sistema',
            'formato' => 'pdf',
            'filtros' => [],
        ])
        ->assertRedirect(route('retaguarda.inicio'))
        ->assertSessionHas('flash.erro');
});

test('a tela de relatorios entra no controle de acesso', function () {
    // Documento oficial com dados de contas não é tela de leitura livre: ela tem
    // de estar no catálogo do Modo Gerente para poder ser concedida ou tirada.
    expect(CatalogoFuncionalidades::contem('relatorios'))->toBeTrue();

    // E o caminho da rota tem de ser o slug — é dele que as guardas deduzem a
    // tela a que cada endereço pertence.
    expect(route('retaguarda.relatorios.index', absolute: false))->toStartWith('/retaguarda/relatorios');
});
