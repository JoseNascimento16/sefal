<?php

namespace Tests\Feature;

use App\Models\Demanda;
use App\Models\Fiscalizacao;
use App\Support\Aplicativo\AplicativoParaTela;
use App\Support\Aplicativo\ReservaDeNumeros;
use App\Support\Prototipo\RecomendacoesDoFiscal;
use Tests\TestCase;

/**
 * Aplicativo do fiscal — a fila fala a MESMA língua do módulo de Denúncias.
 *
 * Desde 13/10/2026 o aplicativo recebe a fila do servidor (ver
 * `AplicativoDoFiscalApiTest`), mas os CATÁLOGOS fechados ainda moram também
 * no TypeScript (`resources/js/pwa/`): situações, desfechos, canais e chaves de
 * recomendação. O gate não executa JS, então o que se prova aqui é que essas
 * cópias são IGUAIS às constantes do servidor — agora lidas direto do PHP,
 * porque os dois lados passaram a viver na mesma branch.
 *
 * O que ele reprova, em uma linha cada:
 *
 *   • situação, desfecho ou canal inventado no aplicativo (vocabulário paralelo);
 *   • desfecho oferecido no ato errado (retorno com opção de primeira vistoria);
 *   • chave de recomendação que o servidor não conhece;
 *   • prefixo do id da demanda diferente entre o servidor e a tela de registro;
 *   • numeração do servidor colidindo com documento já lavrado.
 */
class PwaFilaDeDenunciasTest extends TestCase
{
    /** O catálogo de situações da demanda, na ordem do fluxo. */
    private const SITUACOES_DA_RETAGUARDA = Demanda::SITUACOES;

    /** Os desfechos de vistoria — é o que a Retaguarda soma nos relatórios. */
    private const DESFECHOS_DA_RETAGUARDA = Fiscalizacao::DESFECHOS;

    /**
     * As CHAVES da recomendação, na ordem em que o catálogo unificado as declara.
     *
     * Escritas à mão de propósito, como as outras réguas deste arquivo: elas são
     * a cópia do catálogo da Retaguarda (`config/prototipo_denuncias.php` →
     * `recomendacoes_do_fiscal`), que vive na branch do módulo administrativo e
     * NÃO existe neste diretório. Ler a lista do próprio arquivo que se quer
     * conferir provaria apenas que ele é igual a si mesmo.
     *
     * ⚠️ As duas branches não se veem, então nenhum teste consegue comparar as
     * duas listas de verdade. Se um dia divergirem, é esta constante que precisa
     * ser lida JUNTO com o outro lado — nunca ajustada em silêncio para o teste
     * voltar a passar.
     */
    private const RECOMENDACOES_DA_RETAGUARDA = [
        'retorno',
        'reprogramar',
        'operacao',
        'passagem',
        'chefia',
        'sgci',
        'segub',
        'seab',
        'outro-orgao',
        'guarda',
        'nada',
    ];

    /** Os números de documento que a Retaguarda já mostra nos casos semeados. */
    private const DOCUMENTOS_JA_LAVRADOS = ['194901', '194902', '194903', '194904', '194905', '160051'];

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

    public function test_os_canais_do_aplicativo_sao_exatamente_os_da_retaguarda()
    {
        $fonte = $this->fonteDoAplicativo('dados-demandas.ts');

        preg_match('/export type Origem = (?<tipos>[^;]+);/u', $fonte, $bloco);
        preg_match_all("/'([a-z-]+)'/", $bloco['tipos'] ?? '', $canais);

        $this->assertSame(Demanda::CANAIS, $canais[1]);
    }

    public function test_as_ocorrencias_do_aplicativo_sao_as_do_catalogo_do_servidor()
    {
        $fonte = $this->fonteDoAplicativo('dados-prototipo.ts');
        preg_match('/export const OCORRENCIAS = \[(?<itens>.*?)\R\];/s', $fonte, $bloco);
        preg_match_all("/id: '([a-z-]+)'/", $bloco['itens'] ?? '', $ids);

        $this->assertSame(array_keys((array) config('prototipo_denuncias.ocorrencias_do_fiscal')), $ids[1]);
    }

