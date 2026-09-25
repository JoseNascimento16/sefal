<?php

use App\Models\Area;
use App\Models\Equipe;
use App\Models\Setor;
use App\Models\User;
use App\Support\Estrutura;
use App\Support\ListagensDaRetaguarda;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;

/*
|--------------------------------------------------------------------------
| A régua da LISTAGEM CLEAN — o que não pode voltar
|--------------------------------------------------------------------------
|
| A régua está escrita em `docs/padroes/listagem-clean.md` e aplicada em
| `config/listagens_da_retaguarda.php`. Este arquivo existe porque régua escrita
| em documento não segura nada: o próximo a mexer numa grade acrescenta a coluna
| que "só desta vez" faz falta, e seis meses depois a tela está poluída de novo.
|
| ── O que se testa, e por que CADA lei está aqui ───────────────────────────
|
| 1. **A EXPORTAÇÃO continua completa.** É a lei mais importante, e a razão de o
|    catálogo declarar `grade`, `detalhe` e `exportacao` no mesmo lugar. A ordem
|    do dono foi enxugar a TELA; o risco embutido nela é a limpeza escorregar
|    para o arquivo — quem apaga a coluna da grade apaga a linha vizinha do
|    `BotaoExportar` no mesmo impulso, e o XLSX que a chefia lê para decidir
|    perde o dado sem ninguém notar. A conferência é cruzada: tudo o que a tela
|    mostra (`grade`) e tudo o que desceu para a ficha (`detalhe`) TEM de estar
|    na `exportacao`.
|
| 2. **Teto de cinco colunas** — conferido nas duas resoluções da grade, com e
|    sem a coluna condicional. Testar só uma delas deixaria a outra passar de
|    seis colunas sem nada acusar.
|
| 3. **Texto livre fora da grade.** Foi o diagnóstico do print que gerou a ordem:
|    relato, assunto e considerações dentro da célula é o que quebrava a linha em
|    alturas irregulares.
|
| 4. **A tela ainda CONSOME o catálogo.** Sem isto o catálogo viraria enfeite: a
|    grade voltaria a ser escrita à mão no TSX e as leis acima seriam verdade
|    sobre um arquivo que ninguém lê. É conferido de dois jeitos, porque um só
|    não basta: o prop chega de verdade na tela (requisição HTTP, comparado com o
|    catálogo) e o arquivo da tela cita o identificador da listagem.
|
| ⚠️ A conferência no FONTE (lei 4b) é o último recurso do projeto, e está aqui
| pela razão que a autoriza: o gate não executa JavaScript. Ela é mínima de
| propósito — cita o identificador, importa a linha clicável — para não quebrar
| com mudança legítima de marcação.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

/** As chaves de uma lista de colunas, na ordem. */
function chavesDaListagem(array $colunas): array
{
    return array_map(static fn (array $c): string => (string) $c['chave'], $colunas);
}

/**
 * As duas resoluções possíveis da grade: sem contexto (só as colunas fixas) e
 * com todos os condicionais ligados (o recorte mais largo que a tela desenha).
 */
function resolucoesDaGrade(string $id): array
{
    $contexto = [];

    foreach (ListagensDaRetaguarda::listagem($id)['grade'] as $coluna) {
        if (isset($coluna['quando'])) {
            $contexto[(string) $coluna['quando']] = true;
        }
    }

    return [
        'sem condicional' => ListagensDaRetaguarda::grade($id),
        'com condicional' => ListagensDaRetaguarda::grade($id, $contexto),
    ];
}

it('declara ao menos uma listagem — o catálogo vazio faria toda lei abaixo passar por vacuidade', function () {
    $this->assertNotEmpty(ListagensDaRetaguarda::ids());
});

it('não põe mais de cinco colunas na grade, em nenhuma resolução — salvo exceção DECLARADA com motivo', function () {
    foreach (ListagensDaRetaguarda::ids() as $id) {
        $listagem = ListagensDaRetaguarda::listagem($id);
        $teto = (int) ($listagem['teto'] ?? 5);

        // A exceção existe (a Caixa de Entrada, 25/09/2026), mas é declarada no
        // catálogo com o motivo — e ninguém passa de seis.
        if ($teto !== 5) {
            $this->assertNotEmpty($listagem['teto_motivo'] ?? '', "A listagem \"{$id}\" subiu o teto sem declarar o motivo.");
            $this->assertLessThanOrEqual(6, $teto, "A listagem \"{$id}\" declarou teto acima de seis.");
        }

        foreach (resolucoesDaGrade($id) as $rotulo => $grade) {
            $this->assertLessThanOrEqual(
                $teto,
                count($grade),
                "A grade de \"{$id}\" ({$rotulo}) passou de cinco colunas: "
                .implode(', ', chavesDaListagem($grade))
                .'. Coluna a mais é decisão de não olhar nenhuma — mande a menos '
                .'importante para o detalhe (docs/padroes/listagem-clean.md).'
            );
        }
    }
});

