import { Head } from '@inertiajs/react';
import { CircleCheck, CircleSlash, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment, useMemo, useState } from 'react';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import {
    Paginacao,
    useOrdenacao,
    usePaginacao,
} from '@/components/retaguarda/th-ordenavel';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
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
 *
 * ── O SINAL fica na grade; o parágrafo abre no clique ───────────────────────
 *
 * Quem varre está procurando o que está FORA do requisito, então o selo de
 * situação é o coração da linha e a ordem inicial da tela. A observação — o
 * parágrafo que descreve a divergência ou conta de onde a funcionalidade veio —
 * é o que se lê DEPOIS de achar: ela ocupava até noventa linhas de texto dentro
 * de uma célula, e agora abre na ficha da linha, com o mesmo destaque de
 * divergência. As colunas vêm do catálogo
 * (`config/listagens_da_retaguarda.php`); a régua está em
 * `docs/padroes/listagem-clean.md`.
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
    listagens,
    totais,
    porModulo,
}: {
    telas: Tela[];
    listagens: Listagens;
    totais: Totais;
    porModulo: ResumoModulo[];
}) {
    const listagem = listagens['sistema.requisitos'];
    const [busca, setBusca] = useState('');
    /** A linha aberta, pela mesma identidade que a serve de chave. */
    const [aberta, setAberta] = useState<string | null>(null);

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
        campo: 'situacao',
        acessor: (t: Tela) => SITUACAO[t.hu_status].peso,
    });
    const pag = usePaginacao(ord.itens);

    /** Como ORDENAR por cada coluna da grade — a chave é a do catálogo. */
    const acessores: Record<string, AcessorOrd<Tela> | undefined> = {
        modulo: 'modulo',
        tela: 'tela',
        origem: 'origem',
        hus: (t) => t.hus.join(' '),
        // Pelo PESO da situação, não pelo texto do selo: o que precisa de ação
        // vem primeiro.
        situacao: (t) => SITUACAO[t.hu_status].peso,
    };

    /** Cinza de apoio — o mesmo em toda célula que diz "isto não existe". */
    const fraco = { color: 'var(--sm-texto-fraco)' };

    /** A identidade de uma linha — não há id no mapa, que é configuração. */
    function chaveDa(t: Tela): string {
        return `${t.modulo}-${t.origem}-${t.tela}`;
    }

    /** O que cada célula desenha, com o texto inteiro para a dica. */
    function celula(
        t: Tela,
        chave: string,
    ): { conteudo: ReactNode; dica?: string; resumida?: boolean } {
        const situacao = SITUACAO[t.hu_status];

        if (chave === 'modulo') {
            return { conteudo: t.modulo, dica: t.modulo };
        }

        if (chave === 'tela') {
            return {
                conteudo: <strong>{t.tela}</strong>,
                // O caminho no menu entra na dica, e não numa sub-linha embaixo
                // do nome: era esse empilhamento que o dono mandou tirar.
                dica: t.breadcrumb === null ? t.tela : `${t.tela} — ${t.breadcrumb}`,
            };
        }

        if (chave === 'origem') {
            // Texto, e não chip: o selo desta linha é a situação, e um segundo
            // chip na mesma linha volta a empilhar conteúdo na célula.
            return { conteudo: t.origem, dica: `Funcionalidade da ${t.origem}` };
        }

        if (chave === 'hus') {
            // A PRIMEIRA HU mais "+N": a tela resume de verdade aqui, então a
            // dica é obrigatória e também anunciada.
            return t.hus.length === 0
                ? {
                      conteudo: <span style={fraco}>{VAZIO}</span>,
                      dica: 'Sem requisito escrito — a observação diz de onde a funcionalidade nasceu.',
                  }
                : {
                      conteudo:
                          t.hus.length === 1
                              ? t.hus[0]
                              : `${t.hus[0]} +${t.hus.length - 1}`,
                      dica: t.hus.join(', '),
                      resumida: t.hus.length > 1,
                  };
        }

        return {
            conteudo: (
                <span className={`selo ${situacao.selo}`}>
                    <situacao.Icone size={13} aria-hidden /> {situacao.rotulo}
                </span>
            ),
            dica:
                situacao.prefixo === null
                    ? situacao.rotulo
                    : `${situacao.rotulo} — abra a linha para ler a divergência.`,
        };
    }

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
                        // As colunas do ARQUIVO saem do mesmo catálogo da grade, e
                        // continuam INTEIRAS: a observação, o caminho no menu e a
                        // origem saíram da tela, não do documento.
                        colunas={listagem.exportacao}
                        linhas={linhasExportacao}
                    />
                </div>

                <div className="table-wrap">
                    <table className="data-table enxuta">
                        <thead>
                            <tr>
                                {/* Cabeçalho e células saem da MESMA lista: escritos
                                    em dois lugares, uma coluna nova entra só num
                                    deles e a grade passa a mostrar o valor debaixo
                                    do título errado. */}
                                <CabecaDaGrade
                                    grade={listagem.grade}
                                    ord={ord}
                                    acessores={acessores}
                                />
                            </tr>
                        </thead>

                        <tbody>
                            {pag.visiveis.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={listagem.grade.length}
                                        className="tabela-vazia"
                                    >
                                        {telas.length === 0
                                            ? 'Nenhuma funcionalidade mapeada ainda.'
                                            : 'Nenhuma funcionalidade casa com a busca. Limpe o campo para ver todas.'}
                                    </td>
                                </tr>
                            )}

                            {pag.visiveis.map((t) => {
                                const situacao = SITUACAO[t.hu_status];
                                const chave = chaveDa(t);

                                return (
                                    <Fragment key={chave}>
                                        <tr
                                            {...linhaClicavel(
                                                () =>
                                                    setAberta(
                                                        aberta === chave
                                                            ? null
                                                            : chave,
                                                    ),
                                                'Abrir ou fechar o que o requisito diz sobre esta funcionalidade',
                                                t.hu_status ===
                                                    'desatualizada' &&
                                                    'pendente',
                                            )}
                                        >
                                            {listagem.grade.map((coluna) => {
                                                const {
                                                    conteudo,
                                                    dica,
                                                    resumida,
                                                } = celula(t, coluna.chave);

                                                return (
                                                    <Celula
                                                        key={coluna.chave}
                                                        coluna={coluna}
                                                        dica={dica}
                                                        resumida={resumida}
                                                    >
                                                        {conteudo}
                                                    </Celula>
                                                );
                                            })}
                                        </tr>

                                        {aberta === chave && (
                                            <tr className="linha-detalhe">
                                                <td
                                                    colSpan={
                                                        listagem.grade.length
                                                    }
                                                >
                                                    {/* O que DESCEU da grade: onde a
                                                        funcionalidade fica, de que
                                                        frente ela é, quais HUs a
                                                        especificam — e a observação,
                                                        que é o parágrafo. */}
                                                    <dl className="rt-ficha">
                                                        <div>
                                                            <dt>Onde fica</dt>
                                                            <dd>
                                                                {t.breadcrumb ??
                                                                    VAZIO}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt>Origem</dt>
                                                            <dd>{t.origem}</dd>
                                                        </div>
                                                        <div>
                                                            <dt>
                                                                Requisito escrito
                                                            </dt>
                                                            <dd>
                                                                {t.hus.length ===
                                                                0
                                                                    ? 'Nenhuma HU escrita'
                                                                    : t.hus.join(
                                                                          ', ',
                                                                      )}
                                                            </dd>
                                                        </div>
                                                    </dl>

                                                    {/* O prefixo distingue "aqui está
                                                        o que o requisito diz" de
                                                        "aqui está o que divergiu
                                                        dele" — são coisas opostas, e
                                                        sem ele o mesmo parágrafo
                                                        cinza serviria para as duas. */}
                                                    <p
                                                        className="card-sub"
                                                        style={{
                                                            margin: '14px 0 0',
                                                        }}
                                                    >
                                                        {situacao.prefixo && (
                                                            <strong
                                                                style={{
                                                                    color: 'var(--sm-aviso)',
                                                                }}
                                                            >
                                                                {
                                                                    situacao.prefixo
                                                                }{' '}
                                                            </strong>
                                                        )}
                                                        {t.nota ?? VAZIO}
                                                    </p>
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
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
