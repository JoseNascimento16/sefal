<?php

use App\Models\Setor;
use App\Models\User;
use App\Support\Prototipo\DenunciasFicticias;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\OperacoesFicticias;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;

/*
|--------------------------------------------------------------------------
| Cadastro de Operação — e a FONTE ÚNICA do catálogo
|--------------------------------------------------------------------------
|
| A tela é PROTÓTIPO: nada é gravado, e o dado de partida vem de
| `config/prototipo_operacoes.php`. Isso muda o QUE se testa, não SE se testa — e
| aqui há quatro coisas que só teste garante:
|
| 1. **A FONTE ÚNICA.** O que se cadastra aqui é exatamente o que o direcionamento
|    das Denúncias oferece. Com duas listas, o direcionamento ofereceria amanhã
|    uma operação que o cadastro não conhece — e nada acusaria: as duas telas
|    abrem, cada uma com a sua verdade.
|
| 2. **A OPERAÇÃO ENCERRADA não recebe denúncia nova.** A tela não a oferece, e o
|    servidor a recusa DIZENDO o motivo. Só a primeira metade seria conforto: a
|    denúncia entraria num trabalho que ninguém vai executar e desapareceria da
|    fila sem nunca chegar a campo — a pior falha possível, porque não parece
|    falha nenhuma.
|
| 3. **AS REGRAS COM MOTIVO ESCRITO.** Período invertido, área ausente, nome
|    repetido. Bloqueio mudo faz a pessoa achar que o sistema quebrou.
|
| 4. **O RECORTE POR ÁREA, e que ele é do SERVIDOR.** Esconder da lista não é
|    fronteira; a recusa é nominal, e diz por quais áreas a pessoa responde.
|
| A régua é o doc de regra (`docs/regras-de-negocio/fiscalizacao/cadastro-de-operacao.md`)
| — não há HU escrita neste projeto.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

/** Um Chefe de Setor de verdade: a matrícula é o que o liga à área na estrutura. */
function chefeDeOperacao(string $matricula): User
{
    $u = User::factory()->create(['login' => $matricula, 'admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    return $u->fresh();
}

function coordenadorDeOperacao(): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'coordenador')->firstOrFail());

    return $u->fresh();
}

/** As operações que o servidor entrega a esta pessoa. */
function operacoesServidas(User $u): array
{
    return test()->actingAs($u)
        ->get('/retaguarda/operacoes')
        ->viewData('page')['props']['operacoes'];
}

/** Um corpo de formulário válido, com o que o teste quiser trocar. */
function operacaoValida(array $trocas = []): array
{
    return [
        'nome' => 'Operação Teste da Orla',
        'area' => 'Área 5',
        'regiao' => 'Orla',
        'equipes' => ['C1'],
        'bairros' => ['Costa Azul'],
        'inicio' => now()->format('Y-m-d'),
        'fim' => now()->addDays(10)->format('Y-m-d'),
        'situacao' => OperacoesFicticias::EM_ANDAMENTO,
        'foco' => 'Barracas de praia avançando sobre a faixa de areia liberada.',
        'observacao' => '',
        ...$trocas,
    ];
}

test('a tela abre e entrega o catalogo que a validacao exige', function () {
    $pagina = test()->actingAs(User::factory()->create(['admin' => true, 'ativo' => true]))
        ->get('/retaguarda/operacoes')
        ->viewData('page')['props'];

    // Os catálogos vêm do SERVIDOR: escritos também na tela, um dia discordariam —
    // e a tela ofereceria uma opção que o servidor recusa.
    expect($pagina['situacoes'])->toBe(OperacoesFicticias::situacoes())
        ->and($pagina['areas'])->toBe(EstruturaFicticia::nomesDeArea())
        ->and($pagina['operacoes'])->not->toBe([])
        ->and($pagina['cadastra'])->toBeTrue();
});

