<?php

use App\Http\Middleware\GarantirUsuarioAtivo;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
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
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

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
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            GarantirUsuarioAtivo::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
