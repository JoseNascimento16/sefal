<?php

use App\Models\CicloDeFiscalizacao;
use App\Models\Demanda;
use App\Models\DocumentoCampo;
use App\Models\Fiscalizacao;
use App\Models\Setor;
use App\Models\User;
use App\Support\Apresentacao\FiscalizacaoParaTela;
use App\Support\Estrutura;
use App\Support\Prototipo\RecomendacoesDoFiscal;
use Database\Seeders\DemonstracaoSeeder;
use Database\Seeders\EstruturaSeeder;
use Database\Seeders\FiscalizacoesParaOLiderSeeder;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;

/*
|--------------------------------------------------------------------------
| Fiscalizações — a fila do Chefe de Setor ("A decidir") e o ACERVO
|--------------------------------------------------------------------------
|
| A tela nasceu partida em duas: "Retorno de Campo", construída, e
| "Fiscalizações", um andaime que prometia a consulta. Elas viraram as duas ABAS
| de uma tela só (decisão do dono, 09/09/2026), sobre a MESMA fonte — o acervo é a
| fila sem o corte do estado, não uma segunda consulta.
|
| A tela é PROTÓTIPO: nada é gravado, e o dado de partida é derivado do trâmite
| das denúncias mais o arquivo das fiscalizações avulsas. Isso muda o QUE se
| testa, não SE se testa — e aqui há três coisas que só teste garante:
|
| 1. **A DERIVAÇÃO.** A fila não tem lista própria para os registros que vieram
|    de denúncia: ela os monta a partir do último passo do trâmite que declarou
|    desfecho. Se a derivação escorregar, a fila mostra a vistoria errada (ou
|    nenhuma), e nada acusa — não há erro, só uma lista mais curta.
|
| 2. **O RECORTE, e que ele é do SERVIDOR.** O vazamento que importa não é uma
|    linha de grade: é o RELATO do fiscal, a coordenada e o número do documento,
|    que viajam dentro do registro. Provar que a linha não aparece não basta.
|
| 3. **AS DUAS RECUSAS.** Quem apenas acompanha não decide, e o Chefe de Setor
|    não decide fora da área dele. As duas conferências rodam no servidor, e as
|    duas têm de dizer o motivo — esconder o botão é conforto, não fronteira.
|
| A régua é o doc de regra (`docs/regras-de-negocio/fiscalizacao/fiscalizacoes.md`)
| — não há HU escrita neste projeto.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    /*
     * A fila agora sai do BANCO, e não mais da config de protótipo: sem a
     * estrutura e as demandas semeadas, o teste rodaria contra um acervo vazio e
     * passaria pelo motivo errado — provando que ninguém vê nada, em vez de
     * provar o recorte.
     */
    $this->seed(EstruturaSeeder::class);
    $this->seed(DemonstracaoSeeder::class);
});

/**
 * A fila inteira, lida pelo MESMO caminho da tela (a rota, como administrador).
 *
 * O teste lê pela rota, e não por uma consulta própria: consulta própria provaria
 * o banco, e o que interessa aqui é o que o servidor ENTREGA — que é onde o
 * recorte e a montagem acontecem.
 *
 * @return list<array<string, mixed>>
 */
function filaDoBanco(): array
{
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);

    return filaServida($admin);
}

/**
 * As FISCALIZAÇÕES (ciclos) que o servidor entrega a esta pessoa — desde
 * 24/09/2026 a linha da tela é a Fiscalização, e as vistorias vão dentro dela.
 *
 * @return list<array<string, mixed>>
 */
function ciclosServidos(User $u): array
{
    return test()->actingAs($u)
        ->get('/retaguarda/fiscalizacoes')
        ->viewData('page')['props']['fiscalizacoes'];
}

/** A Fiscalização (ciclo) de uma vistoria, pelo banco. */
function cicloDaVistoria(int $vistoria): CicloDeFiscalizacao
{
    return CicloDeFiscalizacao::findOrFail(Fiscalizacao::findOrFail($vistoria)->ciclo_id);
}

/** O estado de um registro, direto do banco — é o efeito que a decisão produziu. */
function estadoNoBanco(int $id): string
{
    return Fiscalizacao::findOrFail($id)->situacao;
}

/** @return array<string, mixed> */
function registroNoBanco(int $id): array
{
    $f = Fiscalizacao::with(['demanda.tramites', 'equipe.area', 'fiscal', 'fotos', 'recomendacoes', 'documento', 'operacao'])
        ->findOrFail($id);

    return FiscalizacaoParaTela::completa($f);
}

/**
 * O líder de uma equipe de verdade — quem decide sobre o retorno DELA.
 *
 * A conta `lider-<código>` já existe: é o seeder da estrutura que a cria e a
 * liga à equipe (`equipes.lider_id`). Criar outra aqui daria DOIS donos ao mesmo
 * vínculo — e o teste passaria a provar um cadastro que o sistema não tem.
 */
function liderDaFila(string $codigo): User
{
    $u = User::where('login', User::normalizarMatricula('lider-'.$codigo))->firstOrFail();
    $u->setores()->syncWithoutDetaching([Setor::where('slug', 'lider-de-equipe')->firstOrFail()->id]);

    return $u->fresh();
}

/** O Chefe de Setor: lê tudo, sem recorte (22/09/2026). */
function chefeDaFila(): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    return $u->fresh();
}

