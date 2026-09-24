<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Fiscalizacao;
use App\Models\Operacao;
use App\Models\User;
use App\Rules\NomeDeCadastro;
use App\Support\Apresentacao\DemandaParaTela;
use App\Support\Apresentacao\OperacaoParaTela;
use App\Support\CiclosDeFiscalizacao;
use App\Support\Estrutura;
use App\Support\ListagensDaRetaguarda;
use App\Support\Papel;
use App\Support\Protocolo;
use App\Support\Prototipo\RecomendacoesDoFiscal;
use App\Support\TriagemDeDemandas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Denúncias por canal.
 *
 * Duas telas, uma por canal (`e-Salvador` e `Fala Salvador`), com a MESMA
 * mecânica: o Chefe de Setor ENCAMINHA a denúncia a uma equipe (e, portanto, ao
 * líder dela) ou a devolve; o líder de equipe DIRECIONA aos fiscais ou anexa a
 * uma operação. O que muda entre elas é a origem e o que o formato do canal
 * carrega — o e-Salvador vem com requerente identificado e anexos; o outro
 * canal pode ser anônimo e traz a transcrição do atendimento, às vezes sem
 * número nem ponto de referência.
 *
 * Até 22/09/2026 a primeira etapa era do coordenador, que escolhia uma ÁREA. Os
 * coordenadores trabalham no e-Salvador e não entram aqui; o chefe passou a ser
 * um só, e escolhe a equipe.
 *
 * ── Por que UM controller e UMA tela para os dois canais ─────────────────────
 *
 * Porque a REGRA é a mesma: as duas etapas, os estados, o trâmite, a derivação
 * bairro → área e a exigência de justificativa não mudam com o canal. Dois
 * controllers seriam a mesma regra com dois donos, e um dia só um deles
 * receberia a validação nova. O que varia é declarado em
 * `config/demandas.php` → `canais`, e a tela lê de lá.
 *
 * ── Nada é digitado aqui ────────────────────────────────────────────────────
 *
 * Não há rota de inclusão, e isso é deliberado: estas telas não são a Caixa de
 * Entrada (onde o Chefe de Setor digita o papel que chegou ao balcão). A
 * denúncia entra pela integração, e cada uma mostra o carimbo de quando o canal
 * a entregou e sob que número. Um botão "cadastrar denúncia" aqui apagaria
 * justamente a distinção que o módulo existe para deixar clara.
 *
 * ── As duas etapas têm dois donos, e a tela obedece ─────────────────────────
 *
 * A permissão de ABRIR a tela é uma só (slug `denuncias`, no Modo Gerente). O
 * que separa os papéis é a ETAPA, derivada do setor de quem entrou: o CHEFE DE
 * SETOR encaminha, o LÍDER DE EQUIPE direciona, e o administrador do sistema
 * exerce as duas — é ele que demonstra o fluxo inteiro e que cobre a ausência
 * do outro.
 * A conferência acontece AQUI, no servidor, e não só na tela: esconder o botão é
 * conforto, nunca fronteira.
 *
 * ── E o Chefe de Setor responde por uma ÁREA ────────────────────────────────
 *
 * "Pra ele só interessa o que for direcionado para a área dele" (decisão do dono,
 * 02/09/2026). Então o Chefe de Setor tem RECORTE: a listagem dele traz só as denúncias
 * da área que ele responde, e a ação sobre denúncia de outra área é RECUSADA com
 * o motivo escrito. As duas coisas, e não uma: esconder da lista sem barrar a
 * ação deixaria a fronteira valendo apenas para quem não sabe mandar a
 * requisição.
 *
 * O administrador continua vendo tudo — é o dono do sistema. O Chefe de Setor
 * também, porque quem encaminha precisa saber o que aconteceu com o que mandou.
 *
 * ── O que mudou ao sair do protótipo ───────────────────────────────────────
 *
 * As denúncias são linhas de `demandas` — a MESMA tabela da Caixa de Entrada,
 * separada pela coluna `entrada` (ver o cabeçalho do model). Cada decisão vira
 * passo de trâmite gravado, e não mais um item de sessão que sumia no logout.
 *
 * Não há mais "reiniciar": denúncia recebida não se desfaz.
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

    /**
     * As ABAS de cada caixa (dono, 24/09/2026). A chave diz a fonte: `denuncias`
     * e `licencas` são as abertas do canal; `respondidas`, as que fecharam.
     * A caixa das Avulsas não tem abas — é uma lista só.
     */
    private const ABAS = [
        Demanda::CANAL_E_SALVADOR => ['denuncias', 'licencas', 'respondidas'],
        Demanda::CANAL_FALA_SALVADOR => ['denuncias', 'respondidas'],
        Demanda::CANAL_E_PROTOCOLO => ['denuncias', 'respondidas'],
        Demanda::CANAL_AVULSA => [],
    ];

    public function eSalvador(Request $request): Response
    {
        return $this->tela($request, Demanda::CANAL_E_SALVADOR, 'ESalvador');
    }

    public function falaSalvador(Request $request): Response
    {
        return $this->tela($request, Demanda::CANAL_FALA_SALVADOR, 'FalaSalvador');
    }

    public function eProtocolo(Request $request): Response
    {
        return $this->tela($request, Demanda::CANAL_E_PROTOCOLO, 'EProtocolo');
    }

    public function avulsas(Request $request): Response
    {
        return $this->tela($request, Demanda::CANAL_AVULSA, 'Avulsas');
    }

    /**
     * CADASTRO manual de uma demanda, pelo canal.
     *
     * Quem digita depende do canal (`registro` em `config/demandas.php`): o
     * Fala Salvador é do LÍDER — só ele acessa o canal, e o caso nasce na mesa
     * dele; os demais são do CHEFE — o papel do e-Salvador enquanto a integração
     * não lê, a licença, o atendimento presencial do e-Protocolo e a avulsa —, e
     * nascem `Recebida`, esperando o encaminhamento dele.
     */
    public function registrar(Request $request, string $canal): RedirectResponse
    {
        $configuracao = (array) config("demandas.canais.{$canal}", []);

        if (($configuracao['registro'] ?? null) === 'lider') {
            return $this->registrarFalaSalvador($request);
        }

        $usuario = $request->user();

        if (! Papel::ehChefe($usuario) && ! ($usuario?->ehAdmin() ?? false)) {
            return back()->with(
                'flash.erro',
                'O cadastro deste canal é do Chefe de Setor: é a ele que a demanda chega. '
                .'O líder recebe o caso já encaminhado.',
            );
        }

        $avulsa = $canal === Demanda::CANAL_AVULSA;
        $admiteAnonima = (bool) ($configuracao['admite_anonima'] ?? false);

        $dados = $request->validate([
            // A avulsa pode não ter número nenhum: foi uma ligação.
            'documento_origem' => [
                $avulsa ? 'nullable' : 'required', 'string', 'max:40',
                Rule::unique('demandas', 'numero_origem')->where('canal', $canal),
            ],
            'recebida_em' => ['required', 'date', 'before_or_equal:today'],
            // `declined` aceita false/0: o canal que não admite anônima exige quem pediu.
            'anonima' => array_values(array_filter(['required', 'boolean', $admiteAnonima ? null : 'declined'])),
            'requerente' => ['exclude_if:anonima,true', 'required', 'string', 'max:150', new NomeDeCadastro],
            'contato' => ['exclude_if:anonima,true', 'nullable', 'string', 'max:80'],
            'assunto' => ['required', 'string', 'max:180'],
            'endereco' => ['required', 'string', 'max:200'],
            'bairro' => ['required', 'string', 'max:80'],
            'descricao' => ['nullable', 'string', 'max:2000'],
        ], [
            'documento_origem.required' => 'Informe o número do documento no canal de origem.',
            'documento_origem.unique' => 'Já existe uma demanda deste canal com esse número — ela não entra duas vezes.',
            'anonima.declined' => 'Este canal não recebe demanda anônima: informe quem pediu.',
            'requerente.required' => $avulsa ? 'Informe quem pediu a ação.' : 'Informe o requerente — ou marque como anônima.',
            'assunto.required' => 'Descreva em uma linha o que foi pedido.',
            'endereco.required' => 'Informe onde é: sem endereço não há a quem mandar.',
            'bairro.required' => 'Informe o bairro: é ele que sugere a equipe.',
        ]);

        $recebida = Date::parse((string) $dados['recebida_em']);
        $protocolo = Protocolo::proximo('DEM', modelClass: Demanda::class);
        $nome = (string) ($configuracao['nome'] ?? $canal);

        $demanda = Demanda::create([
            'protocolo' => $protocolo,
            'canal' => $canal,
            'entrada' => Demanda::ENTRADA_BALCAO,
            // Sem número (a ligação da avulsa), o próprio protocolo ocupa o lugar:
            // a unicidade é por canal e número, e dois vazios colidiriam no Oracle.
            'numero_origem' => $dados['documento_origem'] ?? $protocolo,
            'recebida_em' => $recebida,
            'prazo_em' => $recebida->copy()->addDays((int) config('demandas.prazo_padrao_em_dias', 10)),
            'anonima' => (bool) $dados['anonima'],
            'requerente' => $dados['requerente'] ?? null,
            'telefone' => $dados['contato'] ?? null,
            'assunto' => $dados['assunto'],
            'relato' => $dados['descricao'] ?? null,
            'logradouro' => $dados['endereco'],
            'bairro' => $dados['bairro'],
            'situacao' => Demanda::RECEBIDA,
            'area_id' => Area::sugeridaParaBairro((string) $dados['bairro'])?->id,
            'criada_por_id' => Auth::id(),
        ]);

        $demanda->tramites()->create([
            'ordem' => 1,
            'ocorrida_em' => $recebida,
            'user_id' => Auth::id(),
            'papel' => DemandaTramite::PAPEL_CHEFE_DE_SETOR,
            'autor' => Auth::user()?->name,
            'acao' => 'Demanda cadastrada',
            'detalhe' => "Registrada pelo Chefe de Setor, com origem {$nome}.",
            'situacao' => Demanda::RECEBIDA,
            'campos' => array_filter([
                'Origem do documento' => $nome,
                'Número na origem' => $dados['documento_origem'] ?? null,
            ]),
        ]);

        return back()->with(
            'flash.sucesso',
            "Demanda {$demanda->protocolo} registrada — {$nome}. Encaminhe à equipe quando for a hora.",
        );
    }

    /**
     * REGISTRO do Fala Salvador — o líder digita o que recebeu por telefone.
     *
     * O canal não tem API e só os líderes o acessam (decisão do dono,
     * 22/09/2026); o SEFAL é intermediário de registro. Por isso a demanda não
     * passa pela Caixa nem pelo chefe: nasce **na mesa do próprio líder**
     * (`Encaminhada ao líder`), com a equipe dele, e segue o fluxo normal —
     * direcionar aos fiscais, receber o retorno. Responder ao cidadão continua
     * sendo no Fala Salvador.
     *
     * Quem lidera mais de uma equipe escolhe; quem lidera uma não precisa dizer.
     */
    private function registrarFalaSalvador(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        if (! Papel::ehLider($usuario) && ! ($usuario?->ehAdmin() ?? false)) {
            return back()->with(
                'flash.erro',
                'Registrar o Fala Salvador é do líder de equipe: é ele que atende o canal. '
                .'O Chefe de Setor registra o que chega a ele na Caixa de Entrada.',
            );
        }

        $minhas = Papel::equipes($usuario);
        $canal = (array) config('demandas.canais.'.Demanda::CANAL_FALA_SALVADOR, []);

        $dados = $request->validate([
            'documento_origem' => ['required', 'string', 'max:40'],
            'recebida_em' => ['required', 'date'],
            'anonima' => ['required', 'boolean'],
            'requerente' => ['exclude_if:anonima,true', 'required', 'string', 'max:150', new NomeDeCadastro],
            'contato' => ['exclude_if:anonima,true', 'nullable', 'string', 'max:80'],
            'assunto' => ['required', 'string', 'max:180'],
            'endereco' => ['required', 'string', 'max:200'],
            'bairro' => ['required', 'string', 'max:80'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            // Uma equipe só: fica implícita. Mais de uma (ou administrador): escolhe.
            'equipe' => [
                count($minhas) === 1 ? 'nullable' : 'required',
                Rule::in(count($minhas) > 0 ? $minhas : Estrutura::codigosDeEquipe()),
            ],
        ], [
            'documento_origem.required' => 'Informe o número do atendimento no Fala Salvador.',
            'requerente.required' => 'Informe quem ligou — ou marque a denúncia como anônima.',
            'assunto.required' => 'Descreva em uma linha o que foi relatado.',
            'endereco.required' => 'Informe onde é: sem endereço não há a quem mandar.',
            'bairro.required' => 'Informe o bairro.',
            'equipe.required' => 'Escolha para qual das suas equipes é este caso.',
            'equipe.in' => 'Essa equipe não é sua. O líder registra para a equipe que lidera.',
        ]);

        $equipe = Estrutura::equipeModel((string) ($dados['equipe'] ?? $minhas[0] ?? ''));

        if ($equipe === null) {
            return back()->with('flash.erro', 'Sua conta não está ligada a nenhuma equipe — procure quem administra o sistema.');
        }

        $recebida = Date::parse((string) $dados['recebida_em']);

        $demanda = Demanda::create([
            'protocolo' => Protocolo::proximo('DEM', modelClass: Demanda::class),
            'canal' => Demanda::CANAL_FALA_SALVADOR,
            'entrada' => Demanda::ENTRADA_BALCAO,
            'numero_origem' => $dados['documento_origem'],
            'recebida_em' => $recebida,
            'prazo_em' => $recebida->copy()->addDays((int) config('demandas.prazo_padrao_em_dias', 10)),
            'anonima' => (bool) $dados['anonima'],
            'requerente' => $dados['requerente'] ?? null,
            'telefone' => $dados['contato'] ?? null,
            'assunto' => $dados['assunto'],
            'relato' => $dados['descricao'] ?? null,
            'logradouro' => $dados['endereco'],
            'bairro' => $dados['bairro'],
            // Já na mesa do líder: é ele quem registrou, e é dele o próximo passo.
            'situacao' => Demanda::ENCAMINHADA_AO_LIDER,
            'equipe_id' => $equipe->id,
            'area_id' => $equipe->area_id,
            'criada_por_id' => Auth::id(),
        ]);

        $demanda->tramites()->create([
            'ordem' => 1,
            'ocorrida_em' => $recebida,
            'user_id' => Auth::id(),
            'papel' => DemandaTramite::PAPEL_LIDER,
            'autor' => Auth::user()?->name,
            'acao' => 'Registrada pelo líder da equipe',
            'detalhe' => 'Recebida no '.((string) ($canal['nome'] ?? 'Fala Salvador'))
                .' e registrada aqui pelo líder da Equipe '.$equipe->codigo.', para direcionar aos fiscais.',
            'situacao' => Demanda::ENCAMINHADA_AO_LIDER,
            'campos' => [
                'Origem do documento' => (string) ($canal['nome'] ?? 'Fala Salvador'),
                'Número na origem' => (string) $dados['documento_origem'],
                'Equipe' => $equipe->codigo,
            ],
        ]);

        // Já na mesa do líder: a FISCALIZAÇÃO nasce junto, para ele enviar à equipe.
        CiclosDeFiscalizacao::abrirParaDemanda($demanda, $usuario);

        return back()->with(
            'flash.sucesso',
            "Demanda {$demanda->protocolo} registrada na sua mesa (Equipe {$equipe->codigo}). "
            .'Direcione aos fiscais quando for a hora — a resposta ao cidadão continua no Fala Salvador.',
        );
    }

    /**
     * ENCAMINHAMENTO — o Chefe de Setor manda cada denúncia à equipe que
     * confirmou, e quem recebe é o líder dela.
     *
     * O corpo traz `destinos`: uma lista de pares identificador → equipe. Não é
     * um identificador por requisição nem uma equipe para o lote inteiro, porque
     * o encaminhamento real é os dois casos ao mesmo tempo — chegam dez denúncias
     * de bairros diferentes, cada uma com a sua equipe sugerida, e o chefe
     * confirma todas de uma vez.
     */
    public function encaminhar(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirEtapa($request, 'encaminhamento')) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'destinos' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'destinos.*.id' => ['required', 'integer'],
            'destinos.*.equipe' => ['required', Rule::in(Estrutura::codigosDeEquipe())],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [
            'destinos.required' => 'Escolha ao menos uma denúncia para encaminhar.',
            'destinos.*.equipe.required' => 'Confirme a equipe de cada denúncia antes de encaminhar.',
            'destinos.*.equipe.in' => 'A equipe escolhida não existe na estrutura de fiscalização.',
        ]);

        $equipesPorId = [];

        foreach ($dados['destinos'] as $destino) {
            $equipesPorId[(int) $destino['id']] = (string) $destino['equipe'];
        }

        $efeito = $this->triagem($request)->encaminharAoLider($equipesPorId, $dados['observacao'] ?? null);

        return back()->with(...$this->recado(
            $efeito,
            'encaminhada ao líder da equipe, que direciona aos fiscais',
            'encaminhadas aos líderes das equipes, que direcionam aos fiscais',
        ));
    }

    /**
     * ENCAMINHAMENTO — o Chefe de Setor devolve ao canal de origem ou arquiva,
     * com motivo e justificativa.
     *
     * A justificativa é exigida no SERVIDOR, e com tamanho mínimo: devolver é ato
     * administrativo, e "não procede" não conta o caso a quem ler depois.
     * Esconder o campo na tela não impede ninguém de mandar a requisição sem ele.
     */
    public function devolver(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirEtapa($request, 'encaminhamento')) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'motivo' => ['required', Rule::in((array) config('demandas.motivos_de_devolucao', []))],
            'justificativa' => ['required', 'string', 'min:15', 'max:1000'],
            'destino' => ['required', Rule::in((array) config('demandas.destinos_de_retorno', []))],
        ], [
            'ids.required' => 'Escolha ao menos uma denúncia.',
            'motivo.required' => 'Escolha o motivo da devolução.',
            'justificativa.required' => 'Escreva a justificativa: devolver ou arquivar é ato administrativo e precisa do motivo por escrito.',
            'justificativa.min' => 'A justificativa está curta demais para explicar a decisão a quem ler depois.',
            'destino.required' => 'Diga se a denúncia volta ao canal de origem ou é arquivada.',
        ]);

        $efeito = $this->triagem($request)->devolver(
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
     * DIRECIONAMENTO — o líder manda a própria equipe vistoriar.
     *
     * A equipe já está na denúncia (o chefe a escolheu ao encaminhar); o líder
     * não troca de equipe aqui — se ela veio para a equipe errada, o caminho é
     * devolver ao chefe. O que ele acrescenta é a ORIENTAÇÃO aos fiscais.
     */
    public function direcionar(Request $request): RedirectResponse
    {
        if (($recusa = $this->exigirEtapa($request, 'direcionamento')) !== null) {
            return $recusa;
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'orientacao' => ['nullable', 'string', 'max:1000'],
        ], [
            'ids.required' => 'Escolha ao menos uma denúncia.',
        ]);

        $ids = array_map('intval', $dados['ids']);

        if (($recusa = $this->exigirEquipe($request, $ids)) !== null) {
            return $recusa;
        }

        $orientacao = trim((string) ($dados['orientacao'] ?? ''));

        $efeito = $this->triagem($request)->direcionarAosFiscais($ids, $orientacao === '' ? null : $orientacao);

        return back()->with(...$this->recado(
            $efeito,
            'direcionada aos fiscais — aparecerá no aplicativo deles',
            'direcionadas aos fiscais — aparecerão no aplicativo deles',
        ));
    }

    /**
     * DIRECIONAMENTO — o líder (ou o chefe) anexa as denúncias a uma operação.
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

        /*
         * OPERAÇÃO ENCERRADA não recebe denúncia nova, e a recusa vem ANTES da
         * validação para poder dizer o porquê.
         *
         * A tela não a oferece (o catálogo servido são as disponíveis), mas
         * esconder da lista não é fronteira: quem souber montar a requisição
         * mandaria o nome de uma encerrada, e a denúncia entraria num trabalho que
         * ninguém vai mais executar — desaparecendo da fila sem nunca chegar a
         * campo. É a pior falha possível aqui, porque não parece falha nenhuma.
         */
        if (! $nova) {
            $escolhida = Operacao::where('nome', (string) $request->input('operacao'))->first();

            if ($escolhida !== null && ! in_array($escolhida->situacao, Operacao::ABERTAS, true)) {
                /*
                 * Recado por `flash.erro`, e não por erro de campo: esta tela não
                 * renderiza o saco de erros de validação, e uma recusa que não
                 * aparece é exatamente o bloqueio em silêncio que a lei do projeto
                 * proíbe. O aviso flutuante é o caminho único das mensagens aqui.
                 */
                return back()->with(
                    'flash.erro',
                    "A {$escolhida->nome} está encerrada e não recebe denúncia nova. Escolha uma "
                    .'operação em andamento, abra uma nova aqui mesmo, ou direcione a denúncia à '
                    .'equipe.',
                );
            }
        }

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LOTE],
            'ids.*' => ['required', 'integer'],
            'nova' => ['required', 'boolean'],

            /*
             * Operação existente: o nome tem de ser uma das que RECEBEM trabalho
             * novo. A ENCERRADA fica fora da lista — e a recusa dela ganha
             * mensagem própria logo abaixo, porque `Rule::in` diria só "seleção
             * inválida": quem escolheu uma operação que existe na tela de cadastro
             * merece ouvir que ela foi encerrada, não que ela não existe.
             */
            'operacao' => ['exclude_if:nova,true', 'required', Rule::in(Operacao::abertas()->pluck('nome')->all())],

            // Operação nova: o mínimo para ela ser reconhecível depois.
            'nome' => ['exclude_unless:nova,true', 'required', 'string', 'min:5', 'max:120'],
            'area' => ['exclude_unless:nova,true', 'required', Rule::in(Estrutura::nomesDeArea())],
            'equipe' => ['exclude_unless:nova,true', 'required', Rule::in(Estrutura::codigosDeEquipe())],
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

        if (($recusa = $this->exigirEquipe($request, $ids)) !== null) {
            return $recusa;
        }

        /*
         * Nome de operação repetido é recusado: é por ele que a equipe reconhece a
         * operação em rua, e é ele que a denúncia grava na linha. Com duas do mesmo
         * nome, a anexação aponta para qualquer uma das duas e ninguém sabe qual.
         */
        if ($nova && Operacao::where('nome', (string) $dados['nome'])->exists()) {
            return back()->with(
                'flash.erro',
                'Já existe uma operação com esse nome. Escolha outro nome, ou anexe a denúncia à '
                .'operação que já existe.',
            );
        }

        $operacao = $nova
            ? $this->abrirOperacao($dados)
            : Operacao::where('nome', (string) $dados['operacao'])->firstOrFail();

        $efeito = $this->triagem($request)->anexarAOperacao(
            $ids,
            $operacao,
            null,
            Papel::papelDoTramite($request->user(), Papel::recorta($request->user()) ? 'lider' : 'chefe'),
        );

        return back()->with(...$this->recado(
            $efeito,
            "incluída na {$operacao->nome}",
            "incluídas na {$operacao->nome}",
        ));
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
        $configuracao = ['slug' => $canal, ...(array) config("demandas.canais.{$canal}", [])];

        $usuario = $request->user();
        $equipesDoLider = Papel::equipes($usuario);
        $comRecorte = Papel::recorta($usuario);

        return Inertia::render("Retaguarda/Denuncias/{$pagina}", [
            'canal' => $configuracao,
            /*
             * Esta pessoa REGISTRA este canal aqui? Só quando o canal é digitado
             * pelo líder (`registro = lider`) e ela lidera uma equipe — ou é o
             * administrador, que cobre a ausência. O chefe não: o que chega a ele
             * entra pela Caixa.
             */
            'registra' => self::registra($usuario, $canal),
            /*
             * Em que canais o formulário de cadastro desta tela grava. A caixa do
             * e-Salvador cadastra denúncia E licença — a licença chega pelo
             * e-Salvador e mora na aba própria dela.
             */
            'registroEm' => array_values(array_map(
                static fn (string $c): array => [
                    'slug' => $c,
                    'nome' => (string) config("demandas.canais.{$c}.nome"),
                    'admite_anonima' => (bool) config("demandas.canais.{$c}.admite_anonima"),
                    'registro' => config("demandas.canais.{$c}.registro"),
                ],
                array_filter(
                    $canal === Demanda::CANAL_E_SALVADOR
                        ? [Demanda::CANAL_E_SALVADOR, Demanda::CANAL_NOVA_LICENCA]
                        : [$canal],
                    static fn (string $c): bool => self::registra($usuario, $c),
                ),
            )),
            // As abas desta caixa, e a fonte da aba Licenças (só no e-Salvador).
            'abas' => self::ABAS[$canal] ?? ['denuncias', 'respondidas'],
            'licencas' => $canal === Demanda::CANAL_E_SALVADOR
                ? $this->doCanal(Demanda::CANAL_NOVA_LICENCA, $comRecorte ? $equipesDoLider : null)
                : [],
            // Quem RESPONDE ao canal, concluído o trabalho: o chefe (e o administrador).
            'decide' => Papel::ehChefe($usuario) || ($usuario?->ehAdmin() ?? false),
            'bairros' => Estrutura::bairros(),
            'sugestoes' => Estrutura::mapaDeSugestoes(),
            // O líder recebe SÓ o que é da equipe dele — o recorte é feito aqui, e
            // não na tela: filtro de front esconde, não protege, e a lista inteira
            // teria viajado até o navegador de quem não deve vê-la.
            'denuncias' => $this->doCanal($canal, $comRecorte ? $equipesDoLider : null),
            // Os catálogos vêm do SERVIDOR: são os MESMOS que a validação exige.
            // Escritos também na tela, um dia discordariam — e a tela ofereceria
            // uma opção que o servidor recusa.
            'situacoes' => Demanda::SITUACOES,
            // Os desfechos de vistoria — a tela usa para a busca reconhecer
            // "regularizado no local" e "nada encontrado" como faceta. Vem do
            // servidor pela mesma razão dos outros catálogos: escrito na tela,
            // um dia reconheceria um desfecho que já não existe.
            'desfechos' => Fiscalizacao::DESFECHOS,
            // O catálogo de recomendações na redação EXPLÍCITA. O passo do
            // trâmite traz a CHAVE (é ela que o aplicativo do fiscal grava), e
            // quem decide lê a frase inteira: a pílula curta é do celular, onde
            // não cabe frase; aqui "Sugerir retorno da equipe" não diz QUANDO
            // voltar, e "Voltar ao ponto no vencimento do prazo" diz.
            'recomendacoesDoFiscal' => RecomendacoesDoFiscal::explicitos(),
            'motivos' => array_values((array) config('demandas.motivos_de_devolucao', [])),
            'destinos' => array_values((array) config('demandas.destinos_de_retorno', [])),
            'equipes' => Estrutura::equipes(),
            'areas' => Estrutura::nomesDeArea(),
            // Quem lidera cada equipe. É o que o chefe precisa ver ANTES de
            // encaminhar: "vai para a C2" só diz metade; a outra metade é para
            // quem.
            'lideres' => Estrutura::lideresPorEquipe(),
            /*
             * A PRÉ-TRIAGEM não é servida aqui.
             *
             * Ela é a etapa ANTERIOR a esta tela e mora na aba própria da Caixa
             * de Entrada: quando a denúncia chega a esta lista, "isto é o mesmo
             * fato que aquilo?" já foi respondido. Servir as propostas nos dois
             * lugares criaria duas mesas para a mesma decisão.
             */
            'operacoes' => Operacao::abertas()->with(['area', 'equipes', 'bairros'])
                ->orderBy('nome')->get()->map(OperacaoParaTela::completa(...))->all(),
            // A etapa de quem entrou — é ela que decide o que a tela oferece, e a
            // mesma resposta governa a recusa no servidor.
            'etapas' => self::etapas($usuario),
            // As equipes que esta pessoa lidera, e se a listagem está recortada
            // por elas. A tela usa isso para dizer QUAL é a sua equipe no selo, e
            // para explicar que a lista não é o universo.
            'equipesDoLider' => $equipesDoLider,
            'recorteDeEquipe' => $comRecorte,
            // As COLUNAS de cada aba — da grade e do arquivo. Uma listagem por
            // aba porque a aba é uma ETAPA do fluxo, e cada etapa se decide
            // olhando um dado diferente. Ver docs/padroes/listagem-clean.md.
            // UMA grade para todas as abas (dono, 24/09/2026): protocolo, recebida,
            // bairro, situação e prazo — com a situação em três palavras.
            'listagens' => ListagensDaRetaguarda::para(['denuncias.todas']),
        ]);
    }

    /**
     * Esta pessoa CADASTRA neste canal? O líder, no canal que é dele (o Fala
     * Salvador); o chefe, nos demais; o administrador, em todos.
     */
    private static function registra(?User $usuario, string $canal): bool
    {
        if ($usuario?->ehAdmin() ?? false) {
            return true;
        }

        return config("demandas.canais.{$canal}.registro") === 'lider'
            ? Papel::ehLider($usuario)
            : Papel::ehChefe($usuario);
    }

    /**
     * As etapas do fluxo que esta pessoa exerce.
     *
     * O papel vem do SETOR, não de uma coluna nova: `chefe-de-setor` encaminha,
     * `lider-de-equipe` direciona, e quem administra o sistema exerce as duas — é
     * ele quem demonstra o fluxo inteiro e quem cobre a ausência do outro. O setor
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
            return ['encaminhamento', 'direcionamento'];
        }

        $setores = $usuario->setores->pluck('slug')->all();

        $etapas = [];

        if (in_array(Papel::CHEFE, $setores, true)) {
            $etapas[] = 'encaminhamento';
        }

        if (in_array(Papel::LIDER, $setores, true)) {
            $etapas[] = 'direcionamento';
        }

        return $etapas;
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

        $recado = $etapa === 'encaminhamento'
            ? 'Encaminhar é do Chefe de Setor. Você acompanha o que foi encaminhado à sua equipe e direciona aos fiscais.'
            : 'O direcionamento é do líder da equipe. O chefe encaminha; quem manda os fiscais ao ponto é ele.';

        return back()->with('flash.erro', $recado);
    }

    /**
     * Recusa a ação do líder sobre denúncia que NÃO é da equipe dele.
     *
     * Existe porque esconder da listagem não é fronteira: a lista do líder já vem
     * recortada, mas quem souber montar a requisição alcançaria a denúncia de
     * outra equipe — e o lote é justamente o caminho fácil para isso, porque
     * manda uma lista de identificadores.
     *
     * A conferência é contra a EQUIPE GRAVADA em cada denúncia e o vínculo do
     * usuário, as duas coisas que o corpo da requisição não controla. O
     * administrador e o Chefe de Setor passam: respondem por tudo.
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
         * etapa de direcionamento (senão não chegaria aqui) e não tem equipe para
         * direcionar. Recusar dizendo isso é o que faz alguém corrigir o cadastro
         * — deixar passar daria a ele o sistema inteiro.
         */
        if ($minhas === []) {
            return back()->with(
                'flash.erro',
                'Sua conta não está vinculada a nenhuma equipe, então não há o que direcionar. '
                .'Procure quem administra o sistema para registrar a sua equipe.',
            );
        }

        $deFora = [];

        foreach ($ids as $id) {
            $denuncia = Demanda::with('equipe')->find($id);
            $equipe = $denuncia?->equipe?->codigo;

            if (! is_string($equipe) || ! in_array($equipe, $minhas, true)) {
                $deFora[] = $denuncia->protocolo ?? "#{$id}";
            }
        }

        if ($deFora === []) {
            return null;
        }

        return back()->with(
            'flash.erro',
            'Você lidera '.(count($minhas) === 1 ? 'a Equipe ' : 'as Equipes ').implode(', ', $minhas).', e '
            .(count($deFora) === 1 ? 'a denúncia ' : 'as denúncias ')
            .implode(', ', $deFora)
            .(count($deFora) === 1 ? ' não é dessa equipe' : ' não são dessa equipe')
            .'. Nada foi alterado — recarregue a listagem.',
        );
    }

    // ── O acesso ao banco ───────────────────────────────────────────────────

    /** O serviço que aplica as decisões em lote, assinando com quem está logado. */
    private function triagem(Request $request): TriagemDeDemandas
    {
        return new TriagemDeDemandas($request->user());
    }

    /**
     * As denúncias de um canal, já na forma que a tela lê.
     *
     * O recorte por equipe é feito AQUI, e não na tela: filtro de front esconde,
     * não protege, e a lista inteira teria viajado até o navegador de quem não
     * deve vê-la. `$equipes` nulo significa "sem recorte" — é o caso do
     * administrador e do Chefe de Setor, que precisam do universo.
     *
     * As AGREGADAS ficam de fora: quem leva o caso a campo é o registro de
     * trabalho, e mostrar as dez faria a fila cobrar dez vezes o mesmo fato. Elas
     * aparecem dentro do registro que as agrupou.
     *
     * @param  list<string>|null  $equipes  códigos
     * @return list<array<string, mixed>>
     */
    private function doCanal(string $canal, ?array $equipes): array
    {
        $consulta = Demanda::where('canal', $canal)
            ->deTrabalho()
            ->with([
                'tramites.fiscalizacao.recomendacoes',
                'tramites.fiscalizacao.fotos',
                'tramites.fiscalizacao.documento',
                'anexos', 'area', 'equipe', 'operacao', 'agregadas',
                'ciclos.equipe', 'ciclos.vistorias.fotos', 'ciclos.vistorias.documento',
            ])
            ->orderByDesc('recebida_em')
            ->orderByDesc('id');

        if ($equipes !== null) {
            /*
             * Sem equipe ainda (recém-recebida) não é da equipe de ninguém — e
             * some da lista do líder por isso, não por engano: ela está na mesa
             * do Chefe de Setor, esperando encaminhamento.
             */
            $consulta->whereHas('equipe', static fn ($q) => $q->whereIn('codigo', $equipes));
        }

        return $consulta->get()->map(DemandaParaTela::completa(...))->all();
    }

    /**
     * Abre a operação que o Chefe de Setor criou no próprio direcionamento.
     *
     * É o caso de não haver trabalho planejado para aquela região ainda. Nasce
     * `Em andamento` porque começa hoje — e a situação é recalculada pela regra
     * do período como qualquer outra, nunca digitada.
     *
     * @param  array<string, mixed>  $dados
     */
    private function abrirOperacao(array $dados): Operacao
    {
        $area = Area::where('nome', (string) $dados['area'])->firstOrFail();
        $equipe = Estrutura::equipeModel((string) $dados['equipe']);

        $operacao = Operacao::create([
            'codigo' => Protocolo::proximo('OP', modelClass: Operacao::class, coluna: 'codigo'),
            'nome' => (string) $dados['nome'],
            'area_id' => $area->id,
            // Quem abriu a operação é quem responde por ela — o vínculo por área
            // deixou de existir com o chefe único.
            'coordenador_id' => Auth::id(),
            'regiao' => $area->regiao,
            'foco' => $dados['foco'] ?? null,
            'inicio' => Date::now()->startOfDay(),
            // Sem data de encerramento: quem a abriu no direcionamento não sabe
            // quando ela acaba, e inventar um fim faria a tela mostrar prazo onde
            // não há.
            'fim' => null,
            'criada_por_id' => Auth::id(),
        ]);

        $operacao->situacao = $operacao->situacaoPeloPeriodo();
        $operacao->save();

        if ($equipe !== null) {
            $operacao->equipes()->attach($equipe->id);
        }

        return $operacao->load(['area', 'equipes', 'bairros']);
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
