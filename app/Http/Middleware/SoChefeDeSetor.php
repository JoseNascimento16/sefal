<?php

namespace App\Http\Middleware;

use App\Support\Papel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deixa passar só o Chefe de Setor (e o administrador).
 *
 * Existe porque, desde 24/09/2026, a permissão da tela `caixa-de-entrada` é
 * dada também ao líder de equipe — é lá que ficam as quatro caixas de canal, e
 * nelas ele recebe o que foi encaminhado. Mas a mesma permissão cobre a mesa
 * antiga do chefe (o cadastro com encaminhamento, a pré-triagem): essas são
 * atos do CHEFE, e a permissão de tela sozinha já não os distingue.
 *
 * Recusa dizendo o porquê e mandando para a caixa do e-Salvador — nunca 403
 * seco.
 */
class SoChefeDeSetor
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (Papel::ehChefe($usuario) || ($usuario?->ehAdmin() ?? false)) {
            return $next($request);
        }

        $recado = 'Esta ação é do Chefe de Setor: é ele quem pré-tria e encaminha o que chega. '
            .'O que é da sua equipe chega às caixas já encaminhado.';

        return $request->isMethod('GET')
            ? redirect()->route('retaguarda.denuncias.e-salvador.index')->with('flash.erro', $recado)
            : back()->with('flash.erro', $recado);
    }
}