/** As VISTORIAS que o servidor entrega a esta pessoa — de dentro das Fiscalizações. */
function filaServida(User $u): array
{
    return collect(ciclosServidos($u))->flatMap(static fn (array $c): array => $c['vistorias'])->values()->all();
}

test('cada ida ao ponto vira UM registro — a vistoria e o retorno não se fundem', function () {
    /*
     * ⚠️ A LEI MUDOU NA CONSOLIDAÇÃO, e de propósito.
     *
     * No protótipo a fila era DERIVADA na leitura, e uma denúncia rendia um
     * registro só: o do último passo com desfecho. A denúncia notificada e
     * depois revisitada aparecia uma vez, e a primeira ida sumia.
     *
     * Agora cada passo que declara desfecho é uma FISCALIZAÇÃO gravada, porque
     * foi uma ida ao ponto de verdade — com data, equipe e, às vezes, documento
     * próprio. Contá-las como uma faria o relatório dizer que a equipe foi à rua
     * metade das vezes que foi, e apagaria a notificação que precedeu o retorno.
     */
    $idasDeclaradas = 0;

    foreach ((array) config('prototipo_denuncias.denuncias', []) as $d) {
        foreach ((array) ($d['tramites'] ?? []) as $passo) {
            if (isset($passo['desfecho'])) {
                $idasDeclaradas++;
            }
        }
    }

    $avulsas = count((array) config('prototipo_registros_de_campo.registros', []));

    expect($idasDeclaradas)->toBeGreaterThan(0, 'a amostra precisa de denúncia com desfecho')
        ->and($avulsas)->toBeGreaterThan(0, 'a amostra precisa de fiscalização avulsa');

    /*
     * A conta não fecha com a soma bruta: a denúncia só vira fiscalização quando
     * tem EQUIPE (sem equipe não há quem assine a vistoria). O que se prova aqui
     * é a direção — nenhuma ida se perde e nenhuma nasce do nada.
     */
    $deDenuncia = Fiscalizacao::whereNotNull('demanda_id')->count();

    /*
     * A conta é pelo VÍNCULO, e não pela origem: a denúncia anexada a uma
     * operação nasce com `origem = operacao` (foi a varredura que a executou) e
     * continua sendo uma ida nascida de denúncia. Contar pela origem misturaria
     * essas com as da ronda planejada, que não têm denúncia atrás.
     */
    expect($deDenuncia)->toBeGreaterThan(0)
        ->and($deDenuncia)->toBeLessThanOrEqual($idasDeclaradas)
        ->and(Fiscalizacao::whereNull('demanda_id')->count())->toBe($avulsas)
        ->and(filaDoBanco())->toHaveCount(Fiscalizacao::despachadas()->count());
});

test('todo registro da fila declara as chaves que a tela le, inclusive as vazias', function () {
    /*
     * A tela lê `documento`, `gps`, `consideracoes` e `decisao` de qualquer linha.
     * Chave ausente em metade delas viraria leitura defensiva espalhada pelo front
     * — e, no caso de `documento`, um `!== null` verdadeiro para chave ausente
     * derruba a tela.
     */
    $chaves = [
        'id', 'protocolo', 'origem', 'referencia', 'denuncia_protocolo', 'concluida_em',
        'area', 'equipe', 'fiscal', 'endereco', 'bairro', 'ponto_de_referencia',
        'gps', 'precisao_m', 'desfecho', 'documento', 'consideracoes', 'recomendacoes',
        'situacao_da_origem', 'estado', 'decisao', 'dias_parado',
    ];

    foreach (filaDoBanco() as $registro) {
        expect($registro)->toHaveKeys($chaves, (string) ($registro['protocolo'] ?? '?'));
    }
});

test('lei: a recomendacao semeada na fiscalizacao avulsa sai do catalogo do aplicativo', function () {
    /*
     * O catálogo é o contrato com o aplicativo do fiscal: atalho escrito à mão no
     * arquivo de dados seria uma recomendação que a Retaguarda não sabe ler — e
     * que o relatório não sabe somar.
     *
     * ⚠️ O que se semeia é a CHAVE (`retorno`, `sgci`…), porque é a chave que o
     * aplicativo grava. A frase que a tela mostra sai da chave, na redação
     * explícita.
     */
    $chaves = RecomendacoesDoFiscal::chaves();
    $problemas = [];

    foreach ((array) config('prototipo_registros_de_campo.registros', []) as $r) {
        foreach ((array) ($r['recomendacoes'] ?? []) as $recomendacao) {
            if (! in_array((string) $recomendacao, $chaves, true)) {
                $problemas[] = "FIS-{$r['id']}: recomendação fora do catálogo '{$recomendacao}'";
            }
        }

        if (! in_array((string) $r['desfecho'], (array) config('prototipo_denuncias.desfechos', []), true)) {
            $problemas[] = "FIS-{$r['id']}: desfecho fora do catálogo '{$r['desfecho']}'";
        }
    }

    expect($problemas)->toBe([], "Recomendação e desfecho saem de catálogo — não são texto livre.\n");
});

