<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Aplicativo do fiscal — a fila fala a MESMA língua do módulo de Denúncias.
 *
 * O aplicativo é um protótipo que roda sem servidor: os dados dele são escritos
 * em TypeScript (`resources/js/pwa/`), e o gate deste projeto não executa JS.
 * Então o que se prova aqui é o que o gate consegue provar — e é justamente o
 * que a demonstração depende: o VOCABULÁRIO e o DE-PARA com a Retaguarda.
 *
 * As listas fechadas abaixo estão escritas à mão de propósito. Elas são a régua:
 * a cópia do catálogo de `config/prototipo_denuncias.php`, que vive na branch do
 * módulo administrativo e não existe neste diretório. Se um dia as duas
 * divergirem, é este arquivo que precisa ser lido junto com o outro lado — nunca
 * ajustado em silêncio para o teste voltar a passar.
 *
 * O que ele reprova, em uma linha cada:
 *
 *   • situação ou desfecho inventado no aplicativo (vocabulário paralelo);
 *   • desfecho oferecido no ato errado (retorno com opção de primeira vistoria);
 *   • denúncia na amostra que a Retaguarda não semeou (protocolo órfão);
 *   • forma de trâmite que a demonstração precisa e a amostra não tem;
 *   • número de documento reservado no aparelho colidindo com um já lavrado;
 *   • amostra que deixou de ser educativa (mais papel do que orientação).
 */
class PwaFilaDeDenunciasTest extends TestCase
{
    /** O catálogo de situações da denúncia, na ordem do fluxo. */
    private const SITUACOES_DA_RETAGUARDA = [
        'Recebida',
        'Encaminhada à área',
        'Direcionada à equipe',
        'Em operação',
        'Em campo',
        'Aguardando regularização',
        'Retorno vencido',
        'Concluída',
        'Devolvida',
        'Arquivada',
    ];

    /** Os desfechos de vistoria — é o que a Retaguarda soma nos relatórios. */
    private const DESFECHOS_DA_RETAGUARDA = [
        'Regularizado no local',
        'Nada encontrado no local',
        'Notificação Preliminar emitida',
        'Regularizado após notificação',
        'Retorno com a situação mantida',
        'Auto de Apreensão lavrado',
    ];

    /**
     * Os ids das denúncias semeadas na Retaguarda que já chegaram a uma equipe,
     * mais as três que a amostra guarda para provar o recorte (6, 14 e 26).
     *
     * O aplicativo pode espelhar menos do que isto; nunca MAIS. Protocolo que
     * não existe do outro lado quebra a demonstração no pior lugar possível: o
     * dono abre DEN-00NN na Retaguarda e não acha.
     */
    private const DENUNCIAS_DA_RETAGUARDA = [
        6, 10, 11, 12, 13, 14, 16, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38,
    ];

    /** Os números de documento que a Retaguarda já mostra nos casos semeados. */
    private const DOCUMENTOS_JA_LAVRADOS = ['194901', '194902', '194903', '194904', '194905', '160051'];

    /** As formas de trâmite que o roteiro da demonstração percorre. */
    private const FORMAS_DA_DEMONSTRACAO = [
        'Direcionada à equipe',
        'Em operação',
        'Em campo',
        'Aguardando regularização',
        'Retorno vencido',
        'Concluída',
    ];

    /**
     * O fonte do aplicativo, lido a partir DESTE arquivo de teste.
     *
     * ⚠️ De propósito não usa `resource_path()`: em árvore de trabalho paralela
     * (worktree) o `vendor/` é um atalho para o diretório principal, e o
     * aplicativo que o teste boota é o de LÁ — `resource_path()` apontaria para
     * os fontes do outro diretório e o teste passaria (ou falharia) pelo motivo
     * errado. Caminho relativo ao teste lê sempre a árvore que está sendo
     * alterada.
     */
    private function fonteDoAplicativo(string $arquivo): string
    {
        $caminho = dirname(__DIR__, 2).'/resources/js/pwa/'.$arquivo;

        $this->assertFileExists($caminho, "o aplicativo perdeu o arquivo {$arquivo}");

        return (string) file_get_contents($caminho);
    }

