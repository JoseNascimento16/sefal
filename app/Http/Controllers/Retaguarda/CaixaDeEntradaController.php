<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\SugestaoAgrupamento;
use App\Rules\NomeDeCadastro;
use App\Support\Apresentacao\DemandaParaTela;
use App\Support\Apresentacao\SugestaoParaTela;
use App\Support\CiclosDeFiscalizacao;
use App\Support\Estrutura;
use App\Support\ListagensDaRetaguarda;
use App\Support\Papel;
use App\Support\Protocolo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caixa de Entrada — a mesa do Chefe de Setor.
 *
 * É a porta por onde a demanda entra QUANDO CHEGA FORA DA INTEGRAÇÃO: o papel
 * do e-Salvador, o ofício, o pedido de licença e a AVULSA — a ligação ou o
 * e-mail de um superior pedindo uma ação. O Chefe de Setor digita, decide e
 * encaminha. O cadastro manual é requisito, não gambiarra — quando a API ler,
 * ele continua existindo, e é a coluna `entrada` que separa um do outro. O Fala
 * Salvador NÃO entra por aqui: é digitado pelo líder, na tela do canal.
 *
 * ── As duas decisões que a tela existe para tomar ───────────────────────────
 *
 *   1. **Registrar e encaminhar** — a demanda ganha destino. O bairro SUGERE a
 *      área; quem confirma é o Chefe de Setor, porque um bairro de divisa pertence
 *      a duas áreas e as duas respostas estão certas.
 *   2. **Registrar e devolver/arquivar** — com MOTIVO e JUSTIFICATIVA
 *      obrigatórios. É ato administrativo: quem, quando, por quê. A validação
 *      está aqui, e não só no formulário — esconder o campo na tela não impede
 *      ninguém de mandar a requisição sem ele.
 *
 * ── O que mudou ao sair do protótipo ────────────────────────────────────────
 *
 * Antes as demandas vinham de `config/prototipo_caixa_entrada.php` e a decisão
 * ficava na SESSÃO — sumia no logout. Agora são linhas de `demandas`, a mesma
 * tabela das que chegam por integração (ver o cabeçalho do model), e cada
 * decisão vira passo de trâmite gravado. Consequências práticas:
 *
 *  - a situação passou a ser a do fluxo completo: o que a tela chamava de
 *    "Aguardando triagem" é `Recebida`, e "Encaminhada" virou duas —
 *    `Encaminhada ao líder` (o Chefe de Setor escolheu a equipe; está na mesa
 *    do líder dela) e `Direcionada aos fiscais` (o líder mandou a equipe ao
 *    ponto). São dois estados diferentes do mundo, e um nome só para os dois
 *    escondia de quem cobra em que mesa o caso está;
 *  - **não há mais "reiniciar"**: caixa de entrada não se reinicia.
 *
 * A guarda de acesso deduz a tela do primeiro trecho do caminho
 * (`/retaguarda/caixa-de-entrada/…`), então as rotas nascem protegidas sem
 * ninguém declarar nada.
 */