test('a fila leva a CHAVE da recomendacao e o catalogo que a traduz na redacao explicita', function () {
    /*
     * O registro carrega a CHAVE (`retorno`, `sgci`…) — é o que o aplicativo do
     * fiscal grava, e é por ela que o relatório soma. Quem traduz é a leitura, e
     * o catálogo da tradução vem do SERVIDOR, na redação EXPLÍCITA: a curta é a
     * pílula do celular, e "Sugerir retorno da equipe" não diz à chefia QUANDO
     * voltar ao ponto.
     *
     * Sem esta prova, a tela cairia no fallback e mostraria a chave crua a quem
     * decide — o que é visível, mas não é o que o dono pediu.
     */
    $pagina = test()->actingAs(liderDaFila('C1'))
        ->get('/retaguarda/fiscalizacoes')
        ->viewData('page')['props'];

    $catalogo = $pagina['recomendacoesDoFiscal'];

    expect($catalogo)->toBeArray()
        ->and($catalogo['retorno'])->toBe('Voltar ao ponto no vencimento do prazo')
        // O SEAB é o atalho que veio do dono: as duas redações são iguais porque
        // ninguém aqui sabe o que a sigla expande.
        ->and($catalogo['seab'])->toBe('Encaminhar ao SEAB');

    $comRecomendacao = array_values(array_filter(
        collect($pagina['fiscalizacoes'])->flatMap(static fn (array $c): array => $c['vistorias'])->all(),
        static fn (array $r): bool => $r['recomendacoes'] !== [],
    ));

    expect($comRecomendacao)->not->toBe([], 'a fila desta área precisa de registro com recomendação');

    $orfas = [];

    foreach ($comRecomendacao as $registro) {
        foreach ($registro['recomendacoes'] as $chave) {
            // É chave, e o catálogo a conhece: a tela tem como escrever a frase.
            if (! array_key_exists((string) $chave, $catalogo)) {
                $orfas[] = "{$registro['protocolo']}: '{$chave}'";
            }
        }
    }

    expect($orfas)->toBe([], 'A fila leva CHAVE, e o catálogo do servidor traduz todas elas.
');
});

test('lei: a leitura traduz a chave pelo catalogo, e a chave desconhecida aparece CRUA', function () {
    /*
     * Teste de FONTE, e último recurso: o gate deste projeto não executa JS, e a
     * tradução é do front. O que ele protege é a decisão do dono e o seu
     * corolário — a Retaguarda mostra a redação explícita, e chave que o catálogo
     * não conhece NÃO desaparece da tela.
     *
     * Recomendação que evapora em silêncio é pior que recomendação feia: a chefia
     * decidiria sem saber que o fiscal pediu alguma coisa, e ninguém teria como
     * perceber que os dois catálogos (aqui e o do aplicativo do fiscal, na branch
     * `feature/pwa-prototipo`) andaram separados. Chave crua na tela é justamente
     * o sintoma visível dessa divergência.
     */
    $helper = (string) file_get_contents(resource_path('js/lib/recomendacoes.ts'));

    // O fallback devolve a PRÓPRIA chave — não string vazia, não `null`, e a
    // recomendação não é filtrada fora da lista.
    expect($helper)->toMatch('/\?\s*chave\s*:/')
        ->and($helper)->not->toContain('.filter(');

    // As três leituras da recomendação passam pelo helper, em vez de imprimir a
    // chave: se uma delas voltar a renderizar o valor cru, TODA recomendação
    // apareceria como código na tela de quem decide.
    $semHelper = [];

    foreach ([
        'js/pages/Retaguarda/Fiscalizacao/Fiscalizacoes.tsx',
        'js/components/retaguarda/tramite-de-denuncia.tsx',
        'js/components/retaguarda/painel-de-denuncias.tsx',
    ] as $arquivo) {
        if (! str_contains((string) file_get_contents(resource_path($arquivo)), '@/lib/recomendacoes')) {
            $semHelper[] = $arquivo;
        }
    }

    expect($semHelper)->toBe([], 'Toda leitura da recomendação passa pelo catálogo, nunca pela chave crua.
');
});

test('lei: a equipe da fiscalizacao avulsa existe na estrutura de areas', function () {
    /*
     * A área e o nome de quem assinou saem da estrutura, pelo código da equipe.
     * Código errado no arquivo de dados não estoura: o registro chega à fila com
     * área vazia, e aí ele não é de ninguém — nem aparece para chefe algum, nem
     * acusa nada.
     */
    $codigos = Estrutura::codigosDeEquipe();
    $problemas = [];

    foreach ((array) config('prototipo_registros_de_campo.registros', []) as $r) {
        if (! in_array((string) $r['equipe'], $codigos, true)) {
            $problemas[] = "FIS-{$r['id']}: equipe inexistente '{$r['equipe']}'";
        }
    }

    expect($problemas)->toBe([], "A equipe da avulsa existe na estrutura de áreas.\n");

    // E o efeito: nenhum registro da fila chega sem área nem sem quem assinou.
    foreach (filaDoBanco() as $registro) {
        expect(trim((string) $registro['area']))->not->toBe('', (string) $registro['protocolo'])
            ->and(trim((string) $registro['fiscal']))->not->toBe('', (string) $registro['protocolo']);
    }
});

test('o lider recebe so o que a equipe dele concluiu', function () {
    $lider = liderDaFila('C1');
    $minhas = Estrutura::equipesDoLider('lider-c1');

    expect($minhas)->not->toBe([]);

    $servidos = filaServida($lider);

    expect($servidos)->not->toBe([], 'a Equipe C1 precisa de retorno para demonstrar');

    $deFora = array_values(array_filter(
        $servidos,
        static fn (array $r): bool => ! in_array((string) $r['equipe'], $minhas, true),
    ));

    expect($deFora)->toBe([], 'a fila do líder traz só a equipe dele');
});

test('o conteudo do retorno alheio nao viaja ate o navegador do chefe de outra area', function () {
    /*
     * O vazamento que importa não é a linha da grade: é o RELATO do fiscal e o
     * número do documento, que vão dentro do registro. Então o teste procura o
     * texto no corpo inteiro da resposta, e não só na lista de identificadores.
     */
    $chefe = liderDaFila('A2');

    $this->actingAs($chefe)
        ->get('/retaguarda/fiscalizacoes')
        // 194903 é a notificação da Área 5 (gestor1); "Operação Verão — Orla" é a
        // avulsa da mesma área.
        ->assertDontSee('194903')
        ->assertDontSee('Operação Verão');
});

test('o chefe de setor ve o universo — todas as equipes, sem recorte', function () {
    $servidos = filaServida(chefeDaFila());
    $areas = array_values(array_unique(array_column($servidos, 'area')));

    // Mais de uma área na mesma fila é a prova de que não há recorte: o líder
    // vê uma equipe; o chefe, o setor inteiro.
    expect($areas)->toContain('Área 2')
        ->and(count($areas))->toBeGreaterThan(1);
});

test('o chefe de setor ACOMPANHA: não conduz a Fiscalização com a equipe, e a recusa diz onde ele delibera', function () {
    $chefe = chefeDaFila();
    $vistoria = collect(filaDoBanco())->firstWhere('estado', Fiscalizacao::AGUARDANDO_LEITURA);
    $ciclo = cicloDaVistoria((int) $vistoria['id']);

    $this->actingAs($chefe)->get('/retaguarda/fiscalizacoes')
        ->assertInertia(fn ($p) => $p->where('conduz', false)->where('arquiva', true));

    $this->actingAs($chefe)
        ->post('/retaguarda/fiscalizacoes/encaminhar-ao-chefe', [
            'ids' => [$ciclo->id],
            'motivo' => 'O chefe tentando encaminhar a si mesmo pela tela do líder.',
        ])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'Caixa de Entrada'));

    expect($ciclo->fresh()->posse)->toBe(CicloDeFiscalizacao::POSSE_EQUIPE);
});