it('mantém texto livre FORA da grade — é ele que quebra a linha em alturas irregulares', function () {
    $livres = ListagensDaRetaguarda::textoLivre();

    $this->assertNotEmpty($livres, 'A lista de campos de texto livre está vazia — a lei passaria por vacuidade.');

    foreach (ListagensDaRetaguarda::ids() as $id) {
        foreach (resolucoesDaGrade($id) as $grade) {
            $intrusos = array_values(array_intersect(chavesDaListagem($grade), $livres));

            $this->assertSame(
                [],
                $intrusos,
                "A grade de \"{$id}\" declara campo de texto livre: "
                .implode(', ', $intrusos)
                .'. Texto livre não entra na grade — no máximo um selo com a '
                .'categoria, e a frase no detalhe.'
            );
        }
    }
});

it('leva ao ARQUIVO tudo o que a grade mostra — a limpeza é da tela, não da exportação', function () {
    foreach (ListagensDaRetaguarda::ids() as $id) {
        $arquivo = chavesDaListagem(ListagensDaRetaguarda::exportacao($id));

        foreach (resolucoesDaGrade($id) as $grade) {
            foreach (chavesDaListagem($grade) as $chave) {
                $this->assertContains(
                    $chave,
                    $arquivo,
                    "A coluna \"{$chave}\" está na grade de \"{$id}\" e não na "
                    .'exportação. O arquivo é lido por quem decide: o que a tela '
                    .'mostra ele tem de levar.'
                );
            }
        }
    }
});

it('leva ao ARQUIVO tudo o que DESCEU para o detalhe — é a lei que evita a limpeza virar perda de dado', function () {
    foreach (ListagensDaRetaguarda::ids() as $id) {
        $desceu = ListagensDaRetaguarda::detalhe($id);
        $arquivo = chavesDaListagem(ListagensDaRetaguarda::exportacao($id));

        // Detalhe vazio significaria "nada saiu da grade": ou a listagem nasceu
        // enxuta por acaso, ou alguém apagou a declaração para calar a lei
        // seguinte. Nos dois casos a régua não foi aplicada.
        $this->assertNotEmpty(
            $desceu,
            "A listagem \"{$id}\" não declara o que desceu para o detalhe. "
            .'Enxugar a grade é mover informação, não apagá-la: declare em '
            .'`detalhe` o que saiu da tela.'
        );

        foreach ($desceu as $chave) {
            $this->assertContains(
                $chave,
                $arquivo,
                "O campo \"{$chave}\" saiu da grade de \"{$id}\" e NÃO está na "
                .'exportação — a limpeza da tela virou perda de dado no arquivo. '
                .'Ponha a coluna de volta na exportação.'
            );
        }
    }
});

it('mantém o arquivo mais rico que a tela, e sem coluna repetida ou sem título', function () {
    foreach (ListagensDaRetaguarda::ids() as $id) {
        $arquivo = chavesDaListagem(ListagensDaRetaguarda::exportacao($id));
        $maiorGrade = max(array_map('count', resolucoesDaGrade($id)));

        $this->assertGreaterThan(
            $maiorGrade,
            count($arquivo),
            "A exportação de \"{$id}\" não tem mais colunas que a grade. Se as "
            .'duas empataram, a limpeza da tela alcançou o arquivo.'
        );

        foreach ([$arquivo, chavesDaListagem(ListagensDaRetaguarda::grade($id))] as $chaves) {
            $this->assertSame(
                array_values(array_unique($chaves)),
                $chaves,
                "A listagem \"{$id}\" declara a mesma chave duas vezes."
            );
        }

        $colunas = array_merge(
            ListagensDaRetaguarda::exportacao($id),
            ListagensDaRetaguarda::listagem($id)['grade'],
        );

        foreach ($colunas as $coluna) {
            $this->assertNotSame(
                '',
                trim((string) ($coluna['titulo'] ?? '')),
                "Coluna sem título em \"{$id}\" (chave {$coluna['chave']})."
            );
        }
    }
});

