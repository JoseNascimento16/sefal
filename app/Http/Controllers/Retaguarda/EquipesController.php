<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Equipe;
use App\Models\User;
use App\Support\Estrutura;
use App\Support\ListagensDaRetaguarda;
use App\Support\Papel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sistema › Equipes — o cadastro das equipes de fiscalização (pedido do dono,
 * 25/09/2026: "preciso de um cadastro de equipes").
 *
 * Áreas e Equipes trata a área e a equipe dela como uma coisa só (é assim que o
 * documento das áreas foi feito), e não diz QUEM está na equipe. Esta tela diz:
 * o código, a área, o turno, o LÍDER (a conta que recebe o trabalho da equipe —
 * RN-03 de papéis) e os FISCAIS. É daqui que sai o recorte do líder em todas as
 * telas e a fila do aplicativo de cada fiscal.
 *
 * Excluir só a equipe sem histórico; a que já recebeu demanda, foi a campo ou
 * entrou em operação é inativada — o histórico continua dizendo o código dela.
 */
class EquipesController extends Controller
{
    /**
     * Onde uma equipe deixa HISTÓRICO. Com qualquer um deles, excluir apagaria
     * (ou anularia) a equipe de trâmites, vistorias e operações.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const HISTORICO = [
        ['demandas', 'equipe_id'],
        ['fiscalizacoes', 'equipe_id'],
        ['fiscalizacao_ciclos', 'equipe_id'],
        ['operacao_equipes', 'equipe_id'],
    ];

    public function index(): Response
    {
        $equipes = Equipe::with(['area', 'lider', 'fiscais'])->orderBy('codigo')->get()
            ->map(fn (Equipe $e): array => [
                'id' => $e->id,
                'codigo' => $e->codigo,
                'nome' => (string) ($e->nome ?? ''),
                'area_id' => $e->area_id,
                'area' => (string) ($e->area?->nome ?? ''),
                'turno' => (string) ($e->turno ?? ''),
                'lider_id' => $e->lider_id,
                'lider' => $e->lider?->name,
                'encarregado' => $e->encarregado,
                'fiscais' => $e->fiscais->sortBy('name')->map(static fn (User $u): array => ['id' => $u->id, 'nome' => $u->name])->values()->all(),
                'ativa' => (bool) $e->ativa,
                'tem_historico' => $this->temHistorico($e),
            ]);

        return Inertia::render('Retaguarda/Sistema/Equipes', [
            'equipes' => $equipes,
            'areas' => Area::orderBy('nome')->get(['id', 'nome'])->map(static fn (Area $a): array => ['id' => $a->id, 'nome' => $a->nome])->all(),
            'turnos' => array_values((array) config('estrutura.turnos', [])),
            // Quem PODE ser líder e quem pode ser fiscal: as contas ativas do setor.
            'lideres' => $this->contasDoSetor(Papel::LIDER),
            'fiscais' => $this->contasDoSetor('fiscal'),
            'listagens' => ListagensDaRetaguarda::para('equipes'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validados($request, null);
        $equipe = DB::transaction(fn () => $this->gravar(new Equipe, $dados));

        return to_route('retaguarda.equipes.index')->with('flash.sucesso', "Equipe {$equipe->codigo} cadastrada.");
    }

    public function update(Request $request, Equipe $equipe): RedirectResponse
    {
        $dados = $this->validados($request, $equipe);
        DB::transaction(fn () => $this->gravar($equipe, $dados));

        return to_route('retaguarda.equipes.index')->with('flash.sucesso', "Equipe {$equipe->codigo} atualizada.");
    }

    public function destroy(Equipe $equipe): RedirectResponse
    {
        if ($this->temHistorico($equipe)) {
            return back()->with(
                'flash.erro',
                "A equipe {$equipe->codigo} já recebeu demanda, foi a campo ou entrou em operação — excluí-la apagaria "
                .'esse histórico. Desmarque "Ativa" para tirá-la de uso.',
            );
        }

        $codigo = $equipe->codigo;
        $equipe->fiscais()->detach();
        $equipe->delete();
        Estrutura::esquecer();

        return to_route('retaguarda.equipes.index')->with('flash.sucesso', "Equipe {$codigo} excluída.");
    }

    // ── Regras ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validados(Request $request, ?Equipe $equipe): array
    {
        $request->merge([
            'codigo' => mb_strtoupper(trim((string) $request->input('codigo', ''))),
            'fiscais' => array_values(array_unique(array_map('intval', (array) $request->input('fiscais', [])))),
            'ativa' => $request->boolean('ativa'),
        ]);

        $lideres = array_column($this->contasDoSetor(Papel::LIDER), 'id');
        $fiscais = array_column($this->contasDoSetor('fiscal'), 'id');

        // O líder e os fiscais JÁ vinculados continuam aceitos mesmo que a conta
        // tenha mudado de setor — senão salvar outro campo da equipe falharia.
        if ($equipe !== null) {
            $lideres[] = $equipe->lider_id;
            $fiscais = [...$fiscais, ...$equipe->fiscais()->pluck('users.id')->all()];
        }

        return $request->validate([
            // O código é a identidade da equipe em rua (C2, A1, I1…).
            'codigo' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/', Rule::unique('equipes', 'codigo')->ignore($equipe)],
            'nome' => ['nullable', 'string', 'max:80'],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'turno' => ['required', Rule::in((array) config('estrutura.turnos', []))],
            'lider_id' => ['nullable', 'integer', Rule::in(array_filter($lideres))],
            'fiscais' => ['array'],
            'fiscais.*' => ['integer', Rule::in($fiscais)],
            'ativa' => ['boolean'],
        ], [
            'codigo.required' => 'Informe o código da equipe.',
            'codigo.regex' => 'O código aceita letras, números e hífen — ex.: C2, A1.',
            'codigo.unique' => 'Já existe uma equipe com este código.',
            'area_id.required' => 'Escolha a área da equipe.',
            'turno.required' => 'Escolha o turno.',
            'lider_id.in' => 'O líder precisa ser uma conta ativa com o cargo Líder de Equipe.',
            'fiscais.*.in' => 'Só contas ativas com o cargo Fiscal entram como fiscais.',
        ]);
    }

    /** @param  array<string, mixed>  $dados */
    private function gravar(Equipe $equipe, array $dados): Equipe
    {
        $lider = $dados['lider_id'] !== null ? User::find($dados['lider_id']) : null;

        $equipe->fill([
            'codigo' => $dados['codigo'],
            'nome' => $dados['nome'] ?: 'Equipe '.$dados['codigo'],
            'area_id' => $dados['area_id'],
            'turno' => $dados['turno'],
            'lider_id' => $lider?->id,
            // O líder É o encarregado (RN-03): com conta, o nome dele; sem, fica o
            // encarregado que o documento das áreas trouxe.
            'encarregado' => $lider?->name ?? $equipe->encarregado,
            'ativa' => $dados['ativa'],
        ]);
        $equipe->save();

        $equipe->fiscais()->sync($dados['fiscais']);

        // A estrutura mudou: a memória da requisição não pode servir o estado antigo.
        Estrutura::esquecer();

        return $equipe;
    }

    private function temHistorico(Equipe $equipe): bool
    {
        foreach (self::HISTORICO as [$tabela, $coluna]) {
            if (DB::table($tabela)->where($coluna, $equipe->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{id: int, nome: string, login: string}> */
    private function contasDoSetor(string $setor): array
    {
        return User::query()
            ->where('ativo', true)
            ->whereHas('setores', static fn ($q) => $q->where('slug', $setor))
            ->orderBy('name')
            ->get(['id', 'name', 'login'])
            ->map(static fn (User $u): array => ['id' => $u->id, 'nome' => $u->name, 'login' => $u->login])
            ->all();
    }
}
