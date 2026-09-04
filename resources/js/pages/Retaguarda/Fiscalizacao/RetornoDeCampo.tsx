import { Head } from '@inertiajs/react';
import {
    Camera,
    Check,
    FileText,
    Info,
    Lightbulb,
    ListChecks,
    MapPin,
    RotateCcw,
    Undo2,
    UserRound,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';
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
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { dataHoraBR, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar, plural } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { ciencia, index, novaVistoria, reiniciar } from '@/routes/retaguarda/retorno-de-campo';

/**
 * Retorno de Campo — a fila do CHEFE DE SETOR. PROTÓTIPO.
 *
 * Tudo que a equipe da área dele concluiu em rua volta para cá. Sem esta tela o
 * trabalho da equipe termina no aplicativo do fiscal e ninguém do outro lado é
 * obrigado a ler: o desfecho existiria no sistema e a decisão que ele pede —
 * voltar ao ponto, encerrar — ficaria sem dono.
 *
 * ── Não é a Caixa de Entrada ─────────────────────────────────────────────────
 *
 * Lá o Coordenador digita o que chegou em PAPEL ao balcão, no começo da cadeia;
 * aqui a chefia lê o que voltou do CAMPO, no fim dela. São as duas pontas do
 * mesmo trabalho, e a tela diz isso em cima para ninguém confundir as duas.
 *
 * ── A RECOMENDAÇÃO do fiscal é a coluna que decide ───────────────────────────
 *
 * O desfecho diz como a vistoria terminou; a recomendação diz o que quem esteve
 * no ponto está PEDINDO. É por ela que a chefia direciona, então ela tem coluna
 * própria na grade — não uma linha no detalhe. Quem precisa varrer trinta
 * retornos com o olho não abre trinta detalhes.
 *
 * ── A lista não é o universo, e a tela avisa ─────────────────────────────────
 *
 * O recorte por área é feito no SERVIDOR (ver o controller). A tela diz de quais
 * áreas é a lista, porque sem o aviso a chefia contaria os registros, acharia o
 * número baixo e concluiria que a equipe não trabalhou.
 *
 * ⚠️ A busca é o filtro ÚNICO — não há chip de filtro paralelo. Os números do
 * topo são o resumo da mesma lista e, clicados, escrevem a faceta na busca. A
 * ABA, sim, troca a FONTE dos dados, e é por isso que ela entra no contexto da
 * exportação.
 */

interface Decisao {
    em: string;
    quem: string;
    o_que: string;
    detalhe: string;
}

interface Registro {
    id: number;
    protocolo: string;
    /** 'Denúncia', 'Operação planejada', 'Ronda da equipe', 'Pedido de outro órgão'. */
    origem: string;
    /** O que originou a ida ao ponto: o canal e o protocolo, ou o nome da operação. */
    referencia: string;
    /** O protocolo da denúncia, quando o registro veio de uma. */
    denuncia_protocolo: string | null;
    /** ISO com hora — quem escreve dd/mm/aaaa é a tela. */
    concluida_em: string;
    area: string;
    equipe: string;
    /** Quem assinou a vistoria, com a equipe. */
    fiscal: string;
    endereco: string;
    bairro: string;
    ponto_de_referencia: string | null;
    gps: string | null;
    precisao_m: number | null;
    desfecho: string;
    /** `np` = Notificação Preliminar; `aa` = Auto de Apreensão. */
    documento: { tipo: 'np' | 'aa'; numero: string } | null;
    consideracoes: string | null;
    recomendacoes: string[];
    /** A situação em que a denúncia de origem ficou — nulo na fiscalização avulsa. */
    situacao_da_origem: string | null;
    estado: string;
    decisao: Decisao | null;
    /** Dias esperando a leitura da chefia. Nulo depois de decidido. */
    dias_parado: number | null;
}

type Aba = 'a-ler' | 'todos';

/** O tom do selo de cada estado da fila. */
const TOM_DO_ESTADO: Record<string, string> = {
    'Aguardando leitura': 'selo-aviso',
    Ciente: 'selo-neutro',
    'Nova vistoria determinada': 'selo-info',
};

/** As expressões do domínio que a busca reconhece e retira do texto livre. */
type Faceta = 'com-documento' | 'sem-documento' | 'de-denuncia' | 'avulsa' | 'com-recomendacao';

const FACETAS = [
    { expressao: /\bsem documento\b|\bsem papel\b/, valor: 'sem-documento' as const },
    { expressao: /\bcom documento\b|\bnotificad\w*\b|\bautuad\w*\b/, valor: 'com-documento' as const },
    { expressao: /\bde denuncia\b|\bdenuncia\b/, valor: 'de-denuncia' as const },
    { expressao: /\bavuls\w*\b|\brond\w*\b|\boperacao\b/, valor: 'avulsa' as const },
    { expressao: /\bcom recomendacao\b|\brecomendad\w*\b/, valor: 'com-recomendacao' as const },
];

/** O documento em uma linha: "Notificação nº 194903". */
function nomeDoDocumento(d: NonNullable<Registro['documento']>): string {
    return `${d.tipo === 'np' ? 'Notificação' : 'Apreensão'} nº ${d.numero}`;
}

export default function RetornoDeCampo({
    registros,
    estados,
    chefias,
    decide,
    areasDoChefe,
    recorteDeArea,
    alterada,
}: {
    registros: Registro[];
    estados: string[];
    chefias: Record<string, { nome: string; matricula: string | null }>;
    /** Esta pessoa DECIDE aqui, ou apenas acompanha? Quem responde é o servidor. */
    decide: boolean;
    areasDoChefe: string[];
    /** A listagem já veio recortada por essas áreas? Quem recorta é o servidor. */
    recorteDeArea: boolean;
    alterada: boolean;
}) {
    const { enviando, ocupado, enviar } = useEnvio();

    const [aba, setAba] = useState<Aba>('a-ler');
    const [busca, setBusca] = useState('');
    const [abertoId, setAbertoId] = useState<number | null>(null);
    const [marcados, setMarcados] = useState<number[]>([]);
    const [observacao, setObservacao] = useState('');
    const [justificativa, setJustificativa] = useState('');
    const [confirmandoVolta, setConfirmandoVolta] = useState(false);

    /*
     * O estado da FILA, e ele vem do servidor: o catálogo chega na ordem em que a
     * fila anda, e o primeiro é o que espera a leitura da chefia. Escrito na tela,
     * "Aguardando leitura" seria a mesma palavra com dois donos — e no dia em que
     * ela mudasse, a aba "A ler" ficaria vazia sem nada acusar.
     */
    const aguardando = estados[0];

    /** O Chefe de Setor de uma área, ou null quando a estrutura não registra nenhum. */
    const chefeDa = (area: string): string | null => {
        const nome = chefias[area]?.nome ?? '';

        return nome.trim() === '' ? null : nome;
    };

    // A ABA troca a FONTE: "A ler" é a fila propriamente dita, "Todos" é o
    // histórico do que a área devolveu. Não é filtro paralelo à busca — é outro
    // conjunto de partida, e é por isso que ela entra no contexto da exportação.
    const daAba = useMemo(
        () =>
            aba === 'a-ler'
                ? registros.filter((r) => r.estado === aguardando)
                : registros,
        [registros, aba, aguardando],
    );

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return daAba.filter((r) => {
            if (facetas.includes('sem-documento') && r.documento !== null) {
                return false;
            }

            if (facetas.includes('com-documento') && r.documento === null) {
                return false;
            }

            if (facetas.includes('de-denuncia') && r.denuncia_protocolo === null) {
                return false;
            }

            if (facetas.includes('avulsa') && r.denuncia_protocolo !== null) {
                return false;
            }

            if (facetas.includes('com-recomendacao') && r.recomendacoes.length === 0) {
                return false;
            }

            return casaTermos(termos, [
                r.protocolo,
                r.referencia,
                r.endereco,
                r.bairro,
                r.ponto_de_referencia,
                r.equipe,
                r.fiscal,
                r.area,
                r.desfecho,
                r.consideracoes,
                r.recomendacoes.join(' '),
                r.estado,
            ]);
        });
    }, [daAba, busca]);

    const ord = useOrdenacao(filtrados, {
        campo: 'concluida_em',
        dir: 'desc',
        acessor: 'concluida_em',
    });
    const pag = usePaginacao(ord.itens);

    /*
     * Trocar de aba, filtrar ou receber a lista de volta do servidor deixaria
     * marcado um registro que já não está à vista — e a decisão em lote alcançaria
     * o que a pessoa não está vendo. A seleção é do RECORTE VISÍVEL, e some com
     * ele.
     */
    useEffect(() => {
        setMarcados((atuais) =>
            atuais.filter((id) => filtrados.some((r) => r.id === id)),
        );
    }, [filtrados]);

    const selecionados = registros.filter((r) => marcados.includes(r.id));
    const podeDecidir = decide && selecionados.length > 0;

    function alternarMarca(id: number) {
        setMarcados((atuais) =>
            atuais.includes(id) ? atuais.filter((i) => i !== id) : [...atuais, id],
        );
    }

    /** Marca ou desmarca TODO o recorte filtrado — não só a página à vista. */
    function alternarTodos() {
        const doRecorte = filtrados.map((r) => r.id);
        const todosMarcados = doRecorte.every((id) => marcados.includes(id));

        setMarcados(todosMarcados ? [] : doRecorte);
    }

    function limpar() {
        setMarcados([]);
        setObservacao('');
        setJustificativa('');
    }

    function darCiencia() {
        enviar(
            'ciencia',
            ciencia().url,
            { ids: marcados, observacao: observacao.trim() || null },
            { onSuccess: limpar },
        );
    }

    function mandarVoltar() {
        enviar(
            'nova-vistoria',
            novaVistoria().url,
            { ids: marcados, justificativa },
            {
                onSuccess: () => {
                    setConfirmandoVolta(false);
                    limpar();
                },
                onError: () => setConfirmandoVolta(false),
            },
        );
    }

    const numeros = {
        total: registros.length,
        aLer: registros.filter((r) => r.estado === aguardando).length,
        comRecomendacao: registros.filter(
            (r) => r.estado === aguardando && r.recomendacoes.length > 0,
        ).length,
        comDocumento: registros.filter((r) => r.documento !== null).length,
    };

    // Só as chaves declaradas entram no arquivo, e a data sai em BR: o documento é
    // lido fora do sistema, onde ninguém traduz ISO.
    const linhasExportacao = ord.itens.map((r) => ({
        protocolo: r.protocolo,
        concluida_em: dataHoraBR(r.concluida_em),
        area: r.area || VAZIO,
        equipe: r.equipe || VAZIO,
        fiscal: r.fiscal,
        ponto: [r.endereco, r.bairro].filter(Boolean).join(' — '),
        desfecho: r.desfecho,
        documento: r.documento === null ? 'nenhum' : nomeDoDocumento(r.documento),
        recomendacoes: r.recomendacoes.length === 0 ? VAZIO : r.recomendacoes.join('; '),
        consideracoes: r.consideracoes ?? VAZIO,
        origem: r.referencia,
        estado: r.estado,
    }));

    return (
        <>
            <Head title="Retorno de Campo" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Fiscalização</p>
                    <h1>Retorno de Campo</h1>
                    <p>
                        Tudo que a equipe <strong>concluiu em rua</strong> volta para
                        cá, com o desfecho e a{' '}
                        <strong>recomendação do fiscal</strong> — é por ela que se
                        sabe se o ponto pede nova ida ou se o caso se encerra.
                    </p>

                    {/* Qual é o SEU papel aqui. O dono usa o selo para mostrar que
                        a mesma tela serve dois papéis: um decide, o outro acompanha. */}
                    <ul className="rt-chips">
                        {decide && (
                            <li className="rt-chip" style={{ color: 'var(--sm-primaria)' }}>
                                <span className="rt-chip-dot" />
                                {/* A ÁREA vai no selo: "você decide" sem dizer sobre o
                                    quê deixaria a chefia sem saber por que a fila é curta. */}
                                Sua fila
                                {areasDoChefe.length > 0
                                    ? ` · ${areasDoChefe.join(' e ')}`
                                    : ''}{' '}
                                — você dá ciência ou manda a equipe voltar
                            </li>
                        )}

                        {/* Chefe de Setor sem área vinculada: ele decide e não tem
                            sobre o quê. Dito na cara, e não em lista vazia sem
                            explicação — lista vazia parece sistema quebrado. */}
                        {decide && areasDoChefe.length === 0 && (
                            <li className="rt-chip" style={{ color: 'var(--sm-perigo)' }}>
                                <span className="rt-chip-dot" />
                                Sua conta não está vinculada a nenhuma área — procure
                                quem administra o sistema
                            </li>
                        )}

                        {!decide && (
                            <li className="rt-chip">
                                <span className="rt-chip-dot" />
                                Você acompanha o que a fiscalização devolveu; a decisão
                                é do Chefe de Setor da área
                            </li>
                        )}
                    </ul>
                </div>

                {/* Resumo da MESMA lista que a grade desenha. Clicar escreve a
                    faceta na busca: atalho sem criar um segundo filtro. */}
                <div className="rt-numeros">
                    <button
                        type="button"
                        className="rt-numero"
                        title={
                            recorteDeArea
                                ? 'Ver todos os retornos da sua área'
                                : 'Ver todos os retornos'
                        }
                        onClick={() => {
                            setBusca('');
                            setAba('todos');
                        }}
                    >
                        <strong>{numeros.total}</strong>
                        <span>{recorteDeArea ? 'da sua área' : 'concluídos'}</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero alerta"
                        title="Ver os que ainda esperam a leitura da chefia"
                        onClick={() => {
                            setBusca('');
                            setAba('a-ler');
                        }}
                    >
                        <strong>{numeros.aLer}</strong>
                        <span>a ler</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero info"
                        title="Ver os que esperam leitura e trazem recomendação do fiscal"
                        onClick={() => {
                            setAba('a-ler');
                            setBusca('com recomendação');
                        }}
                    >
                        <strong>{numeros.comRecomendacao}</strong>
                        <span>com recomendação</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero"
                        title="Ver os que tiveram documento lavrado"
                        onClick={() => {
                            setAba('todos');
                            setBusca('com documento');
                        }}
                    >
                        <strong>{numeros.comDocumento}</strong>
                        <span>com documento</span>
                    </button>
                </div>
            </div>

            <SeloPrototipo>
                Esta tela é a proposta da fila, para conferência da forma antes de
                virar sistema. Os retornos são de exemplo e{' '}
                <strong>nada é gravado</strong>: a ciência e o pedido de nova
                vistoria valem só nesta sessão do navegador.
            </SeloPrototipo>

            {/* O aviso que separa esta tela da Caixa de Entrada. Fica em cima, e
                não numa coluna da grade, porque é a natureza da tela inteira. */}
            <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                <Undo2 size={16} aria-hidden />
                <div>
                    <strong>
                        O trabalho VOLTANDO da rua — ninguém registra fiscalização
                        aqui.
                    </strong>
                    <div>
                        Quem registra é o fiscal, em rua, pelo aplicativo. Esta tela
                        é o outro lado da cadeia: o que chega em papel ao balcão é
                        assunto da <strong>Caixa de Entrada</strong>, e o que chega
                        das ouvidorias é assunto de <strong>Denúncias</strong>.
                    </div>
                </div>
            </div>

            {/* A lista da chefia NÃO é o universo, e a tela diz isso. */}
            {recorteDeArea && (
                <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                    <Info size={16} aria-hidden />
                    <div>
                        {/* A frase evita concordar com a lista de áreas de
                            propósito: "as equipes de Área 5 concluiu/concluíram"
                            erra o número em um dos dois casos, porque o sujeito é
                            "as equipes" e a lista é o complemento. */}
                        <strong>
                            Você está vendo só o que voltou de{' '}
                            {areasDoChefe.join(' e ')}.
                        </strong>
                        <div>
                            Os retornos das outras áreas não aparecem aqui — e a
                            decisão sobre registro de outra área é recusada pelo
                            sistema, não só escondida.
                        </div>
                    </div>
                </div>
            )}

            <div className="card-premium">
                {/* A ABA troca a FONTE dos dados (a fila × o histórico da área),
                    e por isso ela é aba e não chip de filtro — a busca continua
                    sendo o filtro único dentro do conjunto escolhido. */}
                <div className="abas" role="tablist" aria-label="Recorte da fila">
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'a-ler'}
                        onClick={() => setAba('a-ler')}
                    >
                        <Undo2 size={16} aria-hidden />
                        <span className="aba-rotulo">A ler ({numeros.aLer})</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'todos'}
                        onClick={() => setAba('todos')}
                    >
                        <ListChecks size={16} aria-hidden />
                        <span className="aba-rotulo">Todos ({numeros.total})</span>
                    </button>
                </div>

                <BuscaInteligente
                    busca={busca}
                    setBusca={setBusca}
                    placeholder="Procure por ponto, bairro, equipe, fiscal, desfecho ou o que o fiscal escreveu"
                    exemplos={[
                        'com recomendação',
                        'sem documento',
                        'de denúncia',
                        'ronda',
                    ]}
                />

                {/* O painel de DECISÃO só existe com seleção, e só para quem decide:
                    oferecer o botão a quem o servidor recusa é prometer o que a tela
                    não entrega. */}
                {podeDecidir && (
                    <div className="rt-escolha" style={{ marginBottom: 16 }}>
                        <div className="card-premium" style={{ margin: 0 }}>
                            <h3 className="card-titulo">
                                <Check size={16} aria-hidden /> Dar ciência
                            </h3>
                            <p className="card-sub">
                                O retorno sai da sua fila.{' '}
                                {contar(selecionados.length, 'registro', 'registros')}{' '}
                                {plural(selecionados.length, 'selecionado', 'selecionados')}.
                            </p>

                            <div className="form-group">
                                <label className="form-label" htmlFor="observacao">
                                    Observação (opcional)
                                </label>
                                <textarea
                                    id="observacao"
                                    className="form-control"
                                    rows={2}
                                    maxLength={1000}
                                    value={observacao}
                                    onChange={(e) => setObservacao(e.target.value)}
                                    placeholder="O que você quer que fique registrado na leitura"
                                />
                                <p className="form-ajuda">
                                    Opcional de propósito: o ato de ler já é a
                                    informação, e exigir texto para dar ciência de
                                    vários faria escrever frases vazias.
                                </p>
                            </div>

                            <BotaoAcao
                                icone={<Check size={16} aria-hidden />}
                                carregando={enviando === 'ciencia'}
                                ocupado={ocupado}
                                rotuloCarregando="Registrando…"
                                onClick={darCiencia}
                            >
                                Dar ciência
                            </BotaoAcao>
                        </div>

                        <div className="card-premium" style={{ margin: 0 }}>
                            <h3 className="card-titulo">
                                <RotateCcw size={16} aria-hidden /> Mandar a equipe voltar
                            </h3>
                            <p className="card-sub">
                                O ponto volta para a equipe, com o que ela deve
                                procurar desta vez.
                            </p>

                            <div className="form-group">
                                <label className="form-label" htmlFor="justificativa">
                                    Justificativa
                                </label>
                                <textarea
                                    id="justificativa"
                                    className="form-control"
                                    rows={3}
                                    maxLength={1000}
                                    value={justificativa}
                                    onChange={(e) => setJustificativa(e.target.value)}
                                    placeholder="O que a equipe deve procurar, e em que dia ou horário"
                                />
                                <p className="form-ajuda">
                                    Obrigatória: mandar a equipe de volta gasta o
                                    trabalho dela outra vez, e &quot;voltar lá&quot;
                                    não diz o que procurar.
                                </p>
                            </div>

                            <BotaoAcao
                                icone={<RotateCcw size={16} aria-hidden />}
                                carregando={enviando === 'nova-vistoria'}
                                ocupado={ocupado}
                                disabled={justificativa.trim().length < 15}
                                rotuloCarregando="Devolvendo…"
                                onClick={() => setConfirmandoVolta(true)}
                            >
                                Determinar nova vistoria
                            </BotaoAcao>
                        </div>
                    </div>
                )}

                <div style={{ display: 'flex', alignItems: 'center', marginBottom: 10, gap: 12 }}>
                    {marcados.length > 0 && (
                        <p className="form-ajuda" style={{ margin: 0 }}>
                            {contar(marcados.length, 'registro', 'registros')}{' '}
                            {plural(marcados.length, 'selecionado', 'selecionados')}.
                        </p>
                    )}

                    <BotaoExportar
                        titulo="Retorno de Campo"
                        subtitulo="Fiscalização › Retorno de Campo"
                        contexto={[
                            `Aba: ${aba === 'a-ler' ? 'A ler' : 'Todos'}`,
                            recorteDeArea
                                ? `Áreas: ${areasDoChefe.join(' e ')}`
                                : 'Todas as áreas',
                            busca.trim() ? `busca: "${busca.trim()}"` : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                        colunas={[
                            { chave: 'protocolo', titulo: 'Registro' },
                            { chave: 'concluida_em', titulo: 'Concluída em' },
                            { chave: 'area', titulo: 'Área' },
                            { chave: 'equipe', titulo: 'Equipe' },
                            { chave: 'fiscal', titulo: 'Fiscal' },
                            { chave: 'ponto', titulo: 'Ponto' },
                            { chave: 'desfecho', titulo: 'Desfecho' },
                            { chave: 'documento', titulo: 'Documento' },
                            { chave: 'recomendacoes', titulo: 'Recomendação do fiscal' },
                            { chave: 'consideracoes', titulo: 'Considerações do fiscal' },
                            { chave: 'origem', titulo: 'Origem' },
                            { chave: 'estado', titulo: 'Estado' },
                        ]}
                        linhas={linhasExportacao}
                    />
                </div>

                <div className="table-wrap">
                    <table className="data-table">
                        <thead>
                            <tr>
                                {decide && (
                                    <th style={{ width: 34 }}>
                                        <input
                                            type="checkbox"
                                            aria-label="Selecionar todos os registros filtrados"
                                            title="Selecionar todos os registros do filtro — não só os desta página"
                                            checked={
                                                filtrados.length > 0 &&
                                                filtrados.every((r) =>
                                                    marcados.includes(r.id),
                                                )
                                            }
                                            onChange={alternarTodos}
                                        />
                                    </th>
                                )}
                                <ThOrdenavel campo="concluida_em" acessor="concluida_em" ord={ord}>
                                    Concluída
                                </ThOrdenavel>
                                <ThOrdenavel campo="endereco" acessor="endereco" ord={ord}>
                                    Ponto
                                </ThOrdenavel>
                                <ThOrdenavel campo="equipe" acessor="equipe" ord={ord}>
                                    Equipe e fiscal
                                </ThOrdenavel>
                                <ThOrdenavel campo="desfecho" acessor="desfecho" ord={ord}>
                                    Desfecho
                                </ThOrdenavel>
                                {/* A coluna que decide: ela é o motivo de a fila
                                    existir, então tem lugar próprio na grade. */}
                                <th>Recomendação do fiscal</th>
                                <ThOrdenavel campo="estado" acessor="estado" ord={ord}>
                                    Estado
                                </ThOrdenavel>
                            </tr>
                        </thead>

                        <tbody>
                            {pag.visiveis.length === 0 && (
                                <tr>
                                    <td colSpan={decide ? 7 : 6} className="tabela-vazia">
                                        {registros.length === 0
                                            ? 'Nenhum retorno de campo por aqui ainda. Quando a equipe concluir uma fiscalização, ela aparece nesta fila.'
                                            : aba === 'a-ler' && busca.trim() === ''
                                              ? 'Nada a ler: todos os retornos desta fila já foram lidos. Veja a aba “Todos” para o histórico.'
                                              : 'Nenhum registro casa com a busca. Limpe o campo para ver a fila inteira.'}
                                    </td>
                                </tr>
                            )}

                            {pag.visiveis.map((r) => (
                                <Fragment key={r.id}>
                                    <tr
                                        {...linhaClicavel(
                                            () =>
                                                setAbertoId(
                                                    abertoId === r.id ? null : r.id,
                                                ),
                                            'Abrir ou fechar o que o fiscal registrou neste ponto',
                                            r.estado === aguardando && 'pendente',
                                        )}
                                    >
                                        {decide && (
                                            <td
                                                onClick={(e) => e.stopPropagation()}
                                                onKeyDown={(e) => e.stopPropagation()}
                                            >
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Selecionar o registro ${r.protocolo}`}
                                                    checked={marcados.includes(r.id)}
                                                    onChange={() => alternarMarca(r.id)}
                                                />
                                            </td>
                                        )}
                                        <td className="cell-id">
                                            {dataHoraBR(r.concluida_em)}
                                            {r.dias_parado !== null && r.dias_parado > 0 && (
                                                <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                                    há {contar(r.dias_parado, 'dia', 'dias')} na fila
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            {r.endereco}
                                            <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                                {r.bairro}
                                                {r.area ? ` · ${r.area}` : ''}
                                            </div>
                                        </td>
                                        <td>
                                            {r.equipe ? `Equipe ${r.equipe}` : VAZIO}
                                            <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                                {r.fiscal}
                                            </div>
                                        </td>
                                        <td>
                                            {r.desfecho}
                                            {r.documento !== null && (
                                                <div>
                                                    <span className="selo selo-aviso">
                                                        <FileText size={11} aria-hidden />{' '}
                                                        {nomeDoDocumento(r.documento)}
                                                    </span>
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            {r.recomendacoes.length === 0 ? (
                                                <span style={{ color: 'var(--sm-texto-fraco)' }}>
                                                    o fiscal não recomendou nada
                                                </span>
                                            ) : (
                                                r.recomendacoes.map((rec) => (
                                                    <span
                                                        key={rec}
                                                        className="selo selo-info"
                                                        style={{ marginRight: 6, marginBottom: 4 }}
                                                    >
                                                        <Lightbulb size={11} aria-hidden /> {rec}
                                                    </span>
                                                ))
                                            )}
                                        </td>
                                        <td>
                                            <span
                                                className={cn(
                                                    'selo',
                                                    TOM_DO_ESTADO[r.estado] ?? 'selo-neutro',
                                                )}
                                            >
                                                {r.estado}
                                            </span>
                                        </td>
                                    </tr>

                                    {abertoId === r.id && (
                                        <tr>
                                            <td colSpan={decide ? 7 : 6}>
                                                <dl className="rt-ficha">
                                                    <div>
                                                        <dt>Registro</dt>
                                                        <dd>{r.protocolo}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Origem da ida ao ponto</dt>
                                                        <dd>
                                                            {r.origem} · {r.referencia}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt>Área e chefia</dt>
                                                        <dd>
                                                            {r.area || VAZIO}
                                                            {chefeDa(r.area) === null
                                                                ? ''
                                                                : ` · ${chefeDa(r.area)}`}
                                                        </dd>
                                                    </div>
                                                    {r.situacao_da_origem !== null && (
                                                        <div>
                                                            <dt>Situação da denúncia</dt>
                                                            <dd>{r.situacao_da_origem}</dd>
                                                        </div>
                                                    )}
                                                    {r.ponto_de_referencia !== null && (
                                                        <div style={{ gridColumn: '1 / -1' }}>
                                                            <dt>Ponto de referência</dt>
                                                            <dd>{r.ponto_de_referencia}</dd>
                                                        </div>
                                                    )}
                                                </dl>

                                                {/* A coordenada vem SEMPRE com a
                                                    precisão: um ponto ruim é pior que
                                                    um ponto ausente disfarçado de bom. */}
                                                {r.gps !== null && (
                                                    <p className="form-ajuda" style={{ marginTop: 8 }}>
                                                        <MapPin size={14} aria-hidden /> {r.gps}
                                                        {r.precisao_m !== null
                                                            ? ` · precisão de ±${r.precisao_m} m`
                                                            : ''}
                                                    </p>
                                                )}

                                                <div className="rt-sugestao" style={{ marginTop: 12 }}>
                                                    <Lightbulb size={16} aria-hidden />
                                                    <div>
                                                        <strong>
                                                            {r.recomendacoes.length === 0
                                                                ? 'Considerações finais do fiscal'
                                                                : `${plural(r.recomendacoes.length, 'Recomendação', 'Recomendações')} do fiscal`}
                                                        </strong>

                                                        {r.recomendacoes.length > 0 && (
                                                            <div style={{ margin: '6px 0 2px' }}>
                                                                {r.recomendacoes.map((rec) => (
                                                                    <span
                                                                        key={rec}
                                                                        className="selo selo-info"
                                                                        style={{
                                                                            marginRight: 6,
                                                                            marginBottom: 4,
                                                                        }}
                                                                    >
                                                                        {rec}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        )}

                                                        <div>
                                                            {r.consideracoes ?? (
                                                                <span
                                                                    style={{
                                                                        color: 'var(--sm-texto-fraco)',
                                                                    }}
                                                                >
                                                                    O fiscal não escreveu
                                                                    considerações neste retorno.
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                {r.denuncia_protocolo !== null && (
                                                    <p className="form-ajuda" style={{ marginTop: 10 }}>
                                                        <Camera size={14} aria-hidden /> O percurso
                                                        inteiro — relato, fotos e o documento
                                                        lavrado — está no trâmite da denúncia{' '}
                                                        <strong>{r.denuncia_protocolo}</strong>, em
                                                        Denúncias.
                                                    </p>
                                                )}

                                                {r.decisao !== null && (
                                                    <p className="form-ajuda" style={{ marginTop: 10 }}>
                                                        <UserRound size={14} aria-hidden />{' '}
                                                        <strong>{r.decisao.o_que}</strong> ·{' '}
                                                        {dataHoraBR(r.decisao.em)} ·{' '}
                                                        {r.decisao.quem} — {r.decisao.detalhe}
                                                    </p>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacao {...pag.props} />
            </div>

            {/* Reiniciar existe porque é PROTÓTIPO: quem demonstra precisa poder
                recomeçar a cena. Só aparece depois de a sessão ter decidido algo. */}
            {alterada && (
                <p className="form-ajuda" style={{ marginTop: 14 }}>
                    <BotaoAcao
                        className="btn btn-secondary btn-sm"
                        icone={<RotateCcw size={15} aria-hidden />}
                        carregando={enviando === 'reiniciar'}
                        ocupado={ocupado}
                        rotuloCarregando="Reiniciando…"
                        onClick={() => enviar('reiniciar', reiniciar().url, {}, { onSuccess: limpar })}
                    >
                        Reiniciar a demonstração
                    </BotaoAcao>
                </p>
            )}

            {confirmandoVolta && (
                <ModalConfirm
                    titulo="Mandar a equipe voltar ao ponto?"
                    mensagem={
                        <>
                            {contar(selecionados.length, 'registro', 'registros')}{' '}
                            {plural(selecionados.length, 'volta', 'voltam')} para a
                            equipe com a sua justificativa. Isso gasta o trabalho dela
                            outra vez — confirme se é mesmo caso de nova ida.
                        </>
                    }
                    rotuloConfirmar="Determinar nova vistoria"
                    iconeConfirmar={<RotateCcw size={16} aria-hidden />}
                    processando={enviando === 'nova-vistoria'}
                    onCancelar={() => setConfirmandoVolta(false)}
                    onConfirmar={mandarVoltar}
                />
            )}
        </>
    );
}

RetornoDeCampo.layout = {
    breadcrumbs: [{ title: 'Retorno de Campo', href: index() }],
};