    /**
     * Os pares de um mapa literal do TypeScript (`const X = { 'chave': valor }`).
     *
     * Serve para ler catálogo que casa texto com decisão — o
     * `DOCUMENTO_DO_DESFECHO`, que diz qual desfecho lavra papel. O valor volta
     * como TEXTO cru (`'np'`, `null`), porque é isso que a regra pergunta.
     *
     * @return array<string, string>
     */
    private function mapaDeTextos(string $fonte, string $nome): array
    {
        $achou = preg_match(
            '/const '.preg_quote($nome, '/').'(?![A-Z_])[^=]*=\s*\{(?<corpo>.*?)\n\};/su',
            $fonte,
            $bloco,
        );

        $this->assertSame(1, $achou, "o aplicativo não declara mais o mapa {$nome}");

        preg_match_all(
            "/'((?:[^'\\\\]|\\\\.)*)':\s*([^,\n]+)/u",
            $bloco['corpo'],
            $achados,
            PREG_SET_ORDER,
        );

        $mapa = [];

        foreach ($achados as $par) {
            $mapa[str_replace("\\'", "'", $par[1])] = trim($par[2]);
        }

        return $mapa;
    }

    /**
     * Os textos de uma lista literal do TypeScript (`const X = [ … ]`).
     *
     * @return list<string>
     */
    private function listaDeTextos(string $fonte, string $nome): array
    {
        /* O `= [` é o que se procura, e não o primeiro `[` depois do nome: a
           lista pode vir anotada (`: Desfecho[] =`), e aí o primeiro colchete é
           o do TIPO — um par vazio, que devolveria lista vazia e faria o teste
           passar dizendo que a lista está errada. */
        $achou = preg_match(
            '/const '.preg_quote($nome, '/').'(?![A-Z_])[^=]*=\s*\[(?<itens>.*?)\]/su',
            $fonte,
            $bloco,
        );

        $this->assertSame(1, $achou, "o aplicativo não declara mais a lista {$nome}");

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/u", $bloco['itens'], $achados);

        return array_map(static fn (string $t): string => str_replace("\\'", "'", $t), $achados[1]);
    }

    public function test_a_casca_do_aplicativo_continua_tendo_rota_propria()
    {
        /*
         * Sem a rota, todo o resto aqui seria análise de texto sobre um
         * aplicativo que ninguém consegue abrir.
         *
         * A conferência é no fonte da rota, e não por requisição: em worktree o
         * teste boota o aplicativo do diretório principal (ver
         * `fonteDoAplicativo`), onde esta rota pode nem existir — e um 404 dali
         * não diria nada sobre a árvore que está sendo alterada.
         */
        $rotas = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        $this->assertStringContainsString('app/{caminho?}', $rotas);
        $this->assertStringContainsString("name('pwa')", $rotas);
    }

    public function test_as_situacoes_do_aplicativo_sao_exatamente_o_catalogo_da_retaguarda()
    {
        $this->assertSame(
            self::SITUACOES_DA_RETAGUARDA,
            $this->listaDeTextos($this->fonteDoAplicativo('dados-demandas.ts'), 'SITUACOES'),
        );
    }

    public function test_os_desfechos_do_aplicativo_sao_exatamente_os_da_retaguarda()
    {
        $this->assertSame(
            self::DESFECHOS_DA_RETAGUARDA,
            $this->listaDeTextos($this->fonteDoAplicativo('dados-demandas.ts'), 'DESFECHOS'),
        );
    }

    public function test_vistoria_e_retorno_repartem_os_seis_desfechos_sem_sobra_e_sem_repeticao()
    {
        /*
         * Os dois recortes são da MESMA lista. Um desfecho fora dos dois nunca
         * seria escolhível em campo (e a Retaguarda esperaria por ele para
         * sempre); um desfecho nos dois deixaria o fiscal encerrar um retorno
         * com "nada encontrado", que não diz se o notificado cumpriu o prazo.
         */
        $fonte = $this->fonteDoAplicativo('dados-demandas.ts');

        $vistoria = $this->listaDeTextos($fonte, 'DESFECHOS_DE_VISTORIA');
        $retorno = $this->listaDeTextos($fonte, 'DESFECHOS_DE_RETORNO');

        $this->assertSame([], array_values(array_intersect($vistoria, $retorno)));

        $juntos = array_merge($vistoria, $retorno);
        $todos = self::DESFECHOS_DA_RETAGUARDA;
        sort($juntos);
        sort($todos);

        $this->assertSame($todos, $juntos);
    }

