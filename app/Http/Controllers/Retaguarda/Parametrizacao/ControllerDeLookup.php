<?php

namespace App\Http\Controllers\Retaguarda\Parametrizacao;

use App\Http\Controllers\Controller;
use App\Models\ListaDeEscolha;
use App\Support\Parametrizacao\CampoLookup;
use App\Support\Parametrizacao\DefinicaoLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * O comportamento das SEIS telas de parametrização — uma implementação só.
 *
 * Cada tela é uma subclasse de três linhas que declara a sua
 * {@see DefinicaoLookup}: o nome das coisas, o texto que explica para que a
 * lista serve e o campo que só ela tem. Listar, incluir, alterar e excluir
 * acontecem aqui.
 *
 * ## Por que uma base, e não seis controllers
 *
 * Seis cópias do mesmo CRUD é o jeito mais rápido de escrever e o mais caro de
 * manter: a correção entra em cinco e esquece a sexta — e a esquecida é sempre a
 * que ninguém abre. Com uma base, a regra de duplicidade (por exemplo) vale para
 * as seis por construção, e o teste que a prova numa prova em todas.
 *
 * ## Permissão
 *
 * As seis moram sob `/retaguarda/parametrizacao/...`, e é do primeiro trecho do
 * caminho que as guardas deduzem a tela: a permissão é **uma só**, para o
 * conjunto — concedida no Modo Gerente sob o nome "Parametrização". Isso é
 * deliberado: separar a permissão de "motivos de recusa" da de "tipos de
 * operação" seria uma decisão que ninguém precisa tomar, e seis linhas a mais na
 * matriz para todo mundo ler.
 */
abstract class ControllerDeLookup extends Controller
{
    /** O que distingue esta tela das outras cinco. */
    abstract protected function definicao(): DefinicaoLookup;

    public function index(): Response
    {
        $definicao = $this->definicao();

        return Inertia::render($definicao->componente, [
            'itens' => $this->itens($definicao),
            // A definição vem do SERVIDOR, e não escrita na tela: os campos que
            // o formulário desenha são os mesmos que a validação exige.
            'definicao' => $definicao->paraTela(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $definicao = $this->definicao();

        $modelo = new $definicao->modelo;
        $modelo->fill($this->validados($request, $definicao));
        $modelo->save();

        return redirect()
            ->route($definicao->rota('index'))
            ->with('flash.sucesso', $definicao->mensagem('cadastrado'));
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $definicao = $this->definicao();
        $registro = $this->localizar($definicao, $item);

        $registro->update($this->validados($request, $definicao, $item));

        return redirect()
            ->route($definicao->rota('index'))
            ->with('flash.sucesso', 'Alterações salvas.');
    }

    public function destroy(int $item): RedirectResponse
    {
        $definicao = $this->definicao();
        $registro = $this->localizar($definicao, $item);

        $impedimento = $this->impedimentoParaExcluir($registro);

        if ($impedimento !== null) {
            // Recusa EXPLÍCITA, na tela de onde a pessoa clicou: exclusão que
            // simplesmente não acontece parece o sistema travado.
            return back()->with('flash.erro', $impedimento);
        }

        $registro->delete();

        return redirect()
            ->route($definicao->rota('index'))
            ->with('flash.sucesso', $definicao->mensagem('excluído'));
    }

    /**
     * O que impede a exclusão deste registro, ou null quando não há impedimento.
     *
     * Ponto de extensão para quando uma lista passar a ser APONTADA por registros
     * de operação: aí excluir o valor deixaria o histórico apontando para o nada,
     * e a resposta certa é inativar. Hoje nenhuma das seis é apontada por
     * ninguém — a primeira será a atividade do ambulante, quando o cadastro de
     * permissionário existir.
     */
    protected function impedimentoParaExcluir(ListaDeEscolha $registro): ?string
    {
        return null;
    }

    /**
     * A lista inteira (ativos e inativos) como a tela precisa dela.
     *
     * Vai inteira de propósito: a tela filtra, ordena e pagina no navegador, e é
     * desse recorte que sai a exportação. Listas de parametrização têm dezenas
     * de linhas, não milhares.
     *
     * @return list<array<string, mixed>>
     */
    private function itens(DefinicaoLookup $definicao): array
    {
        $colunas = array_map(static fn (CampoLookup $c): string => $c->chave, $definicao->campos);

        $itens = $definicao->modelo::query()
            // Alfabética: é a ordem em que a lista é lida por quem escolhe. A
            // tela reordena por coluna, mas o que chega já chega legível.
            ->orderBy('nome')
            ->get()
            ->map(function (ListaDeEscolha $registro) use ($colunas): array {
                $item = [
                    'id' => (int) $registro->getKey(),
                    'nome' => (string) $registro->nome,
                    'ativo' => (bool) $registro->ativo,
                ];

                foreach ($colunas as $coluna) {
                    $item[$coluna] = $registro->{$coluna};
                }

                return $item;
            })
            ->all();

        return array_values($itens);
    }

    /**
     * Os dados válidos para gravar.
     *
     * @return array<string, mixed>
     */
    private function validados(Request $request, DefinicaoLookup $definicao, ?int $ignorar = null): array
    {
        $regras = [
            'nome' => ['required', 'string', 'max:120', $this->nomeInedito($definicao, $ignorar)],
            'ativo' => ['boolean'],
        ];

        foreach ($definicao->campos as $campo) {
            $regras[$campo->chave] = $campo->regras();
        }

        $dados = $request->validate($regras, [
            'nome.required' => 'Informe o nome.',
        ]);

        // O espaço nas pontas não é conteúdo: gravado, ele faz "Feira " e
        // "Feira" conviverem como se fossem duas coisas.
        $dados['nome'] = trim((string) $dados['nome']);
        $dados['ativo'] = (bool) ($dados['ativo'] ?? true);

        foreach ($definicao->campos as $campo) {
            $valor = $dados[$campo->chave] ?? null;
            $valor = is_string($valor) ? trim($valor) : $valor;
            $dados[$campo->chave] = $valor === '' ? null : $valor;
        }

        return $dados;
    }

    /**
     * Recusa nome já usado na MESMA lista, ignorando caixa e espaços.
     *
     * "Feira livre" e "  FEIRA LIVRE " são a mesma coisa para quem escolhe: duas
     * linhas fariam o valor aparecer duas vezes no formulário do fiscal, e os
     * registros históricos se dividiriam entre as duas sem ninguém perceber.
     *
     * A comparação vai em `LOWER(TRIM(...))` porque nem o Oracle nem o SQLite
     * comparam texto ignorando a caixa por padrão — um índice único na coluna
     * deixaria as duas passarem.
     */
    private function nomeInedito(DefinicaoLookup $definicao, ?int $ignorar): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $falhar) use ($definicao, $ignorar): void {
            if (! is_string($valor)) {
                return;
            }

            $consulta = $definicao->modelo::query()
                ->whereRaw('LOWER(TRIM(nome)) = ?', [mb_strtolower(trim($valor))]);

            if ($ignorar !== null) {
                $consulta->whereKeyNot($ignorar);
            }

            if ($consulta->exists()) {
                $falhar('Já existe um registro com esse nome nesta lista.');
            }
        };
    }

    /** O registro desta lista, ou 404 — nunca o de outra tabela. */
    private function localizar(DefinicaoLookup $definicao, int $id): ListaDeEscolha
    {
        return $definicao->modelo::query()->findOrFail($id);
    }
}