test('lei: o cadastro e o direcionamento leem o MESMO catalogo de operacoes', function () {
    /*
     * Teste-LEI de fonte única, e a razão de esta tela ter sido construída em cima
     * da lista que já existia em vez de ao lado dela.
     *
     * Duas provas, e as duas importam: (a) o direcionamento é um SUBCONJUNTO do
     * cadastro — as disponíveis, sem as encerradas; (b) uma operação CRIADA aqui
     * aparece lá na mesma sessão. Sem (b), duas listas alimentadas pela mesma
     * config passariam neste teste e divergiriam na primeira gravação.
     */
    $doCadastro = array_column(OperacoesFicticias::todas(), 'nome');
    $doDirecionamento = DenunciasFicticias::nomesDeOperacao();

    expect($doDirecionamento)->not->toBe([])
        ->and(array_diff($doDirecionamento, $doCadastro))
        ->toBe([], 'o direcionamento oferece operação que o cadastro não conhece');

    // A encerrada existe no cadastro e NÃO no direcionamento — é o subconjunto.
    $encerradas = array_column(array_filter(
        OperacoesFicticias::todas(),
        static fn (array $o): bool => $o['encerrada'] === true,
    ), 'nome');

    expect($encerradas)->not->toBe([], 'a amostra precisa de operação encerrada');

    foreach ($encerradas as $nome) {
        expect($doDirecionamento)->not->toContain($nome);
    }

    // E o que nasce aqui chega lá: mesma sessão, mesma lista.
    $chefe = chefeDeOperacao('gestor1');

    $this->actingAs($chefe)->post('/retaguarda/operacoes', operacaoValida())
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    expect(DenunciasFicticias::nomesDeOperacao())->toContain('Operação Teste da Orla');
});

test('o direcionamento recebe a operacao com as chaves que a tela dele LE', function () {
    /*
     * O contrato entre o servidor e a tela do direcionamento — e ele existe por um
     * defeito real desta entrega, achado no navegador: o campo `equipe` (singular)
     * virou `equipes` (lista), e o rótulo do seletor de operação continuou lendo o
     * antigo. A opção passou a mostrar "(Equipe )" em branco, e a chefia escolheria
     * a operação sem saber quem a executa. Nada estourou — `undefined` interpolado
     * é string vazia.
     *
     * O gate deste projeto não executa JS, então o que se trava aqui é a metade que
     * ele alcança: as chaves que a tela precisa CHEGAM, e chegam no formato certo.
     */
    $pagina = test()->actingAs(chefeDeOperacao('gestor1'))
        ->get('/retaguarda/denuncias/e-salvador')
        ->viewData('page')['props'];

    expect($pagina['operacoes'])->not->toBe([]);

    $problemas = [];

    foreach ($pagina['operacoes'] as $o) {
        foreach (['id', 'nome', 'area', 'equipes', 'periodo', 'situacao', 'foco'] as $chave) {
            if (! array_key_exists($chave, $o)) {
                $problemas[] = "{$o['nome']}: sem a chave '{$chave}'";
            }
        }

        if (! is_array($o['equipes'] ?? null)) {
            $problemas[] = "{$o['nome']}: 'equipes' não é lista";
        }

        // A encerrada não chega: oferecê-la seria convidar para a recusa da RN-05.
        if (($o['situacao'] ?? null) === OperacoesFicticias::ENCERRADA) {
            $problemas[] = "{$o['nome']}: encerrada oferecida ao direcionamento";
        }

        // O período chega em BR: é etiqueta pronta para a tela, e data é conta do
        // servidor. ISO aqui apareceria cru no seletor.
        if (! preg_match('/\d{2}\/\d{2}\/\d{4}/', (string) ($o['periodo'] ?? ''))) {
            $problemas[] = "{$o['nome']}: período '{$o['periodo']}' não está em dd/mm/aaaa";
        }
    }

    expect($problemas)->toBe([], "O direcionamento recebe a operação pronta para ler.\n");
});

test('a operacao ENCERRADA nao recebe denuncia nova, e a recusa diz o porque', function () {
    /*
     * A tela do direcionamento não oferece a encerrada — mas esconder da lista não é
     * fronteira: quem souber montar a requisição manda o nome dela, e a denúncia
     * entraria num trabalho que ninguém vai mais executar, desaparecendo da fila
     * sem nunca chegar a campo.
     *
     * O vermelho antes do verde desta regra é o próprio caminho: sem a conferência
     * no controller, o POST abaixo passaria (a `Rule::in` diria só "seleção
     * inválida" quando passasse, o que não explica nada a quem escolheu).
     */
    $encerrada = collect(OperacoesFicticias::todas())->firstWhere('encerrada', true);

    expect($encerrada)->not->toBeNull();

    $chefe = chefeDeOperacao('gestor1');

    // Uma denúncia da área dele que esteja esperando direcionamento.
    $daArea = collect(DenunciasFicticias::todas())->first(
        static fn (array $d): bool => (string) ($d['area'] ?? '') === 'Área 5'
            && in_array((string) $d['situacao'], DenunciasFicticias::AGUARDANDO_DIRECIONAMENTO, true),
    );

    expect($daArea)->not->toBeNull('a amostra precisa de denúncia da Área 5 aguardando direcionamento');

    $this->actingAs($chefe)
        ->post('/retaguarda/denuncias/operacao', [
            'ids' => [$daArea['id']],
            'nova' => false,
            'operacao' => $encerrada['nome'],
        ])
        ->assertRedirect()
        ->assertSessionHas(
            'flash.erro',
            fn (string $recado): bool => str_contains($recado, 'encerrada')
                && str_contains($recado, $encerrada['nome']),
        );

    // E nada foi alterado: a denúncia continua esperando o direcionamento.
    $depois = collect(DenunciasFicticias::todas())->firstWhere('id', $daArea['id']);

    expect((string) $depois['situacao'])->toBe((string) $daArea['situacao'])
        ->and($depois['operacao'])->toBeNull();
});