test('o lider e recusado NOMINALMENTE ao agir sobre Fiscalizacao de outra equipe', function () {
    /*
     * Esconder da listagem não é fronteira: quem souber montar a requisição
     * alcança a Fiscalização de outra equipe, e o lote é o caminho fácil. A recusa
     * nomeia a Fiscalização para quem clicou saber o que aconteceu.
     */
    $lider = liderDaFila('A2');
    $minhas = Estrutura::equipesDoLider('lider-a2');

    $alheio = CicloDeFiscalizacao::with('equipe')->daAba(CicloDeFiscalizacao::ABA_ANDAMENTO)->get()
        ->first(static fn (CicloDeFiscalizacao $c): bool => ! in_array((string) $c->equipe?->codigo, $minhas, true));

    expect($alheio)->not->toBeNull();

    $this->actingAs($lider)
        ->post('/retaguarda/fiscalizacoes/encaminhar-ao-chefe', [
            'ids' => [$alheio->id],
            'motivo' => 'Tentando encaminhar ao chefe uma Fiscalização que não é da minha equipe.',
        ])
        ->assertRedirect()
        ->assertSessionHas(
            'flash.erro',
            fn (string $recado): bool => str_contains($recado, $alheio->protocolo) && str_contains($recado, 'Nada foi alterado'),
        );

    expect($alheio->fresh()->posse)->toBe(CicloDeFiscalizacao::POSSE_EQUIPE);
});

test('não existe mais "dar ciência": a rota saiu', function () {
    $this->actingAs(liderDaFila('C1'))
        ->post('/retaguarda/fiscalizacoes/ciencia', ['ids' => [1]])
        ->assertStatus(404);
});

test('o líder encaminha a Fiscalização ao chefe: ela vai para Encaminhadas, posse Chefe, e a demanda volta à mesa dele', function () {
    $lider = liderDaFila('C1');
    $ciclo = collect(ciclosServidos($lider))
        ->first(static fn (array $c): bool => $c['aba'] === 'andamento' && $c['vistoria_pendente'] !== null && $c['demanda'] !== null);

    expect($ciclo)->not->toBeNull('a Equipe C1 precisa de retorno de campo pendente para demonstrar');

    $this->actingAs($lider)
        ->post('/retaguarda/fiscalizacoes/encaminhar-ao-chefe', [
            'ids' => [$ciclo['id']],
            'motivo' => 'Ponto regularizado na vistoria; cabe responder à origem.',
        ])
        ->assertSessionHas('flash.sucesso');

    $gravado = CicloDeFiscalizacao::with('demanda')->findOrFail($ciclo['id']);

    expect($gravado->aba())->toBe(CicloDeFiscalizacao::ABA_ENCAMINHADAS)
        ->and($gravado->posse)->toBe(CicloDeFiscalizacao::POSSE_CHEFE)
        ->and($gravado->motivo_do_encaminhamento)->toContain('regularizado')
        // A vistoria que esperava a leitura fica carimbada.
        ->and(estadoNoBanco((int) $ciclo['vistoria_pendente']))->toBe(Fiscalizacao::DEVOLVIDA)
        // A demanda volta à mesa do chefe, com o resultado no trâmite.
        ->and($gravado->demanda->situacao)->toBe(Demanda::RECEBIDA)
        ->and($gravado->demanda->ultimoTramite()->campos)->toHaveKey('Fiscalização');

    // Na tela, a coluna Posse atual diz com quem está.
    $servido = collect(ciclosServidos($lider))->firstWhere('id', $ciclo['id']);
    expect($servido['posse'])->toBe('Chefe de Setor')->and($servido['aba'])->toBe('encaminhadas');
});

