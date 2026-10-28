/**
 * Saúde dos Fluxos (Sistema › Saúde dos Fluxos, só o administrador) — pedido do dono, 27/10/2026.
 *
 * O fluxograma do trabalho do SEFAL de ponta a ponta: da chegada da demanda, por cada canal, até cada
 * destino final, passando pela Retaguarda e pelo aplicativo do fiscal. Cada quadradinho diz se o
 * trecho é ATENDIDO hoje pelo sistema ou só PREVISTO, e abre o passo a passo daquele trecho.
 *
 * Veio do CODECON com a mesma estrutura: a aba de entrada é a VISÃO GERAL, com todos os fluxos do
 * catálogo, e cada fluxo tem a sua aba. No SEFAL há um fluxo só. Lá os quadradinhos também ficam
 * vermelhos quando uma verificação do ambiente falha; aqui essa parte ainda não existe.
 */
import { Head, router } from '@inertiajs/react';
import { FileText, LayoutDashboard, Waypoints, X } from 'lucide-react';
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import BotaoExportar, { type ColunaExportacao } from '@/components/retaguarda/exportar';
import { COR_ESTADO, FluxogramaSaude, type ArestaFluxo, type EstadoNo, type NoFluxo } from '@/components/retaguarda/fluxograma-saude';
import { exportar, index } from '@/routes/retaguarda/saude-dos-fluxos';

interface No extends NoFluxo {
    descricao: string;
    situacao: 'atendido' | 'previsto';
    /** Onde o trecho acontece: a tela da Retaguarda, do aplicativo ou o canal de fora. */
    tela: string;
}

interface Etapa {
    setor: string;
    titulo: string;
    nos: string[];
    itens: string[];
}

interface Fluxo {
    chave: string;
    titulo: string;
    subtitulo: string;
    resumo: string;
    nos: No[];
    arestas: ArestaFluxo[];
    etapas: Etapa[];
    conclusoes: { conclusao: string; quem: string; caminho: string }[];
}

interface LinhaVisao {
    chave: string;
    titulo: string;
    subtitulo: string;
    total_trechos: number;
    atendidos: number;
    previstos: string[];
}

type Aba = { chave: string; titulo: string };

interface PropsVisao {
    abas: Aba[];
    fluxoAtual: null;
    visaoGeral: LinhaVisao[];
}

interface Props {
    abas: Aba[];
    fluxoAtual: string;
    visaoGeral: null;
    fluxo: Fluxo;
}

const VISAO_GERAL = '__visao_geral__';

/** O negrito do catálogo (`**texto**`) vira <b>; nada de HTML cru. */
function comNegrito(texto: string) {
    return texto
        .split(/(\*\*[^*]+\*\*)/g)
        .map((parte, i) => (parte.startsWith('**') && parte.endsWith('**') ? <b key={i}>{parte.slice(2, -2)}</b> : <Fragment key={i}>{parte}</Fragment>));
}

const rotuloEmLinha = (rotulo: string) => rotulo.replace('\n', ' — ');

/** Abas: a Visão geral e um fluxo por aba. É por elas que cada fluxo novo do catálogo entra. */
function Abas({ abas, atual }: { abas: Aba[]; atual: string }) {
    const todas = [{ chave: VISAO_GERAL, titulo: 'Visão geral' }, ...abas];

    return (
        <div className="abas" role="tablist" aria-label="Fluxos" style={{ marginBottom: 14 }}>
            {todas.map((a) => (
                <button
                    key={a.chave}
                    type="button"
                    role="tab"
                    className="aba"
                    data-aba={a.chave}
                    aria-selected={a.chave === atual}
                    onClick={() => router.get(index.url(), a.chave === VISAO_GERAL ? {} : { fluxo: a.chave })}
                >
                    {a.chave === VISAO_GERAL ? <LayoutDashboard size={16} aria-hidden /> : <Waypoints size={16} aria-hidden />}
                    <span className="aba-rotulo">{a.titulo}</span>
                </button>
            ))}
        </div>
    );
}

function Cabecalho({ children }: { children?: React.ReactNode }) {
    return (
        <div className="rt-page-head">
            <div>
                <p className="sobrancelha">Sistema</p>
                <h1>Saúde dos Fluxos</h1>
                <p>
                    O caminho do trabalho de ponta a ponta: da chegada da demanda, por cada canal, até cada destino final, passando pela
                    Retaguarda e pelo aplicativo do fiscal. Contorno cheio é o que o sistema <strong>atende hoje</strong>; cinza pontilhado é o que está{' '}
                    <strong>previsto</strong>. Clique num quadradinho para ver o passo a passo daquele trecho.
                </p>
            </div>
            {children}
        </div>
    );
}