    public function test_toda_denuncia_da_amostra_existe_no_outro_lado()
    {
        $fonte = $this->fonteDoAplicativo('dados-demandas.ts');

        preg_match_all('/^\s{8}id: (\d+),$/m', $fonte, $ids);

        $encontrados = array_map('intval', $ids[1]);

        $this->assertNotEmpty($encontrados, 'a amostra do aplicativo ficou sem nenhuma denúncia');
        $this->assertSame(
            [],
            array_values(array_diff($encontrados, self::DENUNCIAS_DA_RETAGUARDA)),
            'há denúncia na amostra do aplicativo que a Retaguarda não semeou',
        );
    }

    public function test_o_protocolo_da_caixa_de_entrada_nao_sobrou_na_fila()
    {
        // `CXE-` é o protocolo da Caixa de Entrada (o que chega em papel). A fila
        // do aplicativo passou a ser a das denúncias das ouvidorias, e as duas
        // numerações não se misturam.
        $this->assertStringNotContainsString('CXE-', $this->fonteDoAplicativo('dados-demandas.ts'));
        $this->assertStringNotContainsString('CXE-', $this->fonteDoAplicativo('dados-prototipo.ts'));
    }

    public function test_a_amostra_tem_uma_denuncia_de_cada_forma_que_a_demonstracao_percorre()
    {
        /*
         * O roteiro abre uma de cada: direcionada à equipe, em operação, vistoria
         * em andamento, notificação com prazo correndo, retorno vencido e
         * concluída. Sem uma delas, o dono chega na tela e não tem o que mostrar.
         */
        $fonte = $this->fonteDoAplicativo('dados-demandas.ts');
        $faltando = [];

        foreach (self::FORMAS_DA_DEMONSTRACAO as $situacao) {
            if (! str_contains($fonte, "situacao: '".$situacao."'")) {
                $faltando[] = $situacao;
            }
        }

        $this->assertSame([], $faltando, 'a amostra perdeu uma forma de trâmite do roteiro');
    }

    public function test_o_registro_reconhece_o_id_da_demanda_pelo_prefixo_que_a_fila_gera()
    {
        /*
         * A tela de registro decide, pelo PREFIXO do que veio no endereço, se o
         * alvo é um pino do mapa (`amb-`) ou uma denúncia da fila. Quando o
         * protocolo interno mudou de `CXE-` para `DEN-`, o id passou de `dem-` a
         * `den-` e a tela continuou procurando o prefixo antigo: a denúncia não
         * era encontrada, e "Registrar retorno" abria uma vistoria AVULSA, com os
         * desfechos errados e sem vínculo nenhum. Nem o compilador nem o resto
         * desta suíte pegam isso — só quem abre a tela.
         */
        $fila = $this->fonteDoAplicativo('dados-demandas.ts');
        $registro = $this->fonteDoAplicativo('telas/registro-rapido.tsx');

        $achou = preg_match('/id: `(?<prefixo>[a-z]+)-\$\{String\(s\.id\)/u', $fila, $bloco);

        $this->assertSame(1, $achou, 'a fila não monta mais o id da demanda a partir do id da denúncia');
        $this->assertStringContainsString(
            "startsWith('".$bloco['prefixo']."-')",
            $registro,
            'a tela de registro procura um prefixo de id que a fila não gera mais',
        );
    }

