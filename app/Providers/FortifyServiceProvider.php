<?php

namespace App\Providers;

use App\Actions\Fortify\AutenticarPorMatricula;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->marcarEntradaNoSistema();
    }

    /**
     * Marca na sessão que a pessoa ACABOU DE ENTRAR — é o que faz o splash de
     * boas-vindas aparecer uma vez, na primeira tela, e nunca mais.
     *
     * O gancho é o evento de autenticação, e não uma resposta de login própria:
     * assim vale para todo caminho que autentica de verdade (o formulário de
     * matrícula, o "continuar conectado" que volta pelo cookie, e o que vier
     * amanhã) sem depender de alguém lembrar de marcar em cada um.
     *
     * Quem CONSOME a marca é o `HandleInertiaRequests` — e lá está escrito por que
     * ela é consumida na entrega, e não por flash de sessão.
     */
    private function marcarEntradaNoSistema(): void
    {
        Event::listen(function (Login $evento): void {
            $requisicao = request();

            if ($requisicao->hasSession()) {
                $requisicao->session()->put('boas_vindas', true);
            }
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Entra-se pela matrícula, e cada recusa sai com o motivo escrito.
        Fortify::authenticateUsing(
            fn (Request $request) => app(AutenticarPorMatricula::class)($request),
        );
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
