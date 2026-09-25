<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\CicloDeFiscalizacao;
use App\Models\Fiscalizacao;
use App\Models\Operacao;
use App\Support\Apresentacao\CicloParaTela;
use App\Support\Apresentacao\OperacaoParaTela;
use App\Support\CiclosDeFiscalizacao;
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
            /*
             * As FISCALIZAÇÕES (ciclos) — uma por encaminhamento do chefe ao líder,
             * cada uma com as vistorias dela. O recorte é feito AQUI, e não na
             * tela: filtro de front esconde, não protege, e o acervo inteiro
             * teria viajado até o navegador de quem não deve vê-lo — com o relato
             * do fiscal, as fotos e o número do documento dentro.
             */
            'fiscalizacoes' => $this->ciclos($comRecorte ? $equipes : null),
            // A Fiscalização a abrir, quando se chega por um link (a demanda da
            // Caixa de Entrada aponta para cá). Número: o WAF barra texto na URL.
            'abrir' => $request->integer('fiscalizacao') ?: null,
            'desfechos' => Fiscalizacao::DESFECHOS,
            'recomendacoesDoFiscal' => RecomendacoesDoFiscal::explicitos(),
            'lideres' => Estrutura::lideresPorEquipe(),
            // Quem conduz a Fiscalização com a equipe: o líder (e o administrador).
            'conduz' => Papel::ehLider($usuario) || ($usuario?->ehAdmin() ?? false),
            // Quem arquiva a Fiscalização sem processo atrás: o chefe (e o administrador).
            'arquiva' => Papel::ehChefe($usuario) || ($usuario?->ehAdmin() ?? false),
            'equipesDoLider' => $equipes,
            'recorteDeEquipe' => $comRecorte,
            // As operações abertas — o líder pode incluir a demanda numa delas.
            'operacoes' => Operacao::abertas()->with(['area', 'equipes', 'bairros'])
                ->orderBy('nome')->get()->map(OperacaoParaTela::completa(...))->all(),
            'listagens' => ListagensDaRetaguarda::para(['fiscalizacoes.ciclos']),
        ]);
    }

    /**
     * MANDAR A EQUIPE VOLTAR — nova vistoria, na MESMA Fiscalização.
     *
     * A justificativa é obrigatória no SERVIDOR: mandar a equipe de volta consome
     * tempo de trabalho, e "voltar lá" não diz o que procurar.
     */
    public function novaVistoria(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirConducao($request)) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'justificativa' => ['required', 'string', 'min:15', 'max:1000'],
        ], [
            'ids.required' => 'Escolha ao menos uma Fiscalização.',
            'justificativa.required' => 'Escreva a justificativa: a equipe precisa saber o que procurar desta vez.',
            'justificativa.min' => 'A justificativa está curta demais para orientar a equipe na volta ao ponto.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirEquipe($request, $ids)) !== null) {
            return $recusa;
        }

        // A decisão é sobre a VISTORIA que voltou da rua — a pendente de cada Fiscalização.
        $vistorias = CicloDeFiscalizacao::with('vistorias')->whereIn('id', $ids)->get()
            ->map(static fn (CicloDeFiscalizacao $c): ?int => $c->vistoriaPendente()?->id)
            ->filter()->values()->all();

        $efeito = $this->decisao($request)->pedirNovaVistoria($vistorias, (string) $dados['justificativa']);
        $efeito['ignorados'] += count($ids) - count($vistorias);

        return back()->with(...$this->recado(
            $efeito,
            'Fiscalização com a equipe mandada de volta ao ponto',
            'Fiscalizações com a equipe mandada de volta ao ponto',
        ));
    }

    /**
     * ENCAMINHAR AO CHEFE DE SETOR — o líder devolve a Fiscalização com o
     * resultado, e ela vai para a aba "Encaminhadas". Não existe "dar ciência"
     * (dono, 24/09/2026): o líder decide, e o que decide sai da mão dele.
     */
    public function encaminharAoChefe(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirConducao($request)) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'min:15', 'max:1000'],
        ], [
            'ids.required' => 'Escolha ao menos uma Fiscalização.',
            'motivo.required' => 'Escreva o motivo: é o contexto para o Chefe de Setor deliberar.',
            'motivo.min' => 'O motivo está curto demais para o Chefe de Setor deliberar.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirEquipe($request, $ids)) !== null) {
            return $recusa;
        }

        $efeito = (new CiclosDeFiscalizacao($request->user()))->encaminharAoChefe($ids, (string) $dados['motivo']);

        return back()->with(...$this->recado(
            $efeito,
            'Fiscalização encaminhada ao Chefe de Setor',
            'Fiscalizações encaminhadas ao Chefe de Setor',
        ));
    }

    /**
     * ARQUIVAR — o chefe encerra a Fiscalização sem processo atrás (ronda,
     * operação). A que tem demanda vai ao Arquivo quando o processo volta à origem.
     */
    public function arquivar(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        if (! Papel::ehChefe($usuario) && ! ($usuario?->ehAdmin() ?? false)) {
            return back()->with('flash.erro', 'Arquivar a Fiscalização é do Chefe de Setor.');
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
        ]);

        $efeito = (new CiclosDeFiscalizacao($usuario))->arquivar(array_map('intval', $dados['ids']));

        if ($efeito['alterados'] === 0) {
            return back()->with(
                'flash.erro',
                'Só se arquiva aqui a Fiscalização SEM processo (ronda, operação) que já foi encaminhada. '
                .'A que tem demanda vai ao Arquivo quando o processo volta à origem, pela Caixa de Entrada.',
            );
        }

        return back()->with(...$this->recado($efeito, 'Fiscalização arquivada', 'Fiscalizações arquivadas'));
    }

    /**
     * As Fiscalizações, já na forma que a tela lê — as mais recentes primeiro.
     *
     * @param  list<string>|null  $equipes  códigos; nulo = sem recorte
     * @return list<array<string, mixed>>
     */
    private function ciclos(?array $equipes): array
    {
        $consulta = CicloDeFiscalizacao::with([
            'demanda.anexos', 'demanda.ciclos.vistorias.fotos', 'demanda.ciclos.vistorias.documento', 'demanda.ciclos.equipe',
            'equipe.area', 'equipe.lider', 'encaminhadoPor',
            'vistorias.demanda.tramites', 'vistorias.operacao', 'vistorias.equipe.area', 'vistorias.fiscal',
            'vistorias.ambulante', 'vistorias.fotos', 'vistorias.recomendacoes', 'vistorias.documento',
        ])
            ->orderByDesc('aberto_em')
            ->orderByDesc('id');

        if ($equipes !== null) {
            $consulta->whereHas('equipe', static fn ($q) => $q->whereIn('codigo', $equipes));
        }

        return $consulta->get()->map(CicloParaTela::completo(...))->all();
    }

    /**
     * Recusa quem não CONDUZ a Fiscalização com a equipe.
     *
     * Isto é papel, e não permissão de tela: a permissão (slug `fiscalizacoes`)
     * diz quem entra; isto diz quem decide o que a equipe faz. O chefe acompanha
     * aqui e delibera pela Caixa de Entrada; o fiscal consulta.
     */
    private function exigirConducao(Request $request): ?RedirectResponse
    {
        $usuario = $request->user();

        if (Papel::ehLider($usuario) || ($usuario?->ehAdmin() ?? false)) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'Conduzir a Fiscalização com a equipe é do líder: é ele que manda a equipe ao ponto, a manda voltar '
            .'e encaminha o resultado ao Chefe de Setor. O chefe delibera pela Caixa de Entrada.',
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
            $ciclo = CicloDeFiscalizacao::with('equipe')->find($id);
            $equipe = $ciclo?->equipe?->codigo;

            if ($equipe === null || ! in_array($equipe, $minhas, true)) {
                $deFora[] = $ciclo->protocolo ?? "#{$id}";
            }
        }

        if ($deFora === []) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'Você lidera '.(count($minhas) === 1 ? 'a Equipe ' : 'as Equipes ').implode(', ', $minhas).', e '
            .(count($deFora) === 1 ? 'a Fiscalização ' : 'as Fiscalizações ')
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
                'Nenhuma das Fiscalizações escolhidas está mais disponível para isso. Recarregue a tela.',
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
