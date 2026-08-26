<?php

use App\Http\Controllers\Retaguarda\AcompanhamentoRequisitosController;
use App\Http\Controllers\Retaguarda\CadastroPermissionarioController;
use App\Http\Controllers\Retaguarda\ExportacaoListagemController;
use App\Http\Controllers\Retaguarda\InicioController;
use App\Http\Controllers\Retaguarda\LogsController;
use App\Http\Controllers\Retaguarda\ModoGerenteController;
use App\Http\Controllers\Retaguarda\MonitoramentoParametrizacoesController;
use App\Http\Controllers\Retaguarda\Parametrizacao\AtividadesDoAmbulanteController;
use App\Http\Controllers\Retaguarda\Parametrizacao\MotivosDeRecusaController;
use App\Http\Controllers\Retaguarda\Parametrizacao\OrigensDeOperacaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\TiposDeInfracaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\TiposDeOperacaoController;
use App\Http\Controllers\Retaguarda\Parametrizacao\UnidadesDeMedidaController;
use App\Http\Controllers\Retaguarda\RelatoriosController;
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
     * Permissionários — a identidade de quem é fiscalizado.
     *
     * O primeiro trecho do caminho é o slug da tela (`permissionarios`), que é de
     * onde as guardas deduzem a permissão: as rotas nascem protegidas, e a rota
     * que vier amanhã (prontuário, validação de quarentena) já chega junto.
     *
     * O identificador vai como NÚMERO, e não o código nem o nome: o WAF da
     * Prefeitura barra assinatura de SQL na URL, e nome de gente é texto livre.
     */
    Route::prefix('retaguarda/permissionarios')->name('retaguarda.permissionarios.')->group(function () {
        Route::get('/', [CadastroPermissionarioController::class, 'index'])->name('index');
        Route::post('/', [CadastroPermissionarioController::class, 'store'])->name('store');
        Route::put('{permissionario}', [CadastroPermissionarioController::class, 'update'])
            ->name('update')->whereNumber('permissionario');
        Route::delete('{permissionario}', [CadastroPermissionarioController::class, 'destroy'])
            ->name('destroy')->whereNumber('permissionario');
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