test('o periodo com FIM antes do inicio e recusado, dizendo o efeito', function () {
    /*
     * Período invertido não é detalhe de formulário: a operação apareceria encerrada
     * antes de começar, e a conta de "quanto tempo ela durou" sairia negativa em
     * todo relatório que a somar.
     */
    $chefe = chefeDeOperacao('gestor1');

    $this->actingAs($chefe)
        ->post('/retaguarda/operacoes', operacaoValida([
            'inicio' => now()->format('Y-m-d'),
            'fim' => now()->subDays(3)->format('Y-m-d'),
        ]))
        ->assertSessionHasErrors('fim');

    expect(OperacoesFicticias::nomeEmUso('Operação Teste da Orla'))->toBeFalse();

    // Um dia só PASSA: a interdição de um evento começa e termina no mesmo dia, e
    // exigir fim posterior obrigaria a chefia a mentir a data para poder salvar.
    $this->actingAs($chefe)
        ->post('/retaguarda/operacoes', operacaoValida([
            'nome' => 'Operação de Um Dia',
            'inicio' => now()->format('Y-m-d'),
            'fim' => now()->format('Y-m-d'),
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso');
});

test('a AREA e obrigatoria, e area inventada e recusada', function () {
    /*
     * A área decide quem vê a operação e quem a executa. Operação sem área é
     * operação de ninguém: não aparece para chefe algum e não tem equipe a quem
     * cobrar.
     */
    $chefe = chefeDeOperacao('gestor1');

    $this->actingAs($chefe)
        ->post('/retaguarda/operacoes', [...operacaoValida(), 'area' => ''])
        ->assertSessionHasErrors('area');

    $this->actingAs($chefe)
        ->post('/retaguarda/operacoes', operacaoValida(['area' => 'Área 99']))
        ->assertSessionHasErrors('area');

    expect(OperacoesFicticias::nomeEmUso('Operação Teste da Orla'))->toBeFalse();
});

test('nome repetido e recusado, aqui e no direcionamento', function () {
    /*
     * O nome é como a equipe reconhece a operação em rua e é o que a denúncia grava
     * na linha ao ser anexada. Duas com o mesmo nome fazem a anexação apontar para
     * qualquer uma das duas, e ninguém sabe qual.
     */
    $existente = OperacoesFicticias::todas()[0]['nome'];
    $chefe = chefeDeOperacao('gestor1');

    $this->actingAs($chefe)
        ->post('/retaguarda/operacoes', operacaoValida(['nome' => $existente]))
        ->assertSessionHasErrors('nome');

    // A mesma régua na criação a partir do direcionamento — o outro caminho de
    // nascimento de operação. Ele não é um segundo cadastro: se a regra valesse só
    // aqui, o nome duplicado entraria por lá.
    $daArea = collect(DenunciasFicticias::todas())->first(
        static fn (array $d): bool => (string) ($d['area'] ?? '') === 'Área 5'
            && in_array((string) $d['situacao'], DenunciasFicticias::AGUARDANDO_DIRECIONAMENTO, true),
    );

    $this->actingAs($chefe)
        ->post('/retaguarda/denuncias/operacao', [
            'ids' => [$daArea['id']],
            'nova' => true,
            'nome' => $existente,
            'area' => 'Área 5',
            'equipe' => 'C1',
        ])
        ->assertRedirect()
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'esse nome'));
});

test('o chefe de setor recebe so as operacoes da area dele', function () {
    $chefe = chefeDeOperacao('gestor1');
    $minhas = EstruturaFicticia::areasDoChefe('gestor1');

    expect($minhas)->not->toBe([]);

    $servidas = operacoesServidas($chefe);

    expect($servidas)->not->toBe([], 'a área do gestor1 precisa de operação para demonstrar');

    $deFora = array_values(array_filter(
        $servidas,
        static fn (array $o): bool => ! in_array((string) $o['area'], $minhas, true),
    ));

    expect($deFora)->toBe([], 'o cadastro do Chefe de Setor traz só as áreas dele');
});

test('o conteudo da operacao alheia nao viaja ate o navegador do chefe de outra area', function () {
    /*
     * O vazamento que importa não é a linha da grade: é o FOCO e a OBSERVAÇÃO da
     * gestão, que vão dentro do registro. Então o teste procura o texto no corpo
     * inteiro da resposta, e não só na lista de identificadores.
     */
    $this->actingAs(chefeDeOperacao('gestor3'))
        ->get('/retaguarda/operacoes')
        // "Operação Verão — Orla" e o foco dela são da Área 5 (gestor1).
        ->assertDontSee('Operação Verão')
        ->assertDontSee('barracas de praia');
});

test('quem tria ve o universo — inclusive a area sem chefe com conta', function () {
    $servidas = operacoesServidas(coordenadorDeOperacao());
    $areas = array_values(array_unique(array_column($servidas, 'area')));

    // A Área 2 não tem conta de Chefe de Setor na demonstração: só o Coordenador e
    // o administrador a enxergam, e é isso que faz dela a prova do recorte.
    expect($areas)->toContain('Área 2')
        ->and(count($areas))->toBeGreaterThan(1);
});

test('quem apenas consulta nao cadastra: a tela nao oferece e o servidor recusa', function () {
    /*
     * O Coordenador tria a entrada do trabalho e precisa saber que operação existe
     * para onde encaminhar a demanda — montar operação é de quem responde pela área.
     *
     * As duas metades: a tela não lhe oferece (`cadastra` falso, resposta do
     * servidor) e o servidor recusa com o motivo. Esconder botão é conforto; a
     * fronteira é a recusa.
     */
    $coordenador = coordenadorDeOperacao();

    $this->actingAs($coordenador)->get('/retaguarda/operacoes')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Retaguarda/Fiscalizacao/CadastroDeOperacao')
            ->where('cadastra', false));

    $this->actingAs($coordenador)
        ->post('/retaguarda/operacoes', operacaoValida())
        ->assertRedirect()
        ->assertSessionHas('flash.erro', fn (string $r): bool => trim($r) !== '');

    expect(OperacoesFicticias::nomeEmUso('Operação Teste da Orla'))->toBeFalse();
});

