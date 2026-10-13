import { Head } from '@inertiajs/react';
import { Camera, ClipboardList, Eye, List, Undo2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment, useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import type { Arquivo } from '@/components/retaguarda/lista-de-arquivos';
import { ListaDeArquivos } from '@/components/retaguarda/lista-de-arquivos';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import { Paginacao, useOrdenacao, usePaginacao } from '@/components/retaguarda/th-ordenavel';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import { index } from '@/routes/retaguarda/levantamentos';

/**
 * Fiscalização › Levantamentos — o censo de ambulantes por rua que o aplicativo
 * do fiscal grava (dono, 13/10/2026).
 *
 * CONSULTA: quem levanta é o fiscal, na rua. A lista acha a rua (ou o
 * ambulante: a busca casa também o nome de quem foi levantado); a ficha é a
 * planilha que o setor fazia à mão — item, nome, atividade, referência e foto —
 * com exportação própria. As colunas vêm do catálogo (`levantamentos` e
 * `levantamento-linhas`).
 */

interface Linha {
    id: number;
    item: string;
    nome: string;
    documento: string | null;
    contato: string | null;
    referencia: string | null;
    atividade: string | null;
    latitude: number | null;
    longitude: number | null;
    foto: Arquivo | null;
}

interface Levantamento {
    id: number;
    protocolo: string;
    rua: string;
    bairro: string;
    equipes: string[];
    encarregados: string[];
    fiscal: string;
    data: string;
    data_iso: string;
    periodo: string;
    linhas: Linha[];
}

type Aba = 'localizar' | 'registro';
type Faceta = 'hoje' | 'sem-foto';

const FACETAS = [
    { expressao: /\bhoje\b/, valor: 'hoje' as const },
    { expressao: /\bsem fotos?\b/, valor: 'sem-foto' as const },
];

const hojeBr = () => new Date().toLocaleDateString('pt-BR');

const coordenada = (l: Linha): string =>
    l.latitude === null || l.longitude === null ? VAZIO : `${l.latitude.toFixed(5)}, ${l.longitude.toFixed(5)}`;

export default function Levantamentos({ levantamentos, listagens }: { levantamentos: Levantamento[]; listagens: Listagens }) {
    const listagem = listagens['levantamentos'];
    const listagemDasLinhas = listagens['levantamento-linhas'];
    const [aba, setAba] = useState<Aba>('localizar');
    const [aberto, setAberto] = useState<Levantamento | null>(null);
    const [linhaAberta, setLinhaAberta] = useState<number | null>(null);
    const [busca, setBusca] = useState('');

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);
        const hoje = hojeBr();

        return levantamentos.filter((l) => {
            if (facetas.includes('hoje') && l.data !== hoje) {
                return false;
            }

            if (facetas.includes('sem-foto') && l.linhas.every((linha) => linha.foto !== null)) {
                return false;
            }

            return casaTermos(termos, [
                l.rua,
                l.bairro,
                l.protocolo,
                l.fiscal,
                ...l.equipes,
                ...l.encarregados,
                ...l.linhas.map((linha) => linha.nome),
            ]);
        });
    }, [levantamentos, busca]);

    const ord = useOrdenacao(filtrados, { campo: 'data', acessor: 'data_iso', dir: 'desc' });
    const pag = usePaginacao(ord.itens);

    const acessores: Record<string, AcessorOrd<Levantamento> | undefined> = {
        rua: 'rua',
        bairro: 'bairro',
        equipes: (l) => l.equipes.join(', '),
        data: 'data_iso',
        ambulantes: (l) => l.linhas.length,
    };

    function celula(l: Levantamento, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'rua') {
            return { conteudo: <strong>{l.rua}</strong>, dica: `${l.rua} — ${l.protocolo}` };
        }

        if (chave === 'bairro') {
            return { conteudo: l.bairro, dica: l.bairro };
        }

        if (chave === 'equipes') {
            return { conteudo: l.equipes.join(', '), dica: `Encarregados: ${l.encarregados.join(', ')}` };
        }

        if (chave === 'data') {
            return { conteudo: l.data, dica: `${l.data}, das ${l.periodo}` };
        }

        const semFoto = l.linhas.filter((linha) => linha.foto === null).length;

        return {
            conteudo: l.linhas.length,
            dica: semFoto === 0 ? 'Todas as linhas com foto.' : `${contar(semFoto, 'linha', 'linhas')} sem foto.`,
        };
    }

    const linhasExportacao = ord.itens.map((l) => ({
        protocolo: l.protocolo,
        rua: l.rua,
        bairro: l.bairro,
        equipes: l.equipes.join(', '),
        encarregados: l.encarregados.join(', ') || VAZIO,
        fiscal: l.fiscal,
        data: l.data,
        ambulantes: String(l.linhas.length),
    }));

    function abrir(l: Levantamento) {
        setAberto(l);
        setLinhaAberta(null);
        setAba('registro');
    }

    function voltarParaLista() {
        setAba('localizar');
        setAberto(null);
    }

    // ── A planilha da rua ────────────────────────────────────────────────

    function celulaDaLinha(l: Linha, chave: string): { conteudo: ReactNode; dica?: string } {
        if (chave === 'item') {
            return { conteudo: l.item };
        }

        if (chave === 'nome') {
            return { conteudo: <strong>{l.nome}</strong>, dica: l.nome };
        }

        if (chave === 'foto') {
            return l.foto === null
                ? { conteudo: <span style={{ color: 'var(--sm-texto-fraco)' }}>sem foto</span> }
                : { conteudo: <span className="selo selo-ok"><Camera size={13} aria-hidden /> Sim</span>, dica: 'Clique na linha para ver a foto.' };
        }

        const valor = chave === 'atividade' ? l.atividade : l.referencia;

        return { conteudo: valor || VAZIO, dica: valor ?? undefined };
    }

    const linhasDaFicha = aberto?.linhas ?? [];
    const exportacaoDasLinhas = linhasDaFicha.map((l) => ({
        item: l.item,
        nome: l.nome,
        documento: l.documento || VAZIO,
        contato: l.contato || VAZIO,
        referencia: l.referencia || VAZIO,
        atividade: l.atividade || VAZIO,
        coordenada: coordenada(l),
        foto: l.foto === null ? 'Não' : 'Sim',
    }));

    return (
        <>
            <Head title="Levantamentos" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Fiscalização</p>
                    <h1>Levantamentos</h1>
                    <p>
                        O censo de ambulantes por rua, feito pelas equipes no aplicativo: quem trabalha em cada ponto, a
                        atividade e a foto do equipamento.
                    </p>
                </div>
            </div>

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Levantamentos">
                    <button type="button" role="tab" className="aba" aria-selected={aba === 'localizar'} onClick={voltarParaLista}>
                        <List size={16} aria-hidden />
                        <span className="aba-rotulo">Localizar ({levantamentos.length})</span>
                    </button>
                    {aberto !== null && (
                        <button type="button" role="tab" className="aba" aria-selected={aba === 'registro'} onClick={() => setAba('registro')}>
                            <Eye size={16} aria-hidden />
                            <span className="aba-rotulo">{aberto.rua}</span>
                        </button>
                    )}
                </div>

                {aba === 'localizar' || aberto === null ? (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder="Procure pela rua, bairro, equipe, encarregado ou pelo nome do ambulante"
                            exemplos={['hoje', 'sem foto', 'Arenoso', 'C2']}
                        />

                        <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 10 }}>
                            <BotaoExportar
                                titulo="Levantamentos de ambulantes"
                                subtitulo="Fiscalização › Levantamentos"
                                contexto={busca.trim() ? `Busca: "${busca.trim()}"` : 'Todos os levantamentos'}
                                colunas={listagem.exportacao}
                                linhas={linhasExportacao}
                            />
                        </div>

                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela — para abrir o levantamento.
                            </p>
                        )}

                        <div className="table-wrap">
                            <table className="data-table enxuta">
                                <thead>
                                    <tr>
                                        <CabecaDaGrade grade={listagem.grade} ord={ord} acessores={acessores} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td colSpan={listagem.grade.length} className="tabela-vazia">
                                                {levantamentos.length === 0
                                                    ? 'Nenhum levantamento ainda. Eles chegam quando uma equipe conclui o levantamento de uma rua no aplicativo.'
                                                    : 'Nenhum levantamento casa com a busca. Limpe o campo para ver todos.'}
                                            </td>
                                        </tr>
                                    )}
                                    {pag.visiveis.map((l) => (
                                        <tr key={l.id} {...linhaClicavel(() => abrir(l), `Abrir o levantamento de ${l.rua}`)}>
                                            {listagem.grade.map((coluna) => {
                                                const { conteudo, dica } = celula(l, coluna.chave);

                                                return (
                                                    <Celula key={coluna.chave} coluna={coluna} dica={dica}>
                                                        {conteudo}
                                                    </Celula>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Paginacao {...pag.props} />
                    </>
                ) : (
                    <>
                        <div className="rt-barra-registro">
                            <BotaoAcao className="btn btn-secondary btn-sm" icone={<Undo2 size={16} aria-hidden />} onClick={voltarParaLista}>
                                Voltar
                            </BotaoAcao>
                        </div>

                        <dl className="rt-ficha" style={{ margin: '0 0 16px' }}>
                            <div>
                                <dt>Protocolo</dt>
                                <dd>{aberto.protocolo}</dd>
                            </div>
                            <div>
                                <dt>Rua</dt>
                                <dd>
                                    {aberto.rua} · {aberto.bairro}
                                </dd>
                            </div>
                            <div>
                                <dt>Equipes</dt>
                                <dd>{aberto.equipes.join(', ')}</dd>
                            </div>
                            <div>
                                <dt>Encarregados</dt>
                                <dd>{aberto.encarregados.join(', ') || VAZIO}</dd>
                            </div>
                            <div>
                                <dt>Levantado por</dt>
                                <dd>{aberto.fiscal}</dd>
                            </div>
                            <div>
                                <dt>Quando</dt>
                                <dd>
                                    {aberto.data}, das {aberto.periodo}
                                </dd>
                            </div>
                        </dl>

                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginBottom: 10 }}>
                            <p className="form-ajuda" style={{ margin: 0 }}>
                                <ClipboardList size={14} aria-hidden /> {contar(linhasDaFicha.length, 'ambulante levantado', 'ambulantes levantados')}.
                                Clique numa linha para ver documento, contato, coordenada e foto.
                            </p>
                            <BotaoExportar
                                titulo={`Levantamento — ${aberto.rua}`}
                                subtitulo={`${aberto.bairro} · ${aberto.protocolo} · Equipes ${aberto.equipes.join(', ')}`}
                                contexto={`Levantado por ${aberto.fiscal} em ${aberto.data}`}
                                colunas={listagemDasLinhas.exportacao}
                                linhas={exportacaoDasLinhas}
                            />
                        </div>

                        <div className="table-wrap">
                            <table className="data-table enxuta">
                                <thead>
                                    <tr>
                                        {listagemDasLinhas.grade.map((coluna) => (
                                            <th key={coluna.chave} style={{ width: coluna.largura, textAlign: coluna.alinhar ?? 'left' }}>
                                                {coluna.titulo}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {linhasDaFicha.map((l) => (
                                        <Fragment key={l.id}>
                                            <tr {...linhaClicavel(() => setLinhaAberta(linhaAberta === l.id ? null : l.id), `Ver os dados de ${l.nome}`)}>
                                                {listagemDasLinhas.grade.map((coluna) => {
                                                    const { conteudo, dica } = celulaDaLinha(l, coluna.chave);

                                                    return (
                                                        <Celula key={coluna.chave} coluna={coluna} dica={dica}>
                                                            {conteudo}
                                                        </Celula>
                                                    );
                                                })}
                                            </tr>
                                            {linhaAberta === l.id && (
                                                <tr>
                                                    <td colSpan={listagemDasLinhas.grade.length}>
                                                        <dl className="rt-ficha" style={{ margin: '4px 0 10px' }}>
                                                            <div>
                                                                <dt>CPF / RG</dt>
                                                                <dd>{l.documento || VAZIO}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Contato</dt>
                                                                <dd>{l.contato || VAZIO}</dd>
                                                            </div>
                                                            <div>
                                                                <dt>Coordenada</dt>
                                                                <dd>{coordenada(l)}</dd>
                                                            </div>
                                                        </dl>
                                                        <ListaDeArquivos
                                                            arquivos={l.foto === null ? [] : [l.foto]}
                                                            vazio="O fiscal não tirou foto do equipamento nesta linha."
                                                        />
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Levantamentos.layout = {
    breadcrumbs: [
        {
            title: 'Levantamentos',
            href: index(),
        },
    ],
};
