<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Equipe;
use App\Rules\NomeDeCadastro;
use App\Support\Estrutura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Áreas e Equipes — PROTÓTIPO da estrutura permanente de fiscalização.
 *
 * A operação é evento; a EQUIPE é organização. Área > Equipe > bloco de bairros é
 * a estrutura com que a SEMOP divide a cidade, e é dela que sai a derivação
 * bairro → equipe que a Caixa de Entrada usa para sugerir o destino de cada
 * demanda.
 *
 * ── Três recortes, e não um ─────────────────────────────────────────────────
 *
 * Seis áreas cobrem BLOCOS DE BAIRROS; a Itinerante cobre CORREDORES (Avenida
 * Sete, Comércio, Avenida Joana Angélica); a Noturna cobre a CIDADE INTEIRA, e o
 * recorte dela é o turno. Tratar as oito como iguais faria a Noturna aparecer com
 * "0 bairros" — leitura exatamente invertida: ela cobre todos.
 *
 * ── Bairro em duas áreas não é erro ─────────────────────────────────────────
 *
 * MUSSURUNGA, PATAMARES e JARDIM DAS MARGARIDAS pertencem a duas áreas. O vínculo
 * bairro↔equipe não é 1:1: a Caixa de Entrada SUGERE e o Chefe de Setor CONFIRMA.
 * A tela mostra isso como aviso informativo — marcar como pendência mandaria a
 * chefia "corrigir" um dado que está certo.
 *
 * A estrutura vive no BANCO (`areas`, `area_bairros`, `equipes`, `equipe_fiscais`)
 * e é lida por `App\Support\Estrutura`. Ela nasceu da transcrição do documento do
 * cliente, semeada por `EstruturaSeeder` — e o que se mexe aqui é cadastro, que
 * sobrevive ao logout.
 *
 * Área com demanda ou operação pendurada NÃO se exclui: o histórico apontaria
 * para o nada. O caminho de "não usar mais" é INATIVAR.
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/areas-e-equipes/…`).
 */
class AreasEEquipesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Retaguarda/Estrutura/AreasEEquipes', [
            'areas' => Estrutura::areas(),
            'turnos' => array_values((array) config('estrutura.turnos', [])),
            // Todo bairro conhecido: alimenta a inclusão no bloco de uma área,
            // oferecendo o que já existe em vez de convidar a redigitar.
            'bairros' => Estrutura::bairros(),
            // Resíduo do protótipo: ligava o botão de reiniciar, que não existe
            // mais — estrutura é cadastro, e cadastro não se reinicia.
        ]);
    }

    /** Cria uma área/equipe nova. */
    public function store(Request $request): RedirectResponse
    {
        $this->gravar(new Area, $this->validados($request));

        return back()->with('flash.sucesso', 'Área criada.');
    }

    /** Altera a área — nome, região, equipe, encarregado, recorte e turno. */
    public function update(Request $request, int $area): RedirectResponse
    {
        $existente = Area::find($area);

        if ($existente === null) {
            return back()->with('flash.erro', 'Essa área não existe mais. Recarregue a tela.');
        }

        $this->gravar($existente, $this->validados($request));

        return back()->with('flash.sucesso', 'Alterações salvas.');
    }

    public function destroy(int $area): RedirectResponse
    {
        $existente = Area::find($area);

        if ($existente === null) {
            return back()->with('flash.erro', 'Essa área não existe mais. Recarregue a tela.');
        }

        /*
         * Área com trabalho pendurado NÃO se exclui.
         *
         * A demanda encaminhada a ela e a operação planejada nela apontam para
         * esta linha. Apagá-la deixaria o histórico apontando para o nada — e a
         * denúncia de dois meses atrás perderia o registro de para onde foi. Quem
         * quer parar de usar uma área a INATIVA: some dos formulários e continua
         * legível no que já aconteceu.
         */
        $pendurado = $existente->demandas()->count() + $existente->operacoes()->count();

        if ($pendurado > 0) {
            return back()->with('flash.erro', $pendurado === 1
                ? 'Há 1 registro vinculado a esta área. Inative-a em vez de excluí-la — o histórico apontaria para o nada.'
                : "Há {$pendurado} registros vinculados a esta área. Inative-a em vez de excluí-la — o histórico apontaria para o nada.");
        }

        $existente->equipes()->delete();
        $existente->bairros()->delete();
        $existente->delete();
        Estrutura::esquecer();

        return back()->with('flash.sucesso', 'Área excluída.');
    }

    /**
     * Acrescenta ou tira um bairro do bloco de uma área.
     *
     * Uma rota para os dois lados porque é a MESMA decisão vista de dois
     * ângulos — "este bairro é desta equipe?" —, e separar duplicaria a
     * conferência de que a área existe.
     */
    public function bairros(Request $request, int $area): RedirectResponse
    {
        $dados = $request->validate([
            'acao' => ['required', Rule::in(['adicionar', 'remover'])],
            'bairro' => ['required', 'string', 'max:80', new NomeDeCadastro],
        ], [
            'bairro.required' => 'Informe o bairro.',
        ]);

        if (! Area::whereKey($area)->exists()) {
            return back()->with('flash.erro', 'Essa área não existe mais. Recarregue a tela.');
        }

        if ($dados['acao'] === 'adicionar') {
            AreaBairro::firstOrCreate(['area_id' => $area, 'bairro' => $dados['bairro']]);
            Estrutura::esquecer();

            return back()->with('flash.sucesso', "{$dados['bairro']} entrou no bloco desta área.");
        }

        AreaBairro::where('area_id', $area)->where('bairro', $dados['bairro'])->delete();
        Estrutura::esquecer();

        return back()->with('flash.sucesso', "{$dados['bairro']} saiu do bloco desta área.");
    }

    /**
     * Os dados válidos de uma área.
     *
     * @return array<string, mixed>
     */
    private function validados(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:60', new NomeDeCadastro],
            'regiao' => ['required', 'string', 'max:60', new NomeDeCadastro],
            // Código curto da equipe (C2, A1, I1…). Em caixa alta na tela, mas a
            // conferência é de tamanho e forma, não de caixa.
            'equipe' => ['required', 'string', 'max:6'],
            'encarregado' => ['required', 'string', 'max:120', new NomeDeCadastro],
            'recorte' => ['required', Rule::in(['bairros', 'corredores', 'cidade'])],
            'turno' => ['required', Rule::in((array) config('estrutura.turnos', []))],
        ], [
            'nome.required' => 'Informe o nome da área.',
            'regiao.required' => 'Informe a região que a área cobre.',
            'equipe.required' => 'Informe o código da equipe.',
            'encarregado.required' => 'Informe o encarregado da equipe.',
            'recorte.required' => 'Diga se a área cobre bairros, corredores ou a cidade inteira.',
        ]);
    }

    /**
     * Grava a área e a equipe dela — criação e alteração pelo mesmo caminho.
     *
     * A tela trata área e equipe como UMA coisa (é assim que o cliente trabalha:
     * uma área, uma equipe), e por isso o formulário traz o código e o
     * encarregado junto. O banco já aceita várias equipes por área; quando isso
     * mudar, é a tela que ganha a lista — o modelo não muda.
     *
     * @param  array<string, mixed>  $dados
     */
    private function gravar(Area $area, array $dados): Area
    {
        $area->fill([
            'nome' => (string) $dados['nome'],
            'regiao' => (string) $dados['regiao'],
            'recorte' => (string) $dados['recorte'],
            'turno' => (string) $dados['turno'],
        ]);

        $area->save();

        $codigo = mb_strtoupper(trim((string) $dados['equipe']));

        /*
         * A equipe é procurada pelo CÓDIGO, e não pela área: o código é a
         * identidade dela em rua, e renomear a área não pode criar uma segunda
         * equipe com a mesma sigla — o fiscal não saberia em qual está.
         */
        $equipe = Equipe::firstOrNew(['codigo' => $codigo]);

        $equipe->fill([
            'nome' => 'Equipe '.$codigo,
            'area_id' => $area->id,
            'encarregado' => (string) $dados['encarregado'],
            'turno' => (string) $dados['turno'],
            'ativa' => true,
        ]);

        $equipe->save();

        // A estrutura mudou: a memória da requisição não pode servir o estado antigo.
        Estrutura::esquecer();

        return $area;
    }
}
