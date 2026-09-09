<?php

use App\Models\Setor;
use App\Models\User;
use App\Support\Prototipo\DenunciasFicticias;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\FiscalizacoesFicticias;
use App\Support\Prototipo\RecomendacoesDoFiscal;
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
});

/** Um Chefe de Setor de verdade: a matrícula é o que o liga à área na estrutura. */
function chefeDaFila(string $matricula): User
{
    $u = User::factory()->create(['login' => $matricula, 'admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    return $u->fresh();
}

function coordenadorDaFila(): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'coordenador')->firstOrFail());

    return $u->fresh();
}

/** Os registros que o servidor entrega a esta pessoa. */
function filaServida(User $u): array
{
    return test()->actingAs($u)
        ->get('/retaguarda/fiscalizacoes')
        ->viewData('page')['props']['registros'];
}

test('a fila deriva do tramite: todo desfecho de campo vira um registro, e nenhum a mais', function () {
    /*
     * Um registro por DENÚNCIA que já teve desfecho — não um por passo. A denúncia
     * que foi notificada e depois regularizada tem DOIS passos com desfecho, e o
     * que voltou para a chefia é onde a coisa parou: contar os dois duplicaria a
     * mesma vistoria na fila.
     */
    $comDesfecho = array_values(array_filter(
        DenunciasFicticias::todas(),
        static fn (array $d): bool => $d['desfecho'] !== null,
    ));

    $avulsas = (array) config('prototipo_registros_de_campo.registros', []);
    $fila = FiscalizacoesFicticias::registros();

    expect($comDesfecho)->not->toBe([], 'a amostra precisa de denúncia com desfecho')
        ->and($avulsas)->not->toBe([], 'a amostra precisa de fiscalização avulsa')
        ->and($fila)->toHaveCount(count($comDesfecho) + count($avulsas));

    // O desfecho de cada registro derivado é o do ÚLTIMO passo com desfecho — o
    // mesmo que a denúncia carrega no resumo. Fonte única, provada.
    $porProtocolo = [];

    foreach ($fila as $registro) {
        if ($registro['denuncia_protocolo'] !== null) {
            $porProtocolo[(string) $registro['denuncia_protocolo']] = (string) $registro['desfecho'];
        }
    }

    $divergentes = [];

    foreach ($comDesfecho as $d) {
        $daFila = $porProtocolo[(string) $d['protocolo']] ?? null;

        if ($daFila !== (string) $d['desfecho']) {
            $divergentes[] = "{$d['protocolo']}: denúncia '{$d['desfecho']}', fila '".((string) $daFila)."'";
        }
    }

    expect($divergentes)->toBe([], "O desfecho da fila é o mesmo da denúncia — a fila DERIVA dela.\n");
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

    foreach (FiscalizacoesFicticias::registros() as $registro) {
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
    $pagina = test()->actingAs(chefeDaFila('gestor1'))
        ->get('/retaguarda/fiscalizacoes')
        ->viewData('page')['props'];

    $catalogo = $pagina['recomendacoesDoFiscal'];

    expect($catalogo)->toBeArray()
        ->and($catalogo['retorno'])->toBe('Voltar ao ponto no vencimento do prazo')
        // O SEAB é o atalho que veio do dono: as duas redações são iguais porque
        // ninguém aqui sabe o que a sigla expande.
        ->and($catalogo['seab'])->toBe('Encaminhar ao SEAB');

    $comRecomendacao = array_values(array_filter(
        $pagina['registros'],
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
    $codigos = EstruturaFicticia::codigosDeEquipe();
    $problemas = [];

    foreach ((array) config('prototipo_registros_de_campo.registros', []) as $r) {
        if (! in_array((string) $r['equipe'], $codigos, true)) {
            $problemas[] = "FIS-{$r['id']}: equipe inexistente '{$r['equipe']}'";
        }
    }

    expect($problemas)->toBe([], "A equipe da avulsa existe na estrutura de áreas.\n");

    // E o efeito: nenhum registro da fila chega sem área nem sem quem assinou.
    foreach (FiscalizacoesFicticias::registros() as $registro) {
        expect(trim((string) $registro['area']))->not->toBe('', (string) $registro['protocolo'])
            ->and(trim((string) $registro['fiscal']))->not->toBe('', (string) $registro['protocolo']);
    }
});

test('o chefe de setor recebe so o que as equipes da area dele concluiram', function () {
    $chefe = chefeDaFila('gestor1');
    $minhas = EstruturaFicticia::areasDoChefe('gestor1');

    expect($minhas)->not->toBe([]);

    $servidos = filaServida($chefe);

    expect($servidos)->not->toBe([], 'a área do gestor1 precisa de retorno para demonstrar');

    $deFora = array_values(array_filter(
        $servidos,
        static fn (array $r): bool => ! in_array((string) $r['area'], $minhas, true),
    ));

    expect($deFora)->toBe([], 'a fila do Chefe de Setor traz só as áreas dele');
});

test('o conteudo do retorno alheio nao viaja ate o navegador do chefe de outra area', function () {
    /*
     * O vazamento que importa não é a linha da grade: é o RELATO do fiscal e o
     * número do documento, que vão dentro do registro. Então o teste procura o
     * texto no corpo inteiro da resposta, e não só na lista de identificadores.
     */
    $chefe = chefeDaFila('gestor3');

    $this->actingAs($chefe)
        ->get('/retaguarda/fiscalizacoes')
        // 194903 é a notificação da Área 5 (gestor1); "Operação Verão — Orla" é a
        // avulsa da mesma área.
        ->assertDontSee('194903')
        ->assertDontSee('Operação Verão');
});

test('quem tria ve o universo — inclusive a area sem chefe com conta', function () {
    $servidos = filaServida(coordenadorDaFila());
    $areas = array_values(array_unique(array_column($servidos, 'area')));

    // Área 2 não tem conta de Chefe de Setor na demonstração: só o Coordenador e o
    // administrador a enxergam, e é isso que faz dela a prova do recorte.
    expect($areas)->toContain('Área 2')
        ->and(count($areas))->toBeGreaterThan(1);
});

test('quem apenas acompanha e recusado COM O MOTIVO, e nada e alterado', function () {
    $coordenador = coordenadorDaFila();
    $registro = FiscalizacoesFicticias::registros()[0];

    $this->actingAs($coordenador)
        ->post('/retaguarda/fiscalizacoes/ciencia', ['ids' => [$registro['id']]])
        ->assertRedirect()
        ->assertSessionHas('flash.erro', fn (string $recado): bool => str_contains($recado, 'Chefe de Setor da área'));

    expect(FiscalizacoesFicticias::registro((int) $registro['id'])['estado'])
        ->toBe(FiscalizacoesFicticias::AGUARDANDO);
});

test('o chefe de setor e recusado NOMINALMENTE ao decidir sobre registro de outra area', function () {
    /*
     * Esconder da listagem não é fronteira: quem souber montar a requisição
     * alcança o registro de outra área, e o lote é o caminho fácil porque manda
     * uma lista de identificadores. A recusa nomeia o registro para quem clicou
     * saber o que aconteceu.
     */
    $chefe = chefeDaFila('gestor3');
    $minhas = EstruturaFicticia::areasDoChefe('gestor3');

    $alheio = collect(FiscalizacoesFicticias::registros())
        ->first(static fn (array $r): bool => ! in_array((string) $r['area'], $minhas, true));

    expect($alheio)->not->toBeNull();

    $this->actingAs($chefe)
        ->post('/retaguarda/fiscalizacoes/ciencia', ['ids' => [$alheio['id']]])
        ->assertRedirect()
        ->assertSessionHas(
            'flash.erro',
            fn (string $recado): bool => str_contains($recado, (string) $alheio['protocolo'])
                && str_contains($recado, 'Nada foi alterado'),
        );

    expect(FiscalizacoesFicticias::registro((int) $alheio['id'])['estado'])
        ->toBe(FiscalizacoesFicticias::AGUARDANDO);
});

test('a ciencia do chefe de setor tira o registro da fila e deixa o ato registrado', function () {
    $chefe = chefeDaFila('gestor1');
    $meus = array_column(filaServida($chefe), 'id');

    expect($meus)->not->toBe([]);

    $this->actingAs($chefe)
        ->post('/retaguarda/fiscalizacoes/ciencia', [
            'ids' => [$meus[0]],
            'observacao' => 'Lido; o ponto entra na ronda da semana.',
        ])
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    $registro = FiscalizacoesFicticias::registro((int) $meus[0]);

    expect($registro['estado'])->toBe(FiscalizacoesFicticias::CIENTE)
        ->and($registro['decisao']['quem'])->toBe($chefe->name)
        ->and($registro['decisao']['detalhe'])->toBe('Lido; o ponto entra na ronda da semana.')
        // Decidido deixa de estar parado: contar dias de fila do que saiu da fila
        // seria cobrar um atraso que não existe.
        ->and($registro['dias_parado'])->toBeNull();
});

test('a nova vistoria exige justificativa NO SERVIDOR, e nao so no formulario', function () {
    $chefe = chefeDaFila('gestor1');
    $meus = array_column(filaServida($chefe), 'id');

    $this->actingAs($chefe)
        ->post('/retaguarda/fiscalizacoes/nova-vistoria', ['ids' => [$meus[0]]])
        ->assertSessionHasErrors('justificativa');

    // Curta demais também é recusada: "voltar lá" não conta à equipe o que ela
    // deve procurar desta vez.
    $this->actingAs($chefe)
        ->post('/retaguarda/fiscalizacoes/nova-vistoria', [
            'ids' => [$meus[0]],
            'justificativa' => 'voltar lá',
        ])
        ->assertSessionHasErrors('justificativa');

    expect(FiscalizacoesFicticias::registro((int) $meus[0])['estado'])
        ->toBe(FiscalizacoesFicticias::AGUARDANDO);

    $this->actingAs($chefe)
        ->post('/retaguarda/fiscalizacoes/nova-vistoria', [
            'ids' => [$meus[0]],
            'justificativa' => 'O ponto monta depois das 19h — voltar no horário indicado pela vizinhança.',
        ])
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    expect(FiscalizacoesFicticias::registro((int) $meus[0])['estado'])
        ->toBe(FiscalizacoesFicticias::NOVA_VISTORIA);
});

test('a decisao em lote conta o efeito, e lote inteiro fora da area nao altera nada', function () {
    $chefe = chefeDaFila('gestor1');
    $meus = array_column(filaServida($chefe), 'id');
    $minhas = EstruturaFicticia::areasDoChefe('gestor1');

    $alheio = collect(FiscalizacoesFicticias::registros())
        ->first(static fn (array $r): bool => ! in_array((string) $r['area'], $minhas, true));

    /*
     * O lote MISTO é o caminho fácil para alcançar o que não se vê: um
     * identificador da própria área junto de um de fora. A recusa é do lote
     * inteiro — aplicar a parte válida deixaria a fronteira valendo pela metade,
     * e quem montou a requisição sairia com metade do que pediu.
     */
    $this->actingAs($chefe)
        ->post('/retaguarda/fiscalizacoes/ciencia', ['ids' => [$meus[0], $alheio['id']]])
        ->assertSessionHas('flash.erro');

    expect(FiscalizacoesFicticias::registro((int) $meus[0])['estado'])
        ->toBe(FiscalizacoesFicticias::AGUARDANDO)
        ->and(FiscalizacoesFicticias::registro((int) $alheio['id'])['estado'])
        ->toBe(FiscalizacoesFicticias::AGUARDANDO);
});

test('o administrador ve tudo e decide sobre qualquer area', function () {
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);
    $servidos = filaServida($admin);

    expect($servidos)->toHaveCount(count(FiscalizacoesFicticias::registros()));

    $this->actingAs($admin)
        ->post('/retaguarda/fiscalizacoes/ciencia', ['ids' => [$servidos[0]['id']]])
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');
});

test('o fiscal CONSULTA e nao decide: a tela nao lhe oferece, e o servidor recusa', function () {
    /*
     * A entrada do fiscal é decisão do dono (09/09/2026), com a ressalva registrada
     * de que ele é usuário do APLICATIVO — o acesso à Retaguarda existe por
     * completude, não por fluxo. Antes da unificação ele era barrado na porta.
     *
     * O que NÃO mudou é o que importa: quem escreveu o retorno foi ele, e dar-lhe a
     * decisão permitiria dar ciência do próprio trabalho, apagando a conferência que
     * a fila existe para provocar.
     *
     * As DUAS metades são provadas aqui, e nenhuma substitui a outra: a tela não lhe
     * oferece a seleção (`decide` falso — e é do servidor que essa resposta vem) e o
     * servidor recusa o ato com o motivo escrito. Esconder botão é conforto; a
     * fronteira é a recusa.
     */
    $fiscal = User::factory()->create(['admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());
    $fiscal = $fiscal->fresh();

    $this->actingAs($fiscal)->get('/retaguarda/fiscalizacoes')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Retaguarda/Fiscalizacao/Fiscalizacoes')
            ->where('decide', false));

    $registro = FiscalizacoesFicticias::registros()[0];

    /*
     * Quem responde primeiro aqui é a guarda de AÇÃO do Modo Gerente: a concessão
     * do fiscal é "apenas leitura", então a mutação é barrada antes de o controller
     * ver a requisição. Por isso o recado é o dela, e não o do papel — e é por isso
     * que este teste não exige o texto do controller: exigi-lo faria a prova
     * depender de qual das duas guardas atende, e as duas barram.
     *
     * O que a lei do projeto cobra está cobrado: a recusa é EXPLÍCITA (há
     * `flash.erro`, e não tela em branco) e nada foi alterado. A recusa por PAPEL,
     * com o texto do controller, é provada no teste do Coordenador — que passa pela
     * permissão e é barrado pela regra de quem decide.
     */
    $this->actingAs($fiscal)
        ->post('/retaguarda/fiscalizacoes/ciencia', ['ids' => [$registro['id']]])
        ->assertRedirect()
        ->assertSessionHas('flash.erro', fn (string $recado): bool => trim($recado) !== '');

    expect(FiscalizacoesFicticias::registro((int) $registro['id'])['estado'])
        ->toBe(FiscalizacoesFicticias::AGUARDANDO);
});

test('o fiscal ve o acervo INTEIRO — limite declarado, nao esquecimento', function () {
    /*
     * Ele NÃO é recortado por área (o recorte é do Chefe de Setor) e não existe
     * vínculo entre a CONTA dele e os registros que ela assinou: o registro guarda o
     * NOME de quem assinou, a estrutura guarda a MATRÍCULA do fiscal na equipe, e
     * nada liga os dois. Casar por nome seria adivinhar — e adivinhar em fronteira
     * de dados é pior que não ter fronteira, porque cria a impressão de que existe
     * uma.
     *
     * Este teste NÃO defende o comportamento: ele o PRENDE ao que está escrito. A
     * frase da tela diz "você consulta o que a fiscalização registrou", e não "o que
     * você registrou"; a pendência está em PEND-021. Se alguém for estreitar isso,
     * este teste vermelho é a conversa acontecendo — em vez de a tela e o doc
     * discordarem em silêncio.
     */
    $fiscal = User::factory()->create(['admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());

    $pagina = test()->actingAs($fiscal->fresh())
        ->get('/retaguarda/fiscalizacoes')
        ->viewData('page')['props'];

    expect($pagina['recorteDeArea'])->toBeFalse()
        ->and($pagina['registros'])->toHaveCount(count(FiscalizacoesFicticias::registros()));
});

test('o ACERVO guarda o que a fila perdeu: o decidido segue consultavel, com a prova do ponto', function () {
    /*
     * A razão de ser da segunda aba. Depois da ciência o registro sai da FILA — e se
     * saísse do sistema, ninguém responderia "o que foi feito naquele ponto?".
     *
     * A prova é sobre a FONTE, que é uma só: o mesmo conjunto que a fila corta pelo
     * estado. E sobre o que o acervo carrega a mais — quem foi encontrado, as fotos
     * e a coordenada —, que é o que transforma consulta em prova.
     */
    $chefe = chefeDaFila('gestor1');
    $meus = array_column(filaServida($chefe), 'id');

    $this->actingAs($chefe)->post('/retaguarda/fiscalizacoes/ciencia', ['ids' => [$meus[0]]]);

    $servidos = filaServida($chefe);
    $decidido = collect($servidos)->firstWhere('id', $meus[0]);

    /*
     * Continua sendo entregue à tela (é o acervo) e já não está aguardando (saiu da
     * fila). As duas coisas: se ele deixasse de ser entregue, a aba Acervo não teria
     * como mostrá-lo.
     */
    expect($decidido)->not->toBeNull('o registro decidido tem de continuar no acervo')
        ->and($decidido['estado'])->toBe(FiscalizacoesFicticias::CIENTE);

    // E o acervo carrega a prova do que foi feito: ao menos um registro com foto e
    // coordenada — senão a aba responderia "o que foi feito?" sem nada a mostrar.
    $comProva = array_values(array_filter(
        $servidos,
        static fn (array $r): bool => $r['fotos'] !== [] && $r['gps'] !== null,
    ));

    expect($comProva)->not->toBe([], 'o acervo precisa de registro com foto e coordenada');
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
    $comPrazo = array_values(array_filter(
        FiscalizacoesFicticias::registros(),
        static fn (array $r): bool => $r['prazo'] !== null,
    ));

    expect($comPrazo)->not->toBe([], 'a amostra precisa de notificado com prazo correndo');

    $erros = [];

    foreach (FiscalizacoesFicticias::registros() as $r) {
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
     * Teste-LEI de fonte única, e ele existe por um erro real desta entrega: a
     * primeira versão desta tela recalculava o vencimento a partir da chave do
     * prazo ("5d"), com uma expressão própria. O efeito foi imediato e silencioso —
     * a chave não sobrevive à montagem do documento do trâmite, então o acervo
     * mostrava "sem prazo correndo" justamente nos casos notificados.
     *
     * O conserto foi LER o `vence_em` que o documento do trâmite já resolve. Com
     * duas contas, bastaria o catálogo do impresso mudar "48 horas" de 2 para 3
     * dias — ou a hora da lavratura deixar de ser a da conclusão — para o trâmite
     * mostrar um vencimento e esta tela mostrar outro, e a chefia voltar ao ponto no
     * dia errado.
     *
     * O que se prova: para todo registro vindo de denúncia, a data que a tela mostra
     * é EXATAMENTE a que o documento do trâmite carrega.
     */
    $doTramite = [];

    foreach (DenunciasFicticias::todas() as $denuncia) {
        foreach ((array) ($denuncia['tramites'] ?? []) as $passo) {
            $documento = $passo['documento'] ?? null;

            if (is_array($documento) && ($documento['vence_em'] ?? null) !== null) {
                $doTramite[(string) $documento['numero']] = (string) $documento['vence_em'];
            }
        }
    }

    expect($doTramite)->not->toBe([], 'a amostra precisa de documento com vencimento no trâmite');

    $divergentes = [];
    $conferidos = 0;

    foreach (FiscalizacoesFicticias::registros() as $r) {
        $numero = $r['documento']['numero'] ?? null;

        if ($numero === null || ! array_key_exists((string) $numero, $doTramite)) {
            continue;
        }

        $conferidos++;
        $daTela = $r['prazo']['vence_em'] ?? null;

        if ($daTela !== $doTramite[(string) $numero]) {
            $divergentes[] = "{$r['protocolo']} (doc {$numero}): trâmite '{$doTramite[(string) $numero]}', "
                .'tela '.var_export($daTela, true);
        }
    }

    expect($conferidos)->toBeGreaterThan(0, 'nenhum documento do trâmite chegou à fila')
        ->and($divergentes)->toBe([], 'O vencimento é o do documento — a tela lê, não recalcula.
');
});

test('o CONTADOR do menu e a fila de quem decide, recortada pela mesma area', function () {
    /*
     * O número ao lado do item é o gatilho de trabalho: sem ele, a chefia só
     * descobre que tem retorno parado quando abre a tela.
     *
     * ⚠️ Ele tem de contar EXATAMENTE o que a tela vai mostrar. Um contador que
     * somasse o universo mostraria "12" a quem abre e encontra 3, e a diferença
     * pareceria registro perdido. E ele não conta para quem só acompanha: seria
     * cobrança sobre trabalho que não é dele.
     */
    $chefe = chefeDaFila('gestor1');

    $naFila = count(array_filter(
        filaServida($chefe),
        static fn (array $r): bool => (string) $r['estado'] === FiscalizacoesFicticias::AGUARDANDO,
    ));

    expect($naFila)->toBeGreaterThan(0);

    $contadorDe = function (User $u): mixed {
        $menu = test()->actingAs($u)->get('/retaguarda/inicio')->viewData('page')['props']['menu'];

        foreach (collect($menu)->pluck('itens')->flatten(1) as $item) {
            // O menu entrega `url`, e não o nome da rota: quem resolve o nome é o
            // middleware que monta a barra. Procurar por `rota` aqui devolveria
            // sempre nulo, e o teste passaria a provar nada.
            if (($item['url'] ?? null) === route('retaguarda.fiscalizacoes.index', absolute: false)) {
                return $item['contador'] ?? null;
            }
        }

        return null;
    };

    expect($contadorDe($chefe)['valor'] ?? null)->toBe($naFila);

    // Quem apenas acompanha não recebe número: seria cobrança sobre trabalho alheio.
    expect($contadorDe(coordenadorDaFila()))->toBeNull();
});

test('reiniciar devolve a fila ao estado de demonstracao', function () {
    $chefe = chefeDaFila('gestor1');
    $meus = array_column(filaServida($chefe), 'id');

    $this->actingAs($chefe)->post('/retaguarda/fiscalizacoes/ciencia', ['ids' => [$meus[0]]]);

    expect(FiscalizacoesFicticias::alterada())->toBeTrue();

    $this->actingAs($chefe)->post('/retaguarda/fiscalizacoes/reiniciar')->assertRedirect();

    expect(FiscalizacoesFicticias::alterada())->toBeFalse()
        ->and(FiscalizacoesFicticias::registro((int) $meus[0])['estado'])
        ->toBe(FiscalizacoesFicticias::AGUARDANDO);
});

test('o recorte visivel da fila vira documento pelo ponto unico de exportacao', function () {
    /*
     * A lei do projeto: toda listagem exporta, e pelo endpoint único — nenhuma
     * tela gera arquivo por conta própria. O que se prova aqui é que o recorte da
     * fila atravessa aquele endpoint com as colunas que a tela declara.
     */
    $this->actingAs(chefeDaFila('gestor1'))
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
