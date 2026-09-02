<?php

use App\Http\Controllers\Retaguarda\AcompanhamentoRequisitosController;
use App\Http\Controllers\Retaguarda\AreasEEquipesController;
use App\Http\Controllers\Retaguarda\CadastroAmbulanteController;
use App\Http\Controllers\Retaguarda\CaixaDeEntradaController;
use App\Http\Controllers\Retaguarda\ExportacaoListagemController;
use App\Http\Controllers\Retaguarda\InicioController;
use App\Http\Controllers\Retaguarda\LogsController;
use App\Http\Controllers\Retaguarda\MapaAoVivoController;
use App\Http\Controllers\Retaguarda\MapaDeCalorController;
use App\Http\Controllers\Retaguarda\ModoGerenteController;
use App\Http\Controllers\Retaguarda\MonitoramentoParametrizacoesController;
use App\Http\Controllers\Retaguarda\Parametrizacao\AtividadesDoAmbulanteController;
use App\Http\Controllers\Retaguarda\Parametrizacao\MotivosDeRecusaController;
use App\Http\Controllers\Retaguarda\Parametrizacao\OrigensDeOperacaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\TiposDeInfracaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\TiposDeOperacaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\UnidadesDeMedidaController;
use App\Http\Controllers\Retaguarda\RelatoriosController;
use App\Http\Controllers\Retaguarda\TelasEmPreparacaoController;
use Illuminate\Support\Facades\Route;

/*
 * A raiz não tem conteúdo próprio: este sistema é ferramenta de trabalho, não
 * site. Quem chega pelo endereço nu vai direto para a entrada — e quem já está
 * autenticado é levado do login para a tela inicial pela própria autenticação.
 *
 * O nome `home` fica: é o destino a que o Fortify manda quem sai do sistema.
 */
Route::redirect('/', '/login')->name('home');

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
     * As telas do caminho da fiscalização que ainda não existem — Cadastro de
     * Operação e Fiscalizações.
     *
     * Elas abrem e dizem, em uma linha, o que vão ser e em que fase chegam (ver o
     * cabeçalho do controller). O laço nasce do catálogo do próprio controller, e
     * não de uma segunda lista aqui: a tela real, quando chegar, toma o slug e a
     * rota, e a entrada sai de LÁ — sem deixar aqui um caminho apontando para
     * andaime removido.
     *
     * O nome da rota é `retaguarda.<slug>.index`, o mesmo padrão das telas de
     * verdade: assim o menu não precisa saber que se trata de um stub, e trocar o
     * andaime pela tela é trocar o destino de uma linha.
     */
    foreach (TelasEmPreparacaoController::slugs() as $slugEmPreparacao) {
        Route::get('retaguarda/'.$slugEmPreparacao, [TelasEmPreparacaoController::class, 'mostrar'])
            ->name('retaguarda.'.$slugEmPreparacao.'.index');
    }

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
     * Ambulantes — a identidade de quem é fiscalizado.
     *
     * O primeiro trecho do caminho é o slug da tela (`ambulantes`), que é de
     * onde as guardas deduzem a permissão: as rotas nascem protegidas, e a rota
     * que vier amanhã (prontuário, validação de quarentena) já chega junto.
     *
     * O identificador vai como NÚMERO, e não o código nem o nome: o WAF da
     * Prefeitura barra assinatura de SQL na URL, e nome de gente é texto livre.
     */
    Route::prefix('retaguarda/ambulantes')->name('retaguarda.ambulantes.')->group(function () {
        Route::get('/', [CadastroAmbulanteController::class, 'index'])->name('index');

        // A foto sai por aqui, e não por URL de disco público: é retrato de
        // cidadão fiscalizado, e mora sob o caminho da tela justamente para a
        // guarda de leitura conferir a permissão antes de entregar a imagem.
        Route::get('{ambulante}/foto', [CadastroAmbulanteController::class, 'foto'])
            ->name('foto')->whereNumber('ambulante');

        Route::post('/', [CadastroAmbulanteController::class, 'store'])->name('store');
        Route::put('{ambulante}', [CadastroAmbulanteController::class, 'update'])
            ->name('update')->whereNumber('ambulante');
        Route::delete('{ambulante}', [CadastroAmbulanteController::class, 'destroy'])
            ->name('destroy')->whereNumber('ambulante');
    });

    /*
     * Caixa de Entrada do Administrativo — PROTÓTIPO.
     *
     * A porta por onde a demanda de fora entra: e-Salvador, Fala Salvador 156,
     * pedido de nova licença e ofício chegam em PAPEL, e é aqui que o
     * administrativo digita, decide e encaminha à equipe da área do bairro.
     *
     * O primeiro trecho do caminho é o slug da tela (`caixa-de-entrada`), de onde
     * as guardas deduzem a permissão: as mutações abaixo nascem protegidas pela
     * convenção de nomes (`.store` inclui, o resto opera) — nada a declarar em
     * `config/permissao_acoes.php`.
     *
     * O identificador vai como NÚMERO: o WAF da Prefeitura barra assinatura de
     * SQL na URL, e protocolo é texto.
     */
    Route::prefix('retaguarda/caixa-de-entrada')->name('retaguarda.caixa-de-entrada.')->group(function () {
        Route::get('/', [CaixaDeEntradaController::class, 'index'])->name('index');
        Route::post('/', [CaixaDeEntradaController::class, 'store'])->name('store');
        Route::post('{demanda}/encaminhar', [CaixaDeEntradaController::class, 'encaminhar'])
            ->name('encaminhar')->whereNumber('demanda');
        Route::post('{demanda}/devolver', [CaixaDeEntradaController::class, 'devolver'])
            ->name('devolver')->whereNumber('demanda');
        // Só existe porque é protótipo: devolve a caixa ao estado de demonstração.
        Route::post('reiniciar', [CaixaDeEntradaController::class, 'reiniciar'])->name('reiniciar');
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
        Route::post('reiniciar', [AreasEEquipesController::class, 'reiniciar'])->name('reiniciar');
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

/*
 * PROTÓTIPO do aplicativo do fiscal (PWA).
 *
 * Não é tela da Retaguarda: é uma aplicação de página única, servida por uma
 * casca própria (`resources/views/pwa.blade.php`), com dados FICTÍCIOS escritos
 * no próprio navegador. Enquanto for protótipo ela não tem servidor por trás —
 * nada é lido do banco e NADA é gravado —, e por isso mora fora da autenticação
 * e responde apenas a GET.
 *
 * O caminho é livre depois de `/app` porque a navegação interna é do lado do
 * cliente: quem recarregar a página em qualquer ponto do fluxo recebe a mesma
 * casca, e o aplicativo se reencontra sozinho.
 */
Route::get('app/{caminho?}', fn () => view('pwa'))
    ->where('caminho', '.*')
    ->name('pwa');

require __DIR__.'/settings.php';
