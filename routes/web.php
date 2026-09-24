<?php

use App\Http\Controllers\Retaguarda\AcompanhamentoRequisitosController;
use App\Http\Controllers\Retaguarda\AgrupamentoDeDemandasController;
use App\Http\Controllers\Retaguarda\AmbulantesController;
use App\Http\Controllers\Retaguarda\AreasEEquipesController;
use App\Http\Controllers\Retaguarda\CaixaDeEntradaController;
use App\Http\Controllers\Retaguarda\DenunciasController;
use App\Http\Controllers\Retaguarda\ExportacaoListagemController;
use App\Http\Controllers\Retaguarda\FiscalizacoesController;
use App\Http\Controllers\Retaguarda\InicioController;
use App\Http\Controllers\Retaguarda\LogsController;
use App\Http\Controllers\Retaguarda\MapaAoVivoController;
use App\Http\Controllers\Retaguarda\MapaDeCalorController;
use App\Http\Controllers\Retaguarda\ModoGerenteController;
use App\Http\Controllers\Retaguarda\MonitoramentoParametrizacoesController;
use App\Http\Controllers\Retaguarda\OperacoesController;
use App\Http\Controllers\Retaguarda\Parametrizacao\AtividadesDoAmbulanteController;
use App\Http\Controllers\Retaguarda\Parametrizacao\MotivosDeRecusaController;
use App\Http\Controllers\Retaguarda\Parametrizacao\OrigensDeOperacaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\TiposDeInfracaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\TiposDeOperacaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\UnidadesDeMedidaController;
use App\Http\Controllers\Retaguarda\RelatoriosController;
use App\Http\Controllers\Retaguarda\RetornoAoCanalController;
use App\Http\Controllers\Retaguarda\UsuariosController;
use App\Http\Middleware\SoChefeDeSetor;
use App\Models\Demanda;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

/*
 * A raiz não tem conteúdo próprio: este sistema é ferramenta de trabalho, não
 * site. Quem chega pelo endereço nu vai direto para a entrada — e quem já está
 * autenticado é levado do login para a tela inicial pela própria autenticação.
 *
 * O nome `home` fica: é o destino a que o Fortify manda quem sai do sistema.
 */
Route::redirect('/', '/login')->name('home');

/*
 * O endereço APOSENTADO do Retorno de Campo.
 *
 * A tela virou a aba "A decidir" de Fiscalizações, e o caminho antigo continua
 * respondendo — redirecionando. Quem trabalhava nela tem o endereço no
 * favorito, no e-mail de aviso, na conversa de ontem; devolver "não
 * encontrado" a esse link seria transformar uma melhoria em falha, e a pessoa
 * concluiria que perdeu a fila dela.
 *
 * É 301 (permanente) de propósito: o endereço não volta. E é um redirecionamento,
 * e não uma segunda rota para o mesmo controller — duas portas para a mesma tela
 * dariam dois slugs à mesma permissão, e a guarda deduz a tela do caminho.
 *
 * ⚠️ Só GET, e escrito à mão em vez de `Route::permanentRedirect`: aquele helper
 * registra o caminho para TODOS os verbos, e um POST/PUT/DELETE sob um caminho que
 * já não é tela de ninguém é mutação que a guarda de ações não consegue atribuir a
 * tela nenhuma — brecha, e `PermissaoAcaoCoberturaTest` reprova com razão. O que
 * se preserva aqui é LINK SALVO, e link salvo é GET; as antigas rotas de ação
 * mudaram de caminho junto com a tela e não têm de onde ser chamadas.
 *
 * ⚠️ Fica FORA do grupo autenticado: exigir sessão para ser desviado mandaria quem
 * não está logado para o login sem nunca chegar ao destino certo depois.
 */
Route::get('retaguarda/retorno-de-campo', fn () => redirect('/retaguarda/fiscalizacoes', 301));