test('o chefe de setor e recusado NOMINALMENTE ao gravar operacao de outra area', function () {
    /*
     * Esconder da listagem não é fronteira: quem souber montar a requisição alcança
     * a operação de outra área. A recusa nomeia as áreas por que a pessoa responde,
     * para ela saber o que aconteceu.
     */
    $chefe = chefeDeOperacao('gestor1');
    $minhas = EstruturaFicticia::areasDoChefe('gestor1');

    $alheia = collect(OperacoesFicticias::todas())->first(
        static fn (array $o): bool => ! in_array((string) $o['area'], $minhas, true),
    );

    expect($alheia)->not->toBeNull();

    // (a) criar NA área de outro.
    $this->actingAs($chefe)
        ->post('/retaguarda/operacoes', operacaoValida([
            'nome' => 'Operação Fora da Minha Área',
            'area' => (string) $alheia['area'],
            'equipes' => [],
            'bairros' => [],
        ]))
        ->assertRedirect()
        ->assertSessionHas(
            'flash.erro',
            fn (string $r): bool => str_contains($r, $minhas[0]) && str_contains($r, (string) $alheia['area']),
        );

    expect(OperacoesFicticias::nomeEmUso('Operação Fora da Minha Área'))->toBeFalse();

    // (b) ALTERAR a operação de outro — inclusive tentando trazê-la para a própria
    // área, que é o caminho fácil se só a área de destino fosse conferida.
    $this->actingAs($chefe)
        ->put("/retaguarda/operacoes/{$alheia['id']}", operacaoValida([
            'nome' => 'Sequestro de Operação',
            'area' => $minhas[0],
        ]))
        ->assertRedirect()
        ->assertSessionHas('flash.erro');

    expect(OperacoesFicticias::porId((int) $alheia['id'])['nome'])->toBe((string) $alheia['nome']);

    // (c) EXCLUIR a operação de outro.
    $this->actingAs($chefe)
        ->delete("/retaguarda/operacoes/{$alheia['id']}")
        ->assertRedirect()
        ->assertSessionHas('flash.erro');

    expect(OperacoesFicticias::porId((int) $alheia['id']))->not->toBeNull();
});

