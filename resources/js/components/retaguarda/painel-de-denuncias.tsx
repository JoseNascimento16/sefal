import {
    Antenna,
    ArrowRightCircle,
    CornerUpLeft,
    FileText,
    Info,
    Inbox,
    ListChecks,
    MapPinOff,
    Paperclip,
    RotateCcw,
    Send,
    Siren,
    TriangleAlert,
    UserRound,
    UserX,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import { TramiteDeDenuncia } from '@/components/retaguarda/tramite-de-denuncia';
import { Sobreposicao } from '@/components/retaguarda/sobreposicao';
import {
    Paginacao,
    ThOrdenavel,
    useOrdenacao,
    usePaginacao,
} from '@/components/retaguarda/th-ordenavel';
import type {
    Canal,
    Denuncia,
    EquipeResumo,
    Etapa,
    Operacao,
} from '@/dados-prototipo/denuncias';
import {
    AGUARDANDO_DIRECIONAMENTO,
    AGUARDANDO_TRIAGEM,
    TOM_DA_SITUACAO,
} from '@/dados-prototipo/denuncias';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta, semAcento } from '@/lib/busca';
import { dataBR, dataHoraBR, hojeISO, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import { cn } from '@/lib/utils';
import {
    devolver as rotaDevolver,
    direcionar as rotaDirecionar,
    encaminhar as rotaEncaminhar,
    operacao as rotaOperacao,
    reiniciar as rotaReiniciar,
} from '@/routes/retaguarda/denuncias';

/**
 * Denúncias das ouvidorias — PROTÓTIPO. O miolo das DUAS telas do módulo.
 *
 * As telas de canal (`e-Salvador` e `Fala Salvador`) são cascas de vinte linhas
 * que só declaram título e trilha: a mecânica é a mesma, e escrevê-la duas vezes
 * daria dois donos à mesma regra — um dia só uma das telas ganharia o campo
 * novo. O que varia entre os canais é declarado no servidor
 * (`config/prototipo_denuncias.php` → `canais`) e chega em `canal`.
 *
 * ── A tela é o fluxo, e o fluxo tem duas etapas com dois donos ───────────────
 *
 *   Triagem (administrativo)      → aba "A triar": encaminha à ÁREA derivada do
 *                                   bairro (sugestão editável, porque bairro
 *                                   compartilhado tem duas respostas certas) ou
 *                                   devolve/arquiva com justificativa.
 *   Direcionamento (gestor)       → aba "A direcionar": manda à EQUIPE ou anexa
 *                                   a uma OPERAÇÃO.
 *
 * As abas aparecem conforme a ETAPA de quem entrou, e o selo em cima diz qual é
 * a sua — o dono demonstra o fluxo entrando com perfis diferentes. Quem exerce
 * as duas (o administrador) vê as duas abas, na ordem do fluxo.
 *
 * As duas etapas operam em LOTE e uma a uma, com o MESMO caminho: o botão da
 * grade manda a seleção, o botão do detalhe manda um item só. Dois caminhos
 * seriam a mesma regra duas vezes.
 *
 * ⚠️ A busca é o filtro ÚNICO — não há chip de filtro paralelo. Os números do
 * topo são o resumo da mesma lista e, clicados, escrevem a faceta na busca. A
 * ABA, sim, troca a FONTE dos dados (cada uma é uma etapa do fluxo), e é por
 * isso que ela entra no contexto da exportação.
 */

interface Props {
    canal: Canal;
    denuncias: Denuncia[];
    situacoes: string[];
    /** Os desfechos que uma vistoria pode ter — catálogo do servidor. */
    desfechos: string[];
    motivos: string[];
    destinos: string[];
    equipes: EquipeResumo[];
    areas: string[];
    /** Quem responde por cada área — o triador precisa ver para QUEM encaminha. */
    gestores: Record<string, { nome: string; matricula: string | null }>;
    operacoes: Operacao[];
    /** As etapas do fluxo que esta pessoa exerce — quem responde é o servidor. */
    etapas: Etapa[];
    /** As áreas que esta pessoa responde como gestora (vazio para quem não é). */
    areasDoGestor: string[];
    /** A listagem já veio recortada por essas áreas? Quem recorta é o servidor. */
    recorteDeArea: boolean;
    /** A sessão já decidiu algo sobre a demonstração? */
    alterada: boolean;
}

type Aba = 'triagem' | 'direcionamento' | 'todas' | 'detalhe';

/** Qual decisão está sendo tomada na folha sobreposta. */
type Decisao = 'encaminhar' | 'devolver' | 'direcionar' | 'operacao' | null;

/** O que a busca reconhece além das palavras soltas. */
type Faceta =
    | { tipo: 'situacao'; valor: string }
    | { tipo: 'desfecho'; valor: string }
    | { tipo: 'area'; valor: string }
    | { tipo: 'equipe'; valor: string }
    | { tipo: 'em-trabalho' }
    | { tipo: 'anonima' }
    | { tipo: 'sem-endereco' }
    | { tipo: 'com-anexo' }
    | { tipo: 'prazo-vencido' }
    | { tipo: 'hoje' };

/**
 * As situações em que a denúncia já virou trabalho de campo — o que o número
 * "em trabalho" conta e o que a faceta de mesmo nome filtra.
 *
 * Está declarado uma vez porque os dois leem a MESMA lista: com uma cópia em
 * cada lugar, um dia o número contaria um conjunto e o filtro mostraria outro.
 *
 * As duas situações de pós-vistoria entram aqui: notificação com prazo correndo
 * e retorno vencido são trabalho EM ABERTO — a denúncia não se encerrou, e
 * deixá-las fora faria o número "em trabalho" esconder justamente os casos em
 * que alguém tem de voltar ao ponto.
 */
const EM_TRABALHO = [
    'Direcionada à equipe',
    'Em operação',
    'Em campo',
    'Aguardando regularização',
    'Retorno vencido',
];

/** Uma expressão de busca a partir de um valor do domínio, sem acento e inteira. */
function expressaoDe(valor: string): RegExp {
    const alvo = semAcento(valor).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return new RegExp(`\\b${alvo}\\b`);
}

/** Como uma denúncia anônima se apresenta — nunca um espaço em branco. */
function quemDenunciou(d: Denuncia): string {
    return d.anonima ? 'Anônimo' : (d.requerente ?? VAZIO);
}

/** O endereço numa linha, do jeito que o canal entregou. */
function enderecoDe(d: Denuncia): string {
    return [d.logradouro, d.numero, d.referencia]
        .filter((parte) => parte !== null && String(parte).trim() !== '')
        .join(', ');
}

/**
 * O que vem DEPOIS na vida da denúncia — dito como próximo passo, nunca como
 * fato registrado.
 *
 * Existe uma resposta por situação em que a bola está com alguém, e `null` para
 * quem já se encerrou: escrever "próximo passo" numa denúncia concluída ou
 * arquivada prometeria um ato que não vai acontecer. E o próximo passo do que
 * está com prazo correndo é o RETORNO, não a vistoria — quem lê precisa saber
 * que alguém tem de voltar ao ponto.
 */
function proximoPassoDe(
    d: Denuncia,
): { o_que: string; quem: string; detalhe: string } | null {
    const equipe = `Equipe ${d.equipe ?? VAZIO}`;

    if (['Direcionada à equipe', 'Em operação'].includes(d.situacao)) {
        return {
            o_que: 'Vistoria em campo',
            quem: equipe,
            detalhe:
                'A equipe recebe a denúncia no aplicativo, vistoria o ponto e registra o desfecho.',
        };
    }

    /*
     * Vistoria ABERTA: a equipe já está no ponto e o que falta é o desfecho.
     * Sem esta resposta a linha do tempo parava no passo da vistoria em
     * andamento, e quem lê não via quem deve o próximo ato — a denúncia parecia
     * estacionada quando na verdade está com a equipe, na rua, agora.
     */
    if (d.situacao === 'Em campo') {
        return {
            o_que: 'Desfecho da vistoria',
            quem: equipe,
            detalhe:
                'A equipe está no ponto: encerra a vistoria registrando o desfecho — regularização no local, nada encontrado, ou o documento que o caso exigir.',
        };
    }

    if (d.situacao === 'Aguardando regularização') {
        return {
            o_que: 'Retorno de fiscalização',
            quem: equipe,
            detalhe:
                'Vencido o prazo da notificação, a equipe volta ao ponto para conferir se a situação foi regularizada.',
        };
    }

    if (d.situacao === 'Retorno vencido') {
        return {
            o_que: 'Próxima medida',
            quem: d.area === null ? 'Gestão da área' : `Gestão da ${d.area}`,
            detalhe:
                'O prazo venceu com a situação mantida: cabe ao gestor da área decidir a medida seguinte.',
        };
    }

    return null;
}

/** Para onde a denúncia está indo hoje: a operação, a equipe, ou nada ainda. */
function destinoAtual(d: Denuncia): string {
    if (d.operacao !== null) {
        return d.operacao;
    }

    return d.equipe === null ? VAZIO : `Equipe ${d.equipe}`;
}

/**
 * Folha sobreposta de DECISÃO — a janela com formulário.
 *
 * A `ModalConfirm` do sistema resolve a confirmação de uma frase; aqui a decisão
 * tem campos (motivo, justificativa, equipe, operação), e é a mesma casca do DS
 * — cartão, título, ações à direita — com o corpo aberto para o formulário.
 */
function FolhaDeDecisao({
    titulo,
    icone,
    children,
    rotulo,
    iconeConfirmar,
    processando,
    impedimento,
    onCancelar,
    onConfirmar,
}: {
    titulo: string;
    icone: ReactNode;
    children: ReactNode;
    rotulo: string;
    iconeConfirmar: ReactNode;
    processando: boolean;
    /** O que falta para a decisão poder ser tomada — dito, não só desabilitado. */
    impedimento?: string | null;
    onCancelar: () => void;
    onConfirmar: () => void;
}) {
    return (
        <Sobreposicao clicandoFora={processando ? undefined : onCancelar}>
            <div
                className="card-premium"
                style={{
                    width: '100%',
                    maxWidth: 620,
                    maxHeight: 'min(92vh, 100% - 8px)',
                    overflowY: 'auto',
                }}
                role="dialog"
                aria-modal="true"
                aria-label={titulo}
                onClick={(e) => e.stopPropagation()}
            >
                <h2 className="sobreposicao-titulo">
                    {icone} {titulo}
                </h2>

                <div style={{ marginBottom: 18 }}>{children}</div>

                {impedimento ? (
                    <p className="form-erro" style={{ marginBottom: 12 }}>
                        <TriangleAlert size={15} aria-hidden /> {impedimento}
                    </p>
                ) : null}

                <div className="sobreposicao-acoes">
                    <button
                        type="button"
                        className="btn btn-secondary btn-sm"
                        onClick={onCancelar}
                        disabled={processando}
                    >
                        Voltar
                    </button>

                    <BotaoAcao
                        icone={iconeConfirmar}
                        carregando={processando}
                        disabled={Boolean(impedimento)}
                        rotuloCarregando="Enviando…"
                        onClick={onConfirmar}
                    >
                        {rotulo}
                    </BotaoAcao>
                </div>
            </div>
        </Sobreposicao>
    );
}

export function PainelDeDenuncias({
    canal,
    denuncias,
    situacoes,
    desfechos,
    motivos,
    destinos,
    equipes,
    areas,
    gestores,
    operacoes,
    etapas,
    areasDoGestor,
    recorteDeArea,
    alterada,
}: Props) {
    const { enviando, ocupado, enviar } = useEnvio();

    const tria = etapas.includes('triagem');
    const direciona = etapas.includes('direcionamento');

    /** "Área 5 — Boca do Rio", como o selo da etapa e os avisos a nomeiam. */
    const nomeDaArea = (area: string): string => {
        const equipe = equipes.find((e) => e.area === area);

        return equipe === undefined ? area : `${area} — ${equipe.regiao}`;
    };

    /** O gestor de uma área, ou null quando a estrutura não registra nenhum. */
    const gestorDa = (area: string): string | null => {
        const nome = gestores[area]?.nome ?? '';

        return nome.trim() === '' ? null : nome;
    };

    const [aba, setAba] = useState<Aba>(
        tria ? 'triagem' : direciona ? 'direcionamento' : 'todas',
    );
    const [busca, setBusca] = useState('');
    const [abertaId, setAbertaId] = useState<number | null>(null);
    const [selecionadas, setSelecionadas] = useState<number[]>([]);
    const [decisao, setDecisao] = useState<Decisao>(null);
    /** Os identificadores que a decisão em curso alcança — lote ou um só. */
    const [alvos, setAlvos] = useState<number[]>([]);

    // `hojeISO` e não `toISOString()`: este converte para UTC, e num fuso
    // negativo como o nosso o "hoje" vira o dia seguinte a partir das 21h.
    const hoje = hojeISO();

    /*
     * A área CONFIRMADA de cada denúncia na triagem. Começa vazia: o valor
     * mostrado é a sugestão do bairro, e só entra aqui o que a pessoa trocou —
     * assim a sugestão continua acompanhando um ajuste na estrutura de áreas em
     * vez de ficar congelada no que a tela viu primeiro.
     */
    const [areaPorId, setAreaPorId] = useState<Record<number, string>>({});

    const areaDe = (d: Denuncia): string =>
        areaPorId[d.id] ?? d.area ?? d.area_sugerida?.area ?? '';

    // ── Busca ───────────────────────────────────────────────────────────────

    /*
     * As facetas nascem dos CATÁLOGOS que o servidor mandou, e não de uma lista
     * escrita aqui: área, equipe e situação são os mesmos valores que a validação
     * aceita. Escritas na tela, um dia a busca reconheceria uma área que já não
     * existe e deixaria de reconhecer a que nasceu.
     *
     * A ordem importa: as mais específicas primeiro, senão a genérica engole a
     * outra ("recebidas hoje" tem de ser lido antes de "recebida").
     */
    const facetas = useMemo(() => {
        const lista: { expressao: RegExp; valor: Faceta }[] = [
            { expressao: /\brecebidas? hoje\b|\bhoje\b/, valor: { tipo: 'hoje' } },
            {
                expressao: /\bprazo vencido\b|\bvencid\w*\b|\batrasad\w*\b/,
                valor: { tipo: 'prazo-vencido' },
            },
            {
                expressao: /\banonim\w*\b|\bsem identificacao\b|\bsem requerente\b/,
                valor: { tipo: 'anonima' },
            },
            {
                expressao: /\bsem endereco\b|\bendereco impreciso\b|\bsem numero\b|\bsem referencia\b/,
                valor: { tipo: 'sem-endereco' },
            },
            { expressao: /\bcom anexo\b|\bcom foto\b|\banexos?\b/, valor: { tipo: 'com-anexo' } },
            /*
             * "Em trabalho" é o AGRUPAMENTO das três situações em que a denúncia
             * já virou trabalho de campo. Existe porque o número do cabeçalho
             * conta as três: sem esta faceta, clicar nele escreveria uma situação
             * só na busca e a lista mostraria menos linhas do que o número
             * prometeu — o tipo de contradição que faz duvidar de todos os
             * números da tela.
             */
            { expressao: /\bem trabalho\b/, valor: { tipo: 'em-trabalho' } },
        ];

        for (const situacao of situacoes) {
            lista.push({ expressao: expressaoDe(situacao), valor: { tipo: 'situacao', valor: situacao } });
        }

        /*
         * O DESFECHO da vistoria é faceta como a situação: "concluídas sem
         * documento" é a pergunta que mede se a fiscalização está sendo
         * educativa, e ela precisa ser respondível pela mesma barra de busca — a
         * tela não tem outro filtro.
         */
        for (const desfecho of desfechos) {
            lista.push({ expressao: expressaoDe(desfecho), valor: { tipo: 'desfecho', valor: desfecho } });
        }

        // "encaminhada" sozinha (sem "à área") é como as pessoas falam.
        lista.push({
            expressao: /\bencaminhad\w*\b/,
            valor: { tipo: 'situacao', valor: 'Encaminhada à área' },
        });
        lista.push({
            expressao: /\bdirecionad\w*\b/,
            valor: { tipo: 'situacao', valor: 'Direcionada à equipe' },
        });

        for (const equipe of equipes) {
            lista.push({
                expressao: expressaoDe(`equipe ${equipe.equipe}`),
                valor: { tipo: 'equipe', valor: equipe.equipe },
            });
        }

        for (const area of areas) {
            lista.push({ expressao: expressaoDe(area), valor: { tipo: 'area', valor: area } });
        }

        return lista;
    }, [situacoes, desfechos, equipes, areas]);

    // ── A fonte de cada aba: cada uma é uma ETAPA do fluxo ──────────────────

    const daEtapa = useMemo(
        () => ({
            triagem: denuncias.filter((d) => AGUARDANDO_TRIAGEM.includes(d.situacao)),
            direcionamento: denuncias.filter((d) =>
                AGUARDANDO_DIRECIONAMENTO.includes(d.situacao),
            ),
        }),
        [denuncias],
    );

    const fonte =
        aba === 'triagem'
            ? daEtapa.triagem
            : aba === 'direcionamento'
              ? daEtapa.direcionamento
              : denuncias;

    const filtradas = useMemo(() => {
        const { facetas: achadas, termos } = parseConsulta<Faceta>(busca, facetas);

        return fonte.filter((d) => {
            for (const faceta of achadas) {
                if (faceta.tipo === 'situacao' && d.situacao !== faceta.valor) {
                    return false;
                }

                if (faceta.tipo === 'desfecho' && d.desfecho !== faceta.valor) {
                    return false;
                }

                // A área casa tanto a JÁ definida quanto a sugerida: quem procura
                // "área 5" na triagem ainda não tem área gravada em nada.
                if (
                    faceta.tipo === 'area' &&
                    d.area !== faceta.valor &&
                    d.area_sugerida?.area !== faceta.valor
                ) {
                    return false;
                }

                if (faceta.tipo === 'equipe' && d.equipe !== faceta.valor) {
                    return false;
                }

                if (
                    faceta.tipo === 'em-trabalho' &&
                    !EM_TRABALHO.includes(d.situacao)
                ) {
                    return false;
                }

                if (faceta.tipo === 'anonima' && !d.anonima) {
                    return false;
                }

                if (faceta.tipo === 'sem-endereco' && !d.endereco_impreciso) {
                    return false;
                }

                if (faceta.tipo === 'com-anexo' && d.anexos.length === 0) {
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
                d.protocolo_origem,
                d.anonima ? 'anonimo' : d.requerente,
                d.telefone,
                d.email,
                d.assunto,
                d.categoria,
                d.relato,
                enderecoDe(d),
                d.bairro,
                d.area,
                d.area_sugerida?.area,
                d.equipe,
                d.operacao,
                d.situacao,
                d.desfecho,
                d.motivo,
            ]);
        });
    }, [fonte, busca, facetas, hoje]);

    const ord = useOrdenacao(filtradas, {
        campo: 'recebida_em_hora',
        dir: 'desc',
        acessor: 'recebida_em_hora',
    });
    const pag = usePaginacao(ord.itens);

    // Os números saem da MESMA lista que a grade desenha — não de uma segunda
    // consulta —, então não há como discordarem do que está logo abaixo.
    const numeros = useMemo(
        () => ({
            total: denuncias.length,
            triar: daEtapa.triagem.length,
            direcionar: daEtapa.direcionamento.length,
            emCampo: denuncias.filter((d) => EM_TRABALHO.includes(d.situacao)).length,
            retornadas: denuncias.filter((d) =>
                ['Devolvida', 'Arquivada'].includes(d.situacao),
            ).length,
            vencidas: denuncias.filter(
                (d) => d.prazo < hoje && AGUARDANDO_TRIAGEM.concat(AGUARDANDO_DIRECIONAMENTO).includes(d.situacao),
            ).length,
        }),
        [denuncias, daEtapa, hoje],
    );

    const aberta = denuncias.find((d) => d.id === abertaId) ?? null;

    /*
     * Toda resposta do servidor traz a lista nova, e a seleção antiga passa a
     * apontar para denúncias que já mudaram de etapa: limpar aqui é o que impede
     * o segundo clique de agir sobre o que acabou de sair da aba.
     */
    useEffect(() => {
        setSelecionadas([]);
        setDecisao(null);
        setAlvos([]);
    }, [denuncias]);

    function trocarAba(nova: Aba) {
        setAba(nova);
        // A aba troca a FONTE: a seleção feita na outra não existe mais aqui.
        setSelecionadas([]);
    }

    /**
     * O número do cabeçalho de uma ETAPA, clicado.
     *
     * Quem exerce a etapa vai para a aba dela. Quem NÃO a exerce vê o número —
     * ele é informação legítima sobre a fila do colega — mas não tem aba para
     * onde ir: aí o clique cai em "Todas" com a situação escrita na busca. Sem
     * isto o gestor clicava em "a triar" e chegava numa lista sem aba
     * selecionada e sem ação nenhuma: um estado que a tela não sabe explicar.
     */
    function irParaEtapa(etapa: Aba, exerce: boolean, situacao: string) {
        if (exerce) {
            setBusca('');
            trocarAba(etapa);

            return;
        }

        trocarAba('todas');
        setBusca(situacao);
    }

    function alternar(id: number) {
        setSelecionadas((atual) =>
            atual.includes(id) ? atual.filter((i) => i !== id) : [...atual, id],
        );
    }

    const idsVisiveis = ord.itens.map((d) => d.id);
    const todasMarcadas =
        idsVisiveis.length > 0 && idsVisiveis.every((id) => selecionadas.includes(id));

    function alternarTodas() {
        setSelecionadas(todasMarcadas ? [] : idsVisiveis);
    }

    /** A aba mostra caixas de seleção? Só onde há decisão a tomar. */
    const emLote =
        (aba === 'triagem' && tria) || (aba === 'direcionamento' && direciona);

    function abrirDecisao(qual: Decisao, ids: number[]) {
        setAlvos(ids);
        setDecisao(qual);
    }

    function abrirDetalhe(d: Denuncia) {
        setAbertaId(d.id);
        setAba('detalhe');
    }

    // ── Os formulários de cada decisão ──────────────────────────────────────

    const [observacao, setObservacao] = useState('');
    const [retorno, setRetorno] = useState({
        motivo: motivos[0] ?? '',
        justificativa: '',
        destino: destinos[0] ?? '',
    });
    const [envio, setEnvio] = useState({ equipe: '', justificativa: '' });
    const [operacaoForm, setOperacaoForm] = useState({
        nova: false,
        operacao: operacoes[0]?.nome ?? '',
        nome: '',
        area: areas[0] ?? '',
        equipe: equipes[0]?.equipe ?? '',
        periodo: '',
        foco: '',
    });

    const escolhidas = denuncias.filter((d) => alvos.includes(d.id));

    /** O resumo do lote: quantas vão para cada área. */
    const resumoPorArea = useMemo(() => {
        const contagem: Record<string, number> = {};

        for (const d of escolhidas) {
            const area = areaDe(d) || 'sem área definida';
            contagem[area] = (contagem[area] ?? 0) + 1;
        }

        return Object.entries(contagem);
        // `escolhidas` e `areaPorId` são o que muda o resumo; `areaDe` é derivada
        // dos dois e recriada a cada render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [alvos, denuncias, areaPorId]);

    const semArea = escolhidas.filter((d) => areaDe(d) === '');

    /** A equipe própria da área de cada denúncia escolhida — para avisar a troca. */
    const equipeDaArea = useMemo(() => {
        const mapa: Record<string, string> = {};

        for (const e of equipes) {
            mapa[e.area] = e.equipe;
        }

        return mapa;
    }, [equipes]);

    const trocandoDeEquipe =
        envio.equipe !== '' &&
        escolhidas.some(
            (d) =>
                d.area !== null &&
                equipeDaArea[d.area] !== undefined &&
                equipeDaArea[d.area] !== envio.equipe,
        );

    function encaminhar() {
        enviar('encaminhar', rotaEncaminhar().url, {
            destinos: escolhidas.map((d) => ({ id: d.id, area: areaDe(d) })),
            observacao: observacao.trim() === '' ? null : observacao.trim(),
        }, {
            onSuccess: () => {
                setObservacao('');
                setAreaPorId({});
            },
        });
    }

    function devolver() {
        enviar('devolver', rotaDevolver().url, { ids: alvos, ...retorno }, {
            onSuccess: () =>
                setRetorno({
                    motivo: motivos[0] ?? '',
                    justificativa: '',
                    destino: destinos[0] ?? '',
                }),
        });
    }

    function direcionar() {
        enviar('direcionar', rotaDirecionar().url, {
            ids: alvos,
            equipe: envio.equipe,
            justificativa: envio.justificativa.trim() === '' ? null : envio.justificativa.trim(),
        }, {
            onSuccess: () => setEnvio({ equipe: '', justificativa: '' }),
        });
    }

    function anexarOperacao() {
        enviar('operacao', rotaOperacao().url, {
            ids: alvos,
            nova: operacaoForm.nova,
            operacao: operacaoForm.operacao,
            nome: operacaoForm.nome,
            area: operacaoForm.area,
            equipe: operacaoForm.equipe,
            periodo: operacaoForm.periodo.trim() === '' ? null : operacaoForm.periodo.trim(),
            foco: operacaoForm.foco.trim() === '' ? null : operacaoForm.foco.trim(),
        });
    }

    // Só as chaves declaradas entram no arquivo, e a data sai em BR: o documento
    // é lido fora do sistema, onde ninguém traduz ISO.
    const linhasExportacao = ord.itens.map((d) => ({
        protocolo: d.protocolo,
        protocolo_origem: d.protocolo_origem,
        recebida: dataHoraBR(d.recebida_em_hora),
        requerente: quemDenunciou(d),
        assunto: d.assunto,
        bairro: d.bairro,
        area: d.area ?? d.area_sugerida?.area ?? VAZIO,
        destino: destinoAtual(d),
        situacao: d.situacao,
        // O desfecho é o que o documento exportado precisa dizer: "Concluída"
        // sozinha não conta se houve orientação, notificação ou apreensão.
        desfecho: d.desfecho ?? VAZIO,
        prazo: dataBR(d.prazo),
    }));

    const rotuloDaAba: Record<string, string> = {
        triagem: 'A triar',
        direcionamento: 'A direcionar',
        todas: 'Todas',
        detalhe: 'Detalhe',
    };

    return (
        <>
            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Denúncias</p>
                    <h1>{canal.nome}</h1>
                    <p>
                        {/* O artigo vem do CANAL, não escrito aqui: "o portal
                            e-Salvador" e "a central Fala Salvador" não aceitam o
                            mesmo artigo, e um fixo erraria em um dos dois. */}
                        Denúncias que {canal.artigo}{' '}
                        <strong>{canal.sistema}</strong> entrega ao SEFAL por
                        integração. O administrativo{' '}
                        <strong>tria e encaminha à área</strong> do bairro; o{' '}
                        <strong>gestor da área direciona</strong> à equipe ou
                        inclui numa operação.
                    </p>

                    {/* Qual é a SUA etapa — o selo que o dono usa para mostrar
                        que a mesma tela serve dois papéis. */}
                    <ul className="rt-chips">
                        {tria && (
                            <li className="rt-chip" style={{ color: 'var(--sm-aviso)' }}>
                                <span className="rt-chip-dot" />
                                Sua etapa: triagem — você encaminha à área
                            </li>
                        )}
                        {direciona && (
                            <li className="rt-chip" style={{ color: 'var(--sm-primaria)' }}>
                                <span className="rt-chip-dot" />
                                {/* A ÁREA vai no selo: "sua etapa é direcionamento"
                                    sem dizer de onde deixaria o gestor sem saber
                                    por que a lista dele é curta. */}
                                Sua etapa: direcionamento
                                {areasDoGestor.length > 0
                                    ? ` · ${areasDoGestor.map(nomeDaArea).join(' e ')}`
                                    : ''}{' '}
                                — você escolhe equipe ou operação
                            </li>
                        )}

                        {/* Gestor sem área vinculada: ele exerce a etapa e não tem
                            de onde. Dito na cara, e não em lista vazia sem
                            explicação — a lista vazia parece sistema quebrado. */}
                        {direciona && areasDoGestor.length === 0 && (
                            <li className="rt-chip" style={{ color: 'var(--sm-perigo)' }}>
                                <span className="rt-chip-dot" />
                                Sua conta não está vinculada a nenhuma área — procure quem
                                administra o sistema
                            </li>
                        )}
                        {!tria && !direciona && (
                            <li className="rt-chip">
                                <span className="rt-chip-dot" />
                                Você acompanha o fluxo; as decisões são do administrativo e do gestor
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
                                ? 'Ver todas as denúncias da sua área neste canal'
                                : 'Ver todas as denúncias deste canal'
                        }
                        onClick={() => {
                            setBusca('');
                            trocarAba('todas');
                        }}
                    >
                        <strong>{numeros.total}</strong>
                        <span>{recorteDeArea ? 'na sua área' : 'recebidas'}</span>
                    </button>

                    {/* O número "a triar" só existe para quem TRIA. Para o gestor
                        ele apareceria em zero — a denúncia recebida ainda não tem
                        área, então ela não está na lista dele —, e zero ali leria
                        como "não há nada a triar", que é falso. */}
                    {tria && (
                        <>
                            <div className="rt-numeros-separador" />
                            <button
                                type="button"
                                className="rt-numero alerta"
                                title="Ver as que aguardam triagem"
                                onClick={() => irParaEtapa('triagem', tria, 'recebida')}
                            >
                                <strong>{numeros.triar}</strong>
                                <span>a triar</span>
                            </button>
                        </>
                    )}
                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero info"
                        title="Ver as que aguardam o gestor da área"
                        onClick={() => irParaEtapa('direcionamento', direciona, 'encaminhada')}
                    >
                        <strong>{numeros.direcionar}</strong>
                        <span>a direcionar</span>
                    </button>
                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero ok"
                        title="Ver as que já viraram trabalho de campo"
                        onClick={() => {
                            trocarAba('todas');
                            setBusca('em trabalho');
                        }}
                    >
                        <strong>{numeros.emCampo}</strong>
                        <span>em trabalho</span>
                    </button>
                </div>
            </div>

            <SeloPrototipo>
                Esta tela é a proposta do módulo, para conferência da forma antes
                de virar sistema. As denúncias são de exemplo, a integração{' '}
                <strong>não existe ainda</strong> e{' '}
                <strong>nada é gravado</strong>: o que você encaminhar, direcionar
                ou devolver vale só nesta sessão do navegador.
            </SeloPrototipo>

            {/* O aviso que separa este módulo da Caixa de Entrada. Ele fica em
                cima, e não numa coluna da grade, porque é a natureza da tela
                inteira: aqui ninguém digita nada. */}
            <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                <Antenna size={16} aria-hidden />
                <div>
                    <strong>Recebido de fora, por integração — nada é digitado aqui.</strong>
                    <div>
                        {canal.como_chega} Cada denúncia carrega o número que o
                        canal lhe deu ({canal.prefixo_origem}-…) e a hora em que a
                        integração a entregou. Por isso esta tela não tem botão de
                        cadastrar: o que chega em papel ao balcão é assunto da{' '}
                        <strong>Caixa de Entrada</strong>.
                    </div>
                </div>
            </div>

            {/* A lista do gestor NÃO é o universo, e a tela diz isso. Sem o aviso,
                ele contaria as denúncias, acharia o número baixo e concluiria que o
                canal está parado. */}
            {recorteDeArea && (
                <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                    <Info size={16} aria-hidden />
                    <div>
                        <strong>
                            Você está vendo só o que foi encaminhado a{' '}
                            {areasDoGestor.map(nomeDaArea).join(' e ')}.
                        </strong>
                        <div>
                            As denúncias das outras áreas e as que ainda esperam a
                            triagem do administrativo não aparecem aqui — e a ação
                            sobre denúncia de outra área é recusada pelo sistema, não
                            só escondida.
                        </div>
                    </div>
                </div>
            )}

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label={`Denúncias do ${canal.nome}`}>
                    {tria && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'triagem'}
                            onClick={() => trocarAba('triagem')}
                        >
                            <Inbox size={16} aria-hidden />
                            <span className="aba-rotulo">
                                A triar ({daEtapa.triagem.length})
                            </span>
                        </button>
                    )}

                    {direciona && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'direcionamento'}
                            onClick={() => trocarAba('direcionamento')}
                        >
                            <ArrowRightCircle size={16} aria-hidden />
                            <span className="aba-rotulo">
                                A direcionar ({daEtapa.direcionamento.length})
                            </span>
                        </button>
                    )}

                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'todas'}
                        onClick={() => trocarAba('todas')}
                    >
                        <ListChecks size={16} aria-hidden />
                        <span className="aba-rotulo">
                            Todas ({denuncias.length})
                        </span>
                    </button>

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

                {aba !== 'detalhe' && (
                    <>
                        <BuscaInteligente
                            busca={busca}
                            setBusca={setBusca}
                            placeholder={`Protocolo, requerente, assunto, bairro, área, equipe ou situação — ex.: "denúncias anônimas sem endereço na Área 6"`}
                            exemplos={[
                                'anônimas',
                                'sem endereço',
                                'prazo vencido',
                                'recebidas hoje',
                                'Área 5',
                                // O desfecho como exemplo clicável: é a pergunta
                                // que mede se a fiscalização está sendo educativa,
                                // e sem o exemplo ninguém descobre que a barra
                                // entende isso.
                                'regularizado no local',
                                canal.tem_anexo ? 'com anexo' : 'ocupação',
                            ]}
                        />

                        {numeros.vencidas > 0 && (
                            <p className="form-erro" style={{ marginBottom: 12 }}>
                                <TriangleAlert size={15} aria-hidden />{' '}
                                {contar(numeros.vencidas, 'denúncia', 'denúncias')} com o
                                prazo já vencido esperando decisão.
                            </p>
                        )}

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                flexWrap: 'wrap',
                                marginBottom: 10,
                            }}
                        >
                            {emLote && aba === 'triagem' && (
                                <>
                                    <BotaoAcao
                                        icone={<Send size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        disabled={selecionadas.length === 0}
                                        onClick={() => abrirDecisao('encaminhar', selecionadas)}
                                    >
                                        Encaminhar selecionadas
                                        {selecionadas.length > 0 ? ` (${selecionadas.length})` : ''}
                                    </BotaoAcao>

                                    <BotaoAcao
                                        className="btn btn-secondary btn-sm"
                                        icone={<CornerUpLeft size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        disabled={selecionadas.length === 0}
                                        onClick={() => abrirDecisao('devolver', selecionadas)}
                                    >
                                        Devolver ou arquivar
                                    </BotaoAcao>
                                </>
                            )}

                            {emLote && aba === 'direcionamento' && (
                                <>
                                    <BotaoAcao
                                        icone={<Send size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        disabled={selecionadas.length === 0}
                                        onClick={() => abrirDecisao('direcionar', selecionadas)}
                                    >
                                        Direcionar à equipe
                                        {selecionadas.length > 0 ? ` (${selecionadas.length})` : ''}
                                    </BotaoAcao>

                                    <BotaoAcao
                                        className="btn btn-secondary btn-sm"
                                        icone={<Siren size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        disabled={selecionadas.length === 0}
                                        onClick={() => abrirDecisao('operacao', selecionadas)}
                                    >
                                        Incluir em operação
                                    </BotaoAcao>
                                </>
                            )}

                            {/* Só aparece depois de a sessão decidir algo: num
                                protótipo intocado não há o que reiniciar. */}
                            {alterada && (
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
                                    titulo={`Denúncias — ${canal.nome}`}
                                    subtitulo={`Denúncias › ${canal.nome}`}
                                    contexto={
                                        `Aba: ${rotuloDaAba[aba]}`
                                        + (busca.trim() ? ` · busca: "${busca.trim()}"` : '')
                                    }
                                    colunas={[
                                        { chave: 'protocolo', titulo: 'Protocolo' },
                                        { chave: 'protocolo_origem', titulo: 'Nº na origem' },
                                        { chave: 'recebida', titulo: 'Recebida', alinhar: 'center' },
                                        { chave: 'requerente', titulo: 'Requerente' },
                                        { chave: 'assunto', titulo: 'Assunto' },
                                        { chave: 'bairro', titulo: 'Bairro' },
                                        { chave: 'area', titulo: 'Área' },
                                        { chave: 'destino', titulo: 'Destino' },
                                        { chave: 'situacao', titulo: 'Situação' },
                                        { chave: 'desfecho', titulo: 'Desfecho' },
                                        { chave: 'prazo', titulo: 'Prazo', alinhar: 'center' },
                                    ]}
                                    linhas={linhasExportacao}
                                />
                            </div>
                        </div>

                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela — para
                                abrir a denúncia, o relato e o trâmite dela.
                                {emLote && aba === 'triagem'
                                    ? ' A área vem sugerida pelo bairro: confira e troque na própria linha antes de encaminhar.'
                                    : ''}
                            </p>
                        )}

                        <div className="table-wrap">
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        {emLote && (
                                            <th style={{ width: 38 }}>
                                                <input
                                                    type="checkbox"
                                                    checked={todasMarcadas}
                                                    onChange={alternarTodas}
                                                    aria-label="Selecionar todas as denúncias filtradas"
                                                    title="Selecionar todas as denúncias filtradas"
                                                />
                                            </th>
                                        )}
                                        <ThOrdenavel campo="protocolo" acessor="protocolo" ord={ord}>
                                            Protocolo
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="protocolo_origem"
                                            acessor="protocolo_origem"
                                            ord={ord}
                                        >
                                            Nº na origem
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="recebida_em_hora"
                                            acessor="recebida_em_hora"
                                            ord={ord}
                                        >
                                            Recebida
                                        </ThOrdenavel>
                                        <ThOrdenavel
                                            campo="requerente"
                                            acessor={(d: Denuncia) => quemDenunciou(d)}
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
                                            campo="area"
                                            acessor={(d: Denuncia) => areaDe(d)}
                                            ord={ord}
                                        >
                                            {aba === 'triagem' ? 'Área (sugerida)' : 'Área'}
                                        </ThOrdenavel>
                                        {aba !== 'triagem' && (
                                            <ThOrdenavel
                                                campo="destino"
                                                acessor={(d: Denuncia) => destinoAtual(d)}
                                                ord={ord}
                                            >
                                                Destino
                                            </ThOrdenavel>
                                        )}
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
                                            <td colSpan={12} className="tabela-vazia">
                                                {fonte.length === 0
                                                    ? aba === 'triagem'
                                                        ? 'Nada a triar: toda denúncia recebida deste canal já foi encaminhada ou retornada.'
                                                        : aba === 'direcionamento'
                                                          ? 'Nada a direcionar: nenhuma denúncia deste canal está esperando o gestor da área.'
                                                          : 'Nenhuma denúncia recebida deste canal.'
                                                    : 'Nenhuma denúncia casa com a busca. Limpe o campo para ver a lista inteira.'}
                                            </td>
                                        </tr>
                                    )}

                                    {pag.visiveis.map((d) => {
                                        const vencida =
                                            d.prazo < hoje &&
                                            AGUARDANDO_TRIAGEM.concat(AGUARDANDO_DIRECIONAMENTO).includes(
                                                d.situacao,
                                            );
                                        const sugerida = d.area_sugerida;

                                        return (
                                            <tr
                                                key={d.id}
                                                {...linhaClicavel(
                                                    () => abrirDetalhe(d),
                                                    'Abrir a denúncia e o trâmite dela',
                                                    vencida && 'pendente',
                                                )}
                                            >
                                                {emLote && (
                                                    <td
                                                        onClick={(e) => e.stopPropagation()}
                                                        onKeyDown={(e) => e.stopPropagation()}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={selecionadas.includes(d.id)}
                                                            onChange={() => alternar(d.id)}
                                                            aria-label={`Selecionar a denúncia ${d.protocolo}`}
                                                        />
                                                    </td>
                                                )}

                                                <td className="cell-id">{d.protocolo}</td>
                                                <td className="cell-id">{d.protocolo_origem}</td>
                                                <td className="cell-id">
                                                    {dataHoraBR(d.recebida_em_hora)}
                                                </td>
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
                                                <td>
                                                    {d.bairro}
                                                    {d.endereco_impreciso && (
                                                        <>
                                                            {' '}
                                                            <span
                                                                className="selo selo-aviso"
                                                                title="Endereço sem número nem referência confiável"
                                                            >
                                                                <MapPinOff size={12} aria-hidden />{' '}
                                                                sem endereço
                                                            </span>
                                                        </>
                                                    )}
                                                </td>

                                                {/* Na triagem a área é EDITÁVEL na própria
                                                    linha: é assim que o lote deixa de ser
                                                    "manda tudo para o mesmo lugar". */}
                                                <td
                                                    onClick={(e) => e.stopPropagation()}
                                                    onKeyDown={(e) => e.stopPropagation()}
                                                >
                                                    {emLote && aba === 'triagem' ? (
                                                        <>
                                                            <select
                                                                className="form-control"
                                                                style={{ minWidth: 150 }}
                                                                value={areaDe(d)}
                                                                aria-label={`Área da denúncia ${d.protocolo}`}
                                                                onChange={(e) =>
                                                                    setAreaPorId((atual) => ({
                                                                        ...atual,
                                                                        [d.id]: e.target.value,
                                                                    }))
                                                                }
                                                            >
                                                                <option value="">Escolha a área…</option>
                                                                {/* O nome do GESTOR vai na opção:
                                                                    encaminhar é entregar trabalho a
                                                                    alguém, e "Área 5" não diz a quem.
                                                                    Vai aqui, e não numa linha extra,
                                                                    para não dobrar a altura da grade. */}
                                                                {areas.map((a) => (
                                                                    <option key={a} value={a}>
                                                                        {gestorDa(a) === null
                                                                            ? a
                                                                            : `${a} — ${gestorDa(a)}`}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                            {sugerida !== null &&
                                                                sugerida.alternativas.length > 0 && (
                                                                    <span
                                                                        className="selo selo-info"
                                                                        title={`O bairro ${d.bairro} também é coberto por ${sugerida.alternativas
                                                                            .map((a) => a.area)
                                                                            .join(', ')}`}
                                                                    >
                                                                        bairro compartilhado
                                                                    </span>
                                                                )}
                                                        </>
                                                    ) : (
                                                        (d.area ?? sugerida?.area ?? VAZIO)
                                                    )}
                                                </td>

                                                {aba !== 'triagem' && (
                                                    <td>
                                                        {d.operacao !== null ? (
                                                            <span className="selo selo-info">
                                                                {d.operacao}
                                                            </span>
                                                        ) : d.equipe !== null ? (
                                                            <span className="selo selo-neutro">
                                                                Equipe {d.equipe}
                                                            </span>
                                                        ) : (
                                                            VAZIO
                                                        )}
                                                    </td>
                                                )}

                                                <td>
                                                    <span
                                                        className={cn(
                                                            'selo',
                                                            TOM_DA_SITUACAO[d.situacao] ?? 'selo-neutro',
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

                {aba === 'detalhe' && aberta !== null && (
                    <>
                        <div className="rt-detalhe-cabeca">
                            <div>
                                <p className="sobrancelha">
                                    {canal.nome} · {aberta.protocolo_origem}
                                </p>
                                <h2 className="card-titulo">{aberta.assunto}</h2>
                                <p className="card-sub">
                                    {aberta.protocolo} · recebida por integração em{' '}
                                    {dataHoraBR(aberta.recebida_em_hora)} · prazo{' '}
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
                                            <UserX size={14} aria-hidden /> Anônimo — o
                                            canal não identifica quem denunciou
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

                            {/* O e-Salvador identifica; o 156 pode não ter nada
                                disso. A ficha mostra o campo só onde o canal o
                                entrega — campo vazio em metade das denúncias faria
                                a tela parecer defeituosa. */}
                            {aberta.documento !== null && (
                                <div>
                                    <dt>CPF/CNPJ informado</dt>
                                    <dd>{aberta.documento}</dd>
                                </div>
                            )}
                            {aberta.email !== null && (
                                <div>
                                    <dt>E-mail</dt>
                                    <dd>{aberta.email}</dd>
                                </div>
                            )}
                            {aberta.telefone !== null && (
                                <div>
                                    <dt>Telefone</dt>
                                    <dd>{aberta.telefone}</dd>
                                </div>
                            )}
                            {aberta.categoria !== null && (
                                <div>
                                    <dt>Categoria do atendimento</dt>
                                    <dd>{aberta.categoria}</dd>
                                </div>
                            )}
                            {aberta.atendente !== null && (
                                <div>
                                    <dt>Quem atendeu a ligação</dt>
                                    <dd>{aberta.atendente}</dd>
                                </div>
                            )}

                            <div>
                                <dt>Bairro</dt>
                                <dd>{aberta.bairro}</dd>
                            </div>
                            <div>
                                <dt>Área</dt>
                                <dd>
                                    {aberta.area ??
                                        (aberta.area_sugerida === null
                                            ? 'sem área definida'
                                            : `${aberta.area_sugerida.area} (sugerida pelo bairro)`)}
                                    {/* Quem responde pela área — a informação que
                                        falta para "encaminhada" ter destinatário. */}
                                    {aberta.area !== null && gestorDa(aberta.area) !== null && (
                                        <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                            Gestor: {gestorDa(aberta.area)}
                                        </div>
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt>Destino</dt>
                                <dd>
                                    {aberta.operacao ??
                                        (aberta.equipe === null
                                            ? 'ainda sem equipe'
                                            : `Equipe ${aberta.equipe}`)}
                                </dd>
                            </div>

                            {/* COMO a vistoria terminou. Só aparece depois de a
                                denúncia ir a campo: em branco na metade das
                                linhas, o campo faria a ficha parecer defeituosa. */}
                            {aberta.desfecho !== null && (
                                <div>
                                    <dt>Desfecho da vistoria</dt>
                                    <dd>{aberta.desfecho}</dd>
                                </div>
                            )}

                            <div style={{ gridColumn: '1 / -1' }}>
                                <dt>Endereço da ocorrência</dt>
                                <dd>
                                    {enderecoDe(aberta) || VAZIO}
                                    {aberta.endereco_impreciso && (
                                        <div style={{ color: 'var(--sm-aviso)' }}>
                                            <MapPinOff size={13} aria-hidden /> O canal não
                                            entregou número nem referência confiável — decida
                                            se dá para mandar equipe ao local.
                                        </div>
                                    )}
                                </dd>
                            </div>

                            <div style={{ gridColumn: '1 / -1' }}>
                                <dt>
                                    {canal.endereco_estruturado
                                        ? 'Relato do cidadão'
                                        : 'Relato transcrito do atendimento'}
                                </dt>
                                <dd>{aberta.relato || VAZIO}</dd>
                            </div>

                            {canal.tem_anexo && (
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <dt>Anexos do cidadão</dt>
                                    <dd>
                                        {aberta.anexos.length === 0
                                            ? 'O cidadão não anexou nada.'
                                            : aberta.anexos.map((nome) => (
                                                  <span
                                                      key={nome}
                                                      className="selo selo-neutro"
                                                      style={{ marginRight: 6 }}
                                                  >
                                                      <Paperclip size={12} aria-hidden /> {nome}
                                                  </span>
                                              ))}
                                    </dd>
                                </div>
                            )}

                            {aberta.justificativa_equipe !== null && (
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <dt>Por que saiu da equipe da área</dt>
                                    <dd>{aberta.justificativa_equipe}</dd>
                                </div>
                            )}

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
                            Quem fez o quê, quando —{' '}
                            <strong>e o que cada passo produziu</strong>. Escolha um
                            passo na linha do tempo (o clique ou as setas do teclado)
                            para ver a decisão tomada, o que a equipe registrou em
                            campo e o documento lavrado, quando houve. A primeira
                            linha é assinada pela integração, não por pessoa: é o que
                            prova que a denúncia veio de fora.
                        </p>

                        <TramiteDeDenuncia
                            tramites={aberta.tramites}
                            proximoPasso={proximoPassoDe(aberta)}
                        />

                        {/* A decisão de UM registro usa os MESMOS caminhos do lote:
                            o que muda é o tamanho da lista de alvos. */}
                        {tria && AGUARDANDO_TRIAGEM.includes(aberta.situacao) && (
                            <>
                                <hr className="rt-regua" />
                                <h3 className="card-titulo">Triagem desta denúncia</h3>
                                <p className="card-sub">
                                    Encaminhe à área do bairro — a sugestão vem da
                                    estrutura de áreas e você confirma — ou retire do
                                    fluxo com o motivo por escrito.
                                </p>

                                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                                    <BotaoAcao
                                        icone={<Send size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        onClick={() => abrirDecisao('encaminhar', [aberta.id])}
                                    >
                                        Encaminhar à área
                                    </BotaoAcao>

                                    <BotaoAcao
                                        className="btn btn-secondary btn-sm"
                                        icone={<CornerUpLeft size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        onClick={() => abrirDecisao('devolver', [aberta.id])}
                                    >
                                        Devolver ou arquivar
                                    </BotaoAcao>
                                </div>
                            </>
                        )}

                        {direciona && AGUARDANDO_DIRECIONAMENTO.includes(aberta.situacao) && (
                            <>
                                <hr className="rt-regua" />
                                <h3 className="card-titulo">Direcionamento desta denúncia</h3>
                                <p className="card-sub">
                                    Duas saídas: mandar a uma equipe para vistoria
                                    avulsa, ou incluir numa operação já planejada para
                                    a região.
                                </p>

                                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                                    <BotaoAcao
                                        icone={<Send size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        onClick={() => abrirDecisao('direcionar', [aberta.id])}
                                    >
                                        Direcionar à equipe
                                    </BotaoAcao>

                                    <BotaoAcao
                                        className="btn btn-secondary btn-sm"
                                        icone={<Siren size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        onClick={() => abrirDecisao('operacao', [aberta.id])}
                                    >
                                        Incluir em operação
                                    </BotaoAcao>
                                </div>
                            </>
                        )}

                        <hr className="rt-regua" />

                        <button
                            type="button"
                            className="btn btn-secondary btn-sm"
                            onClick={() => trocarAba(tria ? 'triagem' : direciona ? 'direcionamento' : 'todas')}
                        >
                            <X size={15} aria-hidden /> Fechar a denúncia
                        </button>
                    </>
                )}
            </div>

            {/* ── As folhas de decisão ─────────────────────────────────────── */}

            {decisao === 'encaminhar' && (
                <FolhaDeDecisao
                    titulo={
                        alvos.length === 1
                            ? 'Encaminhar a denúncia à área?'
                            : `Encaminhar ${alvos.length} denúncias às áreas?`
                    }
                    icone={<Send size={19} aria-hidden />}
                    rotulo="Encaminhar"
                    iconeConfirmar={<Send size={16} aria-hidden />}
                    processando={enviando === 'encaminhar'}
                    impedimento={
                        semArea.length > 0
                            ? `${contar(semArea.length, 'denúncia', 'denúncias')} sem área escolhida. Volte à listagem e confirme a área de cada uma.`
                            : null
                    }
                    onCancelar={() => setDecisao(null)}
                    onConfirmar={encaminhar}
                >
                    <p className="sobreposicao-texto" style={{ marginBottom: 12 }}>
                        Cada denúncia vai para a área do bairro dela, e passa a
                        esperar o <strong>gestor daquela área</strong>, que escolhe
                        equipe ou operação. Confira o resumo:
                    </p>

                    <ul className="rt-chips" style={{ marginBottom: 14 }}>
                        {resumoPorArea.map(([area, quantas]) => (
                            <li key={area} className="rt-chip">
                                <span className="rt-chip-dot" />
                                {/* Área E gestor: é a última tela antes de o
                                    trabalho sair da mão de quem tria, e é aqui que
                                    ele confere para quem está entregando. */}
                                {area}
                                {gestorDa(area) === null ? '' : ` · ${gestorDa(area)}`}:{' '}
                                {contar(quantas, 'denúncia', 'denúncias')}
                            </li>
                        ))}
                    </ul>

                    {/* Área sem gestor registrado na estrutura: a denúncia é
                        encaminhada e fica sem quem a receba. Aviso, não bloqueio —
                        o cadastro do gestor é de fora desta tela. */}
                    {resumoPorArea.some(([area]) => gestorDa(area) === null) && (
                        <p className="form-erro" style={{ marginBottom: 12 }}>
                            <TriangleAlert size={15} aria-hidden /> Há área sem gestor
                            registrado na estrutura: a denúncia chega lá e ninguém é
                            avisado. Vale registrar o gestor em Estrutura › Áreas e
                            Equipes.
                        </p>
                    )}

                    <div className="form-group">
                        <label className="form-label" htmlFor="encaminhar-observacao">
                            Orientação ao gestor
                        </label>
                        <input
                            id="encaminhar-observacao"
                            type="text"
                            className="form-control"
                            value={observacao}
                            maxLength={500}
                            placeholder="Ex.: priorizar, o prazo do canal vence esta semana"
                            onChange={(e) => setObservacao(e.target.value)}
                        />
                        <p className="form-ajuda">
                            Opcional. Vale para todas as denúncias deste
                            encaminhamento e fica registrada no trâmite de cada uma.
                        </p>
                    </div>
                </FolhaDeDecisao>
            )}

            {decisao === 'devolver' && (
                <FolhaDeDecisao
                    titulo={
                        alvos.length === 1
                            ? 'Retirar a denúncia do fluxo?'
                            : `Retirar ${alvos.length} denúncias do fluxo?`
                    }
                    icone={<CornerUpLeft size={19} aria-hidden />}
                    rotulo={retorno.destino || 'Confirmar'}
                    iconeConfirmar={<CornerUpLeft size={16} aria-hidden />}
                    processando={enviando === 'devolver'}
                    impedimento={
                        retorno.justificativa.trim().length < 15
                            ? 'Escreva a justificativa: devolver ou arquivar é ato administrativo e precisa do motivo por escrito.'
                            : null
                    }
                    onCancelar={() => setDecisao(null)}
                    onConfirmar={devolver}
                >
                    <p className="sobreposicao-texto" style={{ marginBottom: 12 }}>
                        A denúncia <strong>não chega ao gestor</strong>: fica
                        registrada como recusada, com o motivo e a justificativa no
                        trâmite. É ato administrativo — quem, quando, por quê.
                    </p>

                    <div className="rt-form-linha">
                        <div className="form-group">
                            <label className="form-label" htmlFor="retorno-motivo">
                                Motivo
                            </label>
                            <select
                                id="retorno-motivo"
                                className="form-control"
                                value={retorno.motivo}
                                onChange={(e) =>
                                    setRetorno((r) => ({ ...r, motivo: e.target.value }))
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
                                value={retorno.destino}
                                onChange={(e) =>
                                    setRetorno((r) => ({ ...r, destino: e.target.value }))
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
                            placeholder="Por que a denúncia não segue, em palavras que quem ler depois entenda"
                            onChange={(e) =>
                                setRetorno((r) => ({ ...r, justificativa: e.target.value }))
                            }
                        />
                        <p className="form-ajuda">
                            O motivo de lista não conta o caso. A justificativa é o
                            que explica a decisão a quem abrir a denúncia meses
                            depois — e ao cidadão, se ele cobrar o canal.
                        </p>
                    </div>
                </FolhaDeDecisao>
            )}

            {decisao === 'direcionar' && (
                <FolhaDeDecisao
                    titulo={
                        alvos.length === 1
                            ? 'Direcionar a denúncia à equipe?'
                            : `Direcionar ${alvos.length} denúncias à equipe?`
                    }
                    icone={<Send size={19} aria-hidden />}
                    rotulo="Direcionar"
                    iconeConfirmar={<Send size={16} aria-hidden />}
                    processando={enviando === 'direcionar'}
                    impedimento={
                        envio.equipe === ''
                            ? 'Escolha a equipe que vai vistoriar.'
                            : trocandoDeEquipe && envio.justificativa.trim() === ''
                              ? 'A equipe escolhida não é a da área da denúncia. Escreva por que o trabalho sai da equipe responsável.'
                              : null
                    }
                    onCancelar={() => setDecisao(null)}
                    onConfirmar={direcionar}
                >
                    <p className="sobreposicao-texto" style={{ marginBottom: 12 }}>
                        A denúncia vira <strong>trabalho dirigido</strong> e aparece
                        no aplicativo dos fiscais da equipe escolhida.
                    </p>

                    <div className="form-group">
                        <label className="form-label" htmlFor="direcionar-equipe">
                            Equipe
                        </label>
                        <select
                            id="direcionar-equipe"
                            className="form-control"
                            value={envio.equipe}
                            onChange={(e) => setEnvio((v) => ({ ...v, equipe: e.target.value }))}
                        >
                            <option value="">Escolha a equipe…</option>
                            {equipes.map((e) => (
                                <option key={e.equipe} value={e.equipe}>
                                    Equipe {e.equipe} · {e.area} ({e.regiao}) — {e.encarregado}
                                </option>
                            ))}
                        </select>
                        <p className="form-ajuda">
                            A equipe da própria área é o caminho normal. Outra equipe
                            é decisão consciente — a Noturna, por exemplo, quando o
                            flagrante só é possível de madrugada.
                        </p>
                    </div>

                    {trocandoDeEquipe && (
                        <div className="rt-sugestao">
                            <Info size={16} aria-hidden />
                            <div>
                                <strong>
                                    Ao menos uma das denúncias sai da equipe da própria
                                    área.
                                </strong>
                                <div>
                                    Tirar trabalho da equipe responsável precisa estar
                                    escrito: a justificativa abaixo passa a ser
                                    obrigatória.
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="form-group">
                        <label className="form-label" htmlFor="direcionar-justificativa">
                            Justificativa {trocandoDeEquipe ? '' : '(opcional)'}
                        </label>
                        <textarea
                            id="direcionar-justificativa"
                            className="form-control"
                            rows={3}
                            value={envio.justificativa}
                            maxLength={1000}
                            placeholder="Ex.: o flagrante só é possível depois do fechamento, então vai para a Noturna"
                            onChange={(e) =>
                                setEnvio((v) => ({ ...v, justificativa: e.target.value }))
                            }
                        />
                    </div>
                </FolhaDeDecisao>
            )}

            {decisao === 'operacao' && (
                <FolhaDeDecisao
                    titulo={
                        alvos.length === 1
                            ? 'Incluir a denúncia numa operação?'
                            : `Incluir ${alvos.length} denúncias numa operação?`
                    }
                    icone={<Siren size={19} aria-hidden />}
                    rotulo={operacaoForm.nova ? 'Criar e incluir' : 'Incluir'}
                    iconeConfirmar={<Siren size={16} aria-hidden />}
                    processando={enviando === 'operacao'}
                    impedimento={
                        operacaoForm.nova
                            ? operacaoForm.nome.trim().length < 5
                                ? 'Dê um nome à operação — é por ele que a equipe vai reconhecê-la.'
                                : null
                            : operacaoForm.operacao === ''
                              ? 'Escolha a operação, ou crie uma nova.'
                              : null
                    }
                    onCancelar={() => setDecisao(null)}
                    onConfirmar={anexarOperacao}
                >
                    <p className="sobreposicao-texto" style={{ marginBottom: 12 }}>
                        A denúncia entra num trabalho <strong>já planejado</strong>,
                        em vez de gerar uma ida isolada ao local. A equipe passa a
                        ser a da operação.
                    </p>

                    <div className="rt-escolha">
                        <label
                            className={cn('rt-escolha-cartao', !operacaoForm.nova && 'ativo')}
                        >
                            <input
                                type="radio"
                                name="operacao-origem"
                                checked={!operacaoForm.nova}
                                onChange={() => setOperacaoForm((o) => ({ ...o, nova: false }))}
                            />
                            <span>
                                <strong>Operação já aberta</strong>
                                <span>
                                    {contar(operacoes.length, 'operação', 'operações')} em
                                    curso na estrutura de fiscalização.
                                </span>
                            </span>
                        </label>

                        <label
                            className={cn('rt-escolha-cartao', operacaoForm.nova && 'ativo')}
                        >
                            <input
                                type="radio"
                                name="operacao-origem"
                                checked={operacaoForm.nova}
                                onChange={() => setOperacaoForm((o) => ({ ...o, nova: true }))}
                            />
                            <span>
                                <strong>Abrir uma operação nova</strong>
                                <span>
                                    Quando não há trabalho planejado para a região
                                    ainda.
                                </span>
                            </span>
                        </label>
                    </div>

                    {operacaoForm.nova ? (
                        <>
                            <div className="form-group">
                                <label className="form-label" htmlFor="operacao-nome">
                                    Nome da operação
                                </label>
                                <input
                                    id="operacao-nome"
                                    type="text"
                                    className="form-control"
                                    value={operacaoForm.nome}
                                    maxLength={120}
                                    placeholder="Ex.: Operação Calçada Livre — Barris"
                                    onChange={(e) =>
                                        setOperacaoForm((o) => ({ ...o, nome: e.target.value }))
                                    }
                                />
                            </div>

                            <div className="rt-form-linha">
                                <div className="form-group">
                                    <label className="form-label" htmlFor="operacao-area">
                                        Área
                                    </label>
                                    <select
                                        id="operacao-area"
                                        className="form-control"
                                        value={operacaoForm.area}
                                        onChange={(e) =>
                                            setOperacaoForm((o) => ({ ...o, area: e.target.value }))
                                        }
                                    >
                                        {areas.map((a) => (
                                            <option key={a} value={a}>
                                                {a}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="operacao-equipe">
                                        Equipe que executa
                                    </label>
                                    <select
                                        id="operacao-equipe"
                                        className="form-control"
                                        value={operacaoForm.equipe}
                                        onChange={(e) =>
                                            setOperacaoForm((o) => ({
                                                ...o,
                                                equipe: e.target.value,
                                            }))
                                        }
                                    >
                                        {equipes.map((e) => (
                                            <option key={e.equipe} value={e.equipe}>
                                                Equipe {e.equipe} · {e.area}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="form-group">
                                    <label className="form-label" htmlFor="operacao-periodo">
                                        Período
                                    </label>
                                    <input
                                        id="operacao-periodo"
                                        type="text"
                                        className="form-control"
                                        value={operacaoForm.periodo}
                                        maxLength={80}
                                        placeholder="Ex.: próximas duas semanas"
                                        onChange={(e) =>
                                            setOperacaoForm((o) => ({
                                                ...o,
                                                periodo: e.target.value,
                                            }))
                                        }
                                    />
                                </div>
                            </div>

                            <div className="form-group">
                                <label className="form-label" htmlFor="operacao-foco">
                                    Foco
                                </label>
                                <input
                                    id="operacao-foco"
                                    type="text"
                                    className="form-control"
                                    value={operacaoForm.foco}
                                    maxLength={300}
                                    placeholder="O que a operação vai olhar, em uma linha"
                                    onChange={(e) =>
                                        setOperacaoForm((o) => ({ ...o, foco: e.target.value }))
                                    }
                                />
                                <p className="form-ajuda">
                                    No protótipo a operação nasce só aqui, para a cena
                                    fazer sentido. No sistema ela é o Cadastro de
                                    Operação, com data, alvo e equipe.
                                </p>
                            </div>
                        </>
                    ) : (
                        <div className="form-group">
                            <label className="form-label" htmlFor="operacao-existente">
                                Operação
                            </label>
                            <select
                                id="operacao-existente"
                                className="form-control"
                                value={operacaoForm.operacao}
                                onChange={(e) =>
                                    setOperacaoForm((o) => ({ ...o, operacao: e.target.value }))
                                }
                            >
                                <option value="">Escolha a operação…</option>
                                {operacoes.map((o) => (
                                    <option key={o.id} value={o.nome}>
                                        {o.nome} · {o.area} (Equipe {o.equipe}) — {o.periodo}
                                    </option>
                                ))}
                            </select>
                            {operacoes
                                .filter((o) => o.nome === operacaoForm.operacao)
                                .map((o) => (
                                    <p key={o.id} className="form-ajuda">
                                        {o.foco}
                                    </p>
                                ))}
                        </div>
                    )}
                </FolhaDeDecisao>
            )}
        </>
    );
}
