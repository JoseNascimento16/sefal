import { Head } from '@inertiajs/react';
import { CircleCheck, CircleSlash, TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import {
    Paginacao,
    ThOrdenavel,
    useOrdenacao,
    usePaginacao,
} from '@/components/retaguarda/th-ordenavel';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { VAZIO } from '@/lib/datas';
import { contar, plural } from '@/lib/plural';
import { index } from '@/routes/retaguarda/acompanhamento-de-requisitos';

/**
 * Acompanhamento de Requisitos — o que está construído bate com o escrito?
 *
 * A tela existe para uma cena concreta: alguém vai mexer numa funcionalidade e
 * precisa saber qual requisito é a régua dela — e, principalmente, se esse
 * requisito ainda descreve o que o sistema faz hoje. Sem isso, a resposta vem
 * semanas depois, em forma de card de retorno da Qualidade.
 *
 * É SÓ LEITURA. O mapa vive na configuração versionada junto com o código; um
 * botão de editar aqui daria dois donos à mesma informação.
 */

type Situacao = 'sim' | 'desatualizada' | 'nao';

interface Tela {
    modulo: string;
    tela: string;
    /** 'Retaguarda' ou 'PWA' (o aplicativo do fiscal, quando chegar). */
    origem: string;
    /** Onde a pessoa acha a tela. */
    breadcrumb: string | null;
    hu_status: Situacao;
    /** Códigos das HUs que especificam a tela; vazio quando não há requisito escrito. */
    hus: string[];
    /** O que o requisito diz, o que divergiu, ou de onde a funcionalidade veio. */
    nota: string | null;
}

interface Totais {
    sim: number;
    desatualizada: number;
    nao: number;
    comHu: number;
    total: number;
    percentComHu: number;
    percentAlinhada: number;
}

interface ResumoModulo {
    modulo: string;
    sim: number;
    desatualizada: number;
    nao: number;
    total: number;
}

/**
 * Como cada situação se apresenta, e o PREFIXO que ela dá à nota.
 *
 * O prefixo não é enfeite: sem ele, o mesmo parágrafo cinza serve para "aqui
 * está o que o requisito diz" e para "aqui está o que divergiu dele" — e são
 * coisas opostas. A ordem (`peso`) põe o que precisa de ação primeiro.
 */
const SITUACAO: Record<
    Situacao,
    {
        rotulo: string;
        selo: string;
        Icone: typeof CircleCheck;
        peso: number;
        prefixo: string | null;
    }
> = {
    desatualizada: {
        rotulo: 'Divergente',
        selo: 'selo-aviso',
        Icone: TriangleAlert,
        peso: 0,
        prefixo: 'Divergência:',
    },
    nao: {
        rotulo: 'Sem requisito',
        selo: 'selo-neutro',
        Icone: CircleSlash,
        peso: 1,
        prefixo: null,
    },
    sim: {
        rotulo: 'Alinhada',
        selo: 'selo-ok',
        Icone: CircleCheck,
        peso: 2,
        prefixo: null,
    },
};

/** As expressões do domínio que a busca reconhece e retira do texto livre. */
type FacetaRequisito = 'sem-requisito' | 'divergente' | 'alinhada';

const FACETAS = [
    {
        expressao: /\bsem requisit\w*\b|\bsem hu\b/,
        valor: 'sem-requisito' as const,
    },
    {
        expressao: /\bdivergent\w*\b|\bdivergenci\w*\b|\bdesatualizad\w*\b/,
        valor: 'divergente' as const,
    },
    { expressao: /\balinhad\w*\b/, valor: 'alinhada' as const },
];

const SITUACAO_DA_FACETA: Record<FacetaRequisito, Situacao> = {
    'sem-requisito': 'nao',
    divergente: 'desatualizada',
    alinhada: 'sim',
};

export default function AcompanhamentoDeRequisitos({
    telas,
    totais,
    porModulo,
}: {
    telas: Tela[];
    totais: Totais;
    porModulo: ResumoModulo[];
}) {
    const [busca, setBusca] = useState('');

    const filtradas = useMemo(() => {
        const { facetas, termos } = parseConsulta<FacetaRequisito>(
            busca,
            FACETAS,
        );

        // Facetas de situação somam entre si ("divergente ou sem requisito"),
        // porque quem pede duas quer ver as duas — não a interseção vazia.
        const situacoes = facetas.map((f) => SITUACAO_DA_FACETA[f]);

        return telas.filter((t) => {
            if (situacoes.length > 0 && !situacoes.includes(t.hu_status)) {
                return false;
            }

            return casaTermos(termos, [
                t.modulo,
                t.tela,
                t.origem,
                t.breadcrumb,
                t.nota,
                t.hus.join(' '),
                SITUACAO[t.hu_status].rotulo,
            ]);
        });
    }, [telas, busca]);

    const ord = useOrdenacao(filtradas, {
        campo: 'hu_status',
        acessor: (t: Tela) => SITUACAO[t.hu_status].peso,
    });
    const pag = usePaginacao(ord.itens);

    // Só as chaves declaradas entram no arquivo, e cada uma já formatada como se
    // lê na tela: o documento é aberto fora do sistema, onde ninguém traduz um
    // código interno como "hu_status".
    const linhasExportacao = ord.itens.map((t) => ({
        modulo: t.modulo,
        tela: t.tela,
        origem: t.origem,
        breadcrumb: t.breadcrumb ?? VAZIO,
        situacao: SITUACAO[t.hu_status].rotulo,
        hus: t.hus.length > 0 ? t.hus.join(', ') : VAZIO,
        nota: t.nota ?? VAZIO,
    }));

    return (
        <>
            <Head title="Acompanhamento de Requisitos" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Acompanhamento de Requisitos</h1>
                    <p>
                        Cada funcionalidade entregue e o requisito escrito que a
                        especifica. A pergunta aqui não é "existe?", e sim{' '}
                        <strong>
                            se o que está construído ainda condiz com o que foi
                            escrito
                        </strong>
                        .
                    </p>
                </div>
            </div>

            {/* A faixa-resumo responde em uma linha: alguém abre esta tela para
                saber o tamanho do buraco, não para contar linhas. */}
            <div
                className="card-premium"
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    marginBottom: 18,
                    borderLeft: `4px solid var(--sm-${
                        totais.desatualizada > 0
                            ? 'aviso'
                            : totais.nao > 0
                              ? 'texto-fraco'
                              : 'ok'
                    })`,
                }}
            >
                {totais.desatualizada > 0 ? (
                    <TriangleAlert
                        size={22}
                        aria-hidden
                        style={{ color: 'var(--sm-aviso)' }}
                    />
                ) : totais.nao > 0 ? (
                    <CircleSlash
                        size={22}
                        aria-hidden
                        style={{ color: 'var(--sm-texto-fraco)' }}
                    />
                ) : (
                    <CircleCheck
                        size={22}
                        aria-hidden
                        style={{ color: 'var(--sm-ok)' }}
                    />
                )}

                <div>
                    <p className="card-titulo">
                        {totais.desatualizada > 0
                            ? `${contar(totais.desatualizada, 'funcionalidade', 'funcionalidades')} divergindo do requisito escrito`
                            : totais.nao > 0
                              ? `${contar(totais.nao, 'funcionalidade', 'funcionalidades')} ainda sem requisito escrito`
                              : 'Tudo com requisito escrito e alinhado'}
                    </p>
                    <p className="card-sub">
                        {contar(
                            totais.total,
                            'funcionalidade',
                            'funcionalidades',
                        )}{' '}
                        · {totais.comHu} com requisito escrito (
                        {totais.percentComHu}%)
                        {totais.comHu > 0 &&
                            ` · ${totais.percentAlinhada}% delas ainda alinhadas`}
                    </p>
                </div>
            </div>

            {/* O resumo por módulo diz ONDE está a lacuna — a conta geral não. */}
            <div
                style={{
                    display: 'flex',
                    gap: 10,
                    flexWrap: 'wrap',
                    marginBottom: 18,
                }}
            >
                {porModulo.map((m) => (
                    <div
                        key={m.modulo}
                        className="card-premium"
                        style={{ padding: '12px 16px', minWidth: 190 }}
                    >
                        <p className="card-titulo" style={{ fontSize: 14 }}>
                            {m.modulo}
                        </p>
                        <p className="card-sub" style={{ marginTop: 4 }}>
                            {contar(m.total, 'funcionalidade', 'funcionalidades')}{' '}
                            · {m.sim} {plural(m.sim, 'alinhada', 'alinhadas')} ·{' '}
                            {m.desatualizada}{' '}
                            {plural(
                                m.desatualizada,
                                'divergente',
                                'divergentes',
                            )}{' '}
                            · {m.nao} sem requisito
                        </p>
                    </div>
                ))}
            </div>

            <div className="card-premium">
                <BuscaInteligente
                    busca={busca}
                    setBusca={setBusca}
                    placeholder="Procure por módulo, tela, caminho no menu, HU ou pelo que diz a observação"
                    exemplos={['sem requisito', 'divergente', 'alinhada']}
                />

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        marginBottom: 10,
                    }}
                >
                    <BotaoExportar
                        titulo="Acompanhamento de Requisitos"
                        subtitulo="Sistema › Acompanhamento de Requisitos"
                        contexto={
                            busca.trim()
                                ? `Busca: "${busca.trim()}"`
                                : 'Todas as funcionalidades'
                        }
                        colunas={[
                            { chave: 'modulo', titulo: 'Módulo' },
                            { chave: 'tela', titulo: 'Funcionalidade' },
                            { chave: 'origem', titulo: 'Origem' },
                            { chave: 'breadcrumb', titulo: 'Onde fica' },
                            { chave: 'situacao', titulo: 'Requisito' },
                            { chave: 'hus', titulo: 'HU' },
                            { chave: 'nota', titulo: 'Observação' },
                        ]}
                        linhas={linhasExportacao}
                    />
                </div>

                <div className="table-wrap">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <ThOrdenavel
                                    campo="modulo"
                                    acessor="modulo"
                                    ord={ord}
                                >
                                    Módulo
                                </ThOrdenavel>
                                <ThOrdenavel
                                    campo="tela"
                                    acessor="tela"
                                    ord={ord}
                                >
                                    Funcionalidade
                                </ThOrdenavel>
                                <ThOrdenavel
                                    campo="origem"
                                    acessor="origem"
                                    ord={ord}
                                >
                                    Origem
                                </ThOrdenavel>
                                <ThOrdenavel
                                    campo="breadcrumb"
                                    acessor="breadcrumb"
                                    ord={ord}
                                >
                                    Onde fica
                                </ThOrdenavel>
                                {/* Ordena pelo PESO da situação, não pelo texto:
                                    o que precisa de ação vem primeiro. */}
                                <ThOrdenavel
                                    campo="hu_status"
                                    acessor={(t: Tela) =>
                                        SITUACAO[t.hu_status].peso
                                    }
                                    ord={ord}
                                >
                                    Requisito
                                </ThOrdenavel>
                                <th>Observação</th>
                            </tr>
                        </thead>

                        <tbody>
                            {pag.visiveis.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="tabela-vazia">
                                        {telas.length === 0
                                            ? 'Nenhuma funcionalidade mapeada ainda.'
                                            : 'Nenhuma funcionalidade casa com a busca. Limpe o campo para ver todas.'}
                                    </td>
                                </tr>
                            )}

                            {pag.visiveis.map((t) => {
                                const situacao = SITUACAO[t.hu_status];

                                return (
                                    <tr
                                        key={`${t.modulo}-${t.origem}-${t.tela}`}
                                    >
                                        <td>{t.modulo}</td>
                                        <td>
                                            <strong>{t.tela}</strong>
                                            {t.hus.length > 0 && (
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        gap: 6,
                                                        flexWrap: 'wrap',
                                                        marginTop: 6,
                                                    }}
                                                >
                                                    {t.hus.map((hu) => (
                                                        <span
                                                            key={hu}
                                                            className="selo selo-info"
                                                        >
                                                            {hu}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            <span className="selo selo-neutro">
                                                {t.origem}
                                            </span>
                                        </td>
                                        <td>{t.breadcrumb ?? VAZIO}</td>
                                        <td>
                                            <span
                                                className={`selo ${situacao.selo}`}
                                            >
                                                <situacao.Icone
                                                    size={13}
                                                    aria-hidden
                                                />{' '}
                                                {situacao.rotulo}
                                            </span>
                                        </td>
                                        <td
                                            style={{
                                                fontSize: 13,
                                                color: 'var(--sm-texto-fraco)',
                                                minWidth: 280,
                                            }}
                                        >
                                            {situacao.prefixo && (
                                                <strong
                                                    style={{
                                                        color: 'var(--sm-aviso)',
                                                    }}
                                                >
                                                    {situacao.prefixo}{' '}
                                                </strong>
                                            )}
                                            {t.nota ?? VAZIO}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <Paginacao {...pag.props} />
            </div>
        </>
    );
}

AcompanhamentoDeRequisitos.layout = {
    breadcrumbs: [
        {
            title: 'Acompanhamento de Requisitos',
            href: index(),
        },
    ],
};
