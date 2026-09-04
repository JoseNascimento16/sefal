<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Rules\NomeDeCadastro;
use App\Support\Prototipo\CaixaDeEntradaFicticia;
use App\Support\Prototipo\EstruturaFicticia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caixa de Entrada do Coordenador — PROTÓTIPO.
 *
 * É a porta por onde a demanda de fora entra no sistema. Hoje ela chega em
 * PAPEL: o e-Salvador e o Fala Salvador (Disque 156) entregam documento impresso
 * ao coordenador, que digita, decide e encaminha. O cadastro manual é
 * requisito, não gambiarra — quando a API chegar, ele continua existindo.
 *
 * ── As duas decisões que a tela existe para tomar ───────────────────────────
 *
 *   1. **Registrar e encaminhar** — a demanda vira trabalho DIRIGIDO da equipe
 *      responsável, derivada do BAIRRO. A derivação SUGERE; quem confirma é o
 *      coordenador, porque um bairro pode pertencer a duas áreas e aí as duas
 *      respostas estão certas.
 *   2. **Registrar e devolver/arquivar** — com MOTIVO e JUSTIFICATIVA
 *      obrigatórios. É ato administrativo: quem, quando, por quê. A validação
 *      está aqui, e não só no formulário — esconder o campo na tela não impede
 *      ninguém de mandar a requisição sem ele.
 *
 * ⚠️ PROTÓTIPO: nada é gravado em banco. As demandas de partida vêm de
 * `config/prototipo_caixa_entrada.php` e as decisões ficam na sessão de quem está
 * navegando (ver `App\Support\Prototipo\CaixaDeEntradaFicticia`). A tela diz isso
 * de forma visível — protótipo que se disfarça de sistema pronto vira decisão
 * tomada por engano.
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/caixa-de-entrada/…`), então as rotas nascem protegidas sem
 * ninguém declarar nada.
 */
class CaixaDeEntradaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Retaguarda/Fiscalizacao/CaixaDeEntrada', [
            'demandas' => CaixaDeEntradaFicticia::demandas(),
            // Os catálogos vêm do SERVIDOR: são os MESMOS que a validação exige.
            // Escritos também na tela, um dia discordariam — e a tela ofereceria
            // uma opção que o servidor recusa.
            'origens' => array_values((array) config('prototipo_caixa_entrada.origens', [])),
            'situacoes' => array_values((array) config('prototipo_caixa_entrada.situacoes', [])),
            'motivos' => array_values((array) config('prototipo_caixa_entrada.motivos_de_devolucao', [])),
            'destinos' => array_values((array) config('prototipo_caixa_entrada.destinos_de_retorno', [])),
            'prazoPadraoEmDias' => (int) config('prototipo_caixa_entrada.prazo_padrao_em_dias', 10),
            'equipes' => EstruturaFicticia::equipes(),
            'bairros' => EstruturaFicticia::bairros(),
            // O mapa bairro → equipe sugerida vai inteiro para a tela: é ele que
            // faz a sugestão aparecer no instante em que a pessoa escolhe o
            // bairro, sem uma ida ao servidor por tecla digitada.
            'sugestoes' => $this->mapaDeSugestoes(),
            'alterada' => CaixaDeEntradaFicticia::alterada(),
        ]);
    }

    /**
     * Registra a demanda recebida em papel e a coloca no destino escolhido.
     *
     * Um POST só para os dois caminhos, e não dois: o que muda entre "encaminhar"
     * e "devolver" é o destino, não o registro — a demanda entra na caixa de
     * qualquer maneira, inclusive quando é recusada. Dois endpoints obrigariam a
     * repetir a validação do documento nos dois, e um dia só um deles teria a
     * regra nova.
     */
    public function store(Request $request): RedirectResponse
    {
        $destino = $request->input('destino');

        $dados = $request->validate([
            'destino' => ['required', Rule::in(['encaminhar', 'devolver'])],

            'origem' => ['required', Rule::in((array) config('prototipo_caixa_entrada.origens', []))],
            'documento_origem' => ['required', 'string', 'max:40'],
            'recebida_em' => ['required', 'date'],
            'prazo' => ['nullable', 'date', 'after_or_equal:recebida_em'],

            // Denúncia PODE ser anônima — é a realidade do 156 e do e-Salvador.
            // Quando não é, o nome passa a ser obrigatório: "anônima" tem de ser
            // uma escolha explícita, nunca o resultado de um campo esquecido.
            'anonima' => ['required', 'boolean'],
            'requerente' => ['exclude_if:anonima,true', 'required', 'string', 'max:150', new NomeDeCadastro],
            'contato' => ['exclude_if:anonima,true', 'nullable', 'string', 'max:80'],

            'assunto' => ['required', 'string', 'max:180'],
            'endereco' => ['required', 'string', 'max:200'],
            // O bairro é o que SUGERE a equipe, então ele é obrigatório mesmo
            // quando o endereço vem incompleto: sem ele não há a quem encaminhar.
            'bairro' => ['required', 'string', 'max:80'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'anexo' => ['nullable', 'string', 'max:120'],

            // Encaminhar exige a equipe; devolver exige o porquê. `exclude_unless`
            // tira o campo da conta no caminho em que ele não faz sentido, em vez
            // de recusar um formulário que está correto.
            'equipe' => ['exclude_unless:destino,encaminhar', 'required', Rule::in(EstruturaFicticia::codigosDeEquipe())],
            'observacao' => ['exclude_unless:destino,encaminhar', 'nullable', 'string', 'max:500'],

            'motivo' => ['exclude_unless:destino,devolver', 'required', Rule::in((array) config('prototipo_caixa_entrada.motivos_de_devolucao', []))],
            'justificativa' => ['exclude_unless:destino,devolver', 'required', 'string', 'min:15', 'max:1000'],
            'destino_retorno' => ['exclude_unless:destino,devolver', 'required', Rule::in((array) config('prototipo_caixa_entrada.destinos_de_retorno', []))],
        ], [
            'origem.required' => 'Informe por onde a demanda chegou.',
            'documento_origem.required' => 'Informe o número do documento de origem.',
            'requerente.required' => 'Informe o nome do requerente — ou marque a demanda como anônima.',
            'assunto.required' => 'Escreva o assunto em uma linha.',
            'bairro.required' => 'Informe o bairro: é ele que define a equipe responsável.',
            'equipe.required' => 'Escolha a equipe que vai atender a demanda.',
            'motivo.required' => 'Escolha o motivo da devolução.',
            // A recusa diz o PORQUÊ do tamanho mínimo: devolver é ato
            // administrativo, e "não procede" não conta o caso a quem ler depois.
            'justificativa.required' => 'Escreva a justificativa: devolver ou arquivar é ato administrativo e precisa do motivo por escrito.',
            'justificativa.min' => 'A justificativa está curta demais para explicar a decisão a quem ler depois.',
            'destino_retorno.required' => 'Diga se a demanda volta ao remetente ou é arquivada.',
        ]);

        $demanda = CaixaDeEntradaFicticia::registrar($dados, (string) $destino);

        return redirect()
            ->route('retaguarda.caixa-de-entrada.index')
            ->with('flash.sucesso', $this->recado($demanda));
    }

    /**
     * Triagem de uma demanda que já está na caixa: encaminha à equipe.
     *
     * Serve tanto para a que estava aguardando triagem quanto para reabrir uma
     * que havia sido devolvida — o caso real de o remetente complementar o
     * endereço que faltava.
     */
    public function encaminhar(Request $request, int $demanda): RedirectResponse
    {
        $dados = $request->validate([
            'equipe' => ['required', Rule::in(EstruturaFicticia::codigosDeEquipe())],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [
            'equipe.required' => 'Escolha a equipe que vai atender a demanda.',
        ]);

        $alterada = CaixaDeEntradaFicticia::encaminhar($demanda, $dados['equipe'], $dados['observacao'] ?? null);

        // Demanda que não existe mais volta com o motivo, e não com uma tela de
        // erro: quem clicou tinha a listagem antiga aberta na frente.
        if ($alterada === null) {
            return back()->with('flash.erro', 'Essa demanda não está mais na caixa. Recarregue a listagem.');
        }

        return back()->with('flash.sucesso', $this->recado($alterada));
    }

    /** Devolve ao remetente ou arquiva — com motivo e justificativa. */
    public function devolver(Request $request, int $demanda): RedirectResponse
    {
        $dados = $request->validate([
            'motivo' => ['required', Rule::in((array) config('prototipo_caixa_entrada.motivos_de_devolucao', []))],
            'justificativa' => ['required', 'string', 'min:15', 'max:1000'],
            'destino_retorno' => ['required', Rule::in((array) config('prototipo_caixa_entrada.destinos_de_retorno', []))],
        ], [
            'motivo.required' => 'Escolha o motivo da devolução.',
            'justificativa.required' => 'Escreva a justificativa: devolver ou arquivar é ato administrativo e precisa do motivo por escrito.',
            'justificativa.min' => 'A justificativa está curta demais para explicar a decisão a quem ler depois.',
            'destino_retorno.required' => 'Diga se a demanda volta ao remetente ou é arquivada.',
        ]);

        $alterada = CaixaDeEntradaFicticia::devolver(
            $demanda,
            $dados['motivo'],
            $dados['justificativa'],
            $dados['destino_retorno'],
        );

        if ($alterada === null) {
            return back()->with('flash.erro', 'Essa demanda não está mais na caixa. Recarregue a listagem.');
        }

        return back()->with('flash.sucesso', $this->recado($alterada));
    }

    /**
     * Devolve a caixa ao estado de partida.
     *
     * Existe porque é PROTÓTIPO: quem está demonstrando precisa poder recomeçar a
     * cena. No sistema real esta rota não existe — caixa de entrada não se
     * reinicia.
     */
    public function reiniciar(): RedirectResponse
    {
        CaixaDeEntradaFicticia::reiniciar();

        return back()->with('flash.sucesso', 'Caixa de entrada devolvida ao estado de demonstração.');
    }

    /**
     * O recado que a tela mostra depois de uma decisão — dizendo o EFEITO dela,
     * não só que deu certo. "Salvo com sucesso" não conta a quem a demanda foi.
     */
    private function recado(array $demanda): string
    {
        return match ($demanda['situacao']) {
            'Encaminhada' => "Demanda {$demanda['protocolo']} encaminhada à Equipe {$demanda['equipe']} — "
                .'aparecerá no aplicativo dos fiscais da equipe.',
            'Devolvida' => "Demanda {$demanda['protocolo']} devolvida ao remetente, com a justificativa registrada.",
            'Arquivada' => "Demanda {$demanda['protocolo']} arquivada, com a justificativa registrada.",
            default => "Demanda {$demanda['protocolo']} registrada e aguardando triagem.",
        };
    }

    /**
     * `bairro => sugestão` para a tela responder na hora.
     *
     * A chave é o nome do bairro como ele aparece na lista de escolha; a tela
     * consulta por ele. Sugestão com alternativa (bairro em duas áreas) chega com
     * as duas, e a tela avisa que a escolha é de gente.
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapaDeSugestoes(): array
    {
        $mapa = [];

        foreach (EstruturaFicticia::bairros() as $bairro) {
            $sugestao = EstruturaFicticia::sugerirPorBairro($bairro);

            if ($sugestao !== null) {
                $mapa[$bairro] = $sugestao;
            }
        }

        return $mapa;
    }
}
