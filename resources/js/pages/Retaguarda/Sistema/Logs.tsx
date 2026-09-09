import { Head, router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment, useMemo, useState } from 'react';
import { Spinner } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import {
    Paginacao,
    useOrdenacao,
    usePaginacao,
} from '@/components/retaguarda/th-ordenavel';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { dataBR, dataHoraBR, hojeISO, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import { detalhe, index } from '@/routes/retaguarda/logs';

/**
 * Logs — as exceções que o sistema capturou.
 *
 * A tela existe para uma cena concreta: alguém liga dizendo "deu erro", dita o
 * código que apareceu na página, e quem atende encontra a ocorrência exata em
 * segundos — sem entrar no servidor caçar arquivo de log.
 *
 * É SÓ LEITURA. Log de erro é a prova do que aconteceu; um botão de apagar aqui
 * apagaria a única trilha de um defeito de produção.
 *
 * O rastro (a pilha de chamadas) NÃO vem na listagem: ele é campo longo e custa
 * uma ida ao banco por linha. Ele é buscado quando alguém abre UMA ocorrência.
 *
 * ── A grade responde ao diagnóstico, não ao fluxo ───────────────────────────
 *
 * Quem abre esta tela procura *o que quebrou, onde e quando* — em regra o mais
 * recente, ou o que se repete. Então a linha leva quando · código · tipo do
 * erro · em que tela · quem estava lá, e a MENSAGEM da exceção abre no clique,
 * junto do rastro: era ela que ocupava seis linhas de texto dentro da célula, e
 * é justamente o que impedia varrer o resto. As colunas vêm do catálogo
 * (`config/listagens_da_retaguarda.php`); a régua está em
 * `docs/padroes/listagem-clean.md`.
 */

interface Ocorrencia {
    id: number;
    requestId: string | null;
    /** ISO — a tela converte para BR na hora de mostrar. */
    ocorridoEm: string | null;
    classe: string;
    mensagem: string;
    /**
     * O CAMINHO da requisição, já sem a consulta e com os trechos sensíveis
     * mascarados pelo servidor (`reset-password/[token]`). Nunca o endereço
     * completo: a consulta poderia carregar e-mail, documento ou termo de busca.
     */
    caminho: string | null;
    metodo: string | null;
    usuario: string | null;
}

/**
 * O nome pelo qual as pessoas chamam a exceção — o último trecho da classe.
 *
 * "Illuminate\Database\QueryException" vira "QueryException": é o que alguém
 * diz ao relatar o erro, e o pacote inteiro gastaria a coluna repetindo
 * "Illuminate\…". O nome completo continua na dica, na ficha e no arquivo.
 */
function nomeCurto(classe: string): string {
    const partes = classe.split('\\');

    return partes[partes.length - 1] || classe;
}

/**
 * O endereço da requisição que falhou, como se lê: `GET /retaguarda/logs`.
 *
 * O caminho é gravado SEM a barra da frente, e a barra é acrescentada aqui — com
 * o cuidado de não dobrá-la quando o caminho já é a raiz (`/`), que é o que
 * chega quando a falha nasceu fora de uma tela.
 */
function enderecoDe(log: Ocorrencia): string {
    return `${log.metodo ?? ''} /${(log.caminho ?? '').replace(/^\/+/, '')}`.trim();
}

/** As expressões do domínio que a busca reconhece e retira do texto livre. */
type Faceta = 'hoje' | 'sem-usuario';

const FACETAS = [
    { expressao: /\bhoje\b/, valor: 'hoje' as const },
    {
        expressao: /\bsem usuari\w*\b|\banonim\w*\b/,
        valor: 'sem-usuario' as const,
    },
];

export default function Logs({
    logs,
    listagens,
    janela,
    limite,
    truncado,
}: {
    logs: Ocorrencia[];
    listagens: Listagens;
    janela: { de: string; ate: string };
    limite: number;
    truncado: boolean;
}) {
    const listagem = listagens['sistema.logs'];
    const [busca, setBusca] = useState('');
    const [aberta, setAberta] = useState<number | null>(null);
    const [rastros, setRastros] = useState<Record<number, string>>({});
    const [buscandoRastro, setBuscandoRastro] = useState<number | null>(null);

    // `hojeISO` e não `toISOString()`: este converte para UTC, e num fuso
    // negativo como o nosso o "hoje" vira o dia seguinte a partir das 21h — a
    // faceta "hoje" deixaria de casar com qualquer coisa justamente no plantão
    // da noite, sem nenhum sinal de que estava errada.
    const hoje = hojeISO();

    const filtradas = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return logs.filter((log) => {
            if (
                facetas.includes('hoje') &&
                log.ocorridoEm?.slice(0, 10) !== hoje
            ) {
                return false;
            }

            if (facetas.includes('sem-usuario') && log.usuario !== null) {
                return false;
            }

            return casaTermos(termos, [
                log.requestId,
                log.classe,
                log.mensagem,
                log.caminho,
                log.metodo,
                log.usuario,
            ]);
        });
    }, [logs, busca, hoje]);

    const ord = useOrdenacao(filtradas, {
        campo: 'ocorridoEm',
        dir: 'desc',
        acessor: 'ocorridoEm',
    });
    const pag = usePaginacao(ord.itens);

    /** Como ORDENAR por cada coluna da grade — a chave é a do catálogo. */
    const acessores: Record<string, AcessorOrd<Ocorrencia> | undefined> = {
        ocorridoEm: 'ocorridoEm',
        requestId: 'requestId',
        // Pelo nome CURTO, que é o que a coluna mostra: ordenado pelo nome
        // completo, o agrupamento sairia por pacote e a coluna pareceria fora
        // de ordem para quem olha.
        classe: (log) => nomeCurto(log.classe),
        caminho: 'caminho',
        usuario: 'usuario',
    };

    /**
     * Abre (ou fecha) uma ocorrência. O rastro é pedido ao servidor uma vez só e
     * fica guardado: reabrir a mesma linha não custa outra ida.
     */
    async function alternar(log: Ocorrencia) {
        if (aberta === log.id) {
            setAberta(null);

            return;
        }

        setAberta(log.id);

        if (rastros[log.id] !== undefined) {
            return;
        }

        setBuscandoRastro(log.id);

        try {
            const resposta = await fetch(detalhe(log.id).url, {
                headers: { Accept: 'application/json' },
            });
            const dados = await resposta.json();

            setRastros((atual) => ({
                ...atual,
                [log.id]: String(dados?.stack ?? '').trim(),
            }));
        } catch {
            // Falha ao buscar o rastro não pode ficar muda: quem abriu a linha
            // veria um espaço em branco e concluiria que o erro não tem rastro.
            setRastros((atual) => ({
                ...atual,
                [log.id]:
                    'Não foi possível carregar o rastro desta ocorrência. Tente novamente.',
            }));
        } finally {
            setBuscandoRastro(null);
        }
    }

    /** Troca a JANELA de dados — é o servidor que recorta o período. */
    function mudarJanela(campo: 'de' | 'ate', valor: string) {
        router.get(
            index().url,
            { ...janela, [campo]: valor },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    /** Cinza de apoio — o mesmo em toda célula que diz "isto não existe". */
    const fraco = { color: 'var(--sm-texto-fraco)' };

    /** O que cada célula desenha, com o texto inteiro para a dica. */
    function celula(
        log: Ocorrencia,
        chave: string,
    ): { conteudo: ReactNode; dica?: string; resumida?: boolean } {
        if (chave === 'ocorridoEm') {
            // Com a HORA, por exceção declarada no catálogo: num log a data
            // sozinha não identifica a ocorrência — um surto põe dezenas no
            // mesmo dia, e a pergunta da tela é "o que aconteceu agora".
            return { conteudo: dataHoraBR(log.ocorridoEm), dica: dataHoraBR(log.ocorridoEm) };
        }

        if (chave === 'requestId') {
            return log.requestId === null
                ? {
                      conteudo: <span style={fraco}>{VAZIO}</span>,
                      dica: 'Ocorrência sem código — nasceu fora de uma requisição (tarefa agendada ou trabalho em fila).',
                  }
                : { conteudo: log.requestId, dica: log.requestId };
        }

        if (chave === 'classe') {
            // O nome CURTO da classe, com o nome completo na dica: é assim que
            // as pessoas chamam o erro ("deu QueryException"), e o pacote inteiro
            // gastaria a coluna dizendo três vezes "Illuminate". A tela resume de
            // verdade aqui, então a dica é obrigatória e também anunciada.
            return {
                conteudo: nomeCurto(log.classe),
                dica: log.classe,
                resumida: nomeCurto(log.classe) !== log.classe,
            };
        }

        if (chave === 'caminho') {
            return log.caminho === null
                ? {
                      conteudo: <span style={fraco}>fora de uma requisição</span>,
                      dica: 'A falha não veio de uma tela: nasceu numa tarefa agendada ou num trabalho em fila.',
                  }
                : {
                      conteudo: log.caminho,
                      // O VERBO entra na dica, e não como um segundo selo na
                      // célula: chip ao lado de texto volta a empilhar conteúdo.
                      dica: enderecoDe(log),
                  };
        }

        return log.usuario === null
            ? {
                  conteudo: <span style={fraco}>sem usuário</span>,
                  dica: 'Ninguém autenticado na requisição — ou ela nem veio de uma tela.',
              }
            : { conteudo: log.usuario, dica: log.usuario };
    }

    // Só as chaves declaradas entram no arquivo, e a data sai em BR: o documento
    // é lido fora do sistema, onde ninguém traduz ISO.
    const linhasExportacao = ord.itens.map((log) => ({
        ocorridoEm: dataHoraBR(log.ocorridoEm),
        requestId: log.requestId ?? VAZIO,
        classe: log.classe,
        mensagem: log.mensagem,
        caminho: log.caminho ?? VAZIO,
        metodo: log.metodo ?? VAZIO,
        usuario: log.usuario ?? 'sem usuário',
    }));

    return (
        <>
            <Head title="Logs" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Sistema</p>
                    <h1>Logs</h1>
                    <p>
                        As falhas que o sistema capturou. Cada ocorrência guarda
                        o mesmo <strong>código</strong> que apareceu na tela de
                        quem estava usando o sistema — é por ele que se acha a
                        ocorrência exata que a pessoa relatou.
                    </p>
                </div>
            </div>

            <div className="card-premium">
                {/* O período é a JANELA dos dados (o que o servidor traz), não um
                    filtro paralelo à busca — que continua sendo a barra única. */}
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 14,
                        flexWrap: 'wrap',
                        marginBottom: 4,
                    }}
                >
                    <div className="form-group" style={{ margin: 0 }}>
                        <label className="form-label" htmlFor="periodo-de">
                            Período — de
                        </label>
                        <input
                            id="periodo-de"
                            type="date"
                            className="form-control"
                            value={janela.de}
                            max={janela.ate}
                            onChange={(e) => mudarJanela('de', e.target.value)}
                            style={{ width: 'auto' }}
                        />
                    </div>

                    <div className="form-group" style={{ margin: 0 }}>
                        <label className="form-label" htmlFor="periodo-ate">
                            até
                        </label>
                        <input
                            id="periodo-ate"
                            type="date"
                            className="form-control"
                            value={janela.ate}
                            min={janela.de}
                            onChange={(e) => mudarJanela('ate', e.target.value)}
                            style={{ width: 'auto' }}
                        />
                    </div>

                    <p className="form-ajuda" style={{ margin: '0 0 10px' }}>
                        {contar(logs.length, 'ocorrência', 'ocorrências')} no
                        período.
                    </p>
                </div>

                {truncado && (
                    <p className="form-erro" style={{ marginBottom: 12 }}>
                        <TriangleAlert size={15} aria-hidden /> O período tem
                        mais de {limite} ocorrências e a tela mostra as mais
                        recentes. Estreite o período para ver o resto — o que
                        ficou de fora continua guardado.
                    </p>
                )}

                <BuscaInteligente
                    busca={busca}
                    setBusca={setBusca}
                    placeholder="Procure por código, mensagem, tipo do erro, caminho ou usuário"
                    exemplos={['hoje', 'sem usuário', 'REQ-']}
                />

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        marginBottom: 10,
                    }}
                >
                    <BotaoExportar
                        titulo="Logs"
                        subtitulo="Sistema › Logs"
                        contexto={`Período: ${dataBR(janela.de)} a ${dataBR(janela.ate)}${
                            busca.trim() ? ` · busca: "${busca.trim()}"` : ''
                        }`}
                        // As colunas do ARQUIVO saem do mesmo catálogo da grade, e
                        // continuam INTEIRAS: a mensagem e o verbo saíram da tela,
                        // não do documento — que é o que se manda para alguém
                        // analisar.
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
                                        {logs.length === 0
                                            ? 'Nenhuma falha registrada neste período — é o que se espera de um sistema saudável.'
                                            : 'Nenhuma ocorrência casa com a busca. Limpe o campo para ver todas as do período.'}
                                    </td>
                                </tr>
                            )}

                            {/* Cada ocorrência ocupa DUAS linhas quando aberta (a
                                da tabela e a da ficha), e uma tabela não aceita
                                um <div> entre elas — daí o fragmento nomeado, que
                                é quem carrega a chave. */}
                            {pag.visiveis.map((log) => (
                                <Fragment key={log.id}>
                                    <tr
                                        {...linhaClicavel(
                                            () => alternar(log),
                                            'Abrir ou fechar a mensagem e o rastro desta ocorrência',
                                        )}
                                    >
                                        {listagem.grade.map((coluna) => {
                                            const { conteudo, dica, resumida } =
                                                celula(log, coluna.chave);

                                            return (
                                                <Celula
                                                    key={coluna.chave}
                                                    coluna={coluna}
                                                    dica={dica}
                                                    resumida={resumida}
                                                    className={
                                                        coluna.chave ===
                                                        'requestId'
                                                            ? 'cell-id'
                                                            : undefined
                                                    }
                                                >
                                                    {conteudo}
                                                </Celula>
                                            );
                                        })}
                                    </tr>

                                    {aberta === log.id && (
                                        <tr className="linha-detalhe">
                                            <td colSpan={listagem.grade.length}>
                                                {/* O que DESCEU da grade — a
                                                    mensagem inteira e o verbo — mais
                                                    o rastro, que nunca esteve nela.
                                                    É aqui que os três se leem. */}
                                                <dl className="rt-ficha">
                                                    <div>
                                                        <dt>Tipo do erro</dt>
                                                        <dd>{log.classe}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>
                                                            Requisição que falhou
                                                        </dt>
                                                        <dd>
                                                            {log.caminho === null
                                                                ? 'Fora de uma requisição — tarefa agendada ou trabalho em fila.'
                                                                : enderecoDe(log)}
                                                        </dd>
                                                    </div>
                                                </dl>

                                                <p
                                                    className="card-titulo"
                                                    style={{
                                                        margin: '14px 0 4px',
                                                        fontSize: 14,
                                                    }}
                                                >
                                                    Mensagem
                                                </p>
                                                <p
                                                    className="card-sub"
                                                    style={{ margin: 0 }}
                                                >
                                                    {log.mensagem}
                                                </p>

                                                <p
                                                    className="card-titulo"
                                                    style={{
                                                        margin: '14px 0 4px',
                                                        fontSize: 14,
                                                    }}
                                                >
                                                    Rastro
                                                </p>
                                                {buscandoRastro === log.id ? (
                                                    <p className="card-sub">
                                                        <Spinner tamanho={14} />{' '}
                                                        Carregando o rastro…
                                                    </p>
                                                ) : (
                                                    <pre
                                                        style={{
                                                            margin: 0,
                                                            maxHeight: 320,
                                                            overflow: 'auto',
                                                            fontSize: 12,
                                                            lineHeight: 1.55,
                                                            whiteSpace:
                                                                'pre-wrap',
                                                            wordBreak:
                                                                'break-word',
                                                            color: 'var(--sm-texto-corpo)',
                                                        }}
                                                    >
                                                        {rastros[log.id] ||
                                                            'Esta ocorrência não guardou rastro.'}
                                                    </pre>
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
        </>
    );
}

Logs.layout = {
    breadcrumbs: [
        {
            title: 'Logs',
            href: index(),
        },
    ],
};
