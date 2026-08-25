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

            // 303 ("See Other") quando o método não é GET: o navegador tem de repetir o
            // caminho como GET. Com 302 ele repetiria o PUT/PATCH/DELETE contra a tela de
            // login, que não aceita esses verbos — a pessoa levaria um 405 no lugar da
            // mensagem. O middleware do Inertia já faz essa troca nas requisições dele;
            // aqui ela é explícita para valer também fora do Inertia (formulário comum,
            // requisição de fora da tela), sem depender da ordem em que as guardas rodam.
            $status = $request->isMethod('GET') ? 302 : 303;

            return redirect()->route('login', status: $status)->withErrors([
                'login' => AutenticarPorMatricula::USUARIO_INATIVO,
            ]);
        }

        return $next($request);
    }
}
