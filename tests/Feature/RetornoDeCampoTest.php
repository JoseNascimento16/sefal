<?php

use App\Models\Setor;
use App\Models\User;
use App\Support\Prototipo\DenunciasFicticias;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\RetornoDeCampoFicticio;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;

/*
|--------------------------------------------------------------------------
| Retorno de Campo — a fila do Chefe de Setor
|--------------------------------------------------------------------------
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
| A régua é o doc de regra (`docs/regras-de-negocio/fiscalizacao/retorno-de-campo.md`)
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
        ->get('/retaguarda/retorno-de-campo')
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
    $fila = RetornoDeCampoFicticio::registros();

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

    foreach (RetornoDeCampoFicticio::registros() as $registro) {
        expect($registro)->toHaveKeys($chaves, (string) ($registro['protocolo'] ?? '?'));
    }
});

test('lei: a recomendacao semeada na fiscalizacao avulsa sai do catalogo do aplicativo', function () {
    /*
     * O catálogo é o contrato com o aplicativo do fiscal: atalho escrito à mão no
     * arquivo de dados seria uma recomendação que a Retaguarda não sabe ler — e
     * que o relatório não sabe somar.
     */
    $catalogo = (array) config('prototipo_denuncias.recomendacoes_do_fiscal', []);
    $problemas = [];

    foreach ((array) config('prototipo_registros_de_campo.registros', []) as $r) {
        foreach ((array) ($r['recomendacoes'] ?? []) as $recomendacao) {
            if (! in_array((string) $recomendacao, $catalogo, true)) {
                $problemas[] = "FIS-{$r['id']}: recomendação fora do catálogo '{$recomendacao}'";
            }
        }

        if (! in_array((string) $r['desfecho'], (array) config('prototipo_denuncias.desfechos', []), true)) {
            $problemas[] = "FIS-{$r['id']}: desfecho fora do catálogo '{$r['desfecho']}'";
        }
    }

    expect($problemas)->toBe([], "Recomendação e desfecho saem de catálogo — não são texto livre.\n");
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
    foreach (RetornoDeCampoFicticio::registros() as $registro) {
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
        ->get('/retaguarda/retorno-de-campo')
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
    $registro = RetornoDeCampoFicticio::registros()[0];

    $this->actingAs($coordenador)
        ->post('/retaguarda/retorno-de-campo/ciencia', ['ids' => [$registro['id']]])
        ->assertRedirect()
        ->assertSessionHas('flash.erro', fn (string $recado): bool => str_contains($recado, 'Chefe de Setor da área'));

    expect(RetornoDeCampoFicticio::registro((int) $registro['id'])['estado'])
        ->toBe(RetornoDeCampoFicticio::AGUARDANDO);
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

    $alheio = collect(RetornoDeCampoFicticio::registros())
        ->first(static fn (array $r): bool => ! in_array((string) $r['area'], $minhas, true));

    expect($alheio)->not->toBeNull();

    $this->actingAs($chefe)
        ->post('/retaguarda/retorno-de-campo/ciencia', ['ids' => [$alheio['id']]])
        ->assertRedirect()
        ->assertSessionHas(
            'flash.erro',
            fn (string $recado): bool => str_contains($recado, (string) $alheio['protocolo'])
                && str_contains($recado, 'Nada foi alterado'),
        );

    expect(RetornoDeCampoFicticio::registro((int) $alheio['id'])['estado'])
        ->toBe(RetornoDeCampoFicticio::AGUARDANDO);
});