test('mandar a equipe voltar exige justificativa NO SERVIDOR, e a nova vistoria é da MESMA Fiscalização', function () {
    $lider = liderDaFila('C1');
    $ciclo = collect(ciclosServidos($lider))->first(static fn (array $c): bool => $c['vistoria_pendente'] !== null);

    $this->actingAs($lider)
        ->post('/retaguarda/fiscalizacoes/nova-vistoria', ['ids' => [$ciclo['id']]])
        ->assertSessionHasErrors('justificativa');

    $this->actingAs($lider)
        ->post('/retaguarda/fiscalizacoes/nova-vistoria', ['ids' => [$ciclo['id']], 'justificativa' => 'voltar lá'])
        ->assertSessionHasErrors('justificativa');

    expect(estadoNoBanco((int) $ciclo['vistoria_pendente']))->toBe(Fiscalizacao::AGUARDANDO_LEITURA);

    $this->actingAs($lider)
        ->post('/retaguarda/fiscalizacoes/nova-vistoria', [
            'ids' => [$ciclo['id']],
            'justificativa' => 'O ponto monta depois das 19h — voltar no horário indicado pela vizinhança.',
        ])
        ->assertSessionHas('flash.sucesso');

    expect(estadoNoBanco((int) $ciclo['vistoria_pendente']))->toBe(Fiscalizacao::NOVA_VISTORIA)
        // Continua com a equipe: é a mesma Fiscalização, e o desfecho muda conforme avança.
        ->and(CicloDeFiscalizacao::findOrFail($ciclo['id'])->aba())->toBe(CicloDeFiscalizacao::ABA_ANDAMENTO);
});

test('o lote com Fiscalização de outra equipe não altera nada', function () {
    $lider = liderDaFila('C1');
    $minhas = Estrutura::equipesDoLider('lider-c1');
    $meu = collect(ciclosServidos($lider))->first(static fn (array $c): bool => $c['vistoria_pendente'] !== null);
    $alheio = CicloDeFiscalizacao::with('equipe')->get()
        ->first(static fn (CicloDeFiscalizacao $c): bool => ! in_array((string) $c->equipe?->codigo, $minhas, true));

    $this->actingAs($lider)
        ->post('/retaguarda/fiscalizacoes/nova-vistoria', [
            'ids' => [$meu['id'], $alheio->id],
            'justificativa' => 'Voltar no fim da tarde, quando as mesas saem para a calçada.',
        ])
        ->assertSessionHas('flash.erro');

    expect(estadoNoBanco((int) $meu['vistoria_pendente']))->toBe(Fiscalizacao::AGUARDANDO_LEITURA);
});

test('o administrador vê tudo e conduz qualquer Fiscalização', function () {
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);
    $ciclos = ciclosServidos($admin);

    expect($ciclos)->toHaveCount(CicloDeFiscalizacao::count());

    $alvo = collect($ciclos)->firstWhere('aba', 'andamento');

    $this->actingAs($admin)
        ->post('/retaguarda/fiscalizacoes/encaminhar-ao-chefe', [
            'ids' => [$alvo['id']],
            'motivo' => 'Encaminhando pela administração para demonstrar o fluxo.',
        ])
        ->assertSessionHas('flash.sucesso');
});

