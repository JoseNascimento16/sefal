<?php

namespace App\Http\Middleware;

use App\Actions\Fortify\AutenticarPorMatricula;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Desativar um usuário tem que valer AGORA, não só no próximo login.
 *
 * Conferir `ativo` na hora de entrar não basta: quem já estava com a sessão
 * aberta continuaria trabalhando, e o "Continuar conectado" reentra pelo cookie
 * de lembrete, que não passa pela conferência do login. Esta guarda roda em toda
 * requisição — seja qual for o caminho pelo qual a pessoa se autenticou.
 *
 * E a saída é falada: derruba a sessão e devolve à tela de login com a MESMA
 * frase da recusa no login, para ninguém ficar adivinhando o que aconteceu.
 */
class GarantirUsuarioAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->ativo) {
            Auth::guard(config('fortify.guard'))->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->withErrors([
                'login' => AutenticarPorMatricula::USUARIO_INATIVO,
            ]);
        }

        return $next($request);
    }
}