    public function test_a_faixa_reservada_no_aparelho_nao_colide_com_documento_ja_lavrado()
    {
        /*
         * O número nasce no aparelho, sem sinal, de uma faixa reservada. Se ela
         * começasse nos números já semeados do outro lado, a primeira Notificação
         * lavrada na demonstração sairia com o número de um documento que já
         * existe — dois papéis diferentes com o mesmo número.
         */
        $fonte = $this->fonteDoAplicativo('dados-documentos.ts');

        preg_match('/np: \{ inicio: (\d+)/', $fonte, $np);
        preg_match('/aa: \{ inicio: (\d+)/', $fonte, $aa);

        $this->assertNotEmpty($np, 'a faixa da Notificação Preliminar desapareceu');
        $this->assertNotEmpty($aa, 'a faixa do Auto de Apreensão desapareceu');
        $this->assertNotContains($np[1], self::DOCUMENTOS_JA_LAVRADOS);
        $this->assertNotContains($aa[1], self::DOCUMENTOS_JA_LAVRADOS);
        $this->assertGreaterThan(194905, (int) $np[1]);
        $this->assertGreaterThan(160051, (int) $aa[1]);
    }

    public function test_a_amostra_segue_educativa_com_mais_casos_sem_documento_do_que_com_papel()
    {
        /*
         * Regra da amostra, não observação: uma demonstração em que todo caso de
         * campo termina em papel desenharia um sistema punitivo que não é o do
         * cliente. A fiscalização de ambulante termina, na maioria das vezes, com
         * o ambulante desmontando na frente do fiscal.
         */
        $comPapel = ['Notificação Preliminar emitida', 'Auto de Apreensão lavrado'];
        $semPapel = ['Regularizado no local', 'Nada encontrado no local', 'Regularizado após notificação'];

        $conta = static function (string $fonte, array $desfechos): int {
            $total = 0;

            foreach ($desfechos as $desfecho) {
                $total += substr_count($fonte, "'".$desfecho."'");
            }

            return $total;
        };

        // Os dois lugares que o dono tem na mão durante a demonstração: a amostra
        // das denúncias e o turno já registrado no aparelho.
        foreach (['dados-demandas.ts', 'dados-prototipo.ts'] as $arquivo) {
            $fonte = $this->fonteDoAplicativo($arquivo);

            $this->assertGreaterThan(
                $conta($fonte, $comPapel),
                $conta($fonte, $semPapel),
                "a amostra de {$arquivo} passou a ter mais papel do que orientação",
            );
        }
    }

    public function test_so_conclui_sem_documento_o_desfecho_que_nao_lavra_documento()
    {
        /*
         * REGRA DE NEGÓCIO (dono, 04/09/2026): o registro só se conclui SEM
         * documento lavrado quando o desfecho foi "Regularizado no local" ou
         * "Nada encontrado no local". Com "Notificação Preliminar emitida" ou
         * "Auto de Apreensão lavrado", concluir sem o papel deixaria a
         * Retaguarda com um desfecho que anuncia documento e nenhum documento
         * atrás dele — e o notificado sem a via que faz o prazo correr.
         *
         * A régua é o `DOCUMENTO_DO_DESFECHO`, e de propósito: uma segunda lista
         * de "desfechos que podem concluir" divergiria dele no primeiro ajuste.
         */
        $fonte = $this->fonteDoAplicativo('dados-demandas.ts');

        $mapa = $this->mapaDeTextos($fonte, 'DOCUMENTO_DO_DESFECHO');
        $semPapel = array_keys(array_filter($mapa, static fn (string $v): bool => $v === 'null'));
        $vistoria = $this->listaDeTextos($fonte, 'DESFECHOS_DE_VISTORIA');

        $this->assertSame(
            ['Regularizado no local', 'Nada encontrado no local'],
            array_values(array_intersect($vistoria, $semPapel)),
            'mudou quais desfechos de vistoria concluem sem documento lavrado',
        );

        // A régua mora numa função só, derivada do mapa — não numa cópia da lista.
        $this->assertMatchesRegularExpression(
            '/export const impedimentoParaConcluir\s*=/',
            $fonte,
            'a regra do impedimento de conclusão saiu do lugar onde a tela a lê',
        );
        $this->assertStringContainsString(
            'DOCUMENTO_DO_DESFECHO[',
            $fonte,
            'o impedimento passou a ter lista própria em vez de derivar do documento do desfecho',
        );
    }