test('o fiscal CONSULTA e nao conduz: a tela nao lhe oferece, e o servidor recusa', function () {
    $fiscal = User::factory()->create(['admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());
    $fiscal = $fiscal->fresh();

    $this->actingAs($fiscal)->get('/retaguarda/fiscalizacoes')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Retaguarda/Fiscalizacao/Fiscalizacoes')->where('conduz', false));

    $ciclo = CicloDeFiscalizacao::daAba(CicloDeFiscalizacao::ABA_ANDAMENTO)->firstOrFail();

    $this->actingAs($fiscal)
        ->post('/retaguarda/fiscalizacoes/encaminhar-ao-chefe', [
            'ids' => [$ciclo->id],
            'motivo' => 'O fiscal tentando encaminhar o próprio trabalho.',
        ])
        ->assertRedirect()
        ->assertSessionHas('flash.erro', fn (string $r): bool => trim($r) !== '');

    expect($ciclo->fresh()->posse)->toBe(CicloDeFiscalizacao::POSSE_EQUIPE);
});

test('o fiscal ve o acervo INTEIRO — limite declarado, nao esquecimento', function () {
    /*
     * Ele NÃO é recortado (o recorte é do líder) e não existe vínculo entre a
     * CONTA dele e os registros que ela assinou. Este teste PRENDE o comportamento
     * ao que está escrito (PEND-021).
     */
    $fiscal = User::factory()->create(['admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());

    $pagina = test()->actingAs($fiscal->fresh())->get('/retaguarda/fiscalizacoes')->viewData('page')['props'];

    expect($pagina['recorteDeEquipe'])->toBeFalse()
        ->and($pagina['fiscalizacoes'])->toHaveCount(CicloDeFiscalizacao::count());
});

test('quando o chefe devolve o processo à origem, as Fiscalizações dele vão para o ARQUIVO — e seguem consultáveis', function () {
    $lider = liderDaFila('C1');
    $ciclo = collect(ciclosServidos($lider))
        ->first(static fn (array $c): bool => $c['aba'] === 'andamento' && $c['vistoria_pendente'] !== null && $c['demanda'] !== null);

    $this->actingAs($lider)->post('/retaguarda/fiscalizacoes/encaminhar-ao-chefe', [
        'ids' => [$ciclo['id']],
        'motivo' => 'Resultado da vistoria para o chefe responder à origem.',
    ]);

    $demanda = Demanda::findOrFail($ciclo['demanda']['id']);

    $this->actingAs(chefeDaFila())
        ->post(route('retaguarda.denuncias.responder-ao-canal', $demanda), [
            'texto' => 'A equipe esteve no local e o ponto foi regularizado. Processo respondido.',
            'processo' => '215.5382.009999/2026',
        ])
        ->assertSessionHas('flash.sucesso');

    $servido = collect(ciclosServidos($lider))->firstWhere('id', $ciclo['id']);

    expect($servido['aba'])->toBe('arquivo')
        // O arquivo carrega a prova: as vistorias continuam inteiras dentro dela.
        ->and($servido['vistorias'])->not->toBe([]);
});

test('responder à origem com a Fiscalização ainda com a equipe é recusado: o líder encaminha antes', function () {
    $ciclo = CicloDeFiscalizacao::with('demanda')->daAba(CicloDeFiscalizacao::ABA_ANDAMENTO)
        ->whereNotNull('demanda_id')->get()
        ->first(static fn (CicloDeFiscalizacao $c): bool => $c->demanda->situacao !== Demanda::ENCAMINHADA_AO_LIDER);

    $ciclo->demanda->forceFill(['situacao' => Demanda::CONCLUIDA])->save();

    $this->actingAs(chefeDaFila())
        ->post(route('retaguarda.denuncias.responder-ao-canal', $ciclo->demanda), [
            'texto' => 'Tentando responder antes de o líder encaminhar o resultado.',
            'processo' => '215.5382.000001/2026',
        ])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'ainda está com a equipe'));

    expect($ciclo->fresh()->aba())->toBe(CicloDeFiscalizacao::ABA_ANDAMENTO);
});

test('um novo encaminhamento do chefe abre uma Fiscalização IRMÃ, e as duas ficam consultáveis', function () {
    $lider = liderDaFila('C1');
    $ciclo = collect(ciclosServidos($lider))
        ->first(static fn (array $c): bool => $c['aba'] === 'andamento' && $c['vistoria_pendente'] !== null && $c['demanda'] !== null);

    $this->actingAs($lider)->post('/retaguarda/fiscalizacoes/encaminhar-ao-chefe', [
        'ids' => [$ciclo['id']],
        'motivo' => 'Situação mantida; o chefe decide se pede nova fiscalização.',
    ]);

    $this->actingAs(chefeDaFila())
        ->post(route('retaguarda.denuncias.encaminhar'), [
            'destinos' => [['id' => $ciclo['demanda']['id'], 'equipe' => 'C1']],
        ])
        ->assertSessionHas('flash.sucesso');

    $ciclos = CicloDeFiscalizacao::where('demanda_id', $ciclo['demanda']['id'])->orderBy('id')->get();

    expect($ciclos)->toHaveCount(2)
        ->and($ciclos[0]->aba())->toBe(CicloDeFiscalizacao::ABA_ENCAMINHADAS)
        ->and($ciclos[1]->aba())->toBe(CicloDeFiscalizacao::ABA_ANDAMENTO);

    $nova = collect(ciclosServidos($lider))->firstWhere('id', $ciclos[1]->id);

    expect($nova['desfecho'])->toBe('Aguardando envio à equipe')
        ->and($nova['posse'])->toBe('Líder de Equipe')
        ->and(array_column($nova['irmas'], 'id'))->toContain($ciclos[0]->id);
});

test('a POSSE distingue o líder da equipe: decisão do líder é "Líder de Equipe", fiscais no ponto é "Equipe"', function () {
    $lider = liderDaFila('C1');
    $demanda = Demanda::where('situacao', Demanda::RECEBIDA)->whereNull('agrupada_em_id')->whereDoesntHave('ciclos')->firstOrFail();

    $this->actingAs(chefeDaFila())
        ->post(route('retaguarda.denuncias.encaminhar'), ['destinos' => [['id' => $demanda->id, 'equipe' => 'C1']]])
        ->assertSessionHas('flash.sucesso');

    $posseDaDemanda = static fn (): string => collect(ciclosServidos($lider))
        ->first(static fn (array $c): bool => ($c['demanda']['id'] ?? null) === $demanda->id)['posse'];

    // Chegou do chefe: quem tem de agir é o líder, enviando aos fiscais.
    expect($posseDaDemanda())->toBe('Líder de Equipe');

    $this->actingAs($lider)
        ->post(route('retaguarda.denuncias.direcionar'), ['ids' => [$demanda->id]])
        ->assertSessionHas('flash.sucesso');

    // Enviada: agora está com os fiscais.
    expect($posseDaDemanda())->toBe('Equipe');
});

test('o semeador da demonstração deixa ao líder Fiscalizações vindas do chefe E retornos concluídos pela equipe', function () {
    $this->seed(FiscalizacoesParaOLiderSeeder::class);

    $semeadas = Demanda::whereHas('tramites', static fn ($q) => $q->where('detalhe', FiscalizacoesParaOLiderSeeder::OBSERVACAO))->pluck('id')->all();
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);
    $ciclos = collect(ciclosServidos($admin))->filter(static fn (array $c): bool => in_array($c['demanda']['id'] ?? null, $semeadas, true));

    $vindas = $ciclos->where('desfecho', 'Aguardando envio à equipe');
    $concluidas = $ciclos->filter(static fn (array $c): bool => $c['vistoria_pendente'] !== null);

    expect($ciclos)->toHaveCount(FiscalizacoesParaOLiderSeeder::VINDAS_DO_CHEFE + FiscalizacoesParaOLiderSeeder::CONCLUIDAS)
        ->and($ciclos->pluck('aba')->unique()->values()->all())->toBe(['andamento'])
        ->and($ciclos->pluck('posse')->unique()->values()->all())->toBe(['Líder de Equipe'])
        ->and($vindas)->toHaveCount(FiscalizacoesParaOLiderSeeder::VINDAS_DO_CHEFE)
        ->and($concluidas)->toHaveCount(FiscalizacoesParaOLiderSeeder::CONCLUIDAS);

    // O retorno concluído chega com a prova: relato e fotos.
    $vistoria = $concluidas->first()['vistorias'][0];
    expect($vistoria['consideracoes'])->not->toBeEmpty();

    // Idempotente: rodar de novo não cria nada.
    $antes = CicloDeFiscalizacao::count();
    $this->seed(FiscalizacoesParaOLiderSeeder::class);
    expect(CicloDeFiscalizacao::count())->toBe($antes);
});

test('o chefe arquiva a Fiscalização sem processo; a que tem processo só vai ao Arquivo pela origem', function () {
    $semDemanda = CicloDeFiscalizacao::whereNull('demanda_id')->firstOrFail();
    $semDemanda->forceFill(['posse' => CicloDeFiscalizacao::POSSE_CHEFE, 'arquivado_em' => null])->save();
    $comDemanda = CicloDeFiscalizacao::whereNotNull('demanda_id')->firstOrFail();
    $comDemanda->forceFill(['posse' => CicloDeFiscalizacao::POSSE_CHEFE, 'arquivado_em' => null])->save();

    $chefe = chefeDaFila();

    $this->actingAs($chefe)->post('/retaguarda/fiscalizacoes/arquivar', ['ids' => [$comDemanda->id]])
        ->assertSessionHas('flash.erro', fn (string $r): bool => str_contains($r, 'origem'));

    $this->actingAs($chefe)->post('/retaguarda/fiscalizacoes/arquivar', ['ids' => [$semDemanda->id]])
        ->assertSessionHas('flash.sucesso');

    expect($semDemanda->fresh()->aba())->toBe(CicloDeFiscalizacao::ABA_ARQUIVO)
        ->and($comDemanda->fresh()->aba())->toBe(CicloDeFiscalizacao::ABA_ENCAMINHADAS);
});

test('a demanda na Caixa de Entrada lista as Fiscalizações dela, com o caminho para abrir cada uma', function () {
    $ciclo = CicloDeFiscalizacao::with('demanda')->whereNotNull('demanda_id')->firstOrFail();
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);
    $rota = match ($ciclo->demanda->canal) {
        Demanda::CANAL_FALA_SALVADOR => 'retaguarda.denuncias.fala-salvador.index',
        default => 'retaguarda.denuncias.e-salvador.index',
    };

    $this->actingAs($admin)->get(route($rota))
        ->assertInertia(function ($p) use ($ciclo) {
            $props = $p->toArray()['props'];
            $linha = collect([...$props['denuncias'], ...$props['licencas']])->firstWhere('id', $ciclo->demanda_id);

            expect($linha['fiscalizacoes'])->not->toBe([])
                ->and(collect($linha['fiscalizacoes'])->firstWhere('id', $ciclo->id)['url'])
                ->toEndWith('?fiscalizacao='.$ciclo->id);

            return $p;
        });

    // E o caminho abre a Fiscalização na tela dela.
    $this->actingAs($admin)->get('/retaguarda/fiscalizacoes?fiscalizacao='.$ciclo->id)
        ->assertInertia(fn ($p) => $p->where('abrir', $ciclo->id));
});

