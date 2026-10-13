<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Levantamento;
use App\Models\LevantamentoLinha;
use App\Support\Apresentacao\ArquivoParaTela;
use App\Support\ListagensDaRetaguarda;
use App\Support\Papel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fiscalização › Levantamentos — o censo de ambulantes por rua que o aplicativo
 * do fiscal grava (dono, 13/10/2026: "pode manter e fazer no retaguarda").
 *
 * CONSULTA: quem levanta é o fiscal, na rua; aqui se lê e se exporta. O líder vê
 * só os levantamentos de que as equipes dele participaram — a mesma régua das
 * outras listas.
 */
class LevantamentosController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $recorte = Papel::recorta($usuario) ? Papel::equipes($usuario) : null;

        $levantamentos = Levantamento::query()
            ->with(['linhas', 'user'])
            ->latest('concluido_em')
            ->get()
            ->filter(static fn (Levantamento $l): bool => $recorte === null || array_intersect($l->equipes, $recorte) !== [])
            ->values()
            ->map(static fn (Levantamento $l): array => [
                'id' => $l->id,
                'protocolo' => $l->protocolo,
                'rua' => $l->rua,
                'bairro' => $l->bairro,
                'equipes' => $l->equipes,
                'encarregados' => $l->encarregados,
                'fiscal' => (string) ($l->user?->name ?? '—'),
                'data' => $l->concluido_em->format('d/m/Y'),
                'data_iso' => $l->concluido_em->format('Y-m-d H:i'),
                'periodo' => $l->iniciado_em->format('H:i').' às '.$l->concluido_em->format('H:i'),
                'linhas' => $l->linhas->map(static fn (LevantamentoLinha $linha): array => [
                    'id' => $linha->id,
                    'item' => $linha->item,
                    'nome' => $linha->nome,
                    'documento' => $linha->documento,
                    'contato' => $linha->contato,
                    'referencia' => $linha->referencia,
                    'atividade' => $linha->atividade,
                    'latitude' => $linha->latitude,
                    'longitude' => $linha->longitude,
                    'foto' => $linha->foto === null ? null : ArquivoParaTela::fotoDoLevantamento($linha),
                ])->values()->all(),
            ]);

        return Inertia::render('Retaguarda/Fiscalizacao/Levantamentos', [
            'levantamentos' => $levantamentos,
            'listagens' => ListagensDaRetaguarda::para(['levantamentos', 'levantamento-linhas']),
        ]);
    }
}
