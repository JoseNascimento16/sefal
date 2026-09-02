import { Head } from '@inertiajs/react';
import {
    ArrowRightCircle,
    CornerUpLeft,
    FileText,
    Inbox,
    Info,
    Paperclip,
    Plus,
    RotateCcw,
    Send,
    TriangleAlert,
    UserRound,
    UserX,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import {
    Paginacao,
    ThOrdenavel,
    useOrdenacao,
    usePaginacao,
} from '@/components/retaguarda/th-ordenavel';
import type {
    Demanda,
    EquipeResumo,
    Sugestao,
} from '@/dados-prototipo/administrativo';
import { TOM_DA_SITUACAO } from '@/dados-prototipo/administrativo';
import { useAcoes } from '@/hooks/use-acoes';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { dataBR, hojeISO, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import { cn } from '@/lib/utils';
import {
    devolver as rotaDevolver,
    encaminhar as rotaEncaminhar,
    index,
    reiniciar as rotaReiniciar,
    store,
} from '@/routes/retaguarda/caixa-de-entrada';

/**
 * Caixa de Entrada do Administrativo — PROTÓTIPO.
 *
 * É o começo da cadeia. A demanda chega de FORA e em PAPEL — e-Salvador, Fala
 * Salvador 156, pedido de nova licença, ofício —, o administrativo digita, e daí
 * saem só dois caminhos:
 *
 *   • **encaminhar** à equipe responsável, derivada do BAIRRO. A tela SUGERE e a
 *     pessoa CONFIRMA: bairro que pertence a duas áreas tem duas respostas
 *     igualmente certas, e escolher uma em silêncio esconderia a decisão de quem
 *     tem de tomá-la;
 *   • **devolver ao remetente ou arquivar**, com motivo de lista MAIS
 *     justificativa por escrito — é ato administrativo, e o histórico guarda
 *     quem, quando e por quê.
 *
 * Três vistas, em abas: a **caixa** (a grade, com busca e exportação), o
 * **cadastro** da demanda, e o **detalhe** de uma demanda com o trâmite
 * inteiro.
 *
 * ⚠️ A busca é o filtro ÚNICO — não há chip de filtro paralelo. Os números do
 * topo são o RESUMO da mesma lista e, clicados, escrevem a faceta na busca: o
 * atalho existe sem criar um segundo dono para "o que está filtrado".
 */

interface Props {
    demandas: Demanda[];
    origens: string[];
    situacoes: string[];
    motivos: string[];
    destinos: string[];
    prazoPadraoEmDias: number;
    equipes: EquipeResumo[];
    bairros: string[];
    /** `bairro → equipe sugerida`, para a sugestão aparecer sem ida ao servidor. */
    sugestoes: Record<string, Sugestao>;
    /** A sessão já mexeu na caixa de demonstração? */
    alterada: boolean;
}

type Aba = 'caixa' | 'registro' | 'detalhe';
type Destino = 'encaminhar' | 'devolver';

/** O que a busca reconhece além das palavras soltas. */
type Faceta =
    | { tipo: 'situacao'; valor: string }
    | { tipo: 'origem'; valor: string }
    | { tipo: 'anonima' }
    | { tipo: 'prazo-vencido' }
    | { tipo: 'hoje' };

/**
 * As facetas do domínio, das mais específicas para as mais genéricas — a ordem
 * importa: declarada ao contrário, a genérica engole a outra ("licença" comeria
 * "nova licença").
 */
const FACETAS: { expressao: RegExp; valor: Faceta }[] = [
    {
        expressao: /\baguardando triagem\b|\btriagem\b|\bsem triagem\b|\bnao triad\w*\b/,
        valor: { tipo: 'situacao', valor: 'Aguardando triagem' },
    },
    { expressao: /\bencaminhad\w*\b/, valor: { tipo: 'situacao', valor: 'Encaminhada' } },
    { expressao: /\bdevolvid\w*\b/, valor: { tipo: 'situacao', valor: 'Devolvida' } },
    { expressao: /\barquivad\w*\b/, valor: { tipo: 'situacao', valor: 'Arquivada' } },
    { expressao: /\banonim\w*\b|\bsem requerente\b/, valor: { tipo: 'anonima' } },
    {
        expressao: /\bprazo vencido\b|\bvencid\w*\b|\batrasad\w*\b/,
        valor: { tipo: 'prazo-vencido' },
    },
    { expressao: /\bfala salvador\b|\b156\b/, valor: { tipo: 'origem', valor: 'Fala Salvador 156' } },
    { expressao: /\be-?salvador\b/, valor: { tipo: 'origem', valor: 'e-Salvador' } },
    { expressao: /\bnova licenca\b|\blicenca\b/, valor: { tipo: 'origem', valor: 'Nova licença' } },
    { expressao: /\boficio\b/, valor: { tipo: 'origem', valor: 'Ofício' } },
    { expressao: /\brecebidas? hoje\b|\bhoje\b/, valor: { tipo: 'hoje' } },
];

/** Como uma demanda anônima se apresenta na tela — nunca um espaço em branco. */
function quemPediu(demanda: Demanda): string {
    return demanda.anonima ? 'Anônimo' : (demanda.requerente ?? VAZIO);
}

export default function CaixaDeEntrada({
    demandas,
    origens,
    situacoes,
    motivos,
    destinos,
    prazoPadraoEmDias,
    equipes,
    bairros,
    sugestoes,
    alterada,
}: Props) {
    const acoes = useAcoes();
    const { enviando, ocupado, enviar } = useEnvio();

    const [aba, setAba] = useState<Aba>('caixa');
    const [busca, setBusca] = useState('');
    const [abertaId, setAbertaId] = useState<number | null>(null);

    // `hojeISO` e não `toISOString()`: este converte para UTC, e num fuso
    // negativo como o nosso o "hoje" vira o dia seguinte a partir das 21h.
    const hoje = hojeISO();

    // ── Filtro ──────────────────────────────────────────────────────────────

    const filtradas = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return demandas.filter((d) => {
            for (const faceta of facetas) {
                if (faceta.tipo === 'situacao' && d.situacao !== faceta.valor) {
                    return false;
                }

                if (faceta.tipo === 'origem' && d.origem !== faceta.valor) {
                    return false;
                }

                if (faceta.tipo === 'anonima' && !d.anonima) {
                    return false;
                }

                if (faceta.tipo === 'hoje' && d.recebida_em !== hoje) {
                    return false;
                }

                if (faceta.tipo === 'prazo-vencido' && d.prazo >= hoje) {
                    return false;
                }
            }

            return casaTermos(termos, [
                d.protocolo,
                d.documento_origem,
                d.origem,
                d.anonima ? 'anonimo' : d.requerente,
                d.contato,
                d.assunto,
                d.endereco,
                d.bairro,
                d.descricao,
                d.equipe,
                d.situacao,
                d.motivo,
            ]);
        });
    }, [demandas, busca, hoje]);

    const ord = useOrdenacao(filtradas, {
        campo: 'recebida_em',
        dir: 'desc',
        acessor: 'recebida_em',
    });
    const pag = usePaginacao(ord.itens);

    // Os números saem da MESMA lista que a grade desenha — não de uma segunda
    // consulta —, então não há como discordarem do que está logo abaixo.
    const numeros = useMemo(
        () => ({
            total: demandas.length,
            triagem: demandas.filter((d) => d.situacao === 'Aguardando triagem').length,
            encaminhadas: demandas.filter((d) => d.situacao === 'Encaminhada').length,
            retornadas: demandas.filter((d) =>
                ['Devolvida', 'Arquivada'].includes(d.situacao),
            ).length,
            vencidas: demandas.filter(
                (d) => d.prazo < hoje && d.situacao === 'Aguardando triagem',
            ).length,
        }),
        [demandas, hoje],
    );

    const aberta = demandas.find((d) => d.id === abertaId) ?? null;

    // ── Formulário de registro ──────────────────────────────────────────────

    const vazio = {
        origem: origens[0] ?? '',
        documento_origem: '',
        recebida_em: hoje,
        prazo: '',
        anonima: false,
        requerente: '',
        contato: '',
        assunto: '',
        endereco: '',
        bairro: '',
        descricao: '',
        anexo: '',
        destino: 'encaminhar' as Destino,
        equipe: '',
        observacao: '',
        motivo: motivos[0] ?? '',
        justificativa: '',
        destino_retorno: destinos[0] ?? '',
    };

    const [form, setForm] = useState({ ...vazio });
    const [confirmando, setConfirmando] = useState(false);

    function mudar<C extends keyof typeof vazio>(
        campo: C,
        valor: (typeof vazio)[C],
    ) {
        setForm((atual) => ({ ...atual, [campo]: valor }));
    }

    /**
     * A equipe que o bairro escolhido sugere.
     *
     * A sugestão é aplicada ao campo de equipe só quando a pessoa ainda não
     * escolheu nada — trocar por baixo uma escolha já feita é o tipo de "ajuda"
     * que faz o formulário gravar o que ninguém pediu.
     */
    const sugestao = form.bairro ? (sugestoes[form.bairro] ?? null) : null;
    const equipeEscolhida = form.equipe || sugestao?.equipe || '';

    function escolherBairro(bairro: string) {
        setForm((atual) => ({
            ...atual,
            bairro,
            // A equipe volta a ser a sugerida pelo bairro NOVO: mantida, ela
            // continuaria apontando para a área do bairro anterior.
            equipe: sugestoes[bairro]?.equipe ?? '',
        }));
    }

    function abrirRegistro() {
        setForm({ ...vazio });
        setAbertaId(null);
        setAba('registro');
    }

    function abrirDetalhe(demanda: Demanda) {
        setAbertaId(demanda.id);
        setAba('detalhe');
    }

    function registrar() {
        enviar(
            'registrar',
            store().url,
            {
                ...form,
                equipe: equipeEscolhida,
                // Prazo em branco = o padrão do canal, calculado pelo servidor.
                prazo: form.prazo || null,
                anexo: form.anexo || null,
                requerente: form.anonima ? null : form.requerente,
                contato: form.anonima ? null : form.contato,
            },
            {
                preserveScroll: false,
                onSuccess: () => {
                    setConfirmando(false);
                    setForm({ ...vazio });
                    setAba('caixa');
                },
            },
        );
    }

    // ── Ações sobre uma demanda já na caixa ─────────────────────────────────

    const [triagem, setTriagem] = useState({ equipe: '', observacao: '' });
    const [retorno, setRetorno] = useState({
        motivo: motivos[0] ?? '',
        justificativa: '',
        destino_retorno: destinos[0] ?? '',
    });

    const sugestaoDaAberta = aberta ? (sugestoes[aberta.bairro] ?? null) : null;
    const equipeDaTriagem =
        triagem.equipe || aberta?.equipe || sugestaoDaAberta?.equipe || '';

    function encaminharAberta() {
        if (aberta === null) {
            return;
        }

        enviar(
            'encaminhar',
            rotaEncaminhar(aberta.id).url,
            { equipe: equipeDaTriagem, observacao: triagem.observacao || null },
            { onSuccess: () => setTriagem({ equipe: '', observacao: '' }) },
        );
    }

    function devolverAberta() {
        if (aberta === null) {
            return;
        }

        enviar('devolver', rotaDevolver(aberta.id).url, retorno, {
            onSuccess: () =>
                setRetorno({
                    motivo: motivos[0] ?? '',
                    justificativa: '',
                    destino_retorno: destinos[0] ?? '',
                }),
        });
    }

    // Só as chaves declaradas entram no arquivo, e a data sai em BR: o documento
    // é lido fora do sistema, onde ninguém traduz ISO.
    const linhasExportacao = ord.itens.map((d) => ({
        protocolo: d.protocolo,
        origem: d.origem,
        documento_origem: d.documento_origem,
        recebida_em: dataBR(d.recebida_em),
        requerente: quemPediu(d),
        assunto: d.assunto,
        bairro: d.bairro,
        equipe: d.equipe ?? VAZIO,
        situacao: d.situacao,
        prazo: dataBR(d.prazo),
    }));

    return (
        <>
            <Head title="Caixa de Entrada" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Fiscalização</p>
                    <h1>Caixa de Entrada</h1>
                    <p>
                        O que chega de <strong>fora</strong> e em papel —
                        e-Salvador, Fala Salvador 156, pedido de nova licença e
                        ofício. Aqui o administrativo registra, decide e{' '}
                        <strong>encaminha à equipe da área do bairro</strong> ou
                        devolve com justificativa.
                    </p>
                </div>

                {/* Resumo da MESMA lista que a grade desenha. Clicar escreve a
                    faceta na busca: atalho sem criar um segundo filtro. */}
                <div className="rt-numeros">
                    <button
                        type="button"
                        className="rt-numero"
                        title="Ver todas as demandas"
                        onClick={() => setBusca('')}
                    >
                        <strong>{numeros.total}</strong>
                        <span>na caixa</span>
                    </button>
                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero alerta"
                        title="Filtrar as que aguardam triagem"
                        onClick={() => setBusca('aguardando triagem')}
                    >
                        <strong>{numeros.triagem}</strong>
                        <span>a triar</span>
                    </button>
                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero ok"
                        title="Filtrar as encaminhadas"
                        onClick={() => setBusca('encaminhadas')}
                    >
                        <strong>{numeros.encaminhadas}</strong>
                        <span>encaminhadas</span>
                    </button>
                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero info"
                        title="Filtrar as devolvidas e as arquivadas"
                        onClick={() => setBusca('devolvidas')}
                    >
                        <strong>{numeros.retornadas}</strong>
                        <span>retornadas</span>
                    </button>
                </div>
            </div>

            <SeloPrototipo>
                Esta tela é a proposta do módulo, para conferência da forma antes
                de virar sistema. As demandas são de exemplo e{' '}
                <strong>nada é gravado</strong>: o que você registrar, encaminhar
                ou devolver vale só nesta sessão do navegador.
            </SeloPrototipo>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Caixa de Entrada">
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'caixa'}
                        onClick={() => setAba('caixa')}
                    >
                        <Inbox size={16} aria-hidden />
                        <span className="aba-rotulo">Caixa</span>
                    </button>

                    {acoes.incluir && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'registro'}
                            onClick={abrirRegistro}
                        >
                            <Plus size={16} aria-hidden />
                            <span className="aba-rotulo">Cadastrar Demanda</span>
                        </button>
                    )}

                    {aberta !== null && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'detalhe'}
                            onClick={() => setAba('detalhe')}
                        >
                            <FileText size={16} aria-hidden />
                            <span className="aba-rotulo">{aberta.protocolo}</span>
                        </button>
                    )}
                </div>

                {aba === 'caixa' && (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder='Protocolo, requerente, assunto, bairro, equipe ou situação — ex.: "denúncias anônimas do 156 com prazo vencido"'
                            exemplos={[
                                'aguardando triagem',
                                'anônimas',
                                'prazo vencido',
                                'fala salvador 156',
                                'nova licença',
                                'recebidas hoje',
                            ]}
                        />

                        {numeros.vencidas > 0 && (
                            <p className="form-erro" style={{ marginBottom: 12 }}>
                                <TriangleAlert size={15} aria-hidden />{' '}
                                {contar(numeros.vencidas, 'demanda', 'demandas')}{' '}
                                aguardando triagem com o prazo já vencido.
                            </p>
                        )}

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                marginBottom: 10,
                            }}
                        >
                            {acoes.incluir && (
                                <BotaoAcao
                                    icone={<Plus size={16} aria-hidden />}
                                    ocupado={ocupado}
                                    onClick={abrirRegistro}
                                >
                                    Cadastrar Demanda
                                </BotaoAcao>
                            )}

                            {/* Só aparece depois de a sessão mexer em algo: num
                                protótipo intocado não há o que reiniciar. */}
                            {alterada && acoes.habilitado && (
                                <BotaoAcao
                                    className="btn btn-secondary btn-sm"
                                    icone={<RotateCcw size={16} aria-hidden />}
                                    carregando={enviando === 'reiniciar'}
                                    ocupado={ocupado}
                                    rotuloCarregando="Reiniciando…"
                                    onClick={() => enviar('reiniciar', rotaReiniciar().url)}
                                >
                                    Reiniciar demonstração
                                </BotaoAcao>
                            )}

                            <div style={{ marginLeft: 'auto' }}>
                                <BotaoExportar
                                    titulo="Caixa de Entrada"
                                    subtitulo="Fiscalização › Caixa de Entrada"
                                    contexto={
                                        busca.trim()
                                            ? `Busca: "${busca.trim()}"`
                                            : 'Caixa completa'
                                    }
                                    colunas={[
                                        { chave: 'protocolo', titulo: 'Protocolo' },
                                        { chave: 'origem', titulo: 'Origem' },
                                        { chave: 'documento_origem', titulo: 'Documento' },
                                        { chave: 'recebida_em', titulo: 'Recebida em', alinhar: 'center' },
                                        { chave: 'requerente', titulo: 'Requerente' },
                                        { chave: 'assunto', titulo: 'Assunto' },
                                        { chave: 'bairro', titulo: 'Bairro' },
                                        { chave: 'equipe', titulo: 'Equipe' },
                                        { chave: 'situacao', titulo: 'Situação' },
                                        { chave: 'prazo', titulo: 'Prazo', alinhar: 'center' },
                                    ]}
                                    linhas={linhasExportacao}
                                />
                            </div>
                        </div>

                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela —
                                para abrir a demanda e o trâmite dela.
                            </p>
                        )}

                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <ThOrdenavel campo="protocolo" acessor="protocolo" ord={ord}>
                                            Protocolo
                                        </ThOrdenavel>
                                        <ThOrdenavel campo="origem" acessor="origem" ord={ord}>
                                            Origem
                                        </ThOrdenavel>
                                        <ThOrdenavel campo="recebida_em" acessor="recebida_em" ord={ord}>
                                            Recebida
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="requerente"
                                            acessor={(d: Demanda) => quemPediu(d)}
                                            ord={ord}
                                        >
                                            Requerente
                                        </ThOrdenavel>
                                        <ThOrdenavel campo="assunto" acessor="assunto" ord={ord}>
                                            Assunto
                                        </ThOrdenavel>
                                        <ThOrdenavel campo="bairro" acessor="bairro" ord={ord}>
                                            Bairro
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="equipe"
                                            acessor={(d: Demanda) => d.equipe ?? ''}
                                            ord={ord}
                                        >
                                            Equipe
                                        </ThOrdenavel>
                                        <ThOrdenavel campo="situacao" acessor="situacao" ord={ord}>
                                            Situação
                                        </ThOrdenavel>
                                        <ThOrdenavel campo="prazo" acessor="prazo" ord={ord}>
                                            Prazo
                                        </ThOrdenavel>
                                    </tr>
                                </thead>

                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td colSpan={9} className="tabela-vazia">
                                                {demandas.length === 0
                                                    ? 'Nenhuma demanda na caixa. Use "Cadastrar Demanda" para lançar o que chegou.'
                                                    : 'Nenhuma demanda casa com a busca. Limpe o campo para ver a caixa inteira.'}
                                            </td>
                                        </tr>
                                    )}

                                    {pag.visiveis.map((d) => {
                                        const vencida =
                                            d.prazo < hoje && d.situacao === 'Aguardando triagem';

                                        return (
                                            <tr
                                                key={d.id}
                                                {...linhaClicavel(
                                                    () => abrirDetalhe(d),
                                                    'Abrir a demanda e o trâmite dela',
                                                    vencida && 'pendente',
                                                )}
                                            >
                                                <td className="cell-id">{d.protocolo}</td>
                                                <td>{d.origem}</td>
                                                <td className="cell-id">{dataBR(d.recebida_em)}</td>
                                                <td>
                                                    {d.anonima ? (
                                                        <span
                                                            style={{
                                                                display: 'inline-flex',
                                                                alignItems: 'center',
                                                                gap: 5,
                                                                color: 'var(--sm-texto-fraco)',
                                                            }}
                                                        >
                                                            <UserX size={14} aria-hidden /> Anônimo
                                                        </span>
                                                    ) : (
                                                        (d.requerente ?? VAZIO)
                                                    )}
                                                </td>
                                                <td>{d.assunto}</td>
                                                <td>{d.bairro}</td>
                                                <td>
                                                    {d.equipe ? (
                                                        <span className="selo selo-neutro">
                                                            {d.equipe}
                                                        </span>
                                                    ) : (
                                                        VAZIO
                                                    )}
                                                </td>
                                                <td>
                                                    <span
                                                        className={cn(
                                                            'selo',
                                                            TOM_DA_SITUACAO[d.situacao] ??
                                                                'selo-neutro',
                                                        )}
                                                    >
                                                        {d.situacao}
                                                    </span>
                                                </td>
                                                <td className="cell-id">
                                                    {dataBR(d.prazo)}
                                                    {vencida && (
                                                        <>
                                                            {' '}
                                                            <span className="selo selo-perigo">
                                                                vencido
                                                            </span>
                                                        </>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <Paginacao {...pag.props} />
                    </>
                )}

                {aba === 'registro' && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            setConfirmando(true);
                        }}
                    >
                        <p className="card-sub" style={{ marginBottom: 18 }}>
                            Digite o documento que chegou em papel. O{' '}
                            <strong>bairro</strong> é o que define a equipe
                            responsável — a tela sugere, e você confirma.
                        </p>

                        <div className="rt-form-linha">
                            <div className="form-group">
                                <label className="form-label" htmlFor="origem">
                                    Origem
                                </label>
                                <select
                                    id="origem"
                                    className="form-control"
                                    value={form.origem}
                                    onChange={(e) => mudar('origem', e.target.value)}
                                >
                                    {origens.map((o) => (
                                        <option key={o} value={o}>
                                            {o}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="documento-origem">
                                    Nº do documento de origem
                                </label>
                                <input
                                    id="documento-origem"
                                    type="text"
                                    className="form-control"
                                    value={form.documento_origem}
                                    maxLength={40}
                                    placeholder="Ex.: 156-2026-884120"
                                    onChange={(e) => mudar('documento_origem', e.target.value)}
                                />
                                <p className="form-ajuda">
                                    O número que vem impresso no papel do canal.
                                </p>
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="recebida-em">
                                    Data de recebimento
                                </label>
                                <input
                                    id="recebida-em"
                                    type="date"
                                    className="form-control"
                                    value={form.recebida_em}
                                    max={hoje}
                                    onChange={(e) => mudar('recebida_em', e.target.value)}
                                />
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="prazo">
                                    Prazo de atendimento
                                </label>
                                <input
                                    id="prazo"
                                    type="date"
                                    className="form-control"
                                    value={form.prazo}
                                    min={form.recebida_em}
                                    onChange={(e) => mudar('prazo', e.target.value)}
                                />
                                <p className="form-ajuda">
                                    Em branco, o sistema usa{' '}
                                    {contar(prazoPadraoEmDias, 'dia', 'dias')} a
                                    partir do recebimento.
                                </p>
                            </div>
                        </div>

                        <div className="form-group">
                            <label
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    cursor: 'pointer',
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={form.anonima}
                                    onChange={(e) => mudar('anonima', e.target.checked)}
                                />
                                <span>Denúncia anônima (sem requerente identificado)</span>
                            </label>
                            <p className="form-ajuda">
                                Marcar é uma escolha explícita: sem isso, o nome do
                                requerente é obrigatório.
                            </p>
                        </div>

                        {!form.anonima && (
                            <div className="rt-form-linha">
                                <div className="form-group">
                                    <label className="form-label" htmlFor="requerente">
                                        Requerente
                                    </label>
                                    <input
                                        id="requerente"
                                        type="text"
                                        className="form-control"
                                        value={form.requerente}
                                        maxLength={150}
                                        onChange={(e) => mudar('requerente', e.target.value)}
                                    />
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="contato">
                                        Contato
                                    </label>
                                    <input
                                        id="contato"
                                        type="text"
                                        className="form-control"
                                        value={form.contato}
                                        maxLength={80}
                                        placeholder="Telefone ou e-mail"
                                        onChange={(e) => mudar('contato', e.target.value)}
                                    />
                                </div>
                            </div>
                        )}

                        <div className="rt-form-linha">
                            <div className="form-group" style={{ gridColumn: '1 / -1' }}>
                                <label className="form-label" htmlFor="endereco">
                                    Endereço da ocorrência
                                </label>
                                <input
                                    id="endereco"
                                    type="text"
                                    className="form-control"
                                    value={form.endereco}
                                    maxLength={200}
                                    placeholder="Rua, número e ponto de referência"
                                    onChange={(e) => mudar('endereco', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="form-group">
                            <label className="form-label" htmlFor="bairro">
                                Bairro
                            </label>
                            <select
                                id="bairro"
                                className="form-control"
                                value={form.bairro}
                                onChange={(e) => escolherBairro(e.target.value)}
                            >
                                <option value="">Escolha o bairro…</option>
                                {bairros.map((b) => (
                                    <option key={b} value={b}>
                                        {b}
                                    </option>
                                ))}
                            </select>

                            {sugestao === null ? (
                                <p className="form-ajuda">
                                    É o bairro que define a equipe responsável.
                                </p>
                            ) : (
                                <div className="rt-sugestao">
                                    <Info size={16} aria-hidden />
                                    <div>
                                        <strong>
                                            Bairro {form.bairro} → sugerida Equipe{' '}
                                            {sugestao.equipe} · {sugestao.area} (
                                            {sugestao.regiao})
                                        </strong>
                                        <div>
                                            Encarregado: {sugestao.encarregado}. A
                                            sugestão vem do bloco de bairros da
                                            área — você pode trocar abaixo.
                                        </div>

                                        {sugestao.alternativas.length > 0 && (
                                            <div style={{ marginTop: 6 }}>
                                                <strong>
                                                    Este bairro também é coberto por{' '}
                                                    {sugestao.alternativas
                                                        .map(
                                                            (a) =>
                                                                `Equipe ${a.equipe} · ${a.area}`,
                                                        )
                                                        .join(', ')}
                                                    .
                                                </strong>{' '}
                                                O vínculo bairro↔equipe não é
                                                exclusivo: confirme com quem
                                                conhece o ponto.
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="form-group">
                            <label className="form-label" htmlFor="assunto">
                                Assunto
                            </label>
                            <input
                                id="assunto"
                                type="text"
                                className="form-control"
                                value={form.assunto}
                                maxLength={180}
                                placeholder="O caso em uma linha"
                                onChange={(e) => mudar('assunto', e.target.value)}
                            />
                        </div>

                        <div className="form-group">
                            <label className="form-label" htmlFor="descricao">
                                Descrição
                            </label>
                            <textarea
                                id="descricao"
                                className="form-control"
                                rows={4}
                                value={form.descricao}
                                maxLength={2000}
                                placeholder="O que o documento relata, como o canal registrou"
                                onChange={(e) => mudar('descricao', e.target.value)}
                            />
                        </div>

                        <div className="form-group">
                            <label className="form-label" htmlFor="anexo">
                                <Paperclip size={14} aria-hidden /> Documento
                                digitalizado
                            </label>
                            <input
                                id="anexo"
                                type="text"
                                className="form-control"
                                value={form.anexo}
                                maxLength={120}
                                placeholder="Ex.: denuncia-156-884120.pdf"
                                onChange={(e) => mudar('anexo', e.target.value)}
                            />
                            <p className="form-ajuda">
                                No protótipo entra só o nome do arquivo. No sistema
                                este campo será o envio do PDF digitalizado, com a
                                mesma conferência de anexo das demais telas.
                            </p>
                        </div>

                        {/* ── A decisão ───────────────────────────────────── */}

                        <h3 className="card-titulo" style={{ marginTop: 26 }}>
                            Destino da demanda
                        </h3>
                        <p className="card-sub">
                            As duas saídas do administrativo. Devolver ou arquivar
                            é ato administrativo: exige o motivo por escrito.
                        </p>

                        <div className="rt-escolha">
                            <label
                                className={cn(
                                    'rt-escolha-cartao',
                                    form.destino === 'encaminhar' && 'ativo',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="destino"
                                    value="encaminhar"
                                    checked={form.destino === 'encaminhar'}
                                    onChange={() => mudar('destino', 'encaminhar')}
                                />
                                <span>
                                    <strong>
                                        <ArrowRightCircle size={15} aria-hidden />{' '}
                                        Registrar e encaminhar
                                    </strong>
                                    <span>
                                        Vira demanda de fiscalização dirigida e
                                        aparece no aplicativo dos fiscais da
                                        equipe.
                                    </span>
                                </span>
                            </label>

                            <label
                                className={cn(
                                    'rt-escolha-cartao',
                                    form.destino === 'devolver' && 'ativo',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="destino"
                                    value="devolver"
                                    checked={form.destino === 'devolver'}
                                    onChange={() => mudar('destino', 'devolver')}
                                />
                                <span>
                                    <strong>
                                        <CornerUpLeft size={15} aria-hidden />{' '}
                                        Registrar e devolver/arquivar
                                    </strong>
                                    <span>
                                        Fica registrada na caixa como recusada, com
                                        motivo e justificativa.
                                    </span>
                                </span>
                            </label>
                        </div>

                        {form.destino === 'encaminhar' ? (
                            <div className="rt-form-linha">
                                <div className="form-group">
                                    <label className="form-label" htmlFor="equipe">
                                        Equipe responsável
                                    </label>
                                    <select
                                        id="equipe"
                                        className="form-control"
                                        value={equipeEscolhida}
                                        onChange={(e) => mudar('equipe', e.target.value)}
                                    >
                                        <option value="">Escolha a equipe…</option>
                                        {equipes.map((e) => (
                                            <option key={e.equipe} value={e.equipe}>
                                                Equipe {e.equipe} · {e.area} ({e.regiao}) —{' '}
                                                {e.encarregado}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="observacao">
                                        Orientação à equipe
                                    </label>
                                    <input
                                        id="observacao"
                                        type="text"
                                        className="form-control"
                                        value={form.observacao}
                                        maxLength={500}
                                        placeholder="Ex.: vistoriar depois das 21h"
                                        onChange={(e) => mudar('observacao', e.target.value)}
                                    />
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="rt-form-linha">
                                    <div className="form-group">
                                        <label className="form-label" htmlFor="motivo">
                                            Motivo
                                        </label>
                                        <select
                                            id="motivo"
                                            className="form-control"
                                            value={form.motivo}
                                            onChange={(e) => mudar('motivo', e.target.value)}
                                        >
                                            {motivos.map((m) => (
                                                <option key={m} value={m}>
                                                    {m}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="destino-retorno">
                                            Para onde vai
                                        </label>
                                        <select
                                            id="destino-retorno"
                                            className="form-control"
                                            value={form.destino_retorno}
                                            onChange={(e) =>
                                                mudar('destino_retorno', e.target.value)
                                            }
                                        >
                                            {destinos.map((d) => (
                                                <option key={d} value={d}>
                                                    {d}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="justificativa">
                                        Justificativa
                                    </label>
                                    <textarea
                                        id="justificativa"
                                        className="form-control"
                                        rows={3}
                                        value={form.justificativa}
                                        maxLength={1000}
                                        placeholder="Por que a demanda não é atendida, em palavras que quem ler depois entenda"
                                        onChange={(e) => mudar('justificativa', e.target.value)}
                                    />
                                    <p className="form-ajuda">
                                        O motivo de lista não conta o caso. A
                                        justificativa é o que explica a decisão a
                                        quem abrir a demanda meses depois.
                                    </p>
                                </div>
                            </>
                        )}

                        <div className="sobreposicao-acoes" style={{ marginTop: 22 }}>
                            <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                onClick={() => setAba('caixa')}
                                disabled={ocupado}
                            >
                                Voltar
                            </button>

                            <BotaoAcao
                                type="submit"
                                icone={
                                    form.destino === 'encaminhar' ? (
                                        <Send size={16} aria-hidden />
                                    ) : (
                                        <CornerUpLeft size={16} aria-hidden />
                                    )
                                }
                                carregando={enviando === 'registrar'}
                                ocupado={ocupado}
                                rotuloCarregando="Registrando…"
                            >
                                {form.destino === 'encaminhar'
                                    ? 'Registrar e encaminhar'
                                    : 'Registrar e devolver/arquivar'}
                            </BotaoAcao>
                        </div>
                    </form>
                )}

                {aba === 'detalhe' && aberta !== null && (
                    <>
                        <div className="rt-detalhe-cabeca">
                            <div>
                                <p className="sobrancelha">{aberta.origem}</p>
                                <h2 className="card-titulo">{aberta.assunto}</h2>
                                <p className="card-sub">
                                    {aberta.protocolo} · documento de origem{' '}
                                    {aberta.documento_origem} · recebida em{' '}
                                    {dataBR(aberta.recebida_em)} · prazo{' '}
                                    {dataBR(aberta.prazo)}
                                </p>
                            </div>

                            <span
                                className={cn(
                                    'selo',
                                    TOM_DA_SITUACAO[aberta.situacao] ?? 'selo-neutro',
                                )}
                            >
                                {aberta.situacao}
                            </span>
                        </div>

                        <dl className="rt-ficha">
                            <div>
                                <dt>Requerente</dt>
                                <dd>
                                    {aberta.anonima ? (
                                        <span
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 6,
                                            }}
                                        >
                                            <UserX size={14} aria-hidden /> Anônimo —
                                            denúncia sem identificação
                                        </span>
                                    ) : (
                                        <span
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 6,
                                            }}
                                        >
                                            <UserRound size={14} aria-hidden />{' '}
                                            {aberta.requerente ?? VAZIO}
                                        </span>
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt>Contato</dt>
                                <dd>{aberta.contato ?? VAZIO}</dd>
                            </div>
                            <div>
                                <dt>Endereço</dt>
                                <dd>{aberta.endereco || VAZIO}</dd>
                            </div>
                            <div>
                                <dt>Bairro</dt>
                                <dd>{aberta.bairro}</dd>
                            </div>
                            <div>
                                <dt>Equipe</dt>
                                <dd>
                                    {aberta.equipe
                                        ? `Equipe ${aberta.equipe}`
                                        : 'ainda sem equipe'}
                                </dd>
                            </div>
                            <div>
                                <dt>Documento digitalizado</dt>
                                <dd>
                                    {aberta.anexo ? (
                                        <span
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 6,
                                            }}
                                        >
                                            <Paperclip size={14} aria-hidden />{' '}
                                            {aberta.anexo}
                                        </span>
                                    ) : (
                                        VAZIO
                                    )}
                                </dd>
                            </div>
                            <div style={{ gridColumn: '1 / -1' }}>
                                <dt>Descrição</dt>
                                <dd>{aberta.descricao || VAZIO}</dd>
                            </div>

                            {aberta.motivo !== null && (
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <dt>Motivo do retorno</dt>
                                    <dd>
                                        <strong>{aberta.motivo}</strong>
                                        {aberta.destino ? ` · ${aberta.destino}` : ''}
                                        <div>{aberta.justificativa}</div>
                                    </dd>
                                </div>
                            )}
                        </dl>

                        <h3 className="card-titulo" style={{ marginTop: 26 }}>
                            Trâmite
                        </h3>
                        <p className="card-sub">
                            Quem fez o quê, e quando. É o que faz de "devolvida"
                            um ato, e não uma palavra sem autor.
                        </p>

                        <ol className="rt-tramite">
                            {aberta.tramites.map((t, i) => (
                                <li key={`${t.em}-${t.o_que}-${i}`}>
                                    <strong>{t.o_que}</strong>
                                    <span className="rt-tramite-quando">
                                        {dataBR(t.em)} · {t.quem}
                                    </span>
                                    <span>{t.detalhe}</span>
                                </li>
                            ))}

                            {/* O que vem DEPOIS, quando a demanda está em campo.
                                Dito como próximo passo e não como fato: o
                                protótipo não simula a vistoria. */}
                            {aberta.situacao === 'Encaminhada' && (
                                <li className="rt-tramite-futuro">
                                    <strong>Em campo</strong>
                                    <span className="rt-tramite-quando">
                                        próximo passo · Equipe {aberta.equipe}
                                    </span>
                                    <span>
                                        A equipe recebe a demanda no aplicativo,
                                        vistoria o ponto e registra o desfecho —
                                        que aparecerá aqui quando o módulo de
                                        fiscalização estiver ligado a esta caixa.
                                    </span>
                                </li>
                            )}
                        </ol>

                        {acoes.habilitado && (
                            <>
                                <h3 className="card-titulo" style={{ marginTop: 26 }}>
                                    Decidir
                                </h3>

                                <div className="rt-form-linha">
                                    <div className="form-group">
                                        <label className="form-label" htmlFor="triagem-equipe">
                                            Encaminhar à equipe
                                        </label>
                                        <select
                                            id="triagem-equipe"
                                            className="form-control"
                                            value={equipeDaTriagem}
                                            onChange={(e) =>
                                                setTriagem((t) => ({
                                                    ...t,
                                                    equipe: e.target.value,
                                                }))
                                            }
                                        >
                                            <option value="">Escolha a equipe…</option>
                                            {equipes.map((e) => (
                                                <option key={e.equipe} value={e.equipe}>
                                                    Equipe {e.equipe} · {e.area} ({e.regiao})
                                                </option>
                                            ))}
                                        </select>
                                        {sugestaoDaAberta !== null && (
                                            <p className="form-ajuda">
                                                Bairro {aberta.bairro} → sugerida
                                                Equipe {sugestaoDaAberta.equipe} ·{' '}
                                                {sugestaoDaAberta.area}
                                                {sugestaoDaAberta.alternativas.length > 0
                                                    ? ' (o bairro também é coberto por outra área — confirme)'
                                                    : ''}
                                                .
                                            </p>
                                        )}
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="triagem-observacao">
                                            Orientação à equipe
                                        </label>
                                        <input
                                            id="triagem-observacao"
                                            type="text"
                                            className="form-control"
                                            value={triagem.observacao}
                                            maxLength={500}
                                            onChange={(e) =>
                                                setTriagem((t) => ({
                                                    ...t,
                                                    observacao: e.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                </div>

                                <BotaoAcao
                                    icone={<Send size={16} aria-hidden />}
                                    carregando={enviando === 'encaminhar'}
                                    ocupado={ocupado}
                                    disabled={equipeDaTriagem === ''}
                                    rotuloCarregando="Encaminhando…"
                                    onClick={encaminharAberta}
                                >
                                    Encaminhar à Equipe {equipeDaTriagem || '—'}
                                </BotaoAcao>

                                <hr className="rt-regua" />

                                <div className="rt-form-linha">
                                    <div className="form-group">
                                        <label className="form-label" htmlFor="retorno-motivo">
                                            Motivo do retorno
                                        </label>
                                        <select
                                            id="retorno-motivo"
                                            className="form-control"
                                            value={retorno.motivo}
                                            onChange={(e) =>
                                                setRetorno((r) => ({
                                                    ...r,
                                                    motivo: e.target.value,
                                                }))
                                            }
                                        >
                                            {motivos.map((m) => (
                                                <option key={m} value={m}>
                                                    {m}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="form-group">
                                        <label className="form-label" htmlFor="retorno-destino">
                                            Para onde vai
                                        </label>
                                        <select
                                            id="retorno-destino"
                                            className="form-control"
                                            value={retorno.destino_retorno}
                                            onChange={(e) =>
                                                setRetorno((r) => ({
                                                    ...r,
                                                    destino_retorno: e.target.value,
                                                }))
                                            }
                                        >
                                            {destinos.map((d) => (
                                                <option key={d} value={d}>
                                                    {d}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="retorno-justificativa">
                                        Justificativa
                                    </label>
                                    <textarea
                                        id="retorno-justificativa"
                                        className="form-control"
                                        rows={3}
                                        value={retorno.justificativa}
                                        maxLength={1000}
                                        onChange={(e) =>
                                            setRetorno((r) => ({
                                                ...r,
                                                justificativa: e.target.value,
                                            }))
                                        }
                                    />
                                </div>

                                <BotaoAcao
                                    className="btn btn-secondary btn-sm"
                                    icone={<CornerUpLeft size={16} aria-hidden />}
                                    carregando={enviando === 'devolver'}
                                    ocupado={ocupado}
                                    disabled={retorno.justificativa.trim().length < 15}
                                    rotuloCarregando="Registrando…"
                                    onClick={devolverAberta}
                                >
                                    {retorno.destino_retorno || 'Devolver'}
                                </BotaoAcao>

                                {retorno.justificativa.trim().length < 15 && (
                                    <p className="form-ajuda">
                                        A justificativa é obrigatória para devolver
                                        ou arquivar.
                                    </p>
                                )}
                            </>
                        )}
                    </>
                )}
            </div>

            {confirmando && (
                <ModalConfirm
                    titulo={
                        form.destino === 'encaminhar'
                            ? 'Registrar e encaminhar a demanda?'
                            : 'Registrar e devolver a demanda?'
                    }
                    mensagem={
                        form.destino === 'encaminhar' ? (
                            <>
                                A demanda entra na caixa e é{' '}
                                <strong>
                                    encaminhada à Equipe {equipeEscolhida || '—'}
                                </strong>
                                : ela aparecerá no aplicativo dos fiscais da equipe
                                como trabalho dirigido.
                            </>
                        ) : (
                            <>
                                A demanda entra na caixa como{' '}
                                <strong>{form.destino_retorno}</strong>, com o
                                motivo e a justificativa registrados no trâmite.
                                Devolver é ato administrativo e fica no histórico.
                            </>
                        )
                    }
                    rotuloConfirmar={
                        form.destino === 'encaminhar' ? 'Encaminhar' : 'Confirmar retorno'
                    }
                    iconeConfirmar={
                        form.destino === 'encaminhar' ? (
                            <Send size={16} aria-hidden />
                        ) : (
                            <CornerUpLeft size={16} aria-hidden />
                        )
                    }
                    processando={enviando === 'registrar'}
                    onCancelar={() => setConfirmando(false)}
                    onConfirmar={registrar}
                />
            )}
        </>
    );
}

CaixaDeEntrada.layout = {
    breadcrumbs: [{ title: 'Caixa de Entrada', href: index() }],
};