    public function test_a_conclusao_impedida_diz_o_motivo_e_oferece_o_caminho()
    {
        /*
         * Lei do projeto: impedimento NUNCA em silêncio. Barrar a conclusão sem
         * dizer o porquê faria o fiscal tocar o botão de novo achando que o
         * aparelho travou — e ele está de pé na calçada, com o notificado
         * esperando. Então a tela de conclusão diz o motivo, diz o que fazer e
         * põe o formulário do documento a um toque.
         */
        $tela = $this->fonteDoAplicativo('telas/recibo.tsx');
        $fila = $this->fonteDoAplicativo('dados-demandas.ts');

        $this->assertStringContainsString(
            'impedimentoParaConcluir',
            $tela,
            'a tela de conclusão deixou de consultar o impedimento',
        );
        $this->assertMatchesRegularExpression(
            '/disabled=\{[^}]*impedimento/u',
            $tela,
            'o botão de concluir deixou de ser barrado pelo impedimento',
        );
        // O rótulo do botão nomeia o documento que falta ("Lavrar a Notificação
        // Preliminar"), e o toque abre o formulário dele — não uma tela de menu.
        $this->assertMatchesRegularExpression(
            '/Lavrar \{/u',
            $tela,
            'a tela impedida não oferece mais o caminho de lavrar o documento que falta',
        );
        $this->assertStringContainsString(
            "'notificacao' : 'apreensao'",
            $tela,
            'o caminho do impedimento não abre mais o formulário do documento',
        );
        $this->assertStringContainsString(
            'Lavre ',
            $fila,
            'o impedimento não diz mais o que fazer para concluir',
        );

        // Diálogo do navegador não é mensagem do sistema: some sem rastro, não é
        // estilizável e não cabe na tela de quem trabalha de pé.
        $this->assertStringNotContainsString('alert(', $tela);
        $this->assertStringNotContainsString('confirm(', $tela);
    }

    public function test_o_registro_leva_as_consideracoes_finais_do_fiscal()
    {
        /*
         * As considerações são a ENTREGA do despacho: é por elas que o Chefe de
         * Setor e o Coordenador entendem a recomendação do fiscal e sabem
         * direcionar o registro. Os dois nomes de campo são o contrato com o
         * outro lado (`consideracoes`, texto livre; `recomendacoes`, as chaves
         * dos atalhos escolhidos) — trocar um deles aqui quebra o de-para em
         * silêncio, porque não há servidor conferindo nada.
         */
        $tipos = $this->fonteDoAplicativo('dados-prototipo.ts');

        $this->assertMatchesRegularExpression('/\n\s+consideracoes: string;/u', $tipos);
        $this->assertMatchesRegularExpression('/\n\s+recomendacoes: string\[\];/u', $tipos);

        // A lista de atalhos é FECHADA e mora com o catálogo espelhado, para o
        // outro lado poder somar recomendação — texto livre não se soma.
        $this->assertMatchesRegularExpression(
            '/export const RECOMENDACOES/u',
            $this->fonteDoAplicativo('dados-demandas.ts'),
            'os atalhos de recomendação saíram do catálogo espelhado',
        );
    }

    public function test_o_registro_do_turno_nasce_de_um_desfecho_e_nao_de_regular_ou_irregular()
    {
        /*
         * A leitura "regular / irregular" continua existindo (é a cor do pino no
         * mapa), mas ela é DERIVADA do desfecho. Se voltar a ser escolhida ao
         * lado dele, a mesma decisão passa a ter dois donos — e um dia o pino diz
         * "regular" num ponto que levou Auto de Apreensão.
         */
        $fonte = $this->fonteDoAplicativo('dados-prototipo.ts');

        $this->assertStringContainsString('LOCAL_APOS_O_DESFECHO[s[4]]', $fonte);
        $this->assertDoesNotMatchRegularExpression("/,\s*'irregular',\s*\[/", $fonte);
        $this->assertDoesNotMatchRegularExpression("/,\s*'regular',\s*\[/", $fonte);
    }
}