test('o chefe de setor cria, altera e exclui na propria area', function () {
    $chefe = chefeDeOperacao('gestor1');

    $this->actingAs($chefe)->post('/retaguarda/operacoes', operacaoValida())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.sucesso');

    $criada = OperacoesFicticias::porNome('Operação Teste da Orla');

    expect($criada)->not->toBeNull()
        ->and($criada['area'])->toBe('Área 5')
        ->and($criada['equipes'])->toBe(['C1'])
        // O período vem em BR na etiqueta: lei do projeto, e é o que a tela mostra.
        ->and($criada['periodo'])->toMatch('/^\d{2}\/\d{2}\/\d{4} a \d{2}\/\d{2}\/\d{4}$/');

    $this->actingAs($chefe)
        ->put("/retaguarda/operacoes/{$criada['id']}", operacaoValida([
            'situacao' => OperacoesFicticias::ENCERRADA,
        ]))
        ->assertSessionHas('flash.sucesso');

    // Encerrada: continua no cadastro e sai da escolha do direcionamento.
    expect(OperacoesFicticias::porNome('Operação Teste da Orla')['encerrada'])->toBeTrue()
        ->and(DenunciasFicticias::nomesDeOperacao())->not->toContain('Operação Teste da Orla');

    $this->actingAs($chefe)
        ->delete("/retaguarda/operacoes/{$criada['id']}")
        ->assertSessionHas('flash.sucesso');

    expect(OperacoesFicticias::porNome('Operação Teste da Orla'))->toBeNull();
});

test('operacao sem data de fim e rotina PERMANENTE, e a etiqueta diz isso', function () {
    /*
     * "Rotina Centro" é permanente: inventar um fim faria a tela mostrar prazo onde
     * não há, e a operação apareceria encerrando num dia que ninguém decidiu.
     */
    $chefe = chefeDeOperacao('gestor1');

    $this->actingAs($chefe)
        ->post('/retaguarda/operacoes', operacaoValida(['nome' => 'Rotina Permanente da Orla', 'fim' => null]))
        ->assertSessionHasNoErrors();

    $criada = OperacoesFicticias::porNome('Rotina Permanente da Orla');

    expect($criada['fim'])->toBeNull()
        ->and($criada['periodo'])->toStartWith('a partir de ');
});

test('reiniciar devolve o catalogo ao estado de demonstracao', function () {
    $chefe = chefeDeOperacao('gestor1');

    $this->actingAs($chefe)->post('/retaguarda/operacoes', operacaoValida());

    expect(OperacoesFicticias::alterada())->toBeTrue();

    $this->actingAs($chefe)->post('/retaguarda/operacoes/reiniciar')->assertRedirect();

    expect(OperacoesFicticias::alterada())->toBeFalse()
        ->and(OperacoesFicticias::porNome('Operação Teste da Orla'))->toBeNull();
});

test('o fiscal nao entra no cadastro de operacao: e barrado dizendo o porque', function () {
    /*
     * Planejar operação é ato de gestão; o fiscal recebe o trabalho já dirigido,
     * pelo aplicativo. Barrado não é 403 seco: volta para a tela inicial com o
     * motivo.
     */
    config(['retaguarda.permissao_enforce' => 'block']);

    $fiscal = User::factory()->create(['admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());

    $this->actingAs($fiscal->fresh())
        ->get('/retaguarda/operacoes')
        ->assertRedirect(route('retaguarda.inicio'))
        ->assertSessionHas('flash.erro');
});

test('o recorte visivel do cadastro vira documento pelo ponto unico de exportacao', function () {
    /*
     * A lei do projeto: toda listagem exporta, e pelo endpoint único — nenhuma tela
     * gera arquivo por conta própria.
     */
    $this->actingAs(chefeDeOperacao('gestor1'))
        ->post('/retaguarda/exportar-listagem', [
            'formato' => 'xlsx',
            'titulo' => 'Operações',
            'subtitulo' => 'Fiscalização › Cadastro de Operação',
            'contexto' => 'Áreas: Área 5 · busca: "em andamento"',
            'colunas' => [
                ['chave' => 'nome', 'titulo' => 'Operação'],
                ['chave' => 'periodo', 'titulo' => 'Período'],
            ],
            'linhas' => [
                ['nome' => 'Operação Verão — Orla', 'periodo' => '01/02/2026 a 31/03/2026'],
            ],
        ])
        ->assertOk()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
});
