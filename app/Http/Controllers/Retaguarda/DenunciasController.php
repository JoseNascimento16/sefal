<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Prototipo\DenunciasFicticias;
use App\Support\Prototipo\EstruturaFicticia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Denúncias das ouvidorias — PROTÓTIPO.
 *
 * Duas telas, uma por canal (`e-Salvador` e `Fala Salvador`), com a MESMA
 * mecânica: as denúncias chegam por integração, o administrativo tria e
 * encaminha à área, e o gestor da área direciona à equipe ou anexa a uma
 * operação. O que muda entre elas é a origem e o que o formato do canal
 * carrega — o e-Salvador vem com requerente identificado, endereço estruturado
 * e anexos; o Fala Salvador pode ser anônimo e traz a transcrição do
 * atendimento telefônico, às vezes sem número nem ponto de referência.
 *
 * ── Por que UM controller e UMA tela para os dois canais ─────────────────────
 *
 * Porque a REGRA é a mesma: as duas etapas, os estados, o trâmite, a derivação
 * bairro → área e a exigência de justificativa não mudam com o canal. Dois
 * controllers seriam a mesma regra com dois donos, e um dia só um deles
 * receberia a validação nova. O que varia é declarado em
 * `config/prototipo_denuncias.php` → `canais`, e a tela lê de lá.
 *
 * ── Nada é digitado aqui ────────────────────────────────────────────────────
 *
 * Não há rota de inclusão, e isso é deliberado: estas telas não são a Caixa de
 * Entrada (onde o administrativo digita o papel que chegou ao balcão). A
 * denúncia entra pela integração, e cada uma mostra o carimbo de quando o canal
 * a entregou e sob que número. Um botão "cadastrar denúncia" aqui apagaria
 * justamente a distinção que o módulo existe para deixar clara.
 *
 * ── As duas etapas têm dois donos, e a tela obedece ─────────────────────────
 *
 * A permissão de ABRIR a tela é uma só (slug `denuncias`, no Modo Gerente). O
 * que separa os papéis é a ETAPA, derivada do setor de quem entrou: o
 * ADMINISTRATIVO tria, o GESTOR direciona, e o administrador do sistema exerce
 * as duas — é ele que demonstra o fluxo inteiro e que cobre a ausência do outro.
 * A conferência acontece AQUI, no servidor, e não só na tela: esconder o botão é
 * conforto, nunca fronteira.
 *
 * ── E o gestor é gestor de uma ÁREA ─────────────────────────────────────────
 *
 * "Pra ele só interessa o que for direcionado para a área dele" (decisão do dono,
 * 02/09/2026). Então o gestor tem RECORTE: a listagem dele traz só as denúncias
 * da área que ele responde, e a ação sobre denúncia de outra área é RECUSADA com
 * o motivo escrito. As duas coisas, e não uma: esconder da lista sem barrar a
 * ação deixaria a fronteira valendo apenas para quem não sabe mandar a
 * requisição.
 *
 * O administrador continua vendo tudo — é o dono do sistema. O administrativo
 * também, porque quem tria precisa saber o que aconteceu com o que encaminhou.
 *
 * ⚠️ PROTÓTIPO: nada é gravado em banco. As denúncias de partida vêm da config
 * e as decisões ficam na sessão de quem está navegando (ver
 * `App\Support\Prototipo\DenunciasFicticias`). A tela diz isso de forma
 * visível — protótipo que se disfarça de sistema pronto vira decisão tomada por
 * engano.
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/denuncias/…`), então as rotas dos dois canais e todas as
 * mutações nascem protegidas sem ninguém declarar nada — e com UMA permissão
 * para o módulo, que é o que "quem cuida de denúncia" quer dizer.
 */
class DenunciasController extends Controller
{
    /** Quantos itens o lote aceita de uma vez — o mesmo teto da página da grade. */
    private const MAX_LOTE = 200;

    public function eSalvador(Request $request): Response
    {
        return $this->tela($request, 'e-salvador', 'ESalvador');
    }

    public function falaSalvador(Request $request): Response
    {
        return $this->tela($request, 'fala-salvador', 'FalaSalvador');
    }

    /**
     * TRIAGEM — encaminha as denúncias selecionadas às áreas escolhidas.
     *
     * O corpo traz `destinos`: uma lista de pares identificador → área. Não é um
     * identificador por requisição nem uma área para o lote inteiro, porque a
     * triagem real é os dois casos ao mesmo tempo — chegam dez denúncias de
     * bairros diferentes, cada uma com a sua área, e o administrativo confirma
     * todas de uma vez.
     */
    public function encaminhar(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirEtapa($request, 'triagem')) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'destinos' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'destinos.*.id' => ['required', 'integer'],
            'destinos.*.area' => ['required', Rule::in(EstruturaFicticia::nomesDeArea())],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [
            'destinos.required' => 'Escolha ao menos uma denúncia para encaminhar.',
            'destinos.*.area.required' => 'Confirme a área de cada denúncia antes de encaminhar.',
            'destinos.*.area.in' => 'A área escolhida não existe na estrutura de fiscalização.',
        ]);

        $areasPorId = [];

        foreach ($dados['destinos'] as $destino) {
            $areasPorId[(int) $destino['id']] = (string) $destino['area'];
        }

        $efeito = DenunciasFicticias::encaminharAArea($areasPorId, $dados['observacao'] ?? null);

        return back()->with(...$this->recado(
            $efeito,
            'encaminhada à área, para o gestor direcionar',
            'encaminhadas às áreas, para os gestores direcionarem',
        ));
    }

    /**
     * TRIAGEM — devolve ao canal de origem ou arquiva, com motivo e justificativa.
     *
     * A justificativa é exigida no SERVIDOR, e com tamanho mínimo: devolver é ato
     * administrativo, e "não procede" não conta o caso a quem ler depois.
     * Esconder o campo na tela não impede ninguém de mandar a requisição sem ele.
     */
    public function devolver(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirEtapa($request, 'triagem')) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'motivo' => ['required', Rule::in((array) config('prototipo_denuncias.motivos_de_devolucao', []))],
            'justificativa' => ['required', 'string', 'min:15', 'max:1000'],
            'destino' => ['required', Rule::in((array) config('prototipo_denuncias.destinos_de_retorno', []))],
        ], [
            'ids.required' => 'Escolha ao menos uma denúncia.',
            'motivo.required' => 'Escolha o motivo da devolução.',
            'justificativa.required' => 'Escreva a justificativa: devolver ou arquivar é ato administrativo e precisa do motivo por escrito.',
            'justificativa.min' => 'A justificativa está curta demais para explicar a decisão a quem ler depois.',
            'destino.required' => 'Diga se a denúncia volta ao canal de origem ou é arquivada.',
        ]);

        $efeito = DenunciasFicticias::devolver(
            array_map('intval', $dados['ids']),
            $dados['motivo'],
            $dados['justificativa'],
            $dados['destino'],
        );

        return back()->with(...$this->recado(
            $efeito,
            'retirada do fluxo, com a justificativa registrada',
            'retiradas do fluxo, com a justificativa registrada',
        ));
    }

    /**
     * DIRECIONAMENTO — o gestor manda as denúncias a uma equipe.
     *
     * A justificativa passa a ser OBRIGATÓRIA quando a equipe escolhida não é a
     * da área da denúncia: tirar o trabalho da equipe responsável é decisão que
     * precisa estar escrita. A conferência é feita contra a área GRAVADA na
     * denúncia, não contra o que a tela mandou — senão bastaria omitir a área no
     * corpo para a exigência desaparecer.
     */
    public function direcionar(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirEtapa($request, 'direcionamento')) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'equipe' => ['required', Rule::in(EstruturaFicticia::codigosDeEquipe())],
            'justificativa' => ['nullable', 'string', 'max:1000'],
        ], [
            'ids.required' => 'Escolha ao menos uma denúncia.',
            'equipe.required' => 'Escolha a equipe que vai vistoriar.',
            'equipe.in' => 'A equipe escolhida não existe na estrutura de fiscalização.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirArea($request, $ids)) !== null) {
            return $recusa;
        }

        $equipe = (string) $dados['equipe'];
        $justificativa = trim((string) ($dados['justificativa'] ?? ''));

        if ($justificativa === '' && $this->trocaDeEquipe($ids, $equipe)) {
            return back()->withErrors([
                'justificativa' => 'A equipe escolhida não é a da área da denúncia. Escreva por que o '
                    .'trabalho sai da equipe responsável.',
            ]);
        }

        $efeito = DenunciasFicticias::direcionarAEquipe($ids, $equipe, $justificativa === '' ? null : $justificativa);

        return back()->with(...$this->recado(
            $efeito,
            "direcionada à Equipe {$equipe} — aparecerá no aplicativo dos fiscais dela",
            "direcionadas à Equipe {$equipe} — aparecerão no aplicativo dos fiscais dela",
        ));
    }

    /**
     * DIRECIONAMENTO — o gestor anexa as denúncias a uma operação.
     *
     * A operação pode ser uma das que já existem ou uma NOVA, aberta dali mesmo:
     * é o caso de não haver trabalho planejado para aquela região ainda. Um
     * endpoint só para as duas formas, porque o efeito na denúncia é o mesmo —
     * dois obrigariam a repetir a regra de anexação, e um dia só um a teria.
     */
    public function operacao(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirEtapa($request, 'direcionamento')) !== null) {
            return $recusa;
        }

        $nova = $request->boolean('nova');

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'nova' => ['required', 'boolean'],

            // Operação existente: o nome tem de ser uma das que existem.
            'operacao' => ['exclude_if:nova,true', 'required', Rule::in(DenunciasFicticias::nomesDeOperacao())],

            // Operação nova: o mínimo para ela ser reconhecível depois.
            'nome' => ['exclude_unless:nova,true', 'required', 'string', 'min:5', 'max:120'],
            'area' => ['exclude_unless:nova,true', 'required', Rule::in(EstruturaFicticia::nomesDeArea())],
            'equipe' => ['exclude_unless:nova,true', 'required', Rule::in(EstruturaFicticia::codigosDeEquipe())],
            'periodo' => ['exclude_unless:nova,true', 'nullable', 'string', 'max:80'],
            'foco' => ['exclude_unless:nova,true', 'nullable', 'string', 'max:300'],
        ], [
            'ids.required' => 'Escolha ao menos uma denúncia.',
            'operacao.required' => 'Escolha a operação a que a denúncia será anexada.',
            'nome.required' => 'Dê um nome à operação — é por ele que a equipe vai reconhecê-la.',
            'area.required' => 'Diga de que área é a operação.',
            'equipe.required' => 'Diga qual equipe executa a operação.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirArea($request, $ids)) !== null) {
            return $recusa;
        }

        $operacao = $nova
            ? DenunciasFicticias::criarOperacao($dados)['nome']
            : (string) $dados['operacao'];

        $efeito = DenunciasFicticias::anexarAOperacao($ids, $operacao);

        return back()->with(...$this->recado(
            $efeito,
            "incluída na {$operacao}",
            "incluídas na {$operacao}",
        ));
    }

    /**
     * Devolve o módulo ao estado de partida.
     *
     * Existe porque é PROTÓTIPO: quem está demonstrando precisa poder recomeçar a
     * cena com os dois papéis. No sistema real esta rota não existe — denúncia
     * recebida não se desfaz.
     */
    public function reiniciar(): RedirectResponse
    {
        DenunciasFicticias::reiniciar();

        return back()->with('flash.sucesso', 'Denúncias devolvidas ao estado de demonstração.');
    }

    /**
     * A tela de um canal.
     *
     * As duas páginas são cascas de vinte linhas em volta do MESMO componente —
     * elas existem separadas só porque o título da aba e a trilha de navegação
     * são propriedade estática de layout no Inertia, e não podem sair de um prop.
     * Os dados e a mecânica são os mesmos: o que muda vem em `canal`, e é a
     * configuração do canal que diz o que aquele formato carrega.
     */
    private function tela(Request $request, string $canal, string $pagina): Response
    {
        /** @var array<string, mixed> $configuracao */
        $configuracao = (array) config("prototipo_denuncias.canais.{$canal}", []);

        $usuario = $request->user();
        $areasDoGestor = self::areasDoGestor($usuario);
        $comRecorte = self::temRecorteDeArea($usuario);

        return Inertia::render("Retaguarda/Denuncias/{$pagina}", [
            'canal' => $configuracao,
            // O gestor recebe SÓ o que é da área dele — o recorte é feito aqui, e
            // não na tela: filtro de front esconde, não protege, e a lista inteira
            // teria viajado até o navegador de quem não deve vê-la.
            'denuncias' => $comRecorte
                ? array_values(array_filter(
                    DenunciasFicticias::doCanal($canal),
                    static fn (array $d): bool => is_string($d['area'] ?? null)
                        && in_array($d['area'], $areasDoGestor, true),
                ))
                : DenunciasFicticias::doCanal($canal),
            // Os catálogos vêm do SERVIDOR: são os MESMOS que a validação exige.
            // Escritos também na tela, um dia discordariam — e a tela ofereceria
            // uma opção que o servidor recusa.
            'situacoes' => array_values((array) config('prototipo_denuncias.situacoes', [])),
            // Os desfechos de vistoria — a tela usa para a busca reconhecer
            // "regularizado no local" e "nada encontrado" como faceta. Vem do
            // servidor pela mesma razão dos outros catálogos: escrito na tela,
            // um dia reconheceria um desfecho que já não existe.
            'desfechos' => array_values((array) config('prototipo_denuncias.desfechos', [])),
            'motivos' => array_values((array) config('prototipo_denuncias.motivos_de_devolucao', [])),
            'destinos' => array_values((array) config('prototipo_denuncias.destinos_de_retorno', [])),
            'equipes' => EstruturaFicticia::equipes(),
            'areas' => EstruturaFicticia::nomesDeArea(),
            // Quem responde por cada área. É o que o triador precisa ver ANTES de
            // encaminhar: "vai para a Área 5" só diz metade; a outra metade é para
            // quem.
            'gestores' => EstruturaFicticia::gestoresPorArea(),
            'operacoes' => DenunciasFicticias::operacoes(),
            // A etapa de quem entrou — é ela que decide o que a tela oferece, e a
            // mesma resposta governa a recusa no servidor.
            'etapas' => self::etapas($usuario),
            // As áreas que esta pessoa responde, e se a listagem está recortada
            // por elas. A tela usa isso para dizer QUAL é a sua área no selo, e
            // para explicar que a lista não é o universo.
            'areasDoGestor' => $areasDoGestor,
            'recorteDeArea' => $comRecorte,
            'alterada' => DenunciasFicticias::alterada(),
        ]);
    }

    /**
     * As etapas do fluxo que esta pessoa exerce.
     *
     * O papel vem do SETOR, não de uma coluna nova: `administrativo` tria,
     * `gestor` direciona, e quem administra o sistema exerce as duas — é ele quem
     * demonstra o fluxo inteiro e quem cobre a ausência do outro. O setor
     * `administrador` não precisa de linha própria aqui: `ehAdmin()` já o
     * reconhece, e uma segunda conta do mesmo papel um dia discordaria da
     * primeira.
     *
     * Devolve lista, e não um valor único, porque acumular papéis SOMA — a mesma
     * regra da matriz de permissões, em que quem tem dois setores fica com a
     * união do que cada um concede.
     *
     * @return list<string>
     */
    private static function etapas(?User $usuario): array
    {
        if ($usuario === null) {
            return [];
        }

        if ($usuario->ehAdmin()) {
            return ['triagem', 'direcionamento'];
        }

        $setores = $usuario->setores->pluck('slug')->all();

        $etapas = [];

        if (in_array('administrativo', $setores, true)) {
            $etapas[] = 'triagem';
        }

        if (in_array('gestor', $setores, true)) {
            $etapas[] = 'direcionamento';
        }

        return $etapas;
    }

    /**
     * As áreas que esta pessoa responde como gestora — vazio para quem não é
     * gestor de área nenhuma.
     *
     * ⚠️ PROTÓTIPO: o vínculo mora em `config/prototipo_estrutura.php`, junto da
     * área, e liga pela matrícula. Em produção ele é entre USUÁRIO e área (uma
     * pessoa pode responder por mais de uma, e gestor entra e sai), e isso é
     * tabela — está registrado como pendência no doc de regra. Quem chama aqui já
     * trata LISTA, então a modelagem definitiva não obriga a mexer em quem lê.
     *
     * @return list<string>
     */
    private static function areasDoGestor(?User $usuario): array
    {
        return $usuario === null
            ? []
            : EstruturaFicticia::areasDoGestor($usuario->login);
    }

    /**
     * A listagem desta pessoa é recortada pela área dela?
     *
     * É o gestor, e só ele: quem TRIA precisa ver o universo (não se tria o que
     * não se vê, e quem encaminhou precisa saber o que aconteceu depois), e o
     * administrador é o dono do sistema. Um gestor que também seja administrativo
     * não é recortado — o papel que amplia ganha, a mesma regra da união de
     * setores na matriz de permissões.
     */
    private static function temRecorteDeArea(?User $usuario): bool
    {
        if ($usuario === null || $usuario->ehAdmin()) {
            return false;
        }

        $etapas = self::etapas($usuario);

        return in_array('direcionamento', $etapas, true)
            && ! in_array('triagem', $etapas, true);
    }

    /**
     * Recusa a ação de quem não exerce aquela etapa — dizendo o motivo, e sem
     * tela de erro seca: quem clicou perdeu a seleção, não a explicação.
     *
     * Isto é ETAPA, e não permissão de tela: a permissão (slug `denuncias`) diz
     * quem entra no módulo; a etapa diz qual das duas decisões é sua. As duas
     * conferências existem, e nenhuma substitui a outra.
     */
    private function exigirEtapa(Request $request, string $etapa): ?RedirectResponse
    {
        if (in_array($etapa, self::etapas($request->user()), true)) {
            return null;
        }

        $recado = $etapa === 'triagem'
            ? 'A triagem das denúncias é do setor administrativo. Você acompanha o que foi encaminhado à sua área.'
            : 'O direcionamento é do gestor da área. A triagem encaminha; quem escolhe equipe ou operação é ele.';

        return back()->with('flash.erro', $recado);
    }

    /**
     * Recusa a ação do gestor sobre denúncia que NÃO é da área dele.
     *
     * Existe porque esconder da listagem não é fronteira: a lista do gestor já vem
     * recortada, mas quem souber montar a requisição alcançaria a denúncia de
     * outra área — e o lote é justamente o caminho fácil para isso, porque manda
     * uma lista de identificadores.
     *
     * A conferência é contra a área GRAVADA em cada denúncia e o vínculo do
     * usuário, as duas coisas que o corpo da requisição não controla. O
     * administrador passa: é o dono do sistema. Quem não é gestor de área nenhuma
     * também passa aqui — quem o barra é a guarda de ETAPA, que roda antes.
     *
     * @param  list<int>  $ids
     */
    private function exigirArea(Request $request, array $ids): ?RedirectResponse
    {
        $usuario = $request->user();

        if ($usuario === null || $usuario->ehAdmin()) {
            return null;
        }

        $minhas = self::areasDoGestor($usuario);

        /*
         * Gestor SEM área vinculada não é caso de passar batido: ele exerce a etapa
         * de direcionamento (senão não chegaria aqui) e não tem área para
         * direcionar. Recusar dizendo isso é o que faz alguém corrigir o cadastro —
         * deixar passar daria a ele o sistema inteiro.
         */
        if ($minhas === []) {
            return back()->with(
                'flash.erro',
                'Sua conta não está vinculada a nenhuma área de fiscalização, então não há o que '
                .'direcionar. Procure quem administra o sistema para registrar a sua área.',
            );
        }

        $deFora = [];

        foreach ($ids as $id) {
            $denuncia = DenunciasFicticias::denuncia($id);
            $area = $denuncia === null ? null : ($denuncia['area'] ?? null);

            if (! is_string($area) || ! in_array($area, $minhas, true)) {
                $deFora[] = $denuncia['protocolo'] ?? "#{$id}";
            }
        }

        if ($deFora === []) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'Você responde por '.implode(', ', $minhas).', e '
            .(count($deFora) === 1 ? 'a denúncia ' : 'as denúncias ')
            .implode(', ', $deFora)
            .(count($deFora) === 1 ? ' não é dessa área' : ' não são dessa área')
            .'. Nada foi alterado — recarregue a listagem.',
        );
    }

    /**
     * Alguma das denúncias escolhidas sairia da equipe da própria área?
     *
     * A pergunta é feita contra a área GRAVADA em cada denúncia e a estrutura
     * vigente — as duas coisas que o corpo da requisição não controla.
     *
     * @param  list<int>  $ids
     */
    private function trocaDeEquipe(array $ids, string $equipe): bool
    {
        $equipeDaArea = [];

        foreach (EstruturaFicticia::equipes() as $registro) {
            $equipeDaArea[(string) $registro['area']] = (string) $registro['equipe'];
        }

        foreach ($ids as $id) {
            $denuncia = DenunciasFicticias::denuncia($id);
            $area = $denuncia === null ? null : ($denuncia['area'] ?? null);

            if (! is_string($area) || ! isset($equipeDaArea[$area])) {
                continue;
            }

            if ($equipeDaArea[$area] !== $equipe) {
                return true;
            }
        }

        return false;
    }

    /**
     * O recado depois de uma decisão em lote — dizendo o EFEITO dela, com a
     * conta certa e a concordância certa.
     *
     * "Salvo com sucesso" não conta quantas foram nem para onde. E o caso de
     * NADA ter mudado (a listagem estava velha, alguém decidiu antes) é aviso, e
     * não sucesso: fingir que deu certo esconderia justamente o que a pessoa
     * precisa saber para recarregar a tela.
     *
     * @param  array{alteradas: int, ignoradas: int, resumo: array<string, int>}  $efeito
     * @return array{0: string, 1: string}
     */
    private function recado(array $efeito, string $singular, string $plural): array
    {
        if ($efeito['alteradas'] === 0) {
            return [
                'flash.erro',
                'Nenhuma das denúncias escolhidas está mais disponível. Recarregue a listagem.',
            ];
        }

        $quantas = $efeito['alteradas'];
        $frase = $quantas === 1
            ? "1 denúncia {$singular}."
            : "{$quantas} denúncias {$plural}.";

        if ($efeito['resumo'] !== [] && count($efeito['resumo']) > 1) {
            $partes = [];

            foreach ($efeito['resumo'] as $destino => $total) {
                $partes[] = "{$destino}: {$total}";
            }

            $frase .= ' ('.implode(' · ', $partes).')';
        }

        if ($efeito['ignoradas'] > 0) {
            $frase .= " {$efeito['ignoradas']} não foram encontradas e ficaram como estavam.";
        }

        return ['flash.sucesso', $frase];
    }
}
