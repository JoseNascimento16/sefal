<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\RetornoDeCampoFicticio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Retorno de Campo — a fila do CHEFE DE SETOR: tudo que a equipe da área dele
 * concluiu em rua e voltou para ele. PROTÓTIPO.
 *
 * ── Por que esta tela existe, e por que ela não é a Caixa de Entrada ─────────
 *
 * "Todo registro de fiscalização concluído volta para a caixa de entrada do
 * Chefe de Setor" (decisão do dono, 04/09/2026). Sem ela, o trabalho da equipe
 * termina no aplicativo do fiscal e ninguém do outro lado é obrigado a ler: o
 * desfecho existiria no sistema e a decisão que ele pede — voltar ao ponto,
 * incluir numa operação, encerrar — ficaria sem dono.
 *
 * NÃO é a Caixa de Entrada. Lá o Coordenador digita o que chegou em PAPEL ao
 * balcão, no começo da cadeia; aqui a chefia lê o que voltou do CAMPO, no fim
 * dela. São as duas pontas do mesmo trabalho, com papéis, dados e decisões
 * diferentes — e é justamente essa distinção que as duas telas existem para
 * deixar clara.
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
 * sistema.
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
 * ⚠️ PROTÓTIPO: nada é gravado. Os registros vêm do trâmite das denúncias e de
 * `config/prototipo_registros_de_campo.php`, e as decisões vivem na sessão de
 * quem navega (ver `App\Support\Prototipo\RetornoDeCampoFicticio`).
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/retorno-de-campo/…`), então as mutações abaixo nascem protegidas
 * sem ninguém declarar nada.
 */
class RetornoDeCampoController extends Controller
{
    /** Quantos registros o lote aceita de uma vez — o mesmo teto da página da grade. */
    private const MAX_LOTE = 200;

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $areas = self::areasDoChefe($usuario);
        $comRecorte = self::temRecorteDeArea($usuario);

        return Inertia::render('Retaguarda/Fiscalizacao/RetornoDeCampo', [
            // O recorte é feito AQUI, e não na tela: filtro de front esconde, não
            // protege, e a fila inteira teria viajado até o navegador de quem não
            // deve vê-la — com o relato do fiscal e o número do documento dentro.
            'registros' => $comRecorte
                ? array_values(array_filter(
                    RetornoDeCampoFicticio::registros(),
                    static fn (array $r): bool => in_array((string) $r['area'], $areas, true),
                ))
                : RetornoDeCampoFicticio::registros(),
            // Os catálogos vêm do SERVIDOR: são os MESMOS que a validação exige e
            // que a busca reconhece como faceta. Escritos também na tela, um dia
            // discordariam — e a tela ofereceria um estado que o servidor recusa.
            'estados' => RetornoDeCampoFicticio::estados(),
            'desfechos' => array_values((array) config('prototipo_denuncias.desfechos', [])),
            'origens' => array_values((array) config('prototipo_registros_de_campo.origens', [])),
            // Quem responde por cada área — é o que o Coordenador precisa ver ao
            // acompanhar: "é da Área 5" só diz metade; a outra metade é de quem.
            'chefias' => EstruturaFicticia::chefiasPorArea(),
            // O que esta pessoa exerce nesta tela, e sobre o que. A tela usa para
            // dizer qual é a sua área no selo e para explicar que a lista não é o
            // universo — e a MESMA resposta governa a recusa no servidor.
            'decide' => self::decide($usuario),
            'areasDoChefe' => $areas,
            'recorteDeArea' => $comRecorte,
            'alterada' => RetornoDeCampoFicticio::alterada(),
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

        $efeito = RetornoDeCampoFicticio::darCiencia($ids, $dados['observacao'] ?? null);

        return back()->with(...$this->recado(
            $efeito,
            'retorno lido — sai da fila da sua área',
            'retornos lidos — saem da fila da sua área',
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

        $efeito = RetornoDeCampoFicticio::pedirNovaVistoria($ids, (string) $dados['justificativa']);

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
        RetornoDeCampoFicticio::reiniciar();

        return back()->with('flash.sucesso', 'Fila devolvida ao estado de demonstração.');
    }

    /**
     * Esta pessoa DECIDE nesta tela, ou apenas acompanha?
     *
     * Decide o Chefe de Setor (é a fila dele) e o administrador, que cobre a
     * ausência dele e demonstra o fluxo inteiro. O Coordenador acompanha: ele
     * precisa saber o que aconteceu com o que encaminhou, e a decisão sobre o
     * ponto continua sendo de quem responde pela área.
     */
    private static function decide(?User $usuario): bool
    {
        if ($usuario === null) {
            return false;
        }

        if ($usuario->ehAdmin()) {
            return true;
        }

        return in_array('chefe-de-setor', $usuario->setores->pluck('slug')->all(), true);
    }

    /**
     * As áreas que esta pessoa responde como Chefe de Setor — vazio para quem não
     * responde por área nenhuma.
     *
     * ⚠️ PROTÓTIPO: o vínculo mora em `config/prototipo_estrutura.php` e liga pela
     * matrícula. Em produção ele é entre USUÁRIO e área, e isso é tabela — está
     * registrado como pendência no doc de regra. Quem chama aqui já trata LISTA,
     * então a modelagem definitiva não obriga a mexer em quem lê.
     *
     * @return list<string>
     */
    private static function areasDoChefe(?User $usuario): array
    {
        return $usuario === null ? [] : EstruturaFicticia::areasDoChefe($usuario->login);
    }

    /**
     * A listagem desta pessoa é recortada pela área dela?
     *
     * É o Chefe de Setor, e só ele. O administrador é o dono do sistema, e o
     * Coordenador precisa do universo — não se acompanha o que não se vê. Um
     * Chefe de Setor que também seja Coordenador não é recortado: o papel que
     * amplia ganha, a mesma regra da união de setores na matriz de permissões.
     */
    private static function temRecorteDeArea(?User $usuario): bool
    {
        if ($usuario === null || $usuario->ehAdmin()) {
            return false;
        }

        $setores = $usuario->setores->pluck('slug')->all();

        return in_array('chefe-de-setor', $setores, true)
            && ! in_array('coordenador', $setores, true);
    }

    /**
     * Recusa a decisão de quem apenas ACOMPANHA a fila.
     *
     * Isto é papel, e não permissão de tela: a permissão (slug `retorno-de-campo`)
     * diz quem entra; isto diz de quem é a decisão. As duas conferências existem,
     * e nenhuma substitui a outra.
     */
    private function exigirDecisao(Request $request): ?RedirectResponse
    {
        if (self::decide($request->user())) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'A leitura do retorno de campo é do Chefe de Setor da área — é ele que decide se a '
            .'equipe volta ao ponto. Você acompanha o que a fiscalização devolveu.',
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

        $minhas = self::areasDoChefe($usuario);

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
            $registro = RetornoDeCampoFicticio::registro($id);
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
