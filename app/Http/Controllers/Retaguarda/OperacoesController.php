<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Rules\NomeDeCadastro;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\OperacoesFicticias;
use App\Support\Prototipo\PapelNaArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cadastro de Operação — PROTÓTIPO.
 *
 * "A operação é evento; a equipe é organização." A ÁREA e a EQUIPE são a
 * estrutura permanente com que a SEMOP divide a cidade (Áreas e Equipes); a
 * operação é o trabalho com começo, fim e foco que se monta em cima dela —
 * Operação Verão na orla, Volta às Aulas no entorno das escolas, a rotina semanal
 * do Centro.
 *
 * ── O ponto crítico: é o MESMO catálogo do direcionamento ───────────────────
 *
 * A operação já era consumida antes de existir esta tela: no direcionamento das
 * denúncias, o Chefe de Setor anexa a denúncia a uma operação já planejada em vez
 * de mandar uma ida isolada. Esta tela lê e escreve **o mesmo catálogo**
 * ({@see OperacoesFicticias}) — não uma segunda lista. Com duas, o direcionamento
 * ofereceria amanhã uma operação que o cadastro não conhece, e recusaria a que ele
 * acabou de criar; e o cadastro não veria a operação aberta no meio de um
 * direcionamento, que é justamente o caso em que ela nasce com menos informação e
 * mais precisa de alguém completá-la depois.
 *
 * ── As regras que esta tela faz valer, e por que cada uma ───────────────────
 *
 *  1. **ÁREA é obrigatória** — é ela que decide quem vê a operação e quem a
 *     executa. Operação sem área é operação de ninguém: não aparece para chefe
 *     algum e não tem equipe a quem cobrar;
 *  2. **fim não pode ser antes do início** — período invertido não é detalhe de
 *     formulário: a operação apareceria encerrada antes de começar, e a conta de
 *     "quanto tempo ela durou" sairia negativa em todo relatório que a somar;
 *  3. **NOME único** — é por ele que a equipe reconhece a operação em rua, e é ele
 *     que a denúncia grava na linha ao ser anexada. Duas com o mesmo nome fazem a
 *     anexação apontar para qualquer uma das duas;
 *  4. **ENCERRADA não recebe denúncia nova** — a regra vale onde a inclusão
 *     acontece ({@see DenunciasController::operacao}); aqui o que se garante é que
 *     encerrar é possível e visível. A encerrada não é apagada: o histórico é a
 *     régua da operação do ano que vem.
 *
 * Toda recusa diz o MOTIVO. Bloqueio mudo faz a pessoa achar que o sistema está
 * quebrado — ou que a culpa é dela.
 *
 * ── O recorte por ÁREA ──────────────────────────────────────────────────────
 *
 * O Chefe de Setor cadastra e vê as operações da área dele; o Coordenador e o
 * administrador veem o universo. Quem responde isso é {@see PapelNaArea}, a mesma
 * fonte que governa Denúncias e Fiscalizações — e o recorte é feito no SERVIDOR,
 * porque filtro de front esconde e não protege. A gravação sobre operação de outra
 * área é recusada NOMINALMENTE, dizendo por quais áreas a pessoa responde.
 *
 * ⚠️ PROTÓTIPO: nada é gravado em banco. A lista de partida é
 * `config/prototipo_operacoes.php` e o que a pessoa cria ou altera fica na sessão
 * dela — a tela diz isso em cima, no selo.
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/operacoes/…`), então as mutações abaixo nascem protegidas sem
 * ninguém declarar nada.
 */
class OperacoesController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $areas = PapelNaArea::areas($usuario);
        $comRecorte = PapelNaArea::recorta($usuario);

        return Inertia::render('Retaguarda/Fiscalizacao/CadastroDeOperacao', [
            // O recorte é do SERVIDOR: a operação carrega foco e observação da
            // gestão, e a lista inteira não tem por que viajar até o navegador de
            // quem responde por uma área só.
            'operacoes' => $comRecorte
                ? array_values(array_filter(
                    OperacoesFicticias::todas(),
                    static fn (array $o): bool => in_array((string) $o['area'], $areas, true),
                ))
                : OperacoesFicticias::todas(),
            // Os catálogos vêm do SERVIDOR: são os MESMOS que a validação exige.
            // Escritos também na tela, um dia discordariam — e a tela ofereceria
            // uma opção que o servidor recusa.
            'situacoes' => OperacoesFicticias::situacoes(),
            'areas' => $comRecorte ? $areas : EstruturaFicticia::nomesDeArea(),
            'equipes' => EstruturaFicticia::equipes(),
            'bairros' => EstruturaFicticia::bairros(),
            'chefias' => EstruturaFicticia::chefiasPorArea(),
            // O que esta pessoa exerce aqui, e sobre o que. A MESMA resposta
            // governa a recusa no servidor: a tela não oferece o que ele recusa.
            'cadastra' => PapelNaArea::decide($usuario),
            'areasDoChefe' => $areas,
            'recorteDeArea' => $comRecorte,
            'alterada' => OperacoesFicticias::alterada(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirCadastro($request)) !== null) {
            return $recusa;
        }

        $dados = $this->validados($request);

        if (($recusa = $this->exigirArea($request, (string) $dados['area'])) !== null) {
            return $recusa;
        }

        if (OperacoesFicticias::nomeEmUso((string) $dados['nome'])) {
            return $this->recusarNomeRepetido();
        }

        $operacao = OperacoesFicticias::salvar($dados);

        return back()->with(
            'flash.sucesso',
            "{$operacao['nome']} criada — {$operacao['area']}, {$operacao['periodo']}.",
        );
    }

    public function update(Request $request, int $operacao): RedirectResponse
    {
        if (($recusa = $this->exigirCadastro($request)) !== null) {
            return $recusa;
        }

        $existente = OperacoesFicticias::porId($operacao);

        if ($existente === null) {
            return back()->with('flash.erro', 'Essa operação não existe mais. Recarregue a tela.');
        }

        $dados = $this->validados($request);

        /*
         * As DUAS áreas são conferidas: a de onde a operação está e a para onde ela
         * iria. Sem a primeira, quem responde pela Área 5 alteraria a operação da
         * Área 1 desde que a movesse para a dele; sem a segunda, ele empurraria a
         * própria operação para a área de outro.
         */
        foreach ([(string) $existente['area'], (string) $dados['area']] as $area) {
            if (($recusa = $this->exigirArea($request, $area)) !== null) {
                return $recusa;
            }
        }

        if (OperacoesFicticias::nomeEmUso((string) $dados['nome'], exceto: $operacao)) {
            return $this->recusarNomeRepetido();
        }

        OperacoesFicticias::salvar([...$dados, 'id' => $operacao]);

        return back()->with('flash.sucesso', 'Alterações salvas.');
    }

    public function destroy(Request $request, int $operacao): RedirectResponse
    {
        if (($recusa = $this->exigirCadastro($request)) !== null) {
            return $recusa;
        }

        $existente = OperacoesFicticias::porId($operacao);

        if ($existente === null) {
            return back()->with('flash.erro', 'Essa operação não existe mais. Recarregue a tela.');
        }

        if (($recusa = $this->exigirArea($request, (string) $existente['area'])) !== null) {
            return $recusa;
        }

        OperacoesFicticias::excluir($operacao);

        return back()->with('flash.sucesso', "{$existente['nome']} excluída.");
    }

    /**
     * Devolve o catálogo ao estado de partida.
     *
     * Existe porque é PROTÓTIPO: quem demonstra precisa recomeçar a cena. No
     * sistema real a operação é cadastro, e cadastro não se reinicia.
     */
    public function reiniciar(): RedirectResponse
    {
        OperacoesFicticias::reiniciar();

        return back()->with('flash.sucesso', 'Operações devolvidas ao estado de demonstração.');
    }

    /**
     * Os dados válidos de uma operação.
     *
     * @return array<string, mixed>
     */
    private function validados(Request $request): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:5', 'max:120', new NomeDeCadastro],
            // ÁREA é obrigatória, e tem de existir: é ela que decide quem vê a
            // operação e quem a executa. Área inventada deixaria a operação órfã,
            // sem aparecer para chefe nenhum.
            'area' => ['required', Rule::in(EstruturaFicticia::nomesDeArea())],
            'regiao' => ['nullable', 'string', 'max:120'],
            // As equipes que executam. Lista, porque operação grande junta equipe
            // de mais de uma área — a Noturna reforçando a orla no verão.
            'equipes' => ['array'],
            'equipes.*' => [Rule::in(EstruturaFicticia::codigosDeEquipe())],
            // Os bairros alcançados DENTRO da área. Vazio significa "a área
            // inteira", e não "nenhum" — daí não ser obrigatório.
            'bairros' => ['array'],
            'bairros.*' => ['string', 'max:80'],
            'inicio' => ['required', 'date'],
            // `after_or_equal` e não `after`: operação de um dia só é caso normal
            // (o réveillon, a interdição de um evento), e exigir fim posterior
            // obrigaria a chefia a mentir a data para conseguir salvar.
            'fim' => ['nullable', 'date', 'after_or_equal:inicio'],
            'situacao' => ['required', Rule::in(OperacoesFicticias::situacoes())],
            'foco' => ['nullable', 'string', 'max:300'],
            'observacao' => ['nullable', 'string', 'max:600'],
        ], [
            'nome.required' => 'Dê um nome à operação — é por ele que a equipe vai reconhecê-la.',
            'nome.min' => 'O nome está curto demais para a equipe reconhecer a operação em rua.',
            'area.required' => 'Diga de que área é a operação: é a área que decide quem a vê e '
                .'quem a executa.',
            'area.in' => 'Essa área não existe na estrutura de fiscalização.',
            'inicio.required' => 'Informe a data de início da operação.',
            'fim.after_or_equal' => 'O fim da operação não pode ser antes do início — do jeito que '
                .'está, ela apareceria encerrada antes de começar.',
            'situacao.required' => 'Diga se a operação está planejada, em andamento ou encerrada.',
            'equipes.*.in' => 'Uma das equipes escolhidas não existe na estrutura de fiscalização.',
        ]);

        return [
            'nome' => trim((string) $dados['nome']),
            'area' => (string) $dados['area'],
            'regiao' => trim((string) ($dados['regiao'] ?? '')),
            'equipes' => array_values(array_unique((array) ($dados['equipes'] ?? []))),
            'bairros' => array_values(array_unique((array) ($dados['bairros'] ?? []))),
            'inicio' => (string) $dados['inicio'],
            'fim' => trim((string) ($dados['fim'] ?? '')) === '' ? null : (string) $dados['fim'],
            'situacao' => (string) $dados['situacao'],
            'foco' => trim((string) ($dados['foco'] ?? '')),
            'observacao' => trim((string) ($dados['observacao'] ?? '')),
        ];
    }

    /**
     * Recusa a gravação de quem apenas CONSULTA o cadastro.
     *
     * Planejar operação é ato de quem responde pela área (e do administrador, que
     * cobre a ausência dele). O Coordenador acompanha: ele tria a entrada do
     * trabalho e precisa saber que operação existe para onde encaminhar, mas quem
     * monta a operação é a chefia da área.
     *
     * Isto é papel, e não permissão de tela: a permissão (slug `operacoes`) diz
     * quem entra; isto diz de quem é o cadastro.
     */
    private function exigirCadastro(Request $request): ?RedirectResponse
    {
        if (PapelNaArea::decide($request->user())) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'Montar operação é do Chefe de Setor da área — é ele que responde pelo trabalho de '
            .'rua dela. Você consulta as operações para saber a que encaminhar a demanda.',
        );
    }

    /**
     * Recusa a gravação do Chefe de Setor sobre área que não é dele — dizendo por
     * quais ele responde.
     *
     * Existe porque esconder da listagem não é fronteira: a lista dele já vem
     * recortada, mas quem souber montar a requisição alcança a operação de outra
     * área. O administrador e o Coordenador passam: os dois veem o universo.
     */
    private function exigirArea(Request $request, string $area): ?RedirectResponse
    {
        $usuario = $request->user();

        if (! PapelNaArea::recorta($usuario)) {
            return null;
        }

        $minhas = PapelNaArea::areas($usuario);

        /*
         * Chefe de Setor SEM área vinculada não passa batido: ele exerce o cadastro
         * (senão não chegaria aqui) e não tem área sobre a qual cadastrar. Recusar
         * dizendo isso é o que faz alguém corrigir o vínculo — deixar passar lhe
         * daria o cadastro inteiro do setor.
         */
        if ($minhas === []) {
            return back()->with(
                'flash.erro',
                'Sua conta não está vinculada a nenhuma área de fiscalização, então não há área '
                .'sua em que montar operação. Procure quem administra o sistema para registrar a '
                .'sua área.',
            );
        }

        if (in_array($area, $minhas, true)) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'Você responde por '.implode(', ', $minhas).", e essa operação é de {$area}. Nada foi "
            .'alterado.',
        );
    }

    private function recusarNomeRepetido(): RedirectResponse
    {
        return back()->withErrors([
            'nome' => 'Já existe uma operação com esse nome. É pelo nome que a equipe a reconhece '
                .'em rua e que a denúncia a registra ao ser anexada — duas iguais fariam a '
                .'anexação apontar para qualquer uma das duas.',
        ]);
    }
}
