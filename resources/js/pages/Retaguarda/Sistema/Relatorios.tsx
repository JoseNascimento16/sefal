import { Head } from '@inertiajs/react';
import {
    FileBarChart,
    FileSpreadsheet,
    FileText,
    FileType2,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { Spinner } from '@/components/retaguarda/acao';
import { baixarDocumento } from '@/lib/baixar-documento';
import { gerar, index } from '@/routes/retaguarda/relatorios';

/**
 * Relatórios — o catálogo e a emissão.
 *
 * Esta tela NÃO conhece relatório nenhum por dentro: ela desenha o que o servidor
 * descreve (título, descrição, filtros e modos) e devolve os valores
 * preenchidos. Relatório novo aparece aqui pela própria existência no catálogo,
 * sem uma linha de front — se cada um precisasse do seu formulário escrito à mão,
 * a tela viraria uma lista de exceções.
 *
 * ⚠️ Relatório é documento OFICIAL, pedido de propósito. Exportar a grade que
 * está na tela é outra coisa: isso é o `<BotaoExportar>` de cada listagem.
 */

interface FiltroDoRelatorio {
    nome: string;
    label: string;
    tipo: 'texto' | 'data' | 'select' | 'numero';
    obrigatorio: boolean;
    opcoes: { valor: string; rotulo: string }[];
    padrao: string | null;
    ajuda: string | null;
}

interface RelatorioDoCatalogo {
    chave: string;
    titulo: string;
    grupo: string;
    descricao: string;
    modos: string[];
    formatos: string[];
    filtros: FiltroDoRelatorio[];
}

type Formato = 'pdf' | 'xlsx' | 'docx';

const FORMATOS: {
    chave: Formato;
    rotulo: string;
    Icone: typeof FileText;
    cor: string;
}[] = [
    { chave: 'pdf', rotulo: 'PDF', Icone: FileText, cor: '#b3261e' },
    { chave: 'xlsx', rotulo: 'EXCEL', Icone: FileSpreadsheet, cor: '#0f7a52' },
    { chave: 'docx', rotulo: 'WORD', Icone: FileType2, cor: '#0b6f8c' },
];

/** Como cada modo se chama para quem emite — jargão de sistema não explica nada. */
const MODOS: Record<string, string> = {
    analitico: 'Analítico (relação nominal)',
    sintetico: 'Sintético (resumo)',
    gerencial: 'Gerencial (quadros e gráfico)',
};

export default function Relatorios({
    relatorios,
}: {
    relatorios: RelatorioDoCatalogo[];
}) {
    const [escolhido, setEscolhido] = useState<RelatorioDoCatalogo | null>(
        relatorios[0] ?? null,
    );
    const [modo, setModo] = useState<string>(relatorios[0]?.modos[0] ?? '');
    const [valores, setValores] = useState<Record<string, string>>({});
    const [gerando, setGerando] = useState<Formato | null>(null);
    const [erro, setErro] = useState<string | null>(null);

    function escolher(relatorio: RelatorioDoCatalogo) {
        setEscolhido(relatorio);
        setModo(relatorio.modos[0] ?? '');
        // Os filtros são de cada relatório: manter os do anterior mandaria ao
        // servidor um recorte que a tela não está mais mostrando.
        setValores({});
        setErro(null);
    }

    async function emitir(formato: Formato) {
        if (!escolhido) {
            return;
        }

        setGerando(formato);
        setErro(null);

        const resultado = await baixarDocumento(
            gerar().url,
            { chave: escolhido.chave, formato, modo, filtros: valores },
            `${escolhido.titulo}.${formato}`,
        );

        setGerando(null);

        if (!resultado.ok) {
            setErro(resultado.mensagem);
        }
    }

    return (
        <>
            <Head title="Relatórios" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Relatórios</h1>
                    <p>
                        Documentos oficiais da operação: escolha o relatório,
                        informe o recorte e emita em PDF, Excel ou Word.
                    </p>
                </div>
            </div>

            {relatorios.length === 0 ? (
                <div className="card-premium">
                    <p className="card-titulo">Nenhum relatório disponível</p>
                    <p className="card-sub">
                        Os relatórios entram aqui à medida que são construídos.
                    </p>
                </div>
            ) : (
                <div
                    style={{
                        display: 'grid',
                        gap: 20,
                        gridTemplateColumns: 'minmax(240px, 320px) 1fr',
                        alignItems: 'start',
                    }}
                >
                    <section className="card-premium">
                        <p className="card-titulo">
                            <FileBarChart size={18} aria-hidden /> Catálogo
                        </p>
                        <p className="card-sub" style={{ marginBottom: 14 }}>
                            {relatorios.length} relatório(s) disponível(is).
                        </p>

                        <ul
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                                listStyle: 'none',
                                padding: 0,
                                margin: 0,
                            }}
                        >
                            {relatorios.map((relatorio) => {
                                const ativo =
                                    escolhido?.chave === relatorio.chave;

                                return (
                                    <li key={relatorio.chave}>
                                        <button
                                            type="button"
                                            onClick={() => escolher(relatorio)}
                                            aria-current={ativo || undefined}
                                            style={{
                                                width: '100%',
                                                textAlign: 'left',
                                                padding: '10px 12px',
                                                borderRadius:
                                                    'var(--sm-raio-sm)',
                                                border: '1px solid',
                                                borderColor: ativo
                                                    ? 'var(--sm-primaria)'
                                                    : 'var(--sm-borda)',
                                                background: ativo
                                                    ? 'var(--sm-primaria-suave)'
                                                    : 'var(--sm-superficie)',
                                                color: 'var(--sm-texto)',
                                                cursor: 'pointer',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    display: 'block',
                                                    fontWeight: 600,
                                                    fontSize: 14,
                                                }}
                                            >
                                                {relatorio.titulo}
                                            </span>
                                            <span
                                                style={{
                                                    display: 'block',
                                                    fontSize: 12.5,
                                                    color: 'var(--sm-texto-fraco)',
                                                }}
                                            >
                                                {relatorio.grupo}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    {escolhido && (
                        <section className="card-premium">
                            <p className="card-titulo">{escolhido.titulo}</p>
                            <p
                                className="card-sub"
                                style={{ marginBottom: 18 }}
                            >
                                {escolhido.descricao}
                            </p>

                            <div
                                style={{
                                    display: 'grid',
                                    gap: 16,
                                    gridTemplateColumns:
                                        'repeat(auto-fit, minmax(220px, 1fr))',
                                }}
                            >
                                {escolhido.modos.length > 1 && (
                                    <div className="form-group">
                                        <label
                                            className="form-label"
                                            htmlFor="modo-do-relatorio"
                                        >
                                            Modo
                                        </label>
                                        <select
                                            id="modo-do-relatorio"
                                            className="form-control"
                                            value={modo}
                                            onChange={(e) =>
                                                setModo(e.target.value)
                                            }
                                        >
                                            {escolhido.modos.map((m) => (
                                                <option key={m} value={m}>
                                                    {MODOS[m] ?? m}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                {escolhido.filtros.map((filtro) => (
                                    <div
                                        className="form-group"
                                        key={filtro.nome}
                                    >
                                        <label
                                            className="form-label"
                                            htmlFor={`filtro-${filtro.nome}`}
                                        >
                                            {filtro.label}
                                        </label>

                                        {filtro.tipo === 'select' ? (
                                            <select
                                                id={`filtro-${filtro.nome}`}
                                                className="form-control"
                                                value={
                                                    valores[filtro.nome] ?? ''
                                                }
                                                onChange={(e) =>
                                                    setValores((v) => ({
                                                        ...v,
                                                        [filtro.nome]:
                                                            e.target.value,
                                                    }))
                                                }
                                            >
                                                {filtro.opcoes.map((o) => (
                                                    <option
                                                        key={o.valor}
                                                        value={o.valor}
                                                    >
                                                        {o.rotulo}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            <input
                                                id={`filtro-${filtro.nome}`}
                                                className="form-control"
                                                type={
                                                    filtro.tipo === 'data'
                                                        ? 'date'
                                                        : filtro.tipo ===
                                                            'numero'
                                                          ? 'number'
                                                          : 'text'
                                                }
                                                value={
                                                    valores[filtro.nome] ?? ''
                                                }
                                                onChange={(e) =>
                                                    setValores((v) => ({
                                                        ...v,
                                                        [filtro.nome]:
                                                            e.target.value,
                                                    }))
                                                }
                                            />
                                        )}

                                        {filtro.ajuda && (
                                            <p className="form-ajuda">
                                                {filtro.ajuda}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <p className="form-ajuda" style={{ marginTop: 4 }}>
                                Filtro em branco não restringe nada. O recorte
                                escolhido vai impresso no documento.
                            </p>

                            {erro && (
                                <p
                                    className="form-erro"
                                    style={{ marginTop: 14 }}
                                >
                                    <TriangleAlert size={15} aria-hidden />{' '}
                                    {erro}
                                </p>
                            )}

                            <div
                                style={{
                                    display: 'flex',
                                    gap: 10,
                                    flexWrap: 'wrap',
                                    marginTop: 18,
                                }}
                            >
                                {FORMATOS.filter((f) =>
                                    escolhido.formatos.includes(f.chave),
                                ).map(({ chave, rotulo, Icone, cor }) => (
                                    <button
                                        key={chave}
                                        type="button"
                                        className="btn btn-sm"
                                        onClick={() => emitir(chave)}
                                        // Guarda de duplo clique: emitir demora, e
                                        // o segundo clique só geraria o mesmo
                                        // arquivo de novo.
                                        disabled={gerando !== null}
                                        aria-busy={
                                            gerando === chave || undefined
                                        }
                                        style={{
                                            minWidth: 130,
                                            background: cor,
                                            color: '#fff',
                                        }}
                                    >
                                        {gerando === chave ? (
                                            <>
                                                <Spinner tamanho={15} />{' '}
                                                Gerando…
                                            </>
                                        ) : (
                                            <>
                                                <Icone size={15} aria-hidden />{' '}
                                                {rotulo}
                                            </>
                                        )}
                                    </button>
                                ))}
                            </div>
                        </section>
                    )}
                </div>
            )}
        </>
    );
}

Relatorios.layout = {
    breadcrumbs: [
        {
            title: 'Relatórios',
            href: index(),
        },
    ],
};
