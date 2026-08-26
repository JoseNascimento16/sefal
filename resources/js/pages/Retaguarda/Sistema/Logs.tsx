import { Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, TriangleAlert } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { Spinner } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import {
    Paginacao,
    ThOrdenavel,
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
    janela,
    limite,
    truncado,
}: {
    logs: Ocorrencia[];
    janela: { de: string; ate: string };
    limite: number;
    truncado: boolean;
}) {
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
                        colunas={[
                            { chave: 'ocorridoEm', titulo: 'Quando' },
                            { chave: 'requestId', titulo: 'Código' },
                            { chave: 'classe', titulo: 'Tipo' },
                            { chave: 'mensagem', titulo: 'Mensagem' },
                            { chave: 'caminho', titulo: 'Caminho' },
                            { chave: 'metodo', titulo: 'Verbo' },
                            { chave: 'usuario', titulo: 'Usuário' },
                        ]}
                        linhas={linhasExportacao}
                    />
                </div>

                <div className="table-wrap">
                    <table className="data-table">
                        <thead>
                            <tr>
                                {/* Coluna do sinal de abrir/fechar: sem rótulo à
                                    vista, mas nomeada para quem navega por leitor
                                    de tela. */}
                                <th
                                    style={{ width: 34 }}
                                    aria-label="Detalhe"
                                />
                                <ThOrdenavel
                                    campo="ocorridoEm"
                                    acessor="ocorridoEm"
                                    ord={ord}
                                >
                                    Quando
                                </ThOrdenavel>
                                <ThOrdenavel
                                    campo="requestId"
                                    acessor="requestId"
                                    ord={ord}
                                >
                                    Código
                                </ThOrdenavel>
                                <ThOrdenavel
                                    campo="classe"
                                    acessor="classe"
                                    ord={ord}
                                >
                                    Tipo
                                </ThOrdenavel>
                                <ThOrdenavel
                                    campo="mensagem"
                                    acessor="mensagem"
                                    ord={ord}
                                >
                                    Mensagem
                                </ThOrdenavel>
                                <ThOrdenavel
                                    campo="caminho"
                                    acessor="caminho"
                                    ord={ord}
                                >
                                    Caminho
                                </ThOrdenavel>
                                <ThOrdenavel
                                    campo="usuario"
                                    acessor="usuario"
                                    ord={ord}
                                >
                                    Usuário
                                </ThOrdenavel>
                            </tr>
                        </thead>

                        <tbody>
                            {pag.visiveis.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="tabela-vazia">
                                        {logs.length === 0
                                            ? 'Nenhuma falha registrada neste período — é o que se espera de um sistema saudável.'
                                            : 'Nenhuma ocorrência casa com a busca. Limpe o campo para ver todas as do período.'}
                                    </td>
                                </tr>
                            )}

                            {/* Cada ocorrência ocupa DUAS linhas quando aberta (a
                                da tabela e a do rastro), e uma tabela não aceita
                                um <div> entre elas — daí o fragmento nomeado, que
                                é quem carrega a chave. */}
                            {pag.visiveis.map((log) => (
                                <Fragment key={log.id}>
                                    <tr
                                        {...linhaClicavel(
                                            () => alternar(log),
                                            'Abrir ou fechar o rastro desta ocorrência',
                                        )}
                                    >
                                        <td>
                                            {aberta === log.id ? (
                                                <ChevronDown
                                                    size={16}
                                                    aria-hidden
                                                />
                                            ) : (
                                                <ChevronRight
                                                    size={16}
                                                    aria-hidden
                                                />
                                            )}
                                        </td>
                                        <td className="cell-id">
                                            {dataHoraBR(log.ocorridoEm)}
                                        </td>
                                        <td className="cell-id">
                                            {log.requestId ?? VAZIO}
                                        </td>
                                        <td>{log.classe}</td>
                                        <td>{log.mensagem}</td>
                                        <td>
                                            <span className="selo selo-neutro">
                                                {log.metodo ?? VAZIO}
                                            </span>{' '}
                                            {log.caminho ?? VAZIO}
                                        </td>
                                        <td>
                                            {log.usuario ?? (
                                                <span
                                                    style={{
                                                        color: 'var(--sm-texto-fraco)',
                                                    }}
                                                >
                                                    sem usuário
                                                </span>
                                            )}
                                        </td>
                                    </tr>

                                    {aberta === log.id && (
                                        <tr>
                                            <td colSpan={7}>
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
