<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Support\SaudeFluxos\CatalogoFluxos;
use App\Support\SaudeFluxos\ConhecimentoDoFluxo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Saúde dos Fluxos — Sistema › Saúde dos Fluxos, só o administrador (pedido do dono, 27/10/2026).
 *
 * O fluxograma do trabalho do SEFAL de ponta a ponta: da chegada da demanda, por cada canal, até cada
 * destino final, passando pela Retaguarda e pelo aplicativo do fiscal. Cada quadradinho diz se o
 * trecho é atendido hoje pelo sistema ou só previsto, e abre o passo a passo daquele trecho.
 *
 * A tela veio do CODECON com a mesma estrutura (Visão geral + uma aba por fluxo). Lá os quadradinhos
 * também ficam vermelhos quando uma verificação do ambiente falha; aqui essa parte ainda não existe.
 */
class SaudeDosFluxosController extends Controller
{
    public function index(Request $request): Response
    {
        $abas = array_map(fn (string $chave) => ['chave' => $chave, 'titulo' => CatalogoFluxos::definicao($chave)['titulo']], CatalogoFluxos::chaves());
        $chave = (string) $request->string('fluxo');

        // Sem fluxo (ou um que não existe): a Visão geral, com todos os fluxos do catálogo.
        if (! CatalogoFluxos::existe($chave)) {
            return Inertia::render('Retaguarda/Sistema/SaudeDosFluxos', [
                'abas' => $abas,
                'fluxoAtual' => null,
                'visaoGeral' => CatalogoFluxos::visaoGeral(),
            ]);
        }

        return Inertia::render('Retaguarda/Sistema/SaudeDosFluxos', [
            'abas' => $abas,
            'fluxoAtual' => $chave,
            'visaoGeral' => null,
            'fluxo' => CatalogoFluxos::definicao($chave),
        ]);
    }

    /** O passo a passo do fluxo em texto (Markdown), para guardar ou repassar. */
    public function exportar(string $fluxo): HttpResponse
    {
        abort_unless(CatalogoFluxos::existe($fluxo), 404);

        return response(ConhecimentoDoFluxo::markdown(CatalogoFluxos::definicao($fluxo)), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fluxo-'.$fluxo.'-'.now()->format('Ymd').'.md"',
        ]);
    }
}
