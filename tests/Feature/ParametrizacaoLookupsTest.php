<?php

use App\Models\AtividadeAmbulante;
use App\Models\MotivoRecusa;
use App\Models\OrigemOperacao;
use App\Models\ParametroFiscalizacao;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\TipoInfracao;
use App\Models\TipoOperacao;
use App\Models\UnidadeMedida;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;
use Database\Seeders\ParametrizacaoFiscalizacaoSeeder;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Parametrização — as listas que o resto do sistema escolhe
|--------------------------------------------------------------------------
|
| Seis telas do MESMO feitio (tipo de infração, atividade do ambulante,
| unidade de medida, tipo de operação, origem da operação e motivo de recusa)
| mais os parâmetros numéricos da fiscalização. Nenhuma delas é interessante
| sozinha; o que se testa aqui é o que faz as sete valerem alguma coisa:
|
|   • cada tela grava, altera e exclui de verdade, na SUA tabela;
|   • nenhuma nasce vazia — a operação começa com valores realistas;
|   • nome é obrigatório e não se repete (nem trocando a caixa das letras);
|   • o que foi inativado sai da lista que as outras telas oferecem;
|   • quem não tem a parametrização concedida é mandado de volta com o motivo;
|   • o recorte visível vira documento pelo ponto único de exportação;
|   • os parâmetros são lidos por código e NÃO têm tela nesta entrega.
|
| A régua é a spec de design (Fase 1) — não há HU escrita neste projeto.
|
*/

beforeEach(function () {
    $this->admin = User::factory()->create(['admin' => true]);
    $this->seed();
});

/**
 * As seis telas de lookup: caminho na URL, model e um valor para cada campo
 * além do nome.
 *
 * Existe para os testes cobrirem as SEIS com a mesma régua. Seis blocos
 * copiados divergiriam no primeiro ajuste — e a tela esquecida seria
 * justamente a que ninguém abriu.
 *
 * @return array<string, array{modelo: class-string, extras: array<string, string>}>
 */
function lookupsDeParametrizacao(): array
{
    return [
        'tipos-de-infracao' => [
            'modelo' => TipoInfracao::class,
            'extras' => ['descricao' => 'Descrição de apoio ao fiscal.'],
        ],
        'atividades-do-ambulante' => [
            'modelo' => AtividadeAmbulante::class,
            'extras' => [],
        ],
        'unidades-de-medida' => [
            'modelo' => UnidadeMedida::class,
            'extras' => ['sigla' => 'cx'],
        ],
        'tipos-de-operacao' => [
            'modelo' => TipoOperacao::class,
            'extras' => [],
        ],
        'origens-de-operacao' => [
            'modelo' => OrigemOperacao::class,
            'extras' => [],
        ],
        'motivos-de-recusa' => [
            'modelo' => MotivoRecusa::class,
            'extras' => [],
        ],
    ];
}

/** O endereço de uma tela de parametrização. */
function caminhoDoLookup(string $tela, ?int $id = null): string
{
    return "/retaguarda/parametrizacao/{$tela}".($id === null ? '' : "/{$id}");
}