it('aponta para um arquivo de tela que cita a listagem e abre o detalhe pela LINHA', function () {
    foreach (ListagensDaRetaguarda::ids() as $id) {
        $caminho = (string) (ListagensDaRetaguarda::listagem($id)['tela'] ?? '');

        $this->assertNotSame('', $caminho, "A listagem \"{$id}\" não declara a tela.");
        $this->assertFileExists(
            base_path($caminho),
            "A tela declarada para \"{$id}\" não existe: {$caminho}."
        );

        $fonte = (string) file_get_contents(base_path($caminho));

        $this->assertStringContainsString(
            $id,
            $fonte,
            "A tela {$caminho} não cita a listagem \"{$id}\": ou o identificador "
            .'mudou, ou a grade voltou a ser escrita à mão e o catálogo virou '
            .'enfeite.'
        );

        // A linha inteira abre o registro — não um ícone de lupa no fim dela.
        // `linhaClicavel` é o único lugar do sistema que resolve isso com
        // teclado e dica de acesso.
        $this->assertStringContainsString(
            'linha-clicavel',
            $fonte,
            "A tela {$caminho} não usa `linhaClicavel`: sem ela a informação que "
            .'desceu para o detalhe fica sem porta de acesso.'
        );
    }
});

/*
|--------------------------------------------------------------------------
| E o catálogo CHEGA na tela — a lei que impede o enfeite
|--------------------------------------------------------------------------
|
| Sem esta parte, tudo acima seria verdade sobre um arquivo de configuração que
| nenhuma tela lê.
*/

/** Um usuário que enxerga tudo — o recorte por área é assunto de outro teste. */
function administradorDaListagem(): User
{
    return User::factory()->create(['admin' => true, 'ativo' => true])->fresh();
}

/** Um Chefe de Setor de UMA área — para conferir a coluna condicional. */
/**
 * Um líder com UMA equipe — o papel recortado. A conta é ligada à equipe por
 * `equipes.lider_id` (é assim que a estrutura sabe de quem é a fila), e a
 * árvore em memória é esquecida para enxergar o vínculo recém-nascido.
 */
