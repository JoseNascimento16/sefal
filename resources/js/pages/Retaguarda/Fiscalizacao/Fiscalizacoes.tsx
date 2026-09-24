import { Head } from '@inertiajs/react';
import {
    Archive,
    ClipboardCheck,
    ExternalLink,
    FileText,
    Info,
    Layers,
    RotateCcw,
    Send,
    Siren,
    Undo2,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import type { Registro } from '@/components/retaguarda/detalhe-da-vistoria';
import { DetalheDaVistoria } from '@/components/retaguarda/detalhe-da-vistoria';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import { Sobreposicao } from '@/components/retaguarda/sobreposicao';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import { Paginacao, useOrdenacao, usePaginacao } from '@/components/retaguarda/th-ordenavel';
import type { Operacao } from '@/dados-prototipo/denuncias';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { dataBR, dataHoraBR, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import type { CatalogoDeRecomendacoes } from '@/lib/recomendacoes';
import { cn } from '@/lib/utils';
import { direcionar as rotaDirecionar, operacao as rotaOperacao } from '@/routes/retaguarda/denuncias';
import { arquivar as rotaArquivar, encaminharAoChefe, index, novaVistoria } from '@/routes/retaguarda/fiscalizacoes';

/*
 * Fiscalizações — a mesa do LÍDER de equipe, e o acompanhamento do Chefe de Setor.
 *
 * ⚠️ A LINHA MUDOU DE SENTIDO em 24/09/2026 (decisão do dono). Cada linha era uma
 * VISTORIA (uma ida ao ponto); agora é a FISCALIZAÇÃO — o conjunto de atos que
 * pôs a demanda em prática: nasce quando o chefe encaminha ao líder, o líder a
 * envia à equipe, recebe o retorno de campo (e pode mandar a equipe voltar), e a
 * encaminha ao chefe com o resultado. As vistorias abrem DENTRO dela.
 *
 *   · Em andamento — com a EQUIPE: o líder envia aos fiscais, lê o retorno,
 *     manda voltar ou encaminha ao chefe. O DESFECHO muda conforme ela avança.
 *   · Encaminhadas — com o CHEFE DE SETOR: ele delibera pela Caixa de Entrada
 *     (responde à origem, ou encaminha de novo — o que abre uma Fiscalização irmã).
 *   · Arquivo — o processo voltou à origem.
 *
 * Não existe "dar ciência": o líder decide, e o que decide sai da mão dele.
 */

interface CicloResumo {
    id: number;
    protocolo: string;
    aberto_em: string;
    equipe: string;
    posse: string;
    aba: 'andamento' | 'encaminhadas' | 'arquivo';
    desfecho: string;
    total_vistorias: number;
    total_fotos: number;
    documentos: string[];
    url: string;
}

interface Ciclo extends CicloResumo {
    origem: string;
    area: string;
    lider: string | null;
    a_decidir: boolean;
    aguarda_envio: boolean;
    vistoria_pendente: number | null;
    encaminhado_ao_chefe_em: string | null;
    encaminhado_por: string | null;
    motivo: string | null;
    arquivado_em: string | null;
    demanda: {
        id: number;
        protocolo: string;
        canal: string;
        canal_nome: string;
        assunto: string;
        bairro: string;
        situacao: string;
        situacao_resumida: string;
        url: string;
    } | null;
    vistorias: Registro[];
    irmas: CicloResumo[];
}

type Aba = CicloResumo['aba'] | 'detalhe';

const ROTULO: Record<Aba, string> = {
    andamento: 'Em andamento',
    encaminhadas: 'Encaminhadas',
    arquivo: 'Arquivo',
    detalhe: 'Detalhe',
};

/** O tom da posse: com a equipe é trabalho andando; com o chefe, espera ele. */
const TOM_DA_POSSE: Record<string, string> = {
    Equipe: 'selo-info',
    'Chefe de Setor': 'selo-aviso',
};

type Decisao = 'equipe' | 'operacao' | 'voltar' | 'chefe' | null;

type Faceta = 'a-decidir' | 'aguardando-envio' | 'com-documento' | 'sem-demanda';

const FACETAS: { expressao: RegExp; valor: Faceta }[] = [
    { expressao: /\ba decidir\b|\bpendente\w*\b/, valor: 'a-decidir' },
    { expressao: /\baguardando envio\b|\bsem equipe\b|\ba enviar\b/, valor: 'aguardando-envio' },
    { expressao: /\bcom documento\b|\bnotificad\w*\b|\bautuad\w*\b/, valor: 'com-documento' },
    { expressao: /\bsem demanda\b|\bronda\w*\b|\bavuls\w*\b/, valor: 'sem-demanda' },
];

export default function Fiscalizacoes({
    fiscalizacoes,
    abrir,
    recomendacoesDoFiscal,
    lideres,
    conduz,
    arquiva,
    equipesDoLider,
    recorteDeEquipe,
    operacoes,
    listagens,
}: {
    fiscalizacoes: Ciclo[];
    /** A Fiscalização a abrir ao chegar (link vindo da Caixa de Entrada). */
    abrir: number | null;
    recomendacoesDoFiscal: CatalogoDeRecomendacoes;
    lideres: Record<string, { nome: string; matricula: string | null }>;
    /** Conduz a Fiscalização com a equipe (líder, administrador)? Quem responde é o servidor. */
    conduz: boolean;
    /** Arquiva a Fiscalização sem processo (chefe, administrador)? */
    arquiva: boolean;
    equipesDoLider: string[];
    recorteDeEquipe: boolean;
    operacoes: Operacao[];
    listagens: Listagens;
}) {
    const { enviando, ocupado, enviar } = useEnvio();

    const abertaNoLink = fiscalizacoes.find((c) => c.id === abrir) ?? null;
    const [aba, setAba] = useState<Aba>(abertaNoLink !== null ? 'detalhe' : 'andamento');
    const [abertoId, setAbertoId] = useState<number | null>(abertaNoLink?.id ?? null);
    const [busca, setBusca] = useState('');
    const [marcados, setMarcados] = useState<number[]>([]);
    const [decisao, setDecisao] = useState<Decisao>(null);
    const [alvos, setAlvos] = useState<number[]>([]);
    const [texto, setTexto] = useState('');
    const [operacaoEscolhida, setOperacaoEscolhida] = useState(operacoes[0]?.nome ?? '');

    const liderDa = (equipe: string | null): string | null => {
        const nome = equipe === null ? '' : (lideres[equipe]?.nome ?? '');

        return nome.trim() === '' ? null : nome;
    };

    const porAba = useMemo(
        () => ({
            andamento: fiscalizacoes.filter((c) => c.aba === 'andamento'),
            encaminhadas: fiscalizacoes.filter((c) => c.aba === 'encaminhadas'),
            arquivo: fiscalizacoes.filter((c) => c.aba === 'arquivo'),
        }),
        [fiscalizacoes],
    );

    // A resposta do servidor traz a lista nova: a seleção antiga já não vale.
    useEffect(() => {
        setMarcados([]);
        setDecisao(null);
        setAlvos([]);
    }, [fiscalizacoes]);

    const fonte = aba === 'detalhe' ? [] : porAba[aba];

    const filtradas = useMemo(() => {
        const { facetas: achadas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return fonte.filter((c) => {
            for (const f of achadas) {
                if (f === 'a-decidir' && !c.a_decidir) return false;
                if (f === 'aguardando-envio' && !c.aguarda_envio) return false;
                if (f === 'com-documento' && c.documentos.length === 0) return false;
                if (f === 'sem-demanda' && c.demanda !== null) return false;
            }

            return casaTermos(termos, [
                c.protocolo,
                c.demanda?.protocolo,
                c.demanda?.canal_nome,
                c.demanda?.assunto,
                c.demanda?.bairro,
                c.equipe,
                c.area,
                c.desfecho,
                c.posse,
                c.origem,
                ...c.documentos,
            ]);
        });
    }, [fonte, busca]);

    const acessores: Record<string, AcessorOrd<Ciclo> | undefined> = {
        protocolo: 'protocolo',
        aberto_em: 'aberto_em',
        demanda: (c: Ciclo) => c.demanda?.protocolo ?? '',
        equipe: 'equipe',
        desfecho: 'desfecho',
        posse: 'posse',
    };
    const ord = useOrdenacao(filtradas, { campo: 'aberto_em', dir: 'desc', acessor: 'aberto_em' });
    const pag = usePaginacao(ord.itens);

    const listagem = listagens['fiscalizacoes.ciclos'];
    const selecionavel = conduz && aba === 'andamento';
    const colunas = (selecionavel ? 1 : 0) + listagem.grade.length;
    const aberta = fiscalizacoes.find((c) => c.id === abertoId) ?? null;

    const idsVisiveis = pag.visiveis.map((c) => c.id);
    const todosMarcados = idsVisiveis.length > 0 && idsVisiveis.every((id) => marcados.includes(id));

    /*
     * O que cada ação ALCANÇA do que foi marcado — a lista mistura Fiscalizações
     * em pontos diferentes do ciclo, e cada botão diz quantas vai levar.
     */
    const doMarcado = (ids: number[]) => fiscalizacoes.filter((c) => ids.includes(c.id));
    const paraEnviar = (ids: number[]) => doMarcado(ids).filter((c) => c.aguarda_envio);
    const paraVoltar = (ids: number[]) => doMarcado(ids).filter((c) => c.vistoria_pendente !== null);
    const paraChefe = (ids: number[]) => doMarcado(ids).filter((c) => c.aba === 'andamento');

    function trocarAba(nova: Aba) {
        setAba(nova);
        setMarcados([]);
    }

    function abrirDetalhe(c: Ciclo) {
        setAbertoId(c.id);
        setAba('detalhe');
    }

    function abrirDecisao(qual: Decisao, ids: number[]) {
        setAlvos(ids);
        setTexto('');
        setDecisao(qual);
    }

    function confirmar() {
        const escolhidas = doMarcado(alvos);
        const fechar = { onSuccess: () => setDecisao(null) };

        if (decisao === 'equipe') {
            enviar('equipe', rotaDirecionar().url, {
                ids: paraEnviar(alvos).map((c) => c.demanda!.id),
                orientacao: texto.trim() === '' ? null : texto.trim(),
            }, fechar);
        } else if (decisao === 'operacao') {
            enviar('operacao', rotaOperacao().url, {
                ids: paraEnviar(alvos).map((c) => c.demanda!.id),
                nova: false,
                operacao: operacaoEscolhida,
            }, fechar);
        } else if (decisao === 'voltar') {
            enviar('voltar', novaVistoria().url, { ids: paraVoltar(alvos).map((c) => c.id), justificativa: texto }, fechar);
        } else if (decisao === 'chefe') {
            enviar('chefe', encaminharAoChefe().url, { ids: escolhidas.map((c) => c.id), motivo: texto }, fechar);
        }
    }

    function arquivar(c: Ciclo) {
        enviar('arquivar', rotaArquivar().url, { ids: [c.id] });
    }

    const fraco = { color: 'var(--sm-texto-fraco)' };

    function celula(c: Ciclo, chave: string): { conteudo: ReactNode; dica?: string } {
        switch (chave) {
            case 'protocolo':
                return { conteudo: c.protocolo, dica: `${c.protocolo} · ${c.origem}` };
            case 'aberto_em':
                return { conteudo: dataBR(c.aberto_em), dica: `Aberta em ${dataHoraBR(c.aberto_em)}` };
            case 'demanda':
                return c.demanda === null
                    ? { conteudo: <span style={fraco}>{c.origem}</span>, dica: 'Sem demanda: nasceu em rua' }
                    : {
                          conteudo: `${c.demanda.protocolo} · ${c.demanda.canal_nome}`,
                          dica: `${c.demanda.protocolo} · ${c.demanda.canal_nome} · ${c.demanda.assunto}`,
                      };
            case 'equipe':
                return c.equipe === ''
                    ? { conteudo: <span style={fraco}>{VAZIO}</span> }
                    : {
                          conteudo: c.equipe,
                          dica: `Equipe ${c.equipe}${liderDa(c.equipe) ? ` · líder ${liderDa(c.equipe)}` : ''}`,
                      };
            case 'desfecho':
                return { conteudo: c.desfecho, dica: c.desfecho };
            case 'posse':
                return {
                    conteudo: (
                        <span className={cn('selo', c.aba === 'arquivo' ? 'selo-neutro' : (TOM_DA_POSSE[c.posse] ?? 'selo-neutro'))}>
                            {c.posse}
                        </span>
                    ),
                    dica: c.aba === 'arquivo' ? 'Arquivada — o processo voltou à origem' : `Com: ${c.posse}`,
                };
            default:
                return { conteudo: VAZIO };
        }
    }

    const linhasExportacao = ord.itens.map((c) => ({
        protocolo: c.protocolo,
        aberto_em: dataHoraBR(c.aberto_em),
        demanda: c.demanda?.protocolo ?? c.origem,
        canal: c.demanda?.canal_nome ?? VAZIO,
        equipe: c.equipe || VAZIO,
        desfecho: c.desfecho,
        posse: c.aba === 'arquivo' ? 'Arquivada' : c.posse,
        vistorias: String(c.total_vistorias),
        documentos: c.documentos.join(', ') || VAZIO,
        irmas: c.irmas.map((i) => i.protocolo).join(', ') || VAZIO,
        motivo: c.motivo ?? VAZIO,
    }));

    /** Os botões de decisão — os mesmos no lote e na Fiscalização aberta. */
    function botoes(ids: number[]) {
        const enviar = paraEnviar(ids).length;
        const voltar = paraVoltar(ids).length;
        const chefe = paraChefe(ids).length;
        const n = (x: number) => (ids.length > 1 && x > 0 ? ` (${x})` : '');

        return (
            <>
                <BotaoAcao icone={<Send size={16} aria-hidden />} ocupado={ocupado} disabled={enviar === 0}
                    title={enviar === 0 ? 'Só a Fiscalização que aguarda o envio à equipe' : undefined}
                    onClick={() => abrirDecisao('equipe', ids)}>
                    Encaminhar à equipe{n(enviar)}
                </BotaoAcao>
                <BotaoAcao className="btn btn-secondary btn-sm" icone={<Siren size={16} aria-hidden />} ocupado={ocupado}
                    disabled={enviar === 0 || operacoes.length === 0} onClick={() => abrirDecisao('operacao', ids)}>
                    Incluir em operação{n(enviar)}
                </BotaoAcao>
                <BotaoAcao className="btn btn-secondary btn-sm" icone={<RotateCcw size={16} aria-hidden />} ocupado={ocupado}
                    disabled={voltar === 0} title={voltar === 0 ? 'Só a Fiscalização com retorno de campo esperando você' : undefined}
                    onClick={() => abrirDecisao('voltar', ids)}>
                    Mandar a equipe voltar{n(voltar)}
                </BotaoAcao>
                <BotaoAcao className="btn btn-secondary btn-sm" icone={<Undo2 size={16} aria-hidden />} ocupado={ocupado}
                    disabled={chefe === 0} onClick={() => abrirDecisao('chefe', ids)}>
                    Encaminhar ao Chefe de Setor{n(chefe)}
                </BotaoAcao>
            </>
        );
    }

    const obrigatorio = <span aria-hidden style={{ color: 'var(--sm-perigo)' }}>*</span>;

    return (
        <>
            <Head title="Fiscalizações" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Fiscalização</p>
                    <h1>Fiscalizações</h1>
                    <p>
                        Cada demanda que o Chefe de Setor encaminha gera uma{' '}
                        <strong>Fiscalização</strong>: o líder a envia à equipe, recebe o retorno
                        de campo — e pode mandar a equipe voltar — e a encaminha ao chefe com o
                        resultado. Quando o processo volta à origem, ela vai para o Arquivo.
                    </p>
                    <ul className="rt-chips">
                        {conduz && (
                            <li className="rt-chip" style={{ color: 'var(--sm-primaria)' }}>
                                <span className="rt-chip-dot" />
                                Você conduz
                                {equipesDoLider.length > 0 && equipesDoLider.length <= 2
                                    ? ` · ${equipesDoLider.map((e) => `Equipe ${e}`).join(' e ')}`
                                    : ''}{' '}
                                — envia à equipe, manda voltar ou encaminha ao Chefe de Setor
                            </li>
                        )}
                        {!conduz && arquiva && (
                            <li className="rt-chip" style={{ color: 'var(--sm-aviso)' }}>
                                <span className="rt-chip-dot" />
                                Você acompanha — o que o líder encaminha chega em "Encaminhadas"; a
                                deliberação é na Caixa de Entrada
                            </li>
                        )}
                        {!conduz && !arquiva && (
                            <li className="rt-chip">
                                <span className="rt-chip-dot" />
                                Você consulta o que a fiscalização registrou
                            </li>
                        )}
                    </ul>
                </div>

                <div className="rt-numeros">
                    <button type="button" className="rt-numero alerta" title="Ver as que esperam decisão do líder"
                        onClick={() => { trocarAba('andamento'); setBusca('a decidir'); }}>
                        <strong>{porAba.andamento.filter((c) => c.a_decidir).length}</strong>
                        <span>a decidir</span>
                    </button>
                    <div className="rt-numeros-separador" />
                    <button type="button" className="rt-numero info" title="Ver as encaminhadas ao Chefe de Setor"
                        onClick={() => { trocarAba('encaminhadas'); setBusca(''); }}>
                        <strong>{porAba.encaminhadas.length}</strong>
                        <span>com o chefe</span>
                    </button>
                </div>
            </div>

            <SeloPrototipo>
                Ambiente de demonstração: as vistorias já registradas são <strong>exemplos</strong>.
                O que você encaminhar ou pedir de nova vistoria <strong>é gravado de verdade</strong>{' '}
                e fica no trâmite da demanda.
            </SeloPrototipo>

            {recorteDeEquipe && (
                <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                    <Info size={16} aria-hidden />
                    <div>
                        <strong>Você está vendo só as Fiscalizações das suas equipes.</strong>
                        <div>As das outras equipes não aparecem aqui — e a ação sobre elas é recusada pelo sistema.</div>
                    </div>
                </div>
            )}

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label="Fiscalizações">
                    {(['andamento', 'encaminhadas', 'arquivo'] as const).map((a) => (
                        <button key={a} type="button" role="tab" className="aba" aria-selected={aba === a} onClick={() => trocarAba(a)}>
                            {a === 'arquivo' ? <Archive size={16} aria-hidden /> : a === 'encaminhadas' ? <Undo2 size={16} aria-hidden /> : <ClipboardCheck size={16} aria-hidden />}
                            <span className="aba-rotulo">{ROTULO[a]} ({porAba[a].length})</span>
                        </button>
                    ))}
                    {aberta !== null && (
                        <button type="button" role="tab" className="aba" aria-selected={aba === 'detalhe'} onClick={() => setAba('detalhe')}>
                            <FileText size={16} aria-hidden />
                            <span className="aba-rotulo">{aberta.protocolo}</span>
                        </button>
                    )}
                </div>

                {aba !== 'detalhe' && (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder='Fiscalização, demanda, canal, equipe, bairro ou desfecho — ex.: "a decidir", "aguardando envio"'
                            exemplos={['a decidir', 'aguardando envio', 'com documento', 'regularizado no local', 'sem demanda']}
                        />

                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 10 }}>
                            {selecionavel && botoes(marcados)}
                            <div style={{ marginLeft: 'auto' }}>
                                <BotaoExportar
                                    titulo="Fiscalizações"
                                    subtitulo="Fiscalização › Fiscalizações"
                                    contexto={[
                                        `Aba: ${ROTULO[aba]}`,
                                        recorteDeEquipe ? `Equipes: ${equipesDoLider.join(' e ')}` : 'Todas as equipes',
                                        busca.trim() ? `busca: "${busca.trim()}"` : null,
                                    ].filter(Boolean).join(' · ')}
                                    colunas={listagem.exportacao}
                                    linhas={linhasExportacao}
                                />
                            </div>
                        </div>

                        <div className="table-wrap">
                            <table className="data-table enxuta">
                                <thead>
                                    <tr>
                                        {selecionavel && (
                                            <th style={{ width: 38 }}>
                                                <input type="checkbox" checked={todosMarcados}
                                                    onChange={() => setMarcados(todosMarcados ? [] : idsVisiveis)}
                                                    aria-label="Selecionar as Fiscalizações da página" />
                                            </th>
                                        )}
                                        <CabecaDaGrade grade={listagem.grade} ord={ord} acessores={acessores} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {pag.visiveis.length === 0 && (
                                        <tr>
                                            <td colSpan={colunas} className="tabela-vazia">
                                                {fonte.length === 0
                                                    ? aba === 'andamento'
                                                        ? 'Nenhuma Fiscalização com a equipe agora.'
                                                        : aba === 'encaminhadas'
                                                          ? 'Nada encaminhado ao Chefe de Setor esperando deliberação.'
                                                          : 'O arquivo está vazio.'
                                                    : 'Nenhuma Fiscalização casa com a busca. Limpe o campo para ver a lista inteira.'}
                                            </td>
                                        </tr>
                                    )}
                                    {pag.visiveis.map((c) => (
                                        <tr key={c.id} {...linhaClicavel(() => abrirDetalhe(c), 'Abrir a Fiscalização, as vistorias e o processo', c.a_decidir && 'pendente')}>
                                            {selecionavel && (
                                                <td onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()}>
                                                    <input type="checkbox" checked={marcados.includes(c.id)}
                                                        onChange={() => setMarcados((m) => (m.includes(c.id) ? m.filter((i) => i !== c.id) : [...m, c.id]))}
                                                        aria-label={`Selecionar a Fiscalização ${c.protocolo}`} />
                                                </td>
                                            )}
                                            {listagem.grade.map((coluna) => {
                                                const { conteudo, dica } = celula(c, coluna.chave);

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
                )}

                {aba === 'detalhe' && aberta !== null && (
                    <>
                        <div className="rt-detalhe-cabeca">
                            <div>
                                <p className="sobrancelha">Fiscalização · {aberta.origem}</p>
                                <h2 className="card-titulo">{aberta.protocolo}</h2>
                                <p className="card-sub">
                                    Aberta em {dataHoraBR(aberta.aberto_em)}
                                    {aberta.equipe ? ` · Equipe ${aberta.equipe}` : ''}
                                    {aberta.lider ? ` · líder ${aberta.lider}` : ''}
                                </p>
                            </div>
                            <span className={cn('selo', aberta.aba === 'arquivo' ? 'selo-neutro' : (TOM_DA_POSSE[aberta.posse] ?? 'selo-neutro'))}>
                                {aberta.aba === 'arquivo' ? 'Arquivada' : `Com: ${aberta.posse}`}
                            </span>
                        </div>

                        <dl className="rt-ficha">
                            <div>
                                <dt>Desfecho</dt>
                                <dd>{aberta.desfecho}</dd>
                            </div>
                            <div>
                                <dt>Processo</dt>
                                <dd>
                                    {aberta.demanda === null ? (
                                        <span style={fraco}>Sem demanda — nasceu em rua</span>
                                    ) : (
                                        <a href={aberta.demanda.url}>
                                            {aberta.demanda.protocolo} · {aberta.demanda.canal_nome}{' '}
                                            <ExternalLink size={13} aria-hidden />
                                        </a>
                                    )}
                                    {aberta.demanda !== null && (
                                        <div style={fraco}>
                                            {aberta.demanda.assunto} · {aberta.demanda.situacao_resumida}
                                        </div>
                                    )}
                                </dd>
                            </div>
                            {aberta.encaminhado_ao_chefe_em !== null && (
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <dt>Encaminhada ao Chefe de Setor</dt>
                                    <dd>
                                        {dataHoraBR(aberta.encaminhado_ao_chefe_em)}
                                        {aberta.encaminhado_por ? ` · ${aberta.encaminhado_por}` : ''}
                                        {aberta.motivo && <div>{aberta.motivo}</div>}
                                    </dd>
                                </div>
                            )}
                            {aberta.arquivado_em !== null && (
                                <div>
                                    <dt>Arquivada em</dt>
                                    <dd>{dataHoraBR(aberta.arquivado_em)}</dd>
                                </div>
                            )}
                        </dl>

                        {conduz && aberta.aba === 'andamento' && (
                            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', margin: '14px 0' }}>{botoes([aberta.id])}</div>
                        )}

                        {arquiva && aberta.aba === 'encaminhadas' && (
                            <div className="rt-sugestao" style={{ margin: '14px 0' }}>
                                <Info size={16} aria-hidden />
                                <div>
                                    {aberta.demanda !== null ? (
                                        <>
                                            O líder encaminhou o resultado. A deliberação é na{' '}
                                            <a href={aberta.demanda.url}>Caixa de Entrada — {aberta.demanda.protocolo}</a>:
                                            responder à origem (a Fiscalização vai para o Arquivo) ou encaminhar de novo
                                            ao líder (abre uma Fiscalização irmã).
                                        </>
                                    ) : (
                                        <>
                                            Fiscalização sem processo atrás: depois de ler o resultado, arquive.{' '}
                                            <BotaoAcao className="btn btn-secondary btn-sm" icone={<Archive size={15} aria-hidden />}
                                                carregando={enviando === 'arquivar'} ocupado={ocupado} onClick={() => arquivar(aberta)}>
                                                Arquivar
                                            </BotaoAcao>
                                        </>
                                    )}
                                </div>
                            </div>
                        )}

                        <h3 className="card-titulo" style={{ marginTop: 22 }}>
                            Vistorias ({aberta.vistorias.length})
                        </h3>
                        {aberta.vistorias.length === 0 ? (
                            <p className="card-sub">
                                Nenhuma vistoria ainda — {aberta.aguarda_envio ? 'a Fiscalização espera o envio à equipe.' : 'a equipe está em campo.'}
                            </p>
                        ) : (
                            aberta.vistorias.map((v, i) => (
                                <div key={v.id} className="card-premium" style={{ margin: '10px 0', boxShadow: 'none' }}>
                                    <p className="sobrancelha" style={{ marginBottom: 6 }}>
                                        {i + 1}ª vistoria · {v.protocolo}
                                    </p>
                                    <DetalheDaVistoria r={v} recomendacoesDoFiscal={recomendacoesDoFiscal} liderDa={liderDa} />
                                </div>
                            ))
                        )}

                        {aberta.irmas.length > 0 && (
                            <>
                                <h3 className="card-titulo" style={{ marginTop: 22 }}>
                                    <Layers size={16} aria-hidden /> Outras Fiscalizações deste processo
                                </h3>
                                <p className="card-sub">Cada encaminhamento do Chefe de Setor ao líder abre uma. Todas ficam consultáveis.</p>
                                <ul style={{ margin: '8px 0 0', paddingLeft: 18 }}>
                                    {aberta.irmas.map((irma) => (
                                        <li key={irma.id} style={{ marginBottom: 4 }}>
                                            <button type="button" className="btn-link" onClick={() => setAbertoId(irma.id)}>
                                                {irma.protocolo}
                                            </button>{' '}
                                            · aberta em {dataBR(irma.aberto_em)} · {irma.desfecho} ·{' '}
                                            {irma.aba === 'arquivo' ? 'Arquivada' : `com: ${irma.posse}`} ·{' '}
                                            {contar(irma.total_vistorias, 'vistoria', 'vistorias')}
                                        </li>
                                    ))}
                                </ul>
                            </>
                        )}

                        <hr className="rt-regua" />
                        <button type="button" className="btn btn-secondary btn-sm" onClick={() => trocarAba(aberta.aba)}>
                            <X size={15} aria-hidden /> Fechar a Fiscalização
                        </button>
                    </>
                )}
            </div>

            {decisao !== null && (
                <Sobreposicao clicandoFora={ocupado ? undefined : () => setDecisao(null)}>
                    <div className="card-premium" style={{ width: '100%', maxWidth: 620 }} role="dialog" aria-modal="true">
                        <h2 className="sobreposicao-titulo">
                            {decisao === 'equipe' && 'Encaminhar à equipe'}
                            {decisao === 'operacao' && 'Incluir em operação'}
                            {decisao === 'voltar' && 'Mandar a equipe voltar'}
                            {decisao === 'chefe' && 'Encaminhar ao Chefe de Setor'}
                        </h2>
                        <p className="sobreposicao-texto">
                            {decisao === 'equipe' && `${contar(paraEnviar(alvos).length, 'Fiscalização vai', 'Fiscalizações vão')} para a fila dos fiscais da equipe.`}
                            {decisao === 'operacao' && `${contar(paraEnviar(alvos).length, 'Fiscalização entra', 'Fiscalizações entram')} numa operação já planejada.`}
                            {decisao === 'voltar' && 'O ponto volta para a equipe, com o que ela deve procurar desta vez — na mesma Fiscalização.'}
                            {decisao === 'chefe' && 'O caso volta para o Chefe de Setor deliberar.'}
                        </p>

                        {decisao === 'operacao' ? (
                            <div className="form-group">
                                <label className="form-label" htmlFor="op">Operação {obrigatorio}</label>
                                <select id="op" className="form-control" value={operacaoEscolhida} onChange={(e) => setOperacaoEscolhida(e.target.value)}>
                                    {operacoes.map((o) => (
                                        <option key={o.nome} value={o.nome}>{o.nome}</option>
                                    ))}
                                </select>
                            </div>
                        ) : (
                            <div className="form-group">
                                <label className="form-label" htmlFor="texto">
                                    {decisao === 'equipe' && 'Orientação aos fiscais'}
                                    {decisao === 'voltar' && <>Justificativa {obrigatorio}</>}
                                    {decisao === 'chefe' && <>Motivo {obrigatorio}</>}
                                </label>
                                <textarea id="texto" className="form-control" rows={3} maxLength={1000} value={texto}
                                    onChange={(e) => setTexto(e.target.value)}
                                    placeholder={
                                        decisao === 'equipe' ? 'Opcional — ex.: ir depois das 18h, as mesas saem à noite'
                                            : decisao === 'voltar' ? 'O que a equipe deve procurar, e em que dia ou horário'
                                                : 'Porque você está encaminhando ao Chefe'
                                    } />
                                <p className="form-ajuda">
                                    {decisao === 'voltar' && 'Mandar a equipe de volta consome tempo de trabalho, portanto seja específico nessa justificativa.'}
                                    {decisao === 'chefe' && 'Contexto para o Chefe de Setor saber deliberar para a Coordenadoria ou solicitar nova Fiscalização.'}
                                </p>
                            </div>
                        )}

                        <div className="sobreposicao-acoes">
                            <button type="button" className="btn btn-secondary btn-sm" disabled={ocupado} onClick={() => setDecisao(null)}>
                                Voltar
                            </button>
                            <BotaoAcao
                                carregando={enviando === decisao}
                                ocupado={ocupado}
                                disabled={(decisao === 'voltar' || decisao === 'chefe') && texto.trim().length < 15}
                                rotuloCarregando="Registrando…"
                                onClick={confirmar}
                            >
                                Confirmar
                            </BotaoAcao>
                        </div>
                    </div>
                </Sobreposicao>
            )}
        </>
    );
}

Fiscalizacoes.layout = {
    breadcrumbs: [{ title: 'Fiscalizações', href: index() }],
};