test('crud completo de tipo de infracao', function () {
    // O ciclo inteiro na tela mais completa (é a única com descrição): incluir,
    // alterar e excluir. Se qualquer ponta falhar, a tela é decoração.
    $this->actingAs($this->admin)
        ->post(caminhoDoLookup('tipos-de-infracao'), [
            'nome' => 'Som acima do permitido',
            'descricao' => 'Aparelho de som em volume acima do autorizado para a via.',
            'ativo' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    $tipo = TipoInfracao::where('nome', 'Som acima do permitido')->firstOrFail();

    expect($tipo->ativo)->toBeTrue()
        ->and($tipo->descricao)->toContain('volume acima do autorizado');

    $this->actingAs($this->admin)
        ->put(caminhoDoLookup('tipos-de-infracao', $tipo->id), [
            'nome' => 'Som acima do limite',
            'descricao' => null,
            'ativo' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    expect($tipo->fresh()->nome)->toBe('Som acima do limite')
        ->and($tipo->fresh()->ativo)->toBeFalse()
        ->and($tipo->fresh()->descricao)->toBeNull();

    $this->actingAs($this->admin)
        ->delete(caminhoDoLookup('tipos-de-infracao', $tipo->id))
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    expect(TipoInfracao::find($tipo->id))->toBeNull();
});

test('cada tela de parametrizacao grava, altera e exclui na sua propria tabela', function (string $tela) {
    /*
     * O mesmo ciclo nas seis, pela URL de cada uma. É o que prova que a tela
     * existe DE VERDADE — controller, rota e model ligados — e não só que a
     * base compartilhada funciona uma vez.
     */
    ['modelo' => $modelo, 'extras' => $extras] = lookupsDeParametrizacao()[$tela];

    $this->actingAs($this->admin)
        ->post(caminhoDoLookup($tela), [...$extras, 'nome' => 'Valor de teste', 'ativo' => true])
        ->assertRedirect();

    $registro = $modelo::where('nome', 'Valor de teste')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(caminhoDoLookup($tela, $registro->id), [...$extras, 'nome' => 'Valor corrigido', 'ativo' => false])
        ->assertRedirect();

    expect($registro->fresh()->nome)->toBe('Valor corrigido')
        ->and($registro->fresh()->ativo)->toBeFalse();

    $this->actingAs($this->admin)
        ->delete(caminhoDoLookup($tela, $registro->id))
        ->assertRedirect();

    expect($modelo::find($registro->id))->toBeNull();
})->with(fn () => array_keys(lookupsDeParametrizacao()));

test('todas as tabelas de lookup nascem semeadas', function () {
    // Lista vazia trava a operação em silêncio: o fiscal abre o formulário em
    // rua e não tem o que escolher.
    foreach (lookupsDeParametrizacao() as $tela => $dados) {
        expect($dados['modelo']::count())->toBeGreaterThan(0, "Sem seed: {$tela}");
    }
});

test('a tela entrega os itens e a definicao dos campos que ela mostra', function () {
    $this->actingAs($this->admin)
        ->get(caminhoDoLookup('unidades-de-medida'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Retaguarda/Parametrizacao/UnidadesDeMedida')
            ->has('itens', UnidadeMedida::count())
            ->where('definicao.titulo', 'Unidades de Medida')
            // A sigla é campo da unidade de medida, e a tela precisa saber disso
            // pelo servidor — senão a definição teria dois donos.
            ->where('definicao.campos.0.chave', 'sigla'));
});

test('o nome e obrigatorio, e a recusa diz o que fazer', function () {
    $this->actingAs($this->admin)
        ->post(caminhoDoLookup('motivos-de-recusa'), ['nome' => '   ', 'ativo' => true])
        ->assertSessionHasErrors('nome');

    expect(MotivoRecusa::where('nome', '   ')->exists())->toBeFalse();
});

test('nome repetido e recusado mesmo trocando a caixa das letras e os espacos', function () {
    /*
     * "Feira" e "feira " são a MESMA lista para quem escolhe: duas linhas
     * fazem o mesmo valor aparecer duas vezes no formulário do fiscal, e os
     * registros históricos se dividem entre as duas sem ninguém perceber.
     */
    $this->actingAs($this->admin)->post(caminhoDoLookup('tipos-de-operacao'), [
        'nome' => 'Feira livre',
        'ativo' => true,
    ]);

    $this->actingAs($this->admin)
        ->post(caminhoDoLookup('tipos-de-operacao'), ['nome' => '  FEIRA LIVRE ', 'ativo' => true])
        ->assertSessionHasErrors('nome');

    expect(TipoOperacao::whereRaw('LOWER(nome) = ?', ['feira livre'])->count())->toBe(1);
});

test('nome repetido com ACENTO e recusado — e nunca chega ao banco como erro cru', function () {
    /*
     * O caso que a caixa de letras ASCII não pega. A conferência antiga comparava
     * em `LOWER(TRIM(nome))` DENTRO do banco, e o `LOWER` do SQLite só rebaixa
     * ASCII: "Área não autorizada" continuava com o "Á" maiúsculo do lado do
     * banco e minúsculo do lado do PHP, os dois nunca casavam, a validação
     * liberava e quem recusava era o índice único — com erro 500 e a tela em
     * silêncio, sem dizer nada a quem estava salvando.
     *
     * Dois cenários no mesmo teste, porque é o mesmo defeito: o nome IDÊNTICO
     * (que o índice do banco barraria) e o nome que só difere no acento e na
     * caixa (que o índice deixaria passar, criando gêmeos visuais na lista do
     * fiscal).
     */
    // Vem da semeadura: "Área não autorizada" é um dos tipos com que a operação
    // nasce — e é justamente um valor acentuado, o que passou batido.
    $existente = TipoInfracao::where('nome', 'Área não autorizada')->firstOrFail();

    foreach (['Área não autorizada', 'área nao autorizada', '  ÁREA NÃO AUTORIZADA '] as $repetido) {
        $this->actingAs($this->admin)
            ->post(caminhoDoLookup('tipos-de-infracao'), ['nome' => $repetido, 'ativo' => true])
            ->assertSessionHasErrors('nome');
    }

    expect(TipoInfracao::where('nome', 'like', '%rea n%o autorizada')->count())->toBe(1);

    // E o outro lado: alterar o próprio registro sem mexer no nome acentuado
    // continua passando — a conferência ignora o registro que está sendo salvo.
    $this->actingAs($this->admin)
        ->put(caminhoDoLookup('tipos-de-infracao', $existente->id), [
            'nome' => 'Área não autorizada',
            'descricao' => $existente->descricao,
            'ativo' => false,
        ])
        ->assertSessionHasNoErrors();
});

test('alterar um registro nao esbarra no proprio nome', function () {
    // A conferência de duplicidade tem de ignorar o próprio registro; senão
    // salvar sem mexer no nome (só para inativar) seria recusado.
    $atividade = AtividadeAmbulante::first();

    $this->actingAs($this->admin)
        ->put(caminhoDoLookup('atividades-do-ambulante', $atividade->id), [
            'nome' => $atividade->nome,
            'ativo' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($atividade->fresh()->ativo)->toBeFalse();
});

test('o campo proprio da tela e exigido quando ela o declara obrigatorio', function () {
    // Unidade de medida sem sigla não serve para nada: é a sigla que aparece
    // no documento impresso em rua.
    $this->actingAs($this->admin)
        ->post(caminhoDoLookup('unidades-de-medida'), ['nome' => 'Engradado', 'ativo' => true])
        ->assertSessionHasErrors('sigla');
});

test('o que foi inativado sai da lista que as outras telas oferecem', function () {
    // O `ativos()` é o que separa "existe no cadastro" de "pode ser escolhido
    // hoje". Sem ele, inativar não teria efeito nenhum.
    $tipo = TipoInfracao::first();
    $tipo->update(['ativo' => false]);

    expect(TipoInfracao::ativos()->pluck('id'))->not->toContain($tipo->id)
        ->and(TipoInfracao::ativos()->count())->toBe(TipoInfracao::count() - 1);
});

test('quem nao tem a parametrizacao concedida e mandado de volta dizendo o porque', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $fiscal = User::factory()->create(['admin' => false]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());

    $this->actingAs($fiscal->fresh())
        ->get(caminhoDoLookup('tipos-de-infracao'))
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');
});

test('concedida na matriz, a parametrizacao abre para quem nao e administrador', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $chefe = User::factory()->create(['admin' => false]);
    $chefe->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());
    PermissaoSetor::updateOrCreate(
        ['setor' => 'chefe-de-setor', 'slug' => 'parametrizacao'],
        ['visivel' => true, 'habilitado' => true, 'incluir' => true, 'excluir' => true],
    );

    $this->actingAs($chefe->fresh())->get(caminhoDoLookup('motivos-de-recusa'))->assertOk();
});

test('as seis telas dividem UMA permissao, e ela se chama pelo nome da secao', function () {
    /*
     * Teste-LEI. As seis moram sob o mesmo primeiro trecho do caminho
     * (`/retaguarda/parametrizacao/...`), e é dele que as guardas deduzem a
     * tela — então a permissão é uma só, para o conjunto. Quem a concede tem de
     * ler o nome do CONJUNTO na matriz; ver ali o nome de uma das seis faria
     * parecer que as outras cinco ficaram de fora.
     */
    $catalogo = collect(CatalogoFuncionalidades::itens())
        ->firstWhere('slug', 'parametrizacao');

    expect($catalogo)->not->toBeNull()
        ->and($catalogo['rotulo'])->toBe('Parametrização');
});

test('os parametros da fiscalizacao nascem semeados e sao lidos por codigo, sem tela', function () {
    /*
     * O prazo de notificação é regra de negócio parametrizável, mas nesta
     * entrega ninguém o edita por tela: quem o lê é a cadeia de fiscalização,
     * que ainda não existe. Tela de editar parâmetro sem fluxo que os consuma
     * seria botão que não muda nada — ela nasce junto com a cadeia.
     */
    expect(ParametroFiscalizacao::where('chave', 'prazo_notificacao_dias')->value('valor'))->toBe('10');

    $telaDeParametros = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_contains($r->uri(), 'parametrizacao/parametros'))
        ->map(fn ($r): string => $r->uri())
        ->values()
        ->all();

    expect($telaDeParametros)->toBe([]);
});

test('o recorte visivel da listagem vira documento pelo ponto unico de exportacao', function () {
    // A lei da exportação vale para estas listagens como para qualquer outra: o
    // documento sai do MESMO endpoint, com as colunas que a tela declara.
    $resposta = $this->actingAs($this->admin)->post(route('retaguarda.exportar-listagem'), [
        'formato' => 'pdf',
        'titulo' => 'Tipos de Infração',
        'subtitulo' => 'Parametrização › Tipos de Infração',
        'contexto' => 'Somente ativos',
        'colunas' => [
            ['chave' => 'nome', 'titulo' => 'Nome'],
            ['chave' => 'descricao', 'titulo' => 'Descrição'],
            ['chave' => 'situacao', 'titulo' => 'Situação'],
        ],
        'linhas' => [
            ['nome' => 'Área não autorizada', 'descricao' => '—', 'situacao' => 'Ativo'],
        ],
    ]);

    $resposta->assertOk();

    expect((string) $resposta->getContent())->toStartWith('%PDF');
});

test('a semeadura e idempotente — rodar de novo nao duplica lista nenhuma', function () {
    // O seeder roda no deploy e no ambiente de cada dev. Duplicar valor de
    // lookup é o tipo de sujeira que só aparece no formulário do fiscal.
    $antes = collect(lookupsDeParametrizacao())
        ->map(fn (array $d): int => $d['modelo']::count());

    $this->seed(ParametrizacaoFiscalizacaoSeeder::class);

    $depois = collect(lookupsDeParametrizacao())
        ->map(fn (array $d): int => $d['modelo']::count());

    expect($depois->all())->toBe($antes->all())
        ->and(ParametroFiscalizacao::where('chave', 'prazo_notificacao_dias')->count())->toBe(1);
});
