<?php

use App\Http\Middleware\GarantirUsuarioAtivo;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Permissao;
use App\Http\Middleware\PermissaoAcao;
use App\Http\Middleware\RequestId;
use App\Models\LogErro;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // PRIMEIRO de tudo, no encadeamento global: o código da requisição precisa
        // existir antes de qualquer coisa que possa falhar — inclusive a checagem
        // de manutenção e o início da sessão. É ele que a página de erro mostra ao
        // usuário e que o registro da exceção guarda; se nascesse dentro do grupo
        // `web`, o erro mais grave (o que acontece antes da aplicação ficar de pé)
        // sairia sem código nenhum.
        $middleware->prepend(RequestId::class);

        // O cookie de tema fica em texto claro porque a página precisa lê-lo no
        // servidor para pintar o fundo certo já na primeira renderização (sem ele,
        // quem usa o tema escuro vê um lampejo branco a cada visita).
        $middleware->encryptCookies(except: ['appearance']);

        // Para onde vai quem JÁ está autenticado e abre uma tela de visitante
        // (a de entrar, por exemplo). O padrão do framework é a raiz — e a raiz
        // manda para a tela de entrar, o que fecharia um LOOP de
        // redirecionamento: a pessoa logada digitaria o endereço nu e o
        // navegador morreria em "too many redirects", sem dizer o porquê.
        // O destino é o mesmo do fim do login (`fortify.home`), lido no momento
        // da requisição para não haver dois lugares dizendo onde é a casa.
        $middleware->redirectUsersTo(fn () => config('fortify.home'));

        // A guarda de usuário ativo vai no grupo `web` inteiro, e não numa rota ou
        // outra: assim vale para QUALQUER tela autenticada, inclusive as que ainda
        // vão nascer, sem depender de alguém lembrar de pendurá-la lá.
        //
        // A POSIÇÃO importa: a guarda vem DEPOIS do middleware do Inertia, para que a
        // resposta dela passe pelo tratamento de redirecionamento dele (é o Inertia que
        // transforma 302 em 303 nas requisições PUT/PATCH/DELETE). Se ela viesse antes,
        // envolveria o Inertia em vez de ser envolvida por ele: o navegador repetiria o
        // PATCH contra a tela de login e receberia 405 no lugar da tela — barrado em
        // silêncio, exatamente o que a lei do projeto proíbe.
        //
        // As guardas de permissão também vão no grupo inteiro, e pela mesma
        // razão: é o que faz a tela nova nascer protegida, em vez de depender de
        // alguém lembrar de pendurar a guarda na rota dela. Elas saem de cena
        // sozinhas quando não há ninguém autenticado (aí quem responde é a
        // guarda de autenticação) ou quando o caminho não é da Retaguarda.
        //
        // A ordem entre as duas é indiferente — uma cuida da leitura, a outra das
        // mutações —, mas as duas vêm DEPOIS do middleware do Inertia, pelo mesmo
        // motivo da guarda de usuário ativo: é ele que transforma 302 em 303 nas
        // requisições PUT/PATCH/DELETE, e sem isso o navegador repetiria o verbo
        // contra o destino do redirecionamento.
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            GarantirUsuarioAtivo::class,
            Permissao::class,
            PermissaoAcao::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Toda exceção reportável vira uma LINHA consultável em tela.
         *
         * O framework já não reporta 404/403/419/422 (são conversas normais com o
         * usuário), então aqui caem só os erros de verdade. O registro carrega o
         * mesmo código que a página de erro mostrou a quem estava usando o
         * sistema — é ele que transforma "deu erro" numa ocorrência específica.
         *
         * A gravação é best-effort e nunca lança (ver `LogErro::registrar`): quem
         * chega aqui já está com um problema, e um segundo erro por cima do
         * primeiro custaria ao usuário até a página amigável.
         */
        $exceptions->report(function (Throwable $e): void {
            LogErro::registrar($e, request(), RequestId::atual());
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
