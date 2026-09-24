<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Fiscalizacao;
use App\Support\Apresentacao\FiscalizacaoParaTela;
use App\Support\DecisaoDaChefia;
use App\Support\Estrutura;
use App\Support\ListagensDaRetaguarda;
use App\Support\Papel;
use App\Support\Prototipo\RecomendacoesDoFiscal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fiscalizações — TODO registro de fiscalização concluído, numa tela só.
 * PROTÓTIPO.
 *
 * ── Por que UMA tela, e não duas ────────────────────────────────────────────
 *
 * Isto era duas coisas: "Retorno de Campo", construída, com a fila do Chefe de
 * Setor; e "Fiscalizações", um andaime que prometia a consulta por ambulante,
 * área e período. Duas telas sobre o MESMO registro — a fiscalização concluída —
 * e a lei do projeto diz onde isso ia parar: no dia em que uma ganhasse regra
 * nova, a outra continuaria mostrando o mundo de antes, e o gestor teria de pular
 * de menu para juntar as duas metades da mesma informação.
 *
 * O dono decidiu unificar (09/09/2026). Ficaram duas ABAS, que respondem a
 * perguntas diferentes sobre o mesmo conjunto:
 *
 *   · **A decidir** (padrão) — a FILA: o que voltou da rua e espera a leitura da
 *     chefia. É tela de trabalho: seleção, comando flutuante, janela de decisão.
 *     Responde "o que eu tenho para fazer agora?";
 *   · **Acervo** — a CONSULTA, sem ação: tudo o que já passou por aqui, com o
 *     alvo encontrado, as fotos, a coordenada, o documento que saiu na hora e o
 *     PRAZO de quem foi notificado. Responde "o que foi feito naquele ponto?".
 *
 * A aba troca a FONTE (a fila é o acervo com o corte do estado), não é um filtro
 * paralelo à busca — e é por isso que ela entra no contexto da exportação.
 *
 * ── O que a fila entrega, e por que a RECOMENDAÇÃO vem em destaque ──────────
 *
 * Cada linha traz o essencial para decidir: quando, equipe e fiscal, o ponto, o
 * desfecho, se houve documento (e qual) e — em destaque — a **recomendação do
 * fiscal**. O desfecho diz como a vistoria terminou; a recomendação diz o que
 * quem esteve lá está PEDINDO, e é por ela que a chefia sabe direcionar. Enterrada
 * no meio da linha, ela seria lida depois da decisão que deveria orientar.
 *
 * ── O recorte por EQUIPE, e as duas recusas ─────────────────────────────────
 *
 * A listagem do líder traz só os registros da equipe que ele lidera. O Chefe de
 * Setor e o administrador veem o universo — quem encaminha precisa saber o que
 * aconteceu com o que encaminhou, e o administrador é o dono do sistema. Quem
 * decide isso é {@see Papel}, a fonte única da regra, e não uma cópia por tela.
 *
 * Mas esconder da lista NÃO é fronteira: quem souber montar a requisição
 * alcançaria registro de outra equipe, e o lote é o caminho fácil para isso porque
 * manda uma lista de identificadores. Então há duas conferências no servidor, e
 * nenhuma substitui a outra:
 *
 *   1. **quem decide** — a leitura do retorno é ato de quem mandou a equipe: o
 *      líder dela, ou o Chefe de Setor, que responde pelo setor inteiro. O
 *      fiscal consulta e não decide: dar-lhe a decisão seria deixá-lo dar
 *      ciência do próprio trabalho;
 *   2. **de quem é o registro** — conferido contra a área GRAVADA em cada
 *      registro e o vínculo do usuário, as duas coisas que o corpo da requisição
 *      não controla.
 *
 * As duas recusam dizendo o motivo, e sem tela de erro seca: quem clicou perdeu
 * a seleção, não a explicação.
 *
 * ── O FISCAL entra, e entra para LER ────────────────────────────────────────
 *
 * A concessão inclui o fiscal em apenas leitura (decisão do dono, 09/09/2026),
 * e com uma ressalva escrita: **o fiscal é usuário do APLICATIVO**; o acesso dele
 * à Retaguarda é improvável e existe por completude, não por fluxo. Decidir sobre
 * o retorno continua sendo da chefia — quem escreveu o retorno foi ele, e dar-lhe
 * a decisão apagaria a conferência que a fila existe para provocar. O servidor
 * recusa o ato dele, e a tela não lhe oferece o que o servidor recusa.
 *
 * ⚠️ Ele vê o ACERVO INTEIRO, e não só o que assinou. O recorte por área é do
 * Chefe de Setor; entre a CONTA do fiscal e os registros que ela assinou não há
 * vínculo hoje — o registro guarda o NOME de quem assinou, a estrutura guarda a
 * MATRÍCULA do fiscal na equipe, e nada liga os dois. Casar por nome seria
 * adivinhar, e adivinhar em fronteira de dados é pior que não ter fronteira: cria
 * a impressão de que existe uma. Está registrado como pendência no doc de regra.
 *
 * ⚠️ PROTÓTIPO: nada é gravado. Os registros vêm do trâmite das denúncias e de
 * `config/prototipo_registros_de_campo.php`, e as decisões vivem na sessão de
 * quem navega (ver {@see FiscalizacoesFicticias}).
 *
 * Não há rota de INCLUSÃO, e isso é deliberado: registro de fiscalização nasce em
 * RUA, no aplicativo do fiscal. Um botão de cadastrar aqui criaria um segundo
 * dono para o ato que dá sentido a estas duas abas.
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/fiscalizacoes/…`), então as mutações abaixo nascem protegidas
 * sem ninguém declarar nada.
 */
class FiscalizacoesController extends Controller
{
    /** Quantos registros o lote aceita de uma vez — o mesmo teto da página da grade. */
    private const MAX_LOTE = 200;

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $equipes = Papel::equipes($usuario);
        $comRecorte = Papel::recorta($usuario);

        return Inertia::render('Retaguarda/Fiscalizacao/Fiscalizacoes', [
            // O recorte é feito AQUI, e não na tela: filtro de front esconde, não
            // protege, e o acervo inteiro teria viajado até o navegador de quem não
            // deve vê-lo — com o relato do fiscal, as fotos e o número do documento
            // dentro.
            'registros' => $this->registros($comRecorte ? $equipes : null),
            // Os catálogos vêm do SERVIDOR: são os MESMOS que a validação exige e
            // que a busca reconhece como faceta. Escritos também na tela, um dia
            // discordariam — e a tela ofereceria um estado que o servidor recusa.
            'estados' => [
                Fiscalizacao::AGUARDANDO_LEITURA,
                Fiscalizacao::CIENTE,
                Fiscalizacao::NOVA_VISTORIA,
                Fiscalizacao::DEVOLVIDA,
            ],
            'desfechos' => Fiscalizacao::DESFECHOS,
            // O catálogo de recomendações na redação EXPLÍCITA — o registro traz
            // a CHAVE (`retorno`, `sgci`…), que é o que o aplicativo do fiscal
            // grava, e a chefia lê a frase inteira. A pílula curta é do celular;
            // aqui a frase é lida com atenção por quem decide, e "Sugerir
            // retorno da equipe" não diz QUANDO voltar. O mapa vem do servidor
            // porque a tela também precisa da lista inteira: recomendação é
            // faceta da busca, e a exportação leva a frase, não a chave.
            'recomendacoesDoFiscal' => RecomendacoesDoFiscal::explicitos(),
            // As origens EM PALAVRAS, derivadas das chaves gravadas: é assim que
            // a busca reconhece "ronda" e "operação" como faceta.
            'origens' => ['Denúncia direcionada', 'Operação planejada', 'Ronda da equipe'],
            // Quem lidera cada equipe — é o que o Chefe de Setor precisa ver ao
            // acompanhar: "é da C2" só diz metade; a outra metade é de quem.
            'lideres' => Estrutura::lideresPorEquipe(),
            // O que esta pessoa exerce nesta tela, e sobre o que. A tela usa para
            // dizer qual é a sua equipe no selo e para explicar que a lista não é
            // o universo — e a MESMA resposta governa a recusa no servidor.
            'decide' => Papel::decide($usuario),
            'equipesDoLider' => $equipes,
            'recorteDeEquipe' => $comRecorte,
            // As COLUNAS da grade e as do arquivo — uma listagem por aba, porque
            // a aba troca a fonte dos dados. Ver docs/padroes/listagem-clean.md.
            //
            // A coluna de EQUIPE é condicional e quem resolve é aqui: para o
            // líder de uma equipe só ela repetiria a mesma palavra em toda linha
            // (gasto de largura sem informação); para quem varre várias, ela é o
            // que torna a fila navegável. A conta usa a MESMA resposta do
            // recorte, e não uma segunda regra de tela.
            'listagens' => ListagensDaRetaguarda::para(
                ['fiscalizacoes.a-decidir', 'fiscalizacoes.acervo'],
                ['varias-areas' => ! $comRecorte || count($equipes) > 1],
            ),
        ]);
    }

    /**
     * CIÊNCIA — a chefia leu o retorno e o que era dela está encerrado.
     *
     * A observação é opcional: o ato de ler já é a informação, e exigir texto
     * para dar ciência de seis registros de uma vez faria a chefia escrever seis
     * frases vazias — o que estraga justamente o campo em que ela escreveria algo
     * quando tem algo a dizer.
     */
    public function ciencia(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirDecisao($request)) !== null) {
            return $recusa;
        }

        /*
         * O LÍDER não encerra (decisão do dono, 24/09/2026): ele decide o que o
         * retorno pede — a equipe voltar, ou o caso subir ao Chefe de Setor —, e
         * a tela dele já não oferece a ciência. A recusa aqui é a fronteira: sem
         * ela, bastaria montar a requisição para arquivar a demanda.
         */
        if (Papel::recorta($request->user())) {
            return back()->with(
                'flash.erro',
                'O líder de equipe não encerra o retorno: mande a equipe voltar ao ponto ou encaminhe o caso ao '
                .'Chefe de Setor para ele deliberar. Nada foi alterado.',
            );
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ], [
            'ids.required' => 'Escolha ao menos um registro para dar ciência.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirEquipe($request, $ids)) !== null) {
            return $recusa;
        }

        $efeito = $this->decisao($request)->darCiencia($ids, $dados['observacao'] ?? null);

        return back()->with(...$this->recado(
            $efeito,
            'retorno lido — sai da fila da sua equipe e fica no acervo',
            'retornos lidos — saem da fila da sua equipe e ficam no acervo',
        ));
    }

    /**
     * NOVA VISTORIA — a chefia manda a equipe voltar ao ponto.
     *
     * A justificativa é obrigatória, e com tamanho mínimo, no SERVIDOR: mandar a
     * equipe de volta gasta o trabalho dela outra vez, e "voltar lá" não conta a
     * ela o que deve procurar desta vez. Esconder o campo na tela não impede
     * ninguém de mandar a requisição sem ele.
     */
    public function novaVistoria(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirDecisao($request)) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'justificativa' => ['required', 'string', 'min:15', 'max:1000'],
        ], [
            'ids.required' => 'Escolha ao menos um registro.',
            'justificativa.required' => 'Escreva a justificativa: a equipe precisa saber o que '
                .'procurar desta vez.',
            'justificativa.min' => 'A justificativa está curta demais para orientar a equipe na '
                .'volta ao ponto.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirEquipe($request, $ids)) !== null) {
            return $recusa;
        }

        $efeito = $this->decisao($request)->pedirNovaVistoria($ids, (string) $dados['justificativa']);

        return back()->with(...$this->recado(
            $efeito,
            'devolvido à equipe para nova vistoria',
            'devolvidos à equipe para nova vistoria',
        ));
    }

    /**
     * DEVOLVER AO CHEFE DE SETOR — o líder diz que o caso não é da equipe dele.
     *
     * Terceira saída da leitura, ao lado da ciência e da nova vistoria (ordem do
     * dono, 10/09/2026): fecha o ciclo, porque quem re-encaminha é o chefe. O
     * motivo é obrigatório no SERVIDOR — devolver calado joga o caso de volta na
     * mesa do chefe sem nada com que decidir.
     */
    public function devolver(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirDecisao($request)) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'min:15', 'max:1000'],
        ], [
            'ids.required' => 'Escolha ao menos um registro.',
            'motivo.required' => 'Escreva o motivo: o Chefe de Setor precisa saber por que o caso '
                .'voltou para ele.',
            'motivo.min' => 'O motivo está curto demais para o Chefe de Setor re-encaminhar o caso.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirEquipe($request, $ids)) !== null) {
            return $recusa;
        }

        $efeito = $this->decisao($request)->devolverAoChefe($ids, (string) $dados['motivo']);

        return back()->with(...$this->recado(
            $efeito,
            'devolvido ao Chefe de Setor',
            'devolvidos ao Chefe de Setor',
        ));
    }

    /**
     * A fila do retorno de campo, já na forma que a tela lê.
     *
     * Só o que foi DESPACHADO pelo fiscal entra: o que ainda está em campo é
     * rascunho no aparelho dele, e mostrá-lo aqui faria a chefia decidir sobre
     * vistoria que não terminou.
     *
     * O recorte por equipe é feito AQUI, e não na tela: filtro de front esconde,
     * não protege, e o acervo inteiro teria viajado até o navegador de quem não
     * deve vê-lo — com o relato do fiscal, as fotos e o número do documento
     * dentro. `$equipes` nulo significa "sem recorte".
     *
     * @param  list<string>|null  $equipes  códigos
     * @return list<array<string, mixed>>
     */
    private function registros(?array $equipes): array
    {
        $consulta = Fiscalizacao::despachadas()
            ->with([
                'demanda.tramites', 'operacao', 'equipe.area', 'fiscal',
                'ambulante', 'fotos', 'recomendacoes', 'documento',
            ])
            ->orderByDesc('concluida_em')
            ->orderByDesc('id');

        if ($equipes !== null) {
            $consulta->whereHas('equipe', static fn ($q) => $q->whereIn('codigo', $equipes));
        }

        return $consulta->get()->map(FiscalizacaoParaTela::completa(...))->all();
    }

    /**
     * Recusa a decisão de quem apenas ACOMPANHA a fila.
     *
     * Isto é papel, e não permissão de tela: a permissão (slug `fiscalizacoes`)
     * diz quem entra; isto diz de quem é a decisão. As duas conferências existem,
     * e nenhuma substitui a outra — e é esta que barra o FISCAL, que entra para
     * consultar o próprio trabalho e não pode dar ciência dele.
     */
    private function exigirDecisao(Request $request): ?RedirectResponse
    {
        if (Papel::decide($request->user())) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'A leitura do retorno de campo é do líder da equipe (ou do Chefe de Setor) — é ele que '
            .'decide se a equipe volta ao ponto. Você consulta o que a fiscalização registrou.',
        );
    }

    /**
     * O serviço que aplica a decisão, assinando com o PAPEL de quem age.
     *
     * Quem é recortado por equipe age como líder; quem vê tudo age como chefe.
     * O trâmite guarda essa diferença porque ela é fato: "o líder deu ciência" e
     * "o chefe deu ciência" não são a mesma coisa mesmo quando é a mesma pessoa.
     */
    private function decisao(Request $request): DecisaoDaChefia
    {
        $usuario = $request->user();

        return new DecisaoDaChefia(
            $usuario,
            Papel::papelDoTramite($usuario, Papel::recorta($usuario) ? 'lider' : 'chefe'),
        );
    }

    /**
     * Recusa a decisão do líder sobre registro que NÃO é da equipe dele.
     *
     * Existe porque esconder da listagem não é fronteira: a lista dele já vem
     * recortada, mas quem souber montar a requisição alcançaria o registro de
     * outra equipe — e o lote é o caminho fácil, porque manda uma lista de
     * identificadores.
     *
     * A conferência é contra a EQUIPE GRAVADA em cada registro e o vínculo do
     * usuário. O administrador e o Chefe de Setor passam: respondem por tudo.
     *
     * @param  list<int>  $ids
     */
    private function exigirEquipe(Request $request, array $ids): ?RedirectResponse
    {
        $usuario = $request->user();

        if (! Papel::recorta($usuario)) {
            return null;
        }

        $minhas = Papel::equipes($usuario);

        /*
         * Líder SEM equipe vinculada não é caso de passar batido: ele exerce a
         * decisão (senão não chegaria aqui) e não tem equipe sobre a qual
         * decidir. Recusar dizendo isso é o que faz alguém corrigir o cadastro —
         * deixar passar daria a ele a fila inteira do setor.
         */
        if ($minhas === []) {
            return back()->with(
                'flash.erro',
                'Sua conta não está vinculada a nenhuma equipe, então não há fila sua para ler. '
                .'Procure quem administra o sistema para registrar a sua equipe.',
            );
        }

        $deFora = [];

        foreach ($ids as $id) {
            $registro = Fiscalizacao::with('equipe')->find($id);
            $equipe = $registro?->equipe?->codigo;

            if ($equipe === null || ! in_array($equipe, $minhas, true)) {
                $deFora[] = $registro->protocolo ?? "#{$id}";
            }
        }

        if ($deFora === []) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'Você lidera '.(count($minhas) === 1 ? 'a Equipe ' : 'as Equipes ').implode(', ', $minhas).', e '
            .(count($deFora) === 1 ? 'o registro ' : 'os registros ')
            .implode(', ', $deFora)
            .(count($deFora) === 1 ? ' não é dessa equipe' : ' não são dessa equipe')
            .'. Nada foi alterado — recarregue a fila.',
        );
    }

    /**
     * O recado depois de uma decisão em lote — dizendo o EFEITO dela, com a conta
     * certa e a concordância certa.
     *
     * "Salvo com sucesso" não conta quantos foram nem o que aconteceu. E o caso de
     * NADA ter mudado (a fila estava velha, alguém decidiu antes) é aviso, e não
     * sucesso: fingir que deu certo esconderia justamente o que a pessoa precisa
     * saber para recarregar a tela.
     *
     * @param  array{alterados: int, ignorados: int}  $efeito
     * @return array{0: string, 1: string}
     */
    private function recado(array $efeito, string $singular, string $plural): array
    {
        if ($efeito['alterados'] === 0) {
            return [
                'flash.erro',
                'Nenhum dos registros escolhidos está mais disponível. Recarregue a fila.',
            ];
        }

        $quantos = $efeito['alterados'];
        $frase = $quantos === 1
            ? "1 {$singular}."
            : "{$quantos} {$plural}.";

        if ($efeito['ignorados'] > 0) {
            $frase .= " {$efeito['ignorados']} não foram encontrados e ficaram como estavam.";
        }

        return ['flash.sucesso', $frase];
    }
}
