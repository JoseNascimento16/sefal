<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Rules\NomeDeCadastro;
use App\Support\Prototipo\EstruturaFicticia;
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
 * bairro↔equipe não é 1:1: a Caixa de Entrada SUGERE e o administrativo CONFIRMA.
 * A tela mostra isso como aviso informativo — marcar como pendência mandaria o
 * gestor "corrigir" um dado que está certo.
 *
 * ⚠️ PROTÓTIPO: nada é gravado em banco. A estrutura de partida é a transcrição
 * do documento do cliente em `config/prototipo_estrutura.php`, e o que a pessoa
 * mexe fica na sessão dela (ver `App\Support\Prototipo\EstruturaFicticia`).
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/areas-e-equipes/…`).
 */
class AreasEEquipesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Retaguarda/Estrutura/AreasEEquipes', [
            'areas' => EstruturaFicticia::areas(),
            'turnos' => array_values((array) config('prototipo_estrutura.turnos', [])),
            // Todo bairro conhecido: alimenta a inclusão no bloco de uma área,
            // oferecendo o que já existe em vez de convidar a redigitar.
            'bairros' => EstruturaFicticia::bairros(),
            'alterada' => EstruturaFicticia::alterada(),
        ]);
    }

    /** Cria uma área/equipe nova. */
    public function store(Request $request): RedirectResponse
    {
        EstruturaFicticia::salvarArea($this->validados($request));

        return back()->with('flash.sucesso', 'Área criada.');
    }

    /** Altera a área — nome, região, equipe, encarregado, recorte e turno. */
    public function update(Request $request, int $area): RedirectResponse
    {
        if (! $this->existe($area)) {
            return back()->with('flash.erro', 'Essa área não existe mais. Recarregue a tela.');
        }

        EstruturaFicticia::salvarArea([...$this->validados($request), 'id' => $area]);

        return back()->with('flash.sucesso', 'Alterações salvas.');
    }

    public function destroy(int $area): RedirectResponse
    {
        if (! $this->existe($area)) {
            return back()->with('flash.erro', 'Essa área não existe mais. Recarregue a tela.');
        }

        EstruturaFicticia::excluirArea($area);

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

        if (! $this->existe($area)) {
            return back()->with('flash.erro', 'Essa área não existe mais. Recarregue a tela.');
        }

        if ($dados['acao'] === 'adicionar') {
            EstruturaFicticia::adicionarBairro($area, $dados['bairro']);

            return back()->with('flash.sucesso', "{$dados['bairro']} entrou no bloco desta área.");
        }

        EstruturaFicticia::removerBairro($area, $dados['bairro']);

        return back()->with('flash.sucesso', "{$dados['bairro']} saiu do bloco desta área.");
    }

    /**
     * Devolve a estrutura ao documento do cliente.
     *
     * Existe porque é PROTÓTIPO: quem demonstra precisa recomeçar a cena. No
     * sistema real a estrutura é cadastro, e cadastro não se reinicia.
     */
    public function reiniciar(): RedirectResponse
    {
        EstruturaFicticia::reiniciar();

        return back()->with('flash.sucesso', 'Estrutura devolvida ao documento de 17/04/2026.');
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
            'turno' => ['required', Rule::in((array) config('prototipo_estrutura.turnos', []))],
        ], [
            'nome.required' => 'Informe o nome da área.',
            'regiao.required' => 'Informe a região que a área cobre.',
            'equipe.required' => 'Informe o código da equipe.',
            'encarregado.required' => 'Informe o encarregado da equipe.',
            'recorte.required' => 'Diga se a área cobre bairros, corredores ou a cidade inteira.',
        ]);
    }

    private function existe(int $area): bool
    {
        foreach (EstruturaFicticia::areas() as $existente) {
            if ((int) $existente['id'] === $area) {
                return true;
            }
        }

        return false;
    }
}
