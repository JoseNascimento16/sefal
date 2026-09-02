<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega ao Blade o tema escolhido, para a página já nascer pintada.
 *
 * Sem cookie, vale o PADRÃO do sistema: CLARO. Era `system`, e o efeito era este —
 * quem tem o sistema operacional no escuro (o padrão de fábrica em boa parte dos
 * aparelhos) abria a Retaguarda em navy sem nunca ter pedido. Aqui o dia de
 * trabalho é claro; o escuro é escolha.
 *
 * ⚠️ Este valor tem irmãos: o `PADRAO` do `use-appearance.tsx` e os dois
 * `$appearance ?? …` do `app.blade.php`. Os quatro precisam concordar — se
 * discordarem, a primeira pintura sai de um tema e a segunda de outro, que é o
 * lampejo que a pré-pintura existe para evitar.
 */
class HandleAppearance
{
    /** O tema de quem nunca escolheu. Ver o aviso acima antes de mudar. */
    private const PADRAO = 'light';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? self::PADRAO);

        return $next($request);
    }
}
