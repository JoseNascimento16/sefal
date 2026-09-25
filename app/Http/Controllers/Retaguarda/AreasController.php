<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Bairro;
use App\Models\Demanda;
use App\Models\Operacao;
use App\Rules\NomeDeCadastro;
use App\Support\Estrutura;
use App\Support\ListagensDaRetaguarda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sistema › Áreas — o cadastro das áreas e dos BAIRROS de cada uma (pedido do
 * dono, 25/09/2026: "cadastro de Área com possibilidade de definição dos bairros
 * de cada área, selecionáveis via chips igual na tela de operações").
 *
 * É dos bairros da área que sai a SUGESTÃO de equipe para cada demanda (pelo
 * bairro do endereço), os bairros que a operação marca sozinha ao escolher a área
 * e o recorte do líder nos mapas. Quem está em cada equipe fica em Equipes.
 *
 * Área com equipe, demanda ou operação não se exclui — inativa-se.
 */
class AreasController extends Controller
{
    public function index(): Response
    {
        $areas = Area::with(['bairros', 'equipes'])->orderBy('nome')->get()
            ->map(fn (Area $a): array => [
                'id' => $a->id,
                'nome' => $a->nome,
                'regiao' => (string) ($a->regiao ?? ''),
                'recorte' => (string) ($a->recorte ?? 'bairros'),
                'turno' => (string) ($a->turno ?? ''),
                'ativa' => (bool) ($a->ativa ?? true),
                'bairros' => $a->bairros->pluck('bairro')->sort(fn ($x, $y) => Area::chaveDeBairro($x) <=> Area::chaveDeBairro($y))->values()->all(),
                'equipes' => $a->equipes->pluck('codigo')->sort()->values()->all(),
                'tem_historico' => $this->pendurado($a) > 0,
            ]);

        return Inertia::render('Retaguarda/Sistema/Areas', [
            'areas' => $areas,
            // Todo bairro já conhecido: os chips oferecem o que existe em vez de
            // convidar a redigitar (e a digitar diferente).
            // O catálogo de Bairros (ativos) e o que as áreas já citam.
            'bairrosConhecidos' => collect([...Bairro::where('ativo', true)->pluck('nome')->all(), ...Estrutura::bairros()])
                ->unique(static fn (string $b): string => Area::chaveDeBairro($b))
                ->sortBy(static fn (string $b): string => Area::chaveDeBairro($b))
                ->values()->all(),
            'recortes' => array_values((array) config('estrutura.recortes', [])),
            'turnos' => array_values((array) config('estrutura.turnos', [])),
            'listagens' => ListagensDaRetaguarda::para('areas'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validados($request);
        $area = DB::transaction(fn () => $this->gravar(new Area, $dados));

        return to_route('retaguarda.areas.index')->with('flash.sucesso', "Área {$area->nome} cadastrada.");
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $dados = $this->validados($request);
        DB::transaction(fn () => $this->gravar($area, $dados));

        return to_route('retaguarda.areas.index')->with('flash.sucesso', "Área {$area->nome} atualizada.");
    }

    public function destroy(Area $area): RedirectResponse
    {
        if ($area->equipes()->exists()) {
            return back()->with('flash.erro', "A área {$area->nome} tem equipe cadastrada. Mude a equipe de área (em Equipes) ou desmarque \"Ativa\" para tirá-la de uso.");
        }

        if ($this->pendurado($area) > 0) {
            return back()->with('flash.erro', "A área {$area->nome} já recebeu demanda ou operação — excluí-la apagaria esse histórico. Desmarque \"Ativa\" para tirá-la de uso.");
        }

        $nome = $area->nome;
        $area->bairros()->delete();
        $area->delete();
        Estrutura::esquecer();

        return to_route('retaguarda.areas.index')->with('flash.sucesso', "Área {$nome} excluída.");
    }

    // ── Regras ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validados(Request $request): array
    {
        $request->merge([
            'bairros' => array_values(array_unique(array_filter(array_map(
                static fn ($b): string => trim((string) $b),
                (array) $request->input('bairros', []),
            )))),
            'ativa' => $request->boolean('ativa'),
        ]);

        return $request->validate([
            'nome' => ['required', 'string', 'max:60', new NomeDeCadastro],
            'regiao' => ['required', 'string', 'max:60', new NomeDeCadastro],
            'recorte' => ['required', Rule::in((array) config('estrutura.recortes', []))],
            'turno' => ['required', Rule::in((array) config('estrutura.turnos', []))],
            'ativa' => ['boolean'],
            'bairros' => ['array'],
            'bairros.*' => ['string', 'max:80', new NomeDeCadastro],
        ], [
            'nome.required' => 'Informe o nome da área.',
            'regiao.required' => 'Informe a região que a área cobre.',
            'recorte.required' => 'Diga se a área cobre bairros, corredores ou a cidade inteira.',
            'turno.required' => 'Escolha o turno.',
        ]);
    }

    /** @param  array<string, mixed>  $dados */
    private function gravar(Area $area, array $dados): Area
    {
        $area->fill([
            'nome' => $dados['nome'],
            'regiao' => $dados['regiao'],
            'recorte' => $dados['recorte'],
            'turno' => $dados['turno'],
            'ativa' => $dados['ativa'],
        ]);
        $area->save();

        /*
         * Os bairros: sai o que foi desmarcado, entra o que foi marcado. O que já
         * estava FICA como estava — com a coordenada que o mapa usa —, em vez de
         * ser apagado e recriado sem ela.
         */
        $marcados = $dados['bairros'];
        $chaves = array_map(Area::chaveDeBairro(...), $marcados);

        foreach ($area->bairros()->get() as $vinculo) {
            if (! in_array(Area::chaveDeBairro($vinculo->bairro), $chaves, true)) {
                $vinculo->delete();
            }
        }

        $existentes = array_map(Area::chaveDeBairro(...), $area->bairros()->pluck('bairro')->all());

        foreach ($marcados as $bairro) {
            if (! in_array(Area::chaveDeBairro($bairro), $existentes, true)) {
                /*
                 * O bairro vem do CATÁLOGO (Sistema › Bairros), com o nome certo e a
                 * coordenada; o que foi digitado aqui e ainda não existe entra no
                 * catálogo junto — "Acrescentar" na área é o atalho do cadastro.
                 */
                $catalogo = Bairro::pelaChave($bairro)
                    ?? Bairro::create(['nome' => $bairro, 'ativo' => true]);
                $referencia = AreaBairro::where('bairro', $catalogo->nome)->whereNotNull('latitude')->first();

                AreaBairro::create([
                    'area_id' => $area->id,
                    'bairro' => $catalogo->nome,
                    'latitude' => $catalogo->latitude ?? $referencia?->latitude,
                    'longitude' => $catalogo->longitude ?? $referencia?->longitude,
                ]);
            }
        }

        Estrutura::esquecer();

        return $area;
    }

    /** Quantos registros de trabalho apontam para a área. */
    private function pendurado(Area $area): int
    {
        return Demanda::where('area_id', $area->id)->count() + Operacao::where('area_id', $area->id)->count();
    }
}
