<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Bairro;
use App\Models\OperacaoBairro;
use App\Rules\NomeDeCadastro;
use App\Support\Estrutura;
use App\Support\ListagensDaRetaguarda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sistema › Bairros — o catálogo de bairros da cidade (dono, 25/09/2026).
 *
 * O nome certo (sem "Imbuí" e "Imbui" virarem dois) e a coordenada que o mapa
 * usa. As áreas dizem quais bairros cobrem; o cadastro de Áreas oferece os
 * daqui. Renomear um bairro renomeia também nas áreas e nas operações que o
 * citam; a demanda antiga guarda o nome com que chegou.
 *
 * Bairro que está em alguma área não se exclui — inativa-se.
 */
class BairrosController extends Controller
{
    public function index(): Response
    {
        $areasPorChave = [];

        foreach (AreaBairro::with('area:id,nome')->get(['area_id', 'bairro']) as $vinculo) {
            $areasPorChave[Area::chaveDeBairro($vinculo->bairro)][] = (string) ($vinculo->area?->nome ?? '');
        }

        $bairros = Bairro::query()->get()
            ->sortBy(static fn (Bairro $b): string => Area::chaveDeBairro($b->nome))
            ->values()
            ->map(static fn (Bairro $b): array => [
                'id' => $b->id,
                'nome' => $b->nome,
                'latitude' => $b->latitude,
                'longitude' => $b->longitude,
                'ativo' => (bool) $b->ativo,
                'areas' => array_values(array_unique(array_filter($areasPorChave[Area::chaveDeBairro($b->nome)] ?? []))),
            ]);

        return Inertia::render('Retaguarda/Sistema/Bairros', [
            'bairros' => $bairros,
            'listagens' => ListagensDaRetaguarda::para('bairros'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validados($request, null);
        $bairro = Bairro::create($dados);
        Estrutura::esquecer();

        return to_route('retaguarda.bairros.index')->with('flash.sucesso', "Bairro {$bairro->nome} cadastrado.");
    }

    public function update(Request $request, Bairro $bairro): RedirectResponse
    {
        $dados = $this->validados($request, $bairro);
        $antigo = $bairro->nome;

        DB::transaction(function () use ($bairro, $dados, $antigo) {
            $bairro->update($dados);

            /*
             * As áreas e as operações citam o bairro PELO NOME: renomear aqui
             * renomeia lá, e a coordenada nova vale para o mapa. A demanda antiga
             * guarda o nome com que chegou — é registro do que aconteceu.
             */
            foreach (AreaBairro::all() as $vinculo) {
                if (Area::chaveDeBairro($vinculo->bairro) === Area::chaveDeBairro($antigo)) {
                    $vinculo->update(['bairro' => $dados['nome'], 'latitude' => $dados['latitude'], 'longitude' => $dados['longitude']]);
                }
            }

            foreach (OperacaoBairro::all() as $vinculo) {
                if (Area::chaveDeBairro($vinculo->bairro) === Area::chaveDeBairro($antigo)) {
                    $vinculo->update(['bairro' => $dados['nome']]);
                }
            }
        });

        Estrutura::esquecer();

        return to_route('retaguarda.bairros.index')->with('flash.sucesso', "Bairro {$bairro->nome} atualizado.");
    }

    public function destroy(Bairro $bairro): RedirectResponse
    {
        $areas = AreaBairro::with('area:id,nome')->get()
            ->filter(static fn (AreaBairro $v): bool => Area::chaveDeBairro($v->bairro) === Area::chaveDeBairro($bairro->nome))
            ->map(static fn (AreaBairro $v): string => (string) ($v->area?->nome ?? ''))
            ->unique()->values()->all();

        if ($areas !== []) {
            return back()->with(
                'flash.erro',
                "O bairro {$bairro->nome} está na ".implode(', ', $areas).'. Tire-o da área (em Áreas) ou desmarque "Ativo" para tirá-lo de uso.',
            );
        }

        $nome = $bairro->nome;
        $bairro->delete();

        return to_route('retaguarda.bairros.index')->with('flash.sucesso', "Bairro {$nome} excluído.");
    }

    /** @return array{nome: string, latitude: float|null, longitude: float|null, ativo: bool} */
    private function validados(Request $request, ?Bairro $bairro): array
    {
        $request->merge(['nome' => trim((string) $request->input('nome', '')), 'ativo' => $request->boolean('ativo')]);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', new NomeDeCadastro],
            // Salvador fica perto de -12,97 / -38,50: a faixa larga barra só coordenada trocada ou digitada errada.
            'latitude' => ['nullable', 'numeric', 'between:-14,-12'],
            'longitude' => ['nullable', 'numeric', 'between:-39.5,-37.5'],
            'ativo' => ['boolean'],
        ], [
            'nome.required' => 'Informe o nome do bairro.',
            'latitude.between' => 'A latitude está fora de Salvador (fica perto de -12,97).',
            'longitude.between' => 'A longitude está fora de Salvador (fica perto de -38,50).',
        ]);

        // Único SEM acento e caixa: "Imbuí" e "Imbui" são o mesmo bairro.
        $mesmo = Bairro::pelaChave($dados['nome']);

        if ($mesmo !== null && $mesmo->id !== $bairro?->id) {
            throw ValidationException::withMessages([
                'nome' => "Já existe o bairro {$mesmo->nome} (o nome é comparado sem acento).",
            ]);
        }

        return [
            'nome' => $dados['nome'],
            'latitude' => isset($dados['latitude']) ? (float) $dados['latitude'] : null,
            'longitude' => isset($dados['longitude']) ? (float) $dados['longitude'] : null,
            'ativo' => (bool) $dados['ativo'],
        ];
    }
}