export default function SaudeDosFluxos(props: Props | PropsVisao) {
    return props.fluxoAtual === null ? <VisaoGeral {...props} /> : <PainelDoFluxo {...props} />;
}

const COLUNAS_VISAO: ColunaExportacao[] = [
    { chave: 'fluxo', titulo: 'Fluxo' },
    { chave: 'trechos', titulo: 'Trechos' },
    { chave: 'previstos', titulo: 'Ainda previstos' },
];

const resumoTrechos = (l: LinhaVisao) =>
    `${l.atendidos} de ${l.total_trechos} atendidos hoje${l.previstos.length > 0 ? ` · ${l.previstos.length} previsto${l.previstos.length === 1 ? '' : 's'}` : ''}`;

/** A aba de entrada: todos os fluxos do catálogo. */
function VisaoGeral({ abas, visaoGeral }: PropsVisao) {
    const linhasExportacao = visaoGeral.map((l) => ({
        fluxo: l.titulo,
        trechos: resumoTrechos(l),
        previstos: l.previstos.join('; ') || '—',
    }));

    return (
        <>
            <Head title="Saúde dos Fluxos" />
            <Cabecalho />
            <Abas abas={abas} atual={VISAO_GERAL} />

            <div className="card-premium" data-bloco="visao-geral">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 10 }}>
                    <p className="form-ajuda" style={{ margin: 0 }}>
                        {visaoGeral.length} {visaoGeral.length === 1 ? 'fluxo desenhado' : 'fluxos desenhados'}. Abra um fluxo para ver o fluxograma e o passo a passo.
                    </p>
                    <BotaoExportar
                        titulo="Saúde dos Fluxos"
                        subtitulo="Sistema › Saúde dos Fluxos"
                        contexto="Visão geral: todos os fluxos desenhados"
                        colunas={COLUNAS_VISAO}
                        linhas={linhasExportacao}
                    />
                </div>

                <div className="table-wrap">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Fluxo</th>
                                <th>Trechos</th>
                                <th style={{ minWidth: 240 }}>Ainda previstos</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {visaoGeral.map((l) => (
                                <tr key={l.chave} data-fluxo={l.chave}>
                                    <td>
                                        <div style={{ fontWeight: 700 }}>{l.titulo}</div>
                                        <div className="form-ajuda" style={{ margin: 0 }}>{l.subtitulo}</div>
                                    </td>
                                    <td>{resumoTrechos(l)}</td>
                                    <td>
                                        {l.previstos.length === 0 ? (
                                            <span className="form-ajuda">Nenhum.</span>
                                        ) : (
                                            <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                                                {l.previstos.map((t) => (
                                                    <span
                                                        key={t}
                                                        data-chip-previsto
                                                        style={{
                                                            fontSize: 11.5,
                                                            lineHeight: 1.35,
                                                            padding: '2px 8px',
                                                            borderRadius: 8,
                                                            background: COR_ESTADO.previsto.fundo,
                                                            border: `1px dotted ${COR_ESTADO.previsto.traco}`,
                                                        }}
                                                    >
                                                        {t}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </td>
                                    <td style={{ whiteSpace: 'nowrap' }}>
                                        <button type="button" className="btn btn-secondary btn-sm" data-abrir-fluxo={l.chave} onClick={() => router.get(index.url(), { fluxo: l.chave })}>
                                            <Waypoints size={15} aria-hidden /> Abrir
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function PainelDoFluxo({ abas, fluxoAtual, fluxo }: Props) {
    const [selecionado, setSelecionado] = useState<string | null>(null);
    const painel = useRef<HTMLElement>(null);

    // Esc fecha o painel do quadradinho.
    useEffect(() => {
        const fechar = (e: KeyboardEvent) => e.key === 'Escape' && setSelecionado(null);
        window.addEventListener('keydown', fechar);

        return () => window.removeEventListener('keydown', fechar);
    }, []);

    // Clique FORA do painel também fecha. Clicar em outro quadradinho (ou num atalho de trecho do
    // passo a passo) troca o trecho em vez de fechar.
    useEffect(() => {
        if (selecionado === null) {
            return;
        }
        const fora = (e: PointerEvent) => {
            const alvo = e.target instanceof Element ? e.target : null;
            if (!alvo || painel.current?.contains(alvo) || alvo.closest('[data-no],[data-abre-no]')) {
                return;
            }
            setSelecionado(null);
        };
        document.addEventListener('pointerdown', fora);

        return () => document.removeEventListener('pointerdown', fora);
    }, [selecionado]);

    const estados = useMemo(() => Object.fromEntries(fluxo.nos.map((n) => [n.id, n.situacao as EstadoNo])), [fluxo.nos]);
    const previstos = fluxo.nos.filter((n) => n.situacao === 'previsto').length;
    const no = selecionado ? (fluxo.nos.find((n) => n.id === selecionado) ?? null) : null;
    const etapaDoNo = no ? (fluxo.etapas.find((e) => e.nos.includes(no.id)) ?? null) : null;

    return (
        <>
            <Head title={`Saúde dos Fluxos — ${fluxo.titulo}`} />
            <Cabecalho>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                    <a className="btn btn-secondary btn-sm" href={exportar.url(fluxoAtual)} data-acao="exportar-texto" title="O passo a passo em texto (Markdown), para guardar ou repassar">
                        <FileText size={15} aria-hidden /> Exportar texto
                    </a>
                </div>
            </Cabecalho>
            <Abas abas={abas} atual={fluxoAtual} />

            {/* O fluxograma. */}
            <div className="card-premium" data-bloco="fluxograma" style={{ marginBottom: 14 }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap', marginBottom: 8 }}>
                    <div style={{ minWidth: 260, flex: 1 }}>
                        <div style={{ fontWeight: 700, fontSize: 15 }}>{fluxo.subtitulo}</div>
                        <p className="form-ajuda" style={{ margin: '2px 0 0' }}>{fluxo.resumo}</p>
                    </div>
                    <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', fontSize: 12.5 }} data-bloco="legenda">
                        {(['atendido', 'previsto'] as const).map((e) => (
                            <span key={e} data-contagem={e} style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                <i
                                    style={{
                                        width: 11,
                                        height: 11,
                                        borderRadius: 3,
                                        display: 'inline-block',
                                        background: COR_ESTADO[e].fundo,
                                        border: `1.5px ${e === 'previsto' ? 'dotted' : 'solid'} ${COR_ESTADO[e].traco}`,
                                    }}
                                />
                                {COR_ESTADO[e].rotulo}: <b>{e === 'previsto' ? previstos : fluxo.nos.length - previstos}</b>
                            </span>
                        ))}
                    </div>
                </div>
                <FluxogramaSaude nos={fluxo.nos} arestas={fluxo.arestas} estados={estados} selecionado={selecionado} onSelecionar={setSelecionado} />
            </div>

            {/* Passo a passo completo. */}
            <div className="card-premium" data-bloco="passo-a-passo" style={{ marginBottom: 14 }}>
                <h2 style={{ fontSize: 17, margin: '0 0 10px' }}>Passo a passo</h2>
                <ol style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 16 }}>
                    {fluxo.etapas.map((etapa, i) => (
                        <li key={i} style={{ display: 'grid', gridTemplateColumns: '30px 1fr', gap: '0 12px' }}>
                            <span
                                style={{
                                    width: 26,
                                    height: 26,
                                    borderRadius: '50%',
                                    background: 'var(--sm-primaria)',
                                    color: '#fff',
                                    fontWeight: 700,
                                    fontSize: 13,
                                    display: 'grid',
                                    placeItems: 'center',
                                }}
                            >
                                {i + 1}
                            </span>
                            <div style={{ minWidth: 0 }}>
                                <div className="sobrancelha" style={{ margin: 0 }}>{etapa.setor}</div>
                                <div style={{ fontWeight: 700, margin: '1px 0 4px' }}>{etapa.titulo}</div>
                                <ul style={{ margin: 0, paddingLeft: 18, display: 'flex', flexDirection: 'column', gap: 3, fontSize: 13.5 }}>
                                    {etapa.itens.map((item, j) => (
                                        <li key={j}>{comNegrito(item)}</li>
                                    ))}
                                </ul>
                                {etapa.nos.length > 0 && (
                                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 6 }}>
                                        {etapa.nos.map((id) => {
                                            const n = fluxo.nos.find((x) => x.id === id);
                                            const e = estados[id] ?? 'atendido';

                                            return n ? (
                                                <button
                                                    key={id}
                                                    type="button"
                                                    data-abre-no={id}
                                                    onClick={() => setSelecionado(id)}
                                                    title="Ver este trecho"
                                                    style={{
                                                        maxWidth: '100%',
                                                        fontSize: 11.5,
                                                        lineHeight: 1.35,
                                                        padding: '2px 8px',
                                                        borderRadius: 8,
                                                        overflowWrap: 'anywhere',
                                                        textAlign: 'left',
                                                        cursor: 'pointer',
                                                        background: COR_ESTADO[e].fundo,
                                                        border: `1px ${e === 'previsto' ? 'dotted' : 'solid'} ${COR_ESTADO[e].traco}`,
                                                        color: 'var(--sm-texto)',
                                                    }}
                                                >
                                                    {n.rotulo.split('\n')[0]}
                                                </button>
                                            ) : null;
                                        })}
                                    </div>
                                )}
                            </div>
                        </li>
                    ))}
                </ol>
            </div>

            <div className="card-premium" data-bloco="conclusoes">
                <h2 style={{ fontSize: 17, margin: '0 0 10px' }}>Todas as formas de conclusão</h2>
                <div className="table-wrap">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Conclusão</th>
                                <th>Quem encerra</th>
                                <th>Caminho</th>
                            </tr>
                        </thead>
                        <tbody>
                            {fluxo.conclusoes.map((c, i) => (
                                <tr key={i}>
                                    <td>{i + 1}</td>
                                    <td>{c.conclusao}</td>
                                    <td>{c.quem}</td>
                                    <td>{c.caminho}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Painel do quadradinho. */}
            {no && (
                <aside
                    ref={painel}
                    data-painel-no={no.id}
                    role="dialog"
                    aria-label={`Trecho: ${rotuloEmLinha(no.rotulo)}`}
                    style={{
                        position: 'fixed',
                        top: 0,
                        right: 0,
                        bottom: 0,
                        width: 'min(420px, 100vw)',
                        background: 'var(--sm-superficie)',
                        borderLeft: '1px solid var(--sm-borda)',
                        boxShadow: 'var(--sm-sombra-lg)',
                        zIndex: 60,
                        overflowY: 'auto',
                        padding: '0 20px 18px',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'flex-start',
                            gap: 10,
                            position: 'sticky',
                            top: 0,
                            background: 'var(--sm-superficie)',
                            padding: '18px 0 10px',
                            borderBottom: '1px solid var(--sm-borda)',
                            zIndex: 1,
                        }}
                    >
                        <div>
                            <div className="sobrancelha" style={{ margin: 0 }}>{no.setor}</div>
                            <h2 style={{ fontSize: 17, margin: '2px 0 6px' }}>{rotuloEmLinha(no.rotulo)}</h2>
                            <span
                                data-situacao={no.situacao}
                                style={{
                                    fontSize: 12,
                                    fontWeight: 700,
                                    padding: '2px 9px',
                                    borderRadius: 999,
                                    background: COR_ESTADO[no.situacao].fundo,
                                    border: `1px ${no.situacao === 'previsto' ? 'dotted' : 'solid'} ${COR_ESTADO[no.situacao].traco}`,
                                }}
                            >
                                {COR_ESTADO[no.situacao].rotulo}
                            </span>
                        </div>
                        <button type="button" className="btn btn-secondary btn-sm" onClick={() => setSelecionado(null)} aria-label="Fechar">
                            <X size={15} aria-hidden />
                        </button>
                    </div>

                    <p style={{ fontSize: 13.5, marginTop: 12 }}>{no.descricao}</p>
                    {no.tela && (
                        <p className="form-ajuda" data-tela-do-no>
                            <strong>Onde:</strong> {no.tela}
                        </p>
                    )}

                    {etapaDoNo && (
                        <div style={{ fontSize: 13, border: '1px solid var(--sm-borda)', borderRadius: 10, padding: '10px 12px', marginTop: 12 }}>
                            <div style={{ fontWeight: 700, marginBottom: 4 }}>
                                {etapaDoNo.setor} — {etapaDoNo.titulo}
                            </div>
                            <ul style={{ margin: 0, paddingLeft: 18, display: 'flex', flexDirection: 'column', gap: 3 }}>
                                {etapaDoNo.itens.map((item, j) => (
                                    <li key={j}>{comNegrito(item)}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </aside>
            )}
        </>
    );
}