test('a ciencia do chefe de setor tira o registro da fila e deixa o ato registrado', function () {
    $chefe = chefeDaFila('gestor1');
    $meus = array_column(filaServida($chefe), 'id');

    expect($meus)->not->toBe([]);

    $this->actingAs($chefe)
        ->post('/retaguarda/retorno-de-campo/ciencia', [
            'ids' => [$meus[0]],
            'observacao' => 'Lido; o ponto entra na ronda da semana.',
        ])
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    $registro = RetornoDeCampoFicticio::registro((int) $meus[0]);

    expect($registro['estado'])->toBe(RetornoDeCampoFicticio::CIENTE)
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
        ->post('/retaguarda/retorno-de-campo/nova-vistoria', ['ids' => [$meus[0]]])
        ->assertSessionHasErrors('justificativa');

    // Curta demais também é recusada: "voltar lá" não conta à equipe o que ela
    // deve procurar desta vez.
    $this->actingAs($chefe)
        ->post('/retaguarda/retorno-de-campo/nova-vistoria', [
            'ids' => [$meus[0]],
            'justificativa' => 'voltar lá',
        ])
        ->assertSessionHasErrors('justificativa');

    expect(RetornoDeCampoFicticio::registro((int) $meus[0])['estado'])
        ->toBe(RetornoDeCampoFicticio::AGUARDANDO);

    $this->actingAs($chefe)
        ->post('/retaguarda/retorno-de-campo/nova-vistoria', [
            'ids' => [$meus[0]],
            'justificativa' => 'O ponto monta depois das 19h — voltar no horário indicado pela vizinhança.',
        ])
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    expect(RetornoDeCampoFicticio::registro((int) $meus[0])['estado'])
        ->toBe(RetornoDeCampoFicticio::NOVA_VISTORIA);
});

test('a decisao em lote conta o efeito, e lote inteiro fora da area nao altera nada', function () {
    $chefe = chefeDaFila('gestor1');
    $meus = array_column(filaServida($chefe), 'id');
    $minhas = EstruturaFicticia::areasDoChefe('gestor1');

    $alheio = collect(RetornoDeCampoFicticio::registros())
        ->first(static fn (array $r): bool => ! in_array((string) $r['area'], $minhas, true));

    /*
     * O lote MISTO é o caminho fácil para alcançar o que não se vê: um
     * identificador da própria área junto de um de fora. A recusa é do lote
     * inteiro — aplicar a parte válida deixaria a fronteira valendo pela metade,
     * e quem montou a requisição sairia com metade do que pediu.
     */
    $this->actingAs($chefe)
        ->post('/retaguarda/retorno-de-campo/ciencia', ['ids' => [$meus[0], $alheio['id']]])
        ->assertSessionHas('flash.erro');

    expect(RetornoDeCampoFicticio::registro((int) $meus[0])['estado'])
        ->toBe(RetornoDeCampoFicticio::AGUARDANDO)
        ->and(RetornoDeCampoFicticio::registro((int) $alheio['id'])['estado'])
        ->toBe(RetornoDeCampoFicticio::AGUARDANDO);
});

test('o administrador ve tudo e decide sobre qualquer area', function () {
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);
    $servidos = filaServida($admin);

    expect($servidos)->toHaveCount(count(RetornoDeCampoFicticio::registros()));

    $this->actingAs($admin)
        ->post('/retaguarda/retorno-de-campo/ciencia', ['ids' => [$servidos[0]['id']]])
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');
});

test('o fiscal nao entra na fila: ele e barrado ao inicio, dizendo o porque', function () {
    /*
     * Quem escreveu o retorno foi o fiscal. Dar-lhe a fila permitiria dar ciência
     * do próprio trabalho, o que apaga a conferência que ela existe para provocar.
     */
    $fiscal = User::factory()->create(['admin' => false, 'ativo' => true]);
    $fiscal->setores()->attach(Setor::where('slug', 'fiscal')->firstOrFail());

    $this->actingAs($fiscal->fresh())
        ->get('/retaguarda/retorno-de-campo')
        ->assertRedirect(route('retaguarda.inicio'))
        ->assertSessionHas('flash.erro');
});

test('reiniciar devolve a fila ao estado de demonstracao', function () {
    $chefe = chefeDaFila('gestor1');
    $meus = array_column(filaServida($chefe), 'id');

    $this->actingAs($chefe)->post('/retaguarda/retorno-de-campo/ciencia', ['ids' => [$meus[0]]]);

    expect(RetornoDeCampoFicticio::alterada())->toBeTrue();

    $this->actingAs($chefe)->post('/retaguarda/retorno-de-campo/reiniciar')->assertRedirect();

    expect(RetornoDeCampoFicticio::alterada())->toBeFalse()
        ->and(RetornoDeCampoFicticio::registro((int) $meus[0])['estado'])
        ->toBe(RetornoDeCampoFicticio::AGUARDANDO);
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