function liderDeUmaEquipeSo(): User
{
    $u = User::factory()->create(['login' => 'lider-z1', 'admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'lider-de-equipe')->firstOrFail());

    $area = Area::create(['nome' => 'Área Z', 'regiao' => 'Teste']);
    Equipe::create(['codigo' => 'Z1', 'nome' => 'Equipe Z1', 'area_id' => $area->id, 'lider_id' => $u->id]);
    Estrutura::esquecer();

    return $u->fresh();
}

dataset('telas com listagem', [
    'Fiscalizações' => ['/retaguarda/fiscalizacoes', ['fiscalizacoes.ciclos']],
    // As quatro caixas de canal têm UMA grade (dono, 24/09/2026).
    'Caixa do e-Salvador' => ['/retaguarda/caixa-de-entrada/e-salvador', ['denuncias.todas']],
    'Caixa do Fala Salvador' => ['/retaguarda/caixa-de-entrada/fala-salvador', ['denuncias.todas']],
    'Caixa do e-Protocolo' => ['/retaguarda/caixa-de-entrada/e-protocolo', ['denuncias.todas']],
    'Caixa das Avulsas' => ['/retaguarda/caixa-de-entrada/avulsas', ['denuncias.todas']],
    'Cadastro de Operação' => ['/retaguarda/operacoes', ['operacoes']],
    'Ambulantes' => ['/retaguarda/ambulantes', ['ambulantes']],
    'Caixa de Entrada' => ['/retaguarda/caixa-de-entrada', ['caixa-de-entrada']],
    // As duas telas de DIAGNÓSTICO. Elas não movem trabalho — respondem "o que
    // quebrou" e "o que está fora do requisito" —, mas listagem é listagem: fora
    // da varredura, é a que apodrece.
    'Logs' => ['/retaguarda/logs', ['sistema.logs']],
    'Usuários' => ['/retaguarda/usuarios', ['usuarios.ativos', 'usuarios.excluidos']],
    'Equipes' => ['/retaguarda/equipes', ['equipes']],
    'Áreas' => ['/retaguarda/areas', ['areas']],
    'Acompanhamento de Requisitos' => ['/retaguarda/acompanhamento-de-requisitos', ['sistema.requisitos']],
]);

it('entrega as colunas da listagem como prop da tela', function (string $url, array $ids) {
    $pagina = $this->actingAs(administradorDaListagem())->get($url);

    $pagina->assertOk();

    $pagina->assertInertia(function ($p) use ($ids) {
        $listagens = $p->toArray()['props']['listagens'] ?? [];

        foreach ($ids as $id) {
            $this->assertArrayHasKey(
                $id,
                $listagens,
                "A tela não recebeu a listagem \"{$id}\"."
            );

            // O que chega tem de ser a MESMA coisa que o catálogo declara: com o
            // prop montado à mão no controller, o teste do catálogo continuaria
            // verde e a tela mostraria outra coisa.
            $this->assertSame(
                chavesDaListagem(ListagensDaRetaguarda::exportacao($id)),
                chavesDaListagem($listagens[$id]['exportacao']),
                "As colunas de arquivo que a tela recebeu para \"{$id}\" não são as do catálogo."
            );

            $this->assertNotEmpty($listagens[$id]['grade']);
        }

        return $p;
    });
})->with('telas com listagem');

it('não gasta coluna do acompanhamento com o que é igual em TODA linha — e a devolve quando passa a variar', function () {
    /*
     * A mesma lei da coluna condicional, com a condição resolvida pelo outro
     * lado: nas Fiscalizações quem decide é QUEM olha; aqui é o que os dados
     * TÊM. Hoje o mapa é todo "Retaguarda" e nenhuma linha aponta HU — as duas
     * colunas seriam a mesma palavra, ou o mesmo travessão, repetidos em toda
     * linha; e o selo "Sem requisito" já diz isso uma vez, no lugar certo.
     *
     * O FLIP é a metade que importa: sem ele, uma condição travada em `false`
     * passaria neste teste para sempre, e a coluna nunca voltaria no dia em que
     * fizesse falta.
     */
    $admin = administradorDaListagem();

    $this->actingAs($admin)->get('/retaguarda/acompanhamento-de-requisitos')
        ->assertOk()
        ->assertInertia(function ($p) {
            $grade = chavesDaListagem($p->toArray()['props']['listagens']['sistema.requisitos']['grade']);

            foreach (['origem', 'hus'] as $constante) {
                $this->assertNotContains(
                    $constante,
                    $grade,
                    "A coluna \"{$constante}\" entrou na grade do acompanhamento sem variar "
                    .'entre as linhas do mapa — ela gastaria largura repetindo o mesmo valor.'
                );
            }

            return $p;
        });

    // Agora com variedade no mapa: duas frentes e uma HU escrita.
    config(['acompanhamento_requisitos.telas' => [
        [
            'modulo' => 'Sistema', 'tela' => 'Logs', 'origem' => 'Retaguarda',
            'rota' => 'retaguarda.logs.index', 'breadcrumb' => 'Sistema › Logs',
            'hu_status' => 'sim', 'hus' => ['HU 001'], 'nota' => 'Requisito escrito e alinhado.',
        ],
        [
            'modulo' => 'Campo', 'tela' => 'Registrar fiscalização', 'origem' => 'PWA',
            'rota' => null, 'breadcrumb' => 'Aplicativo do fiscal',
            'hu_status' => 'nao', 'hus' => [], 'nota' => 'Sem requisito escrito — origem: spec de design.',
        ],
    ]]);

    $this->actingAs($admin)->get('/retaguarda/acompanhamento-de-requisitos')
        ->assertOk()
        ->assertInertia(function ($p) {
            $grade = chavesDaListagem($p->toArray()['props']['listagens']['sistema.requisitos']['grade']);

            foreach (['origem', 'hus'] as $variavel) {
                $this->assertContains(
                    $variavel,
                    $grade,
                    "A coluna \"{$variavel}\" passou a variar entre as linhas e continuou fora "
                    .'da grade — a condição está travada, e a coluna nunca voltaria.'
                );
            }

            // O teto vale para esta resolução como para qualquer outra.
            $this->assertLessThanOrEqual(5, count($grade));

            return $p;
        });
});

it('a grade das Fiscalizações diz com quem está: a coluna POSSE ATUAL existe para todos', function () {
    /*
     * Desde 24/09/2026 a linha é a Fiscalização (o ciclo), e a pergunta da grade é
     * "com quem está" — Equipe ou Chefe de Setor (pedido do dono). A área saiu da
     * grade (vai no detalhe e no arquivo); a posse não sai para ninguém.
     */
    foreach ([liderDeUmaEquipeSo(), administradorDaListagem()] as $quem) {
        $this->actingAs($quem)
            ->get('/retaguarda/fiscalizacoes')
            ->assertOk()
            ->assertInertia(function ($p) {
                $grade = chavesDaListagem($p->toArray()['props']['listagens']['fiscalizacoes.ciclos']['grade']);

                $this->assertContains('posse', $grade);
                $this->assertContains('desfecho', $grade);

                return $p;
            });
    }
});