test('o PRAZO de retorno sai da notificacao e a conta e do servidor', function () {
    /*
     * O prazo é a única informação da fila que continua correndo depois de a chefia
     * dar ciência — é por isso que ele mora no acervo. E a conta é do SERVIDOR: no
     * navegador ela dependeria do relógio e do fuso da máquina de quem abre a tela,
     * e "vence amanhã" viraria "venceu ontem".
     *
     * Só a NOTIFICAÇÃO PRELIMINAR tem prazo de retorno: ela dá um tempo para
     * regularizar, e alguém tem de voltar no vencimento — sem retorno, ela fica no
     * papel. O Auto de Apreensão não pede volta ao ponto.
     */
    $fila = filaDoBanco();

    $comPrazo = array_values(array_filter(
        $fila,
        static fn (array $r): bool => $r['prazo'] !== null,
    ));

    expect($comPrazo)->not->toBe([], 'a amostra precisa de notificado com prazo correndo');

    $erros = [];

    foreach ($fila as $r) {
        $tipo = $r['documento']['tipo'] ?? null;

        // Prazo sem notificação preliminar atrás é prazo inventado.
        if ($r['prazo'] !== null && $tipo !== 'np') {
            $erros[] = "{$r['protocolo']}: prazo sem Notificação Preliminar";
        }

        // E notificação com prazo declarado tem de PRODUZIR prazo: um registro que
        // trouxesse a notificação e nenhuma data significaria, na tela, "sem prazo
        // correndo" — e ninguém voltaria ao ponto.
        if ($tipo === 'np' && $r['documento']['vence_em'] !== null && $r['prazo'] === null) {
            $erros[] = "{$r['protocolo']}: notificação com vencimento e sem prazo na tela";
        }

        if ($r['prazo'] === null) {
            continue;
        }

        /*
         * O vencimento é DEPOIS da vistoria, e o sinal dos dias concorda com o
         * "vencido": um prazo que dissesse "vence em 3 dias" e "vencido" ao mesmo
         * tempo é o defeito que dois campos para a mesma verdade produzem.
         */
        if ($r['prazo']['vence_em'] < substr((string) $r['concluida_em'], 0, 10)) {
            $erros[] = "{$r['protocolo']}: prazo vence antes da vistoria";
        }

        if ($r['prazo']['vencido'] !== ($r['prazo']['dias'] < 0)) {
            $erros[] = "{$r['protocolo']}: o sinalizador de vencido discorda da contagem de dias";
        }
    }

    expect($erros)->toBe([], 'O prazo sai da Notificação Preliminar, e a conta é do servidor.
');
});

test('lei: o vencimento do prazo tem UM dono — a tela LE a data, nao a recalcula', function () {
    /*
     * O prazo do documento tem uma data GRAVADA (`documentos_campo.prazo_ate`),
     * e é ela que vence. A tela lê essa data; não a recalcula a partir da chave
     * do catálogo.
     *
     * Por que isso é lei: a duração de "48 horas" mora no catálogo do impresso e
     * pode mudar. Se a tela recalculasse, todo documento lavrado ANTES da mudança
     * passaria a vencer num dia diferente do que está no papel que o notificado
     * tem na mão — e a chefia voltaria ao ponto no dia errado.
     */
    $comPrazo = DocumentoCampo::whereNotNull('prazo_ate')->get();

    expect($comPrazo)->not->toBeEmpty('a amostra precisa de documento com prazo');

    $divergentes = [];

    foreach (filaDoBanco() as $r) {
        $numero = $r['documento']['numero'] ?? null;

        if ($numero === null) {
            continue;
        }

        $gravado = $comPrazo->firstWhere('numero', (string) $numero);

        if ($gravado === null) {
            continue;
        }

        $daTela = $r['documento']['vence_em'] ?? null;
        $daColuna = $gravado->prazo_ate->format('Y-m-d');

        if ($daTela !== $daColuna) {
            $divergentes[] = "{$r['protocolo']} (doc {$numero}): coluna '{$daColuna}', tela ".var_export($daTela, true);
        }
    }

    expect($divergentes)->toBe([], 'a tela nao esta lendo a data gravada:
'.implode('
', $divergentes));
});

test('o CONTADOR do menu é o trabalho de cada um: o líder, o que pede decisão dele; o chefe, o que chegou a ele', function () {
    $contadorDe = function (User $u): mixed {
        $menu = test()->actingAs($u)->get('/retaguarda/inicio')->viewData('page')['props']['menu'];

        foreach (collect($menu)->pluck('itens')->flatten(1) as $item) {
            if (($item['url'] ?? null) === route('retaguarda.fiscalizacoes.index', absolute: false)) {
                return $item['contador'] ?? null;
            }
        }

        return null;
    };

    $lider = liderDaFila('C1');
    $aDecidir = count(array_filter(ciclosServidos($lider), static fn (array $c): bool => $c['a_decidir']));

    expect($aDecidir)->toBeGreaterThan(0)
        ->and($contadorDe($lider)['valor'] ?? null)->toBe($aDecidir);

    $encaminhadas = CicloDeFiscalizacao::daAba(CicloDeFiscalizacao::ABA_ENCAMINHADAS)->count();
    $chefe = chefeDaFila();

    expect($contadorDe($chefe)['valor'] ?? null)->toBe($encaminhadas > 0 ? $encaminhadas : null);

    // Quem apenas consulta não recebe número.
    $fiscal = User::factory()->create(['admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());

    expect($contadorDe($fiscal->fresh()))->toBeNull();
});

test('o recorte visivel da fila vira documento pelo ponto unico de exportacao', function () {
    /*
     * A lei do projeto: toda listagem exporta, e pelo endpoint único — nenhuma
     * tela gera arquivo por conta própria. O que se prova aqui é que o recorte da
     * fila atravessa aquele endpoint com as colunas que a tela declara.
     */
    $this->actingAs(liderDaFila('C1'))
        ->post('/retaguarda/exportar-listagem', [
            'formato' => 'xlsx',
            'titulo' => 'Retorno de Campo',
            'subtitulo' => 'Fiscalização › Retorno de Campo',
            'contexto' => 'Aba: A ler · Áreas: Área 5',
            'colunas' => [
                ['chave' => 'protocolo', 'titulo' => 'Registro'],
                ['chave' => 'recomendacoes', 'titulo' => 'Recomendação do fiscal'],
            ],
            'linhas' => [
                ['protocolo' => 'FIS-1029', 'recomendacoes' => 'Voltar ao ponto no vencimento do prazo'],
            ],
        ])
        ->assertOk()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
});
