<?php

namespace App\Http\Middleware;

use App\Support\Aplicativo\AplicativoParaTela;
use App\Support\Aplicativo\CampoDoFiscal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A porta das rotas do aplicativo do fiscal (`/app/api/*`).
 *
 * Elas ficam fora de `/retaguarda/`, e por isso fora da guarda de telas: quem
 * protege é esta. Só entra quem está autenticado E está em alguma equipe — a
 * fila, a reserva de números e a gravação são todas da equipe. A recusa sempre
 * diz o porquê, em JSON, que é o que o aplicativo sabe mostrar.
 */
class AplicativoDoFiscal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Sua sessão terminou. Entre de novo com matrícula e senha.'], 401);
        }

        if (! (new CampoDoFiscal($user))->temCampo()) {
            return response()->json(['message' => AplicativoParaTela::semEquipe($user)], 403);
        }

        return $next($request);
    }
}
