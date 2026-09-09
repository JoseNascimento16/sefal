<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\FiscalizacoesFicticias;
use App\Support\Prototipo\PapelNaArea;
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
 * ── O recorte por ÁREA, e as duas recusas ───────────────────────────────────
 *
 * A listagem do Chefe de Setor traz só os registros das equipes da área que ele
 * responde. O Coordenador e o administrador veem o universo — quem tria precisa
 * saber o que aconteceu com o que encaminhou, e o administrador é o dono do
 * sistema. Quem decide isso é {@see PapelNaArea}, a fonte única da regra, e não
 * uma cópia por tela.
 *
 * Mas esconder da lista NÃO é fronteira: quem souber montar a requisição
 * alcançaria registro de outra área, e o lote é o caminho fácil para isso porque
 * manda uma lista de identificadores. Então há duas conferências no servidor, e
 * nenhuma substitui a outra:
 *
 *   1. **quem decide** — a leitura do retorno é ato da CHEFIA da área. O
 *      Coordenador acompanha e não decide: dar-lhe a decisão criaria um segundo
 *      dono para o direcionamento, que é do Chefe de Setor;
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
        $areas = PapelNaArea::areas($usuario);
        $comRecorte = PapelNaArea::recorta($usuario);

        return Inertia::render('Retaguarda/Fiscalizacao/Fiscalizacoes', [
            // O recorte é feito AQUI, e não na tela: filtro de front esconde, não
            // protege, e o acervo inteiro teria viajado até o navegador de quem não
            // deve vê-lo — com o relato do fiscal, as fotos e o número do documento
            // dentro.
            'registros' => $comRecorte
                ? array_values(array_filter(
                    FiscalizacoesFicticias::registros(),
                    static fn (array $r): bool => in_array((string) $r['area'], $areas, true),
                ))
                : FiscalizacoesFicticias::registros(),
            // Os catálogos vêm do SERVIDOR: são os MESMOS que a validação exige e
            // que a busca reconhece como faceta. Escritos também na tela, um dia
            // discordariam — e a tela ofereceria um estado que o servidor recusa.
            'estados' => FiscalizacoesFicticias::estados(),
            'desfechos' => array_values((array) config('prototipo_denuncias.desfechos', [])),
            // O catálogo de recomendações na redação EXPLÍCITA — o registro traz
            // a CHAVE (`retorno`, `sgci`…), que é o que o aplicativo do fiscal
            // grava, e a chefia lê a frase inteira. A pílula curta é do celular;
            // aqui a frase é lida com atenção por quem decide, e "Sugerir
            // retorno da equipe" não diz QUANDO voltar. O mapa vem do servidor
            // porque a tela também precisa da lista inteira: recomendação é
            // faceta da busca, e a exportação leva a frase, não a chave.
            'recomendacoesDoFiscal' => RecomendacoesDoFiscal::explicitos(),
            'origens' => array_values((array) config('prototipo_registros_de_campo.origens', [])),
            // Quem responde por cada área — é o que o Coordenador precisa ver ao
            // acompanhar: "é da Área 5" só diz metade; a outra metade é de quem.
            'chefias' => EstruturaFicticia::chefiasPorArea(),
            // O que esta pessoa exerce nesta tela, e sobre o que. A tela usa para
            // dizer qual é a sua área no selo e para explicar que a lista não é o
            // universo — e a MESMA resposta governa a recusa no servidor.
            'decide' => PapelNaArea::decide($usuario),
            'areasDoChefe' => $areas,
            'recorteDeArea' => $comRecorte,
            'alterada' => FiscalizacoesFicticias::alterada(),
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

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ], [
            'ids.required' => 'Escolha ao menos um registro para dar ciência.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirArea($request, $ids)) !== null) {
            return $recusa;
        }

        $efeito = FiscalizacoesFicticias::darCiencia($ids, $dados['observacao'] ?? null);

        return back()->with(...$this->recado(
            $efeito,
            'retorno lido — sai da fila da sua área e fica no acervo',
            'retornos lidos — saem da fila da sua área e ficam no acervo',
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

        if (($recusa = $this->exigirArea($request, $ids)) !== null) {
            return $recusa;
        }

        $efeito = FiscalizacoesFicticias::pedirNovaVistoria($ids, (string) $dados['justificativa']);

        return back()->with(...$this->recado(
            $efeito,
            'devolvido à equipe para nova vistoria',
            'devolvidos à equipe para nova vistoria',
        ));
    }

    /**
     * Devolve a fila ao estado de partida.
     *
     * Existe porque é PROTÓTIPO: quem está demonstrando precisa poder recomeçar a
     * cena. No sistema real esta rota não existe — ciência dada não se desfaz.
     */
    public function reiniciar(): RedirectResponse
    {
        FiscalizacoesFicticias::reiniciar();

        return back()->with('flash.sucesso', 'Fila devolvida ao estado de demonstração.');
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
        if (PapelNaArea::decide($request->user())) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'A leitura do retorno de campo é do Chefe de Setor da área — é ele que decide se a '
            .'equipe volta ao ponto. Você consulta o que a fiscalização registrou.',
        );
    }

    /**
     * Recusa a decisão do Chefe de Setor sobre registro que NÃO é da área dele.
     *
     * Existe porque esconder da listagem não é fronteira: a lista dele já vem
     * recortada, mas quem souber montar a requisição alcançaria o registro de
     * outra área — e o lote é o caminho fácil, porque manda uma lista de
     * identificadores.
     *
     * A conferência é contra a área GRAVADA em cada registro e o vínculo do
     * usuário. O administrador passa: é o dono do sistema.
     *
     * @param  list<int>  $ids
     */
    private function exigirArea(Request $request, array $ids): ?RedirectResponse
    {
        $usuario = $request->user();

        if ($usuario === null || $usuario->ehAdmin()) {
            return null;
        }

        $minhas = PapelNaArea::areas($usuario);

        /*
         * Chefe de Setor SEM área vinculada não é caso de passar batido: ele
         * exerce a decisão (senão não chegaria aqui) e não tem área sobre a qual
         * decidir. Recusar dizendo isso é o que faz alguém corrigir o cadastro —
         * deixar passar daria a ele a fila inteira do setor.
         */
        if ($minhas === []) {
            return back()->with(
                'flash.erro',
                'Sua conta não está vinculada a nenhuma área de fiscalização, então não há fila '
                .'sua para ler. Procure quem administra o sistema para registrar a sua área.',
            );
        }

        $deFora = [];

        foreach ($ids as $id) {
            $registro = FiscalizacoesFicticias::registro($id);
            $area = $registro === null ? null : (string) $registro['area'];

            if ($area === null || ! in_array($area, $minhas, true)) {
                $deFora[] = $registro['protocolo'] ?? "#{$id}";
            }
        }

        if ($deFora === []) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'Você responde por '.implode(', ', $minhas).', e '
            .(count($deFora) === 1 ? 'o registro ' : 'os registros ')
            .implode(', ', $deFora)
            .(count($deFora) === 1 ? ' não é dessa área' : ' não são dessa área')
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