class CaixaDeEntradaController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        $comTudo = [
            'tramites.fiscalizacao.recomendacoes',
            'tramites.fiscalizacao.fotos',
            'tramites.fiscalizacao.documento',
            'anexos', 'area', 'equipe', 'operacao', 'agregadas',
        ];

        /*
         * A CAIXA — o que já é caso entendido.
         *
         * Inclui o que o Chefe de Setor digitou (que nunca esteve em pré-triagem) e
         * o que veio por integração e já foi liberado. A partir daqui a origem
         * não muda a decisão: as duas passam pelo mesmo crivo de encaminhar ou
         * devolver, e separá-las em duas filas faria a mesma escolha ser tomada
         * em dois lugares — com duas regras, um dia.
         */
        $demandas = Demanda::triadas()
            ->deTrabalho()
            ->with($comTudo)
            ->orderByDesc('recebida_em')
            ->orderByDesc('id')
            ->get()
            ->map(DemandaParaTela::completa(...))
            ->all();

        /*
         * A PRÉ-TRIAGEM — a leva crua do e-Salvador, antes de ser entendida.
         *
         * Vem em ordem CRESCENTE, ao contrário da Caixa: aqui a mais antiga é a
         * que tende a ser a principal de um grupo (a varredura agrega na mais
         * velha), e o Chefe de Setor lê na ordem em que os fatos chegaram.
         */
        $preTriagem = Demanda::emPreTriagem()
            ->deTrabalho()
            ->with($comTudo)
            ->when(
                /*
                 * O MESMO recorte que a varredura usa.
                 *
                 * O Chefe de Setor vê a fila da área dele; a varredura já era
                 * recortada assim, e a fila não era — o que fazia a tela se
                 * contradizer na mesma dobra: "7 denúncias aguardam pré-triagem"
                 * logo acima de "nenhuma repetição entre as denúncias abertas".
                 * Quem lê não tem como saber se a funcionalidade quebrou ou se a
                 * área dele simplesmente não tem repetição.
                 */
                Papel::recorta($usuario),
                fn ($consulta) => $consulta->whereHas(
                    'equipe',
                    fn ($q) => $q->whereIn('codigo', Papel::equipes($usuario)),
                ),
            )
            ->orderBy('recebida_em')
            ->orderBy('id')
            ->get()
            ->map(DemandaParaTela::completa(...))
            ->all();

        return Inertia::render('Retaguarda/Fiscalizacao/CaixaDeEntrada', [
            'demandas' => $demandas,
            'preTriagem' => $preTriagem,
            // Os catálogos vêm do SERVIDOR: são os MESMOS que a validação exige.
            // Escritos também na tela, um dia discordariam — e a tela ofereceria
            // uma opção que o servidor recusa.
            'origens' => $this->origens(),
            'situacoes' => Demanda::SITUACOES,
            'motivos' => array_values((array) config('demandas.motivos_de_devolucao', [])),
            'destinos' => array_values((array) config('demandas.destinos_de_retorno', [])),
            'prazoPadraoEmDias' => (int) config('demandas.prazo_padrao_em_dias', 10),
            // Quem RESPONDE ao canal (a avulsa vira processo no e-Salvador): o chefe e o administrador.
            'decide' => Papel::ehChefe($usuario) || ($usuario?->ehAdmin() ?? false),
            'equipes' => Estrutura::equipes(),
            'areas' => Estrutura::nomesDeArea(),
            'bairros' => Estrutura::bairros(),
            // O mapa bairro → sugestão vai inteiro para a tela: é ele que faz a
            // sugestão aparecer no instante em que a pessoa escolhe o bairro,
            // sem uma ida ao servidor por tecla digitada.
            'sugestoes' => Estrutura::mapaDeSugestoes(),
            /*
             * A PRÉ-TRIAGEM: as propostas de agrupamento que esperam decisão.
             * Vêm com os dois lados inteiros porque aceitar junta casos de
             * cidadãos diferentes — ninguém deve decidir isso lendo dois
             * protocolos e um número de confiança.
             */
            'sugestoesDeAgrupamento' => SugestaoAgrupamento::pendentes()
                ->with(['demanda', 'principal'])
                ->get()
                ->map(SugestaoParaTela::completa(...))
                ->all(),
            // As COLUNAS da grade e as do arquivo — APRESENTAÇÃO, e só ela.
            // Ver docs/padroes/listagem-clean.md.
            'listagens' => ListagensDaRetaguarda::para('caixa-de-entrada'),
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
        $destino = (string) $request->input('destino');

        $dados = $request->validate([
            'destino' => ['required', Rule::in(['encaminhar', 'devolver'])],

            'origem' => ['required', Rule::in($this->origens())],
            'documento_origem' => ['required', 'string', 'max:40'],
            'recebida_em' => ['required', 'date'],
            'prazo' => ['nullable', 'date', 'after_or_equal:recebida_em'],

            // Denúncia PODE ser anônima — é a realidade do que chega por
            // telefone. Quando não é, o nome passa a ser obrigatório:
            // "anônima" tem de ser escolha explícita, nunca campo esquecido.
            'anonima' => ['required', 'boolean'],
            'requerente' => ['exclude_if:anonima,true', 'required', 'string', 'max:150', new NomeDeCadastro],
            'contato' => ['exclude_if:anonima,true', 'nullable', 'string', 'max:80'],

            'assunto' => ['required', 'string', 'max:180'],
            'endereco' => ['required', 'string', 'max:200'],
            // O bairro é o que SUGERE a área, então ele é obrigatório mesmo
            // quando o endereço vem incompleto: sem ele não há a quem encaminhar.
            'bairro' => ['required', 'string', 'max:80'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'anexo' => ['nullable', 'string', 'max:120'],

            /*
             * Encaminhar exige DESTINO, e ele pode ser a área (o caso normal: o
             * Chefe de Setor é quem escolhe a equipe) ou já a equipe, quando o
             * Chefe de Setor sabe qual é. `exclude_unless` tira os campos da conta
             * no caminho em que não fazem sentido, em vez de recusar um
             * formulário que está correto.
             */
            // O destino é a EQUIPE: o Chefe de Setor escolhe, e quem recebe é o
            // líder dela. A área não é mais escolha — é o que a equipe carrega.
            'equipe' => ['exclude_unless:destino,encaminhar', 'required', Rule::in(Estrutura::codigosDeEquipe())],
            'observacao' => ['exclude_unless:destino,encaminhar', 'nullable', 'string', 'max:500'],

            'motivo' => ['exclude_unless:destino,devolver', 'required', Rule::in((array) config('demandas.motivos_de_devolucao', []))],
            'justificativa' => ['exclude_unless:destino,devolver', 'required', 'string', 'min:15', 'max:1000'],
            'destino_retorno' => ['exclude_unless:destino,devolver', 'required', Rule::in((array) config('demandas.destinos_de_retorno', []))],
        ], [
            'origem.required' => 'Informe por onde a demanda chegou.',
            'documento_origem.required' => 'Informe o número do documento de origem.',
            'requerente.required' => 'Informe o nome do requerente — ou marque a demanda como anônima.',
            'assunto.required' => 'Escreva o assunto em uma linha.',
            'bairro.required' => 'Informe o bairro: é ele que define a área responsável.',
            'motivo.required' => 'Escolha o motivo da devolução.',
            // A recusa diz o PORQUÊ do tamanho mínimo: devolver é ato
            // administrativo, e "não procede" não conta o caso a quem ler depois.
            'justificativa.required' => 'Escreva a justificativa: devolver ou arquivar é ato administrativo e precisa do motivo por escrito.',
            'justificativa.min' => 'A justificativa está curta demais para explicar a decisão a quem ler depois.',
            'destino_retorno.required' => 'Diga se a demanda volta ao remetente ou é arquivada.',
        ]);

        if ($destino === 'encaminhar' && ($dados['equipe'] ?? null) === null) {
            // A recusa diz o que fazer, e não só que faltou algo.
            return back()
                ->withInput()
                ->withErrors(['equipe' => 'Escolha a equipe que vai receber a demanda — o líder dela é quem direciona aos fiscais.']);
        }

        $demanda = $this->criar($dados);

        $destino === 'encaminhar'
            ? $this->encaminharDemanda($demanda, $dados['equipe'], $dados['observacao'] ?? null)
            : $this->devolverDemanda($demanda, $dados['motivo'], $dados['justificativa'], $dados['destino_retorno']);

        return redirect()
            ->route('retaguarda.caixa-de-entrada.index')
            ->with('flash.sucesso', $this->recado($demanda));
    }

    /**
     * Encaminhamento de uma demanda que já está na caixa: o Chefe de Setor
     * escolhe a EQUIPE, e quem recebe é o líder dela.
     *
     * Serve tanto para a que estava esperando quanto para reabrir uma que havia
     * sido devolvida — o caso real de o remetente complementar o endereço que
     * faltava.
     */
    public function encaminhar(Request $request, Demanda $demanda): RedirectResponse
    {
        $dados = $request->validate([
            'equipe' => ['required', Rule::in(Estrutura::codigosDeEquipe())],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [
            'equipe.required' => 'Escolha a equipe que vai receber a demanda — o líder dela é quem direciona aos fiscais.',
        ]);

        $this->encaminharDemanda($demanda, $dados['equipe'], $dados['observacao'] ?? null);

        return back()->with('flash.sucesso', $this->recado($demanda));
    }

    /** Devolve ao remetente ou arquiva — com motivo e justificativa. */
    public function devolver(Request $request, Demanda $demanda): RedirectResponse
    {
        $dados = $request->validate([
            'motivo' => ['required', Rule::in((array) config('demandas.motivos_de_devolucao', []))],
            'justificativa' => ['required', 'string', 'min:15', 'max:1000'],
            'destino_retorno' => ['required', Rule::in((array) config('demandas.destinos_de_retorno', []))],
        ], [
            'motivo.required' => 'Escolha o motivo da devolução.',
            'justificativa.required' => 'Escreva a justificativa: devolver ou arquivar é ato administrativo e precisa do motivo por escrito.',
            'justificativa.min' => 'A justificativa está curta demais para explicar a decisão a quem ler depois.',
            'destino_retorno.required' => 'Diga se a demanda volta ao remetente ou é arquivada.',
        ]);

        $this->devolverDemanda($demanda, $dados['motivo'], $dados['justificativa'], $dados['destino_retorno']);

        return back()->with('flash.sucesso', $this->recado($demanda));
    }

    // ── O trabalho ──────────────────────────────────────────────────────────

    /**
     * Cria a demanda digitada, já com o passo de recebimento.
     *
     * @param  array<string, mixed>  $dados
     */
    private function criar(array $dados): Demanda
    {
        $recebida = Date::parse((string) $dados['recebida_em']);
        $canal = $this->canalDoRotulo((string) $dados['origem']);

        $demanda = Demanda::create([
            'protocolo' => Protocolo::proximo('DEM', modelClass: Demanda::class),
            'canal' => $canal,
            'entrada' => Demanda::ENTRADA_BALCAO,
            'numero_origem' => $dados['documento_origem'],
            'recebida_em' => $recebida,
            'prazo_em' => isset($dados['prazo']) && $dados['prazo'] !== null
                ? Date::parse((string) $dados['prazo'])
                : $recebida->copy()->addDays((int) config('demandas.prazo_padrao_em_dias', 10)),
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
            'detalhe' => 'Registrada pelo Chefe de Setor, com origem '.$dados['origem'].'.',
            'situacao' => Demanda::RECEBIDA,
            'campos' => [
                'Origem do documento' => (string) $dados['origem'],
                'Número na origem' => (string) $dados['documento_origem'],
            ],
        ]);

        if (isset($dados['anexo']) && $dados['anexo'] !== null && $dados['anexo'] !== '') {
            $demanda->anexos()->create([
                'nome' => (string) $dados['anexo'],
                'caminho' => 'demandas/'.$demanda->id.'/'.$dados['anexo'],
            ]);
        }

        return $demanda;
    }

    /**
     * Encaminha à equipe escolhida — e, portanto, ao líder dela.
     *
     * O que fica registrado é a equipe E o líder: "encaminhei para a C2" só diz
     * metade, a outra metade é para quem. Se a equipe ainda não tem líder com
     * conta, o passo diz isso, e o caso fica visível ao chefe e ao administrador
     * até alguém assumir.
     */
    private function encaminharDemanda(Demanda $demanda, string $codigoDaEquipe, ?string $observacao): void
    {
        $equipe = Estrutura::equipeModel($codigoDaEquipe);

        if ($equipe === null) {
            return;
        }

        $equipe->loadMissing(['area', 'lider']);

        $demanda->registrar(
            acao: 'Encaminhada ao líder de equipe',
            situacao: Demanda::ENCAMINHADA_AO_LIDER,
            papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR,
            autor: Auth::user(),
            detalhe: $observacao,
            campos: array_filter([
                'Bairro que sugeriu a equipe' => $demanda->bairro,
                'Equipe de destino' => $equipe->rotulo().' — '.($equipe->area?->nome ?? 'sem área'),
                'Líder da equipe' => $equipe->lider?->name
                    ?? ($equipe->encarregado !== null
                        ? $equipe->encarregado.' (ainda sem conta no sistema)'
                        : 'equipe ainda sem líder cadastrado'),
            ], static fn (?string $v): bool => $v !== null && $v !== ''),
            mudancas: [
                'area_id' => $equipe->area_id,
                'equipe_id' => $equipe->id,
                'operacao_id' => null,
            ],
        );

        CiclosDeFiscalizacao::abrirParaDemanda($demanda->fresh(), Auth::user());
    }

    private function devolverDemanda(Demanda $demanda, string $motivo, string $justificativa, string $destinoRetorno): void
    {
        $arquivar = $destinoRetorno === 'Arquivada';

        $demanda->registrar(
            acao: $arquivar ? 'Arquivada pelo Chefe de Setor' : 'Devolvida ao remetente',
            situacao: $arquivar ? Demanda::ARQUIVADA : Demanda::DEVOLVIDA,
            papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR,
            autor: Auth::user(),
            detalhe: $justificativa,
            campos: ['Motivo' => $motivo, 'Destino' => $destinoRetorno],
        );
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /**
     * O recado que a tela mostra depois de uma decisão — dizendo o EFEITO dela,
     * não só que deu certo. "Salvo com sucesso" não conta a quem a demanda foi.
     */
    private function recado(Demanda $demanda): string
    {
        $demanda->refresh();

        $lider = $demanda->equipe?->nomeDoLider();

        return match ($demanda->situacao) {
            Demanda::ENCAMINHADA_AO_LIDER => "Demanda {$demanda->protocolo} encaminhada à Equipe {$demanda->equipe?->codigo}"
                .($lider !== null && $lider !== '' ? " — na mesa de {$lider}, que direciona aos fiscais." : ' — a equipe ainda não tem líder no sistema.'),
            Demanda::DIRECIONADA_AOS_FISCAIS => "Demanda {$demanda->protocolo} direcionada aos fiscais da Equipe {$demanda->equipe?->codigo} — "
                .'aparecerá no aplicativo deles.',
            Demanda::DEVOLVIDA => "Demanda {$demanda->protocolo} devolvida ao remetente, com a justificativa registrada.",
            Demanda::ARQUIVADA => "Demanda {$demanda->protocolo} arquivada, com a justificativa registrada.",
            default => "Demanda {$demanda->protocolo} registrada na Caixa, à espera do encaminhamento.",
        };
    }

    /**
     * Os nomes de canal que o formulário oferece — e que a validação aceita.
     *
     * Só os que o CHEFE registra (`registro = chefe`). O Fala Salvador fica de
     * fora de propósito: só os líderes o acessam, e eles o digitam na tela do
     * próprio canal (decisão do dono, 22/09/2026).
     *
     * @return list<string>
     */
    private function origens(): array
    {
        return array_values(array_map(
            static fn (array $canal): string => (string) $canal['nome'],
            array_filter(
                (array) config('demandas.canais', []),
                static fn (array $canal): bool => ($canal['registro'] ?? 'chefe') === 'chefe',
            ),
        ));
    }

    /** O rótulo escolhido no formulário de volta para a chave que o banco guarda. */
    private function canalDoRotulo(string $rotulo): string
    {
        foreach ((array) config('demandas.canais', []) as $chave => $canal) {
            if ((string) $canal['nome'] === $rotulo) {
                return (string) $chave;
            }
        }

        return Demanda::CANAL_OFICIO;
    }
}