    public function test_os_desfechos_de_vistoria_e_de_retorno_do_aplicativo_sao_os_do_servidor()
    {
        $fonte = $this->fonteDoAplicativo('dados-demandas.ts');
        $lista = function (string $nome) use ($fonte): array {
            preg_match('/export const '.$nome.': Desfecho\[\] = \[(?<itens>.*?)\];/s', $fonte, $bloco);
            preg_match_all("/'([^']+)'/u", $bloco['itens'] ?? '', $itens);

            return $itens[1];
        };

        // Quem decide é o servidor; o aplicativo só oferece a mesma lista.
        $this->assertSame(Fiscalizacao::desfechosDoAto(null), $lista('DESFECHOS_DE_VISTORIA'));
        $this->assertSame(Fiscalizacao::DESFECHOS_DE_RETORNO, $lista('DESFECHOS_DE_RETORNO'));
    }

    public function test_os_motivos_de_sem_gps_do_aplicativo_sao_os_do_servidor()
    {
        $fonte = $this->fonteDoAplicativo('gps.ts');
        preg_match('/export const MOTIVOS_SEM_GPS = \{(?<itens>.*?)\R\} as const;/s', $fonte, $bloco);
        preg_match_all("/^\s*'?([a-z-]+)'?: '([^']+)',/m", $bloco['itens'] ?? '', $itens);

        $this->assertSame((array) config('prototipo_denuncias.motivos_sem_gps'), array_combine($itens[1], $itens[2]));
    }

    public function test_as_chaves_de_recomendacao_sao_as_do_servidor()
    {
        $this->assertSame(RecomendacoesDoFiscal::chaves(), self::RECOMENDACOES_DA_RETAGUARDA);
    }

    public function test_o_protocolo_da_caixa_de_entrada_nao_sobrou_na_fila()
    {
        // `CXE-` é o protocolo da Caixa de Entrada (o que chega em papel). A fila
        // do aplicativo passou a ser a das denúncias das ouvidorias, e as duas
        // numerações não se misturam.
        $this->assertStringNotContainsString('CXE-', $this->fonteDoAplicativo('dados-demandas.ts'));
        $this->assertStringNotContainsString('CXE-', $this->fonteDoAplicativo('dados-prototipo.ts'));
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
        $servidor = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Aplicativo/AplicativoParaTela.php');
        $registro = $this->fonteDoAplicativo('telas/registro-rapido.tsx');
        $envio = $this->fonteDoAplicativo('app.tsx');

        $achou = preg_match("/'id' => '(?<prefixo>[a-z]+)-'\.\\\$demanda->id/u", $servidor, $bloco);

        $this->assertSame(1, $achou, 'o servidor não monta mais o id da demanda com prefixo');
        $this->assertStringContainsString(
            "startsWith('".$bloco['prefixo']."-')",
            $registro,
            'a tela de registro procura um prefixo de id que o servidor não gera mais',
        );
        $this->assertStringContainsString(
            "startsWith('".$bloco['prefixo']."-')",
            $envio,
            'o envio não reconhece mais o id da demanda que o servidor gera',
        );
    }

    public function test_a_numeracao_reservada_pelo_servidor_nao_colide_com_documento_ja_lavrado()
    {
        /*
         * O número nasce no aparelho, sem sinal, de uma faixa reservada. Se ela
         * começasse nos números já semeados do outro lado, a primeira Notificação
         * lavrada na demonstração sairia com o número de um documento que já
         * existe — dois papéis diferentes com o mesmo número.
         */
        $np = ReservaDeNumeros::INICIO['np'];
        $aa = ReservaDeNumeros::INICIO['aa'];

        $this->assertNotContains((string) $np, self::DOCUMENTOS_JA_LAVRADOS);
        $this->assertNotContains((string) $aa, self::DOCUMENTOS_JA_LAVRADOS);
        $this->assertGreaterThan(194905, $np);
        $this->assertGreaterThan(160051, $aa);
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
        $this->assertStringContainsString(
            'status: LOCAL_APOS_O_DESFECHO[novo.desfecho]',
            $this->fonteDoAplicativo('app.tsx'),
            'o registro novo deixou de derivar a leitura regular/irregular do desfecho',
        );

        // E a régua é a MESMA dos dois lados: o servidor deriva igual ao aparelho.
        $doAparelho = array_map(
            static fn (string $v): string => trim($v, " '"),
            $this->mapaDeTextos($this->fonteDoAplicativo('dados-demandas.ts'), 'LOCAL_APOS_O_DESFECHO'),
        );
        $doServidor = (new \ReflectionClassConstant(AplicativoParaTela::class, 'LOCAL_APOS_O_DESFECHO'))->getValue();

        $this->assertEqualsCanonicalizing($doServidor, $doAparelho);
    }
}