Route::middleware(['auth'])->group(function () {
    // Tela inicial da Retaguarda: é para cá que o login joga quem entrou — e é
    // para cá que a guarda de permissão manda quem foi barrado, então ela não
    // pode ser controlada por permissão (fecharia um loop). Ver o cabeçalho de
    // `config/retaguarda_menu.php`.
    // Os atalhos vêm do SERVIDOR (ver o cabeçalho do controller): escritos na
    // tela, o cartão de uma tela pronta continuava anunciando "Em construção".
    Route::get('retaguarda/inicio', [InicioController::class, 'index'])->name('retaguarda.inicio');

    /*
     * Modo Gerente — quem entra onde.
     *
     * O caminho começa com o slug da tela (`modo-gerente`) porque é dele que as
     * guardas deduzem a que tela cada endereço pertence: assim a rota que nascer
     * aqui amanhã já chega protegida, sem ninguém declarar nada.
     */
    Route::prefix('retaguarda/modo-gerente')->name('retaguarda.modo-gerente.')->group(function () {
        Route::get('/', [ModoGerenteController::class, 'index'])->name('index');
        Route::post('/', [ModoGerenteController::class, 'salvar'])->name('salvar');
    });

    /*
     * As duas telas de MAPA — PROTÓTIPO, no padrão imersivo (RN-07).
     *
     * Só GET: mapa é leitura. Quem grava fiscalização é o aplicativo do fiscal,
     * em rua; quem cria operação é o Cadastro de Operação, para onde a
     * recomendação do mapa de calor leva.
     *
     * Elas TOMARAM o slug e o nome de rota que o catálogo de telas em preparação
     * lhes emprestava (`retaguarda.mapa.index`, `retaguarda.mapa-de-calor.index`)
     * — é a troca prevista pela RN-09, e por isso o menu não foi tocado.
     */
    Route::get('retaguarda/mapa', [MapaAoVivoController::class, 'index'])
        ->name('retaguarda.mapa.index');

    Route::get('retaguarda/mapa-de-calor', [MapaDeCalorController::class, 'index'])
        ->name('retaguarda.mapa-de-calor.index');

    /*
     * Relatórios — documento oficial, pedido de propósito, com período e totais.
     *
     * A emissão é POST, e não GET: os filtros carregam texto livre e datas, e o
     * WAF da Prefeitura barra assinatura de SQL na URL — a falha voltaria
     * disfarçada de erro de CORS. O nome `gerar` cai na ação "opera" da guarda,
     * que é o que emitir um documento é: usar a tela, não incluir registro.
     */
    Route::prefix('retaguarda/relatorios')->name('retaguarda.relatorios.')->group(function () {
        Route::get('/', [RelatoriosController::class, 'index'])->name('index');
        Route::post('gerar', [RelatoriosController::class, 'gerar'])->name('gerar');
    });

    /*
     * Logs — as exceções capturadas, consultáveis em tela.
     *
     * Só GET: log de erro é a prova do que aconteceu, e uma tela que permitisse
     * apagar linha daqui apagaria a única trilha de um defeito de produção.
     * `ObservabilidadeTest` reprova se alguma mutação nascer sob este caminho.
     */
    /*
     * Usuários — quem tem conta na Retaguarda, e em que setor.
     *
     * O caminho começa pelo slug da tela (`usuarios`): é dele que as guardas de
     * leitura e de ação deduzem a permissão, e a semente a dá só ao
     * administrador. Excluir é mandar para a lixeira; `restaurar` a tira de lá.
     * O `{usuario}` da restauração é o número, e não o modelo: a conta excluída
     * não é encontrada pela busca comum — é justamente o que a lixeira faz.
     */
    Route::prefix('retaguarda/usuarios')->name('retaguarda.usuarios.')->group(function () {
        Route::get('/', [UsuariosController::class, 'index'])->name('index');
        Route::post('/', [UsuariosController::class, 'store'])->name('store');
        Route::put('{usuario}', [UsuariosController::class, 'update'])->name('update');
        Route::delete('{usuario}', [UsuariosController::class, 'destroy'])->name('destroy');
        Route::post('{usuario}/convite', [UsuariosController::class, 'convite'])->name('convite');
        Route::post('{usuario}/restaurar', [UsuariosController::class, 'restaurar'])
            ->whereNumber('usuario')->name('restaurar');
    });

    Route::prefix('retaguarda/logs')->name('retaguarda.logs.')->group(function () {
        Route::get('/', [LogsController::class, 'index'])->name('index');
        // O rastro de UMA ocorrência, que a listagem não carrega (campo longo).
        Route::get('{log}', [LogsController::class, 'detalhe'])->name('detalhe');
    });

    /*
     * Monitoramento — as verificações de "tudo verde, sistema operacional".
     *
     * As profundas (escrita real em disco, serviço externo) ficam numa rota à
     * parte, chamada só pelo botão: a tela de diagnóstico não pode depender do
     * que está diagnosticando para conseguir abrir.
     */
    Route::prefix('retaguarda/monitoramento')->name('retaguarda.monitoramento.')->group(function () {
        Route::get('/', [MonitoramentoParametrizacoesController::class, 'index'])->name('index');
        Route::get('profundo', [MonitoramentoParametrizacoesController::class, 'profundo'])->name('profundo');
    });

    /*
     * Acompanhamento de Requisitos — o que está construído bate com o escrito?
     *
     * Só GET: o mapa vive em `config/acompanhamento_requisitos.php`, versionado
     * junto com o código que ele descreve. Editar por tela daria dois donos à
     * mesma informação, e um dia os dois discordariam.
     */
    Route::get('retaguarda/acompanhamento-de-requisitos', [AcompanhamentoRequisitosController::class, 'index'])
        ->name('retaguarda.acompanhamento-de-requisitos.index');

    /*
     * Ambulantes — CONSULTA da base que o SGCI entrega.
     *
     * ⚠️ SÓ GET, e isso é a decisão do dono de 10/09/2026 ("a tela de Ambulantes
     * não será CRUD, só irá receber os registros do SGCI via integração"). As
     * rotas de inclusão (`store`), alteração (`update`) e exclusão (`destroy`)
     * SAÍRAM daqui junto com os métodos do controller.
     *
     * Tirar o botão da tela e deixar a rota viva seria PIOR que não mudar nada:
     * o servidor continuaria aceitando escrita de quem montasse a requisição, e
     * o que fosse gravado na base espelho seria desfeito em silêncio pela
     * próxima carga do SGCI. A base não é nossa.
     *
     * O primeiro trecho do caminho é o slug da tela (`ambulantes`), que é de
     * onde as guardas deduzem a permissão: as rotas nascem protegidas, e a rota
     * que vier amanhã (o prontuário de movimentação) já chega junto. O slug NÃO
     * mudou com a natureza da tela — ele é identidade de acesso, e trocá-lo
     * mataria a permissão de quem já a tem.
     *
     * O identificador vai como NÚMERO, e não o código nem o nome: o WAF da
     * Prefeitura barra assinatura de SQL na URL, e nome de gente é texto livre.
     */
    Route::prefix('retaguarda/ambulantes')->name('retaguarda.ambulantes.')->group(function () {
        Route::get('/', [AmbulantesController::class, 'index'])->name('index');

        // A foto sai por aqui, e não por URL de disco público: é retrato de
        // cidadão fiscalizado, e mora sob o caminho da tela justamente para a
        // guarda de leitura conferir a permissão antes de entregar a imagem.
        Route::get('{ambulante}/foto', [AmbulantesController::class, 'foto'])
            ->name('foto')->whereNumber('ambulante');
    });

    /*
     * Caixa de Entrada — a mesa do Chefe de Setor.
     *
     * A porta por onde a demanda entra fora da integração: papel do
     * e-Salvador, pedido de nova licença, ofício e a avulsa (ligação ou e-mail
     * de superior). É aqui que o chefe digita, decide e encaminha à equipe
     * sugerida pelo bairro. O Fala Salvador não entra por aqui: é do líder.
     *
     * O primeiro trecho do caminho é o slug da tela (`caixa-de-entrada`), de onde
     * as guardas deduzem a permissão: as mutações abaixo nascem protegidas pela
     * convenção de nomes (`.store` inclui, o resto opera) — nada a declarar em
     * `config/permissao_acoes.php`.
     *
     * O identificador vai como NÚMERO: o WAF da Prefeitura barra assinatura de
     * SQL na URL, e protocolo é texto.
     */
    // A mesa antiga do chefe (cadastro com encaminhamento, pré-triagem): fora
    // do menu desde 24/09/2026 e SÓ do chefe — ver App\Http\Middleware\SoChefeDeSetor.
    Route::prefix('retaguarda/caixa-de-entrada')->name('retaguarda.caixa-de-entrada.')
        ->middleware(SoChefeDeSetor::class)->group(function () {
            Route::get('/', [CaixaDeEntradaController::class, 'index'])->name('index');
            Route::post('/', [CaixaDeEntradaController::class, 'store'])->name('store');
            Route::post('{demanda}/encaminhar', [CaixaDeEntradaController::class, 'encaminhar'])
                ->name('encaminhar')->whereNumber('demanda');
            Route::post('{demanda}/devolver', [CaixaDeEntradaController::class, 'devolver'])
                ->name('devolver')->whereNumber('demanda');

            /*
             * A PRÉ-TRIAGEM: dez denúncias que são um fato.
             *
             * As mesmas quatro ações existem sob os dois caminhos (aqui e em
             * `denuncias`), apontando para o MESMO controller. É de propósito: a
             * guarda de acesso deduz a tela do primeiro trecho do caminho, então cada
             * porta herda a permissão da tela onde o chefe já está — em vez de
             * a pré-triagem virar uma terceira tela, com uma terceira concessão para
             * alguém esquecer de dar.
             */
            Route::post('agrupamento/varrer', [AgrupamentoDeDemandasController::class, 'varrer'])
                ->name('agrupamento.varrer');
            Route::post('agrupamento/liberar', [AgrupamentoDeDemandasController::class, 'liberar'])
                ->name('agrupamento.liberar');
            Route::post('agrupamento/juntar', [AgrupamentoDeDemandasController::class, 'juntar'])
                ->name('agrupamento.juntar');
            Route::post('agrupamento/sugestoes/{sugestao}/aceitar', [AgrupamentoDeDemandasController::class, 'aceitar'])
                ->name('agrupamento.aceitar')->whereNumber('sugestao');
            Route::post('agrupamento/sugestoes/{sugestao}/recusar', [AgrupamentoDeDemandasController::class, 'recusar'])
                ->name('agrupamento.recusar')->whereNumber('sugestao');
            Route::post('agrupamento/{demanda}/agrupar', [AgrupamentoDeDemandasController::class, 'agrupar'])
                ->name('agrupamento.agrupar')->whereNumber('demanda');
            Route::post('agrupamento/{demanda}/desagrupar', [AgrupamentoDeDemandasController::class, 'desagrupar'])
                ->name('agrupamento.desagrupar')->whereNumber('demanda');
        });

    /*
     * As CAIXAS DE ENTRADA por canal — as quatro frentes (decisão do dono,
     * 24/09/2026): e-Salvador (denúncias e licenças), Fala Salvador, e-Protocolo
     * (atendimento presencial na sede da SEFAL) e Avulsas.
     *
     * Eram duas seções que faziam o mesmo trabalho — a Caixa de Entrada (o que o
     * chefe digitava) e as Denúncias (o que chegava pelos canais). Agora cada
     * canal é uma tela, com o mesmo fluxo: o chefe encaminha à equipe, o líder
     * direciona aos fiscais, o retorno volta ao líder e ao chefe, e o chefe
     * responde ao canal.
     *
     * Moram sob `caixa-de-entrada` porque é desse primeiro trecho que as guardas
     * deduzem a permissão: UMA concessão para as quatro. Os NOMES das rotas
     * continuam `retaguarda.denuncias.*` — é o nome do módulo no código, e é o
     * que o front importa.
     *
     * As mutações vão no corpo do POST porque carregam lista de identificadores
     * e texto livre — em query string o WAF da Prefeitura barraria, e a falha
     * voltaria disfarçada de erro de CORS.
     */
    Route::prefix('retaguarda/caixa-de-entrada')->name('retaguarda.denuncias.')->group(function () {
        Route::get('e-salvador', [DenunciasController::class, 'eSalvador'])->name('e-salvador.index');
        Route::get('fala-salvador', [DenunciasController::class, 'falaSalvador'])->name('fala-salvador.index');
        Route::get('e-protocolo', [DenunciasController::class, 'eProtocolo'])->name('e-protocolo.index');
        Route::get('avulsas', [DenunciasController::class, 'avulsas'])->name('avulsas.index');

        /*
         * O CADASTRO manual, por canal: o chefe digita o que chega a ele (papel
         * do e-Salvador, licença, e-Protocolo, avulsa); o líder digita o Fala
         * Salvador, que só ele acessa. Quem pode o quê é regra do controller.
         */
        Route::post('{canal}/registrar', [DenunciasController::class, 'registrar'])
            ->name('registrar')->whereIn('canal', Demanda::CANAIS);

        // O RETORNO AO CANAL — o chefe responde (ou abre o processo da avulsa).
        Route::post('{demanda}/responder-ao-canal', [RetornoAoCanalController::class, 'store'])
            ->name('responder-ao-canal')->whereNumber('demanda');

        Route::post('encaminhar', [DenunciasController::class, 'encaminhar'])->name('encaminhar');
        Route::post('devolver', [DenunciasController::class, 'devolver'])->name('devolver');
        Route::post('direcionar', [DenunciasController::class, 'direcionar'])->name('direcionar');
        Route::post('operacao', [DenunciasController::class, 'operacao'])->name('operacao');
    });

    /*
     * Endereços antigos das telas de canal (até 24/09/2026): quem tinha o link
     * salvo cai na tela nova, e não num 404.
     */
    // Só GET: `Route::redirect` responde a qualquer verbo, e uma "mutação" sem
    // permissão mapeada é justamente o que a cobertura de ações barra.
    foreach (['e-salvador', 'fala-salvador'] as $antigo) {
        Route::get("retaguarda/denuncias/{$antigo}", RedirectController::class)
            ->defaults('destination', "/retaguarda/caixa-de-entrada/{$antigo}")
            ->defaults('status', 302);
    }

    /*
     * Fiscalizações — TODO registro de fiscalização concluído. PROTÓTIPO.
     *
     * Uma tela, duas abas sobre o MESMO conjunto: "A decidir" é a fila do Chefe de
     * Setor (o que voltou da rua e espera a leitura dele) e "Acervo" é a consulta
     * do que já passou por aqui. Elas eram DUAS telas — "Retorno de Campo",
     * construída, e "Fiscalizações", um andaime que prometia a consulta —, e duas
     * telas sobre o mesmo registro divergem: uma ganharia regra nova e a outra
     * continuaria mostrando o mundo de antes. Unificadas por decisão do dono
     * (09/09/2026).
     *
     * As duas decisões da chefia: dar CIÊNCIA (o retorno sai da fila e fica no
     * acervo) ou determinar NOVA VISTORIA (a equipe volta ao ponto, com
     * justificativa obrigatória).
     *
     * Não há rota de INCLUSÃO, e isso é deliberado: registro de fiscalização nasce
     * em RUA, no aplicativo do fiscal. Um botão de cadastrar aqui criaria um
     * segundo dono para o ato que dá sentido às duas abas.
     *
     * O primeiro trecho do caminho é o slug da tela (`fiscalizacoes`), de onde
     * as guardas deduzem a permissão: as mutações nascem protegidas pela convenção
     * de nomes (nada a declarar em `config/permissao_acoes.php`). É por isso que
     * elas MUDARAM de caminho junto com a tela — deixadas sob `retorno-de-campo`,
     * elas passariam a pedir a permissão de uma tela que não existe mais.
     *
     * As mutações vão no CORPO do POST porque carregam lista de identificadores e
     * texto livre de justificativa — em query string o WAF da Prefeitura
     * barraria, e a falha voltaria disfarçada de erro de CORS.
     */
    Route::prefix('retaguarda/fiscalizacoes')->name('retaguarda.fiscalizacoes.')->group(function () {
        Route::get('/', [FiscalizacoesController::class, 'index'])->name('index');
        // Não há "dar ciência" (dono, 24/09/2026): o líder manda voltar ou encaminha ao chefe.
        Route::post('nova-vistoria', [FiscalizacoesController::class, 'novaVistoria'])->name('nova-vistoria');
        Route::post('encaminhar-ao-chefe', [FiscalizacoesController::class, 'encaminharAoChefe'])->name('encaminhar-ao-chefe');
        Route::post('arquivar', [FiscalizacoesController::class, 'arquivar'])->name('arquivar');
        // Só existe porque é protótipo: devolve a fila ao estado de demonstração.
    });

    /*
     * Cadastro de Operação — PROTÓTIPO.
     *
     * "A operação é evento; a equipe é organização." O trabalho de rua com começo,
     * fim e foco que a gestão monta em cima da estrutura de áreas e equipes.
     *
     * ⚠️ Esta tela lê e escreve o MESMO catálogo que o direcionamento das
     * denúncias consome (`App\Support\Prototipo\OperacoesFicticias`): com duas
     * listas, o direcionamento ofereceria amanhã uma operação que o cadastro não
     * conhece.
     *
     * O identificador vai como NÚMERO no caminho, e não o nome: o WAF da
     * Prefeitura barra assinatura de SQL na URL, e nome de operação é texto livre
     * digitado por gente.
     */
    Route::prefix('retaguarda/operacoes')->name('retaguarda.operacoes.')->group(function () {
        Route::get('/', [OperacoesController::class, 'index'])->name('index');
        Route::post('/', [OperacoesController::class, 'store'])->name('store');
        Route::put('{operacao}', [OperacoesController::class, 'update'])
            ->name('update')->whereNumber('operacao');
        Route::delete('{operacao}', [OperacoesController::class, 'destroy'])
            ->name('destroy')->whereNumber('operacao');
        // Só existe porque é protótipo: devolve o catálogo ao estado de demonstração.
    });

    /*
     * Áreas e Equipes — PROTÓTIPO da estrutura permanente de fiscalização.
     *
     * Área > Equipe > bloco de bairros. É desta estrutura que sai a derivação
     * bairro → equipe usada pela Caixa de Entrada, então as duas telas leem a
     * MESMA fonte (`App\Support\Prototipo\EstruturaFicticia`): duplicar a lista
     * faria a sugestão discordar do cadastro no primeiro ajuste.
     */
    Route::prefix('retaguarda/areas-e-equipes')->name('retaguarda.areas-e-equipes.')->group(function () {
        Route::get('/', [AreasEEquipesController::class, 'index'])->name('index');
        Route::post('/', [AreasEEquipesController::class, 'store'])->name('store');
        Route::put('{area}', [AreasEEquipesController::class, 'update'])
            ->name('update')->whereNumber('area');
        Route::delete('{area}', [AreasEEquipesController::class, 'destroy'])
            ->name('destroy')->whereNumber('area');
        Route::post('{area}/bairros', [AreasEEquipesController::class, 'bairros'])
            ->name('bairros')->whereNumber('area');
    });

    /*
     * Parametrização — as listas de escolha que o resto do sistema oferece.
     *
     * As seis têm o mesmo desenho de rotas (listar, incluir, alterar, excluir),
     * então o registro é um laço: seis famílias escritas à mão seriam seis
     * chances de uma delas nascer sem a rota de exclusão.
     *
     * O primeiro trecho do caminho é `parametrizacao` para as seis, e é dele que
     * as guardas de acesso deduzem a tela: a permissão é UMA, para o conjunto —
     * ver o cabeçalho do `ControllerDeLookup`.
     *
     * O identificador do registro vai como NÚMERO no caminho, e não o nome: o
     * WAF da Prefeitura barra assinatura de SQL na URL, e nome de lista é texto
     * livre digitado por gente.
     */
    $telasDeParametrizacao = [
        'tipos-de-infracao' => TiposDeInfracaoController::class,
        'atividades-do-ambulante' => AtividadesDoAmbulanteController::class,
        'unidades-de-medida' => UnidadesDeMedidaController::class,
        'tipos-de-operacao' => TiposDeOperacaoController::class,
        'origens-de-operacao' => OrigensDeOperacaoController::class,
        'motivos-de-recusa' => MotivosDeRecusaController::class,
    ];

    Route::prefix('retaguarda/parametrizacao')->name('retaguarda.parametrizacao.')
        ->group(function () use ($telasDeParametrizacao) {
            foreach ($telasDeParametrizacao as $caminho => $controlador) {
                Route::prefix($caminho)->name($caminho.'.')->group(function () use ($controlador) {
                    Route::get('/', [$controlador, 'index'])->name('index');
                    Route::post('/', [$controlador, 'store'])->name('store');
                    Route::put('{item}', [$controlador, 'update'])->name('update')->whereNumber('item');
                    Route::delete('{item}', [$controlador, 'destroy'])->name('destroy')->whereNumber('item');
                });
            }
        });

    /*
     * Exportação de LISTAGEM — o ponto único de PDF/XLSX/DOCX de toda grade e de
     * toda aba "Localizar". Não é tela: é serviço que qualquer listagem usa, com
     * o recorte visível no CORPO do POST (ver o cabeçalho do controller e a
     * declaração em `config/permissao_acoes.php`).
     */
    Route::post('retaguarda/exportar-listagem', [ExportacaoListagemController::class, 'exportar'])
        ->name('retaguarda.exportar-listagem');
});

require __DIR__.'/settings.php';
