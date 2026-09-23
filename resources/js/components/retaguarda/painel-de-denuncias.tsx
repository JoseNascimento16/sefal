import {
    ArrowRightCircle,
    CornerUpLeft,
    FileText,
    Info,
    Inbox,
    ListChecks,
    MapPinOff,
    Paperclip,
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
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import { TramiteDeDenuncia } from '@/components/retaguarda/tramite-de-denuncia';
import { Sobreposicao } from '@/components/retaguarda/sobreposicao';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import {
    Paginacao,
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
    AGUARDANDO_ENCAMINHAMENTO,
    TOM_DA_SITUACAO,
} from '@/dados-prototipo/denuncias';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta, semAcento } from '@/lib/busca';
import { dataBR, dataHoraBR, hojeISO, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar } from '@/lib/plural';
import type { CatalogoDeRecomendacoes } from '@/lib/recomendacoes';
import { cn } from '@/lib/utils';
import {
    devolver as rotaDevolver,
    direcionar as rotaDirecionar,
    encaminhar as rotaEncaminhar,
    operacao as rotaOperacao,
} from '@/routes/retaguarda/denuncias';

/**
 * Denúncias das ouvidorias — PROTÓTIPO. O miolo das DUAS telas do módulo.
 *
 * As telas de canal (`e-Salvador` e `Salvador Digital`) são cascas de vinte linhas
 * que só declaram título e trilha: a mecânica é a mesma, e escrevê-la duas vezes
 * daria dois donos à mesma regra — um dia só uma das telas ganharia o campo
 * novo. O que varia entre os canais é declarado no servidor
 * (`config/prototipo_denuncias.php` → `canais`) e chega em `canal`.
 *
 * ── A tela é o fluxo, e o fluxo tem duas etapas com dois donos ───────────────
 *
 *   Encaminhamento (Chefe de Setor) → aba "A encaminhar": manda à EQUIPE sugerida
 *                                   pelo bairro (sugestão editável, porque bairro
 *                                   compartilhado tem duas respostas certas) — e,
 *                                   portanto, ao LÍDER dela — ou devolve/arquiva
 *                                   com justificativa.
 *   Direcionamento (líder de equipe) → aba "A direcionar": manda os FISCAIS da
 *                                   própria equipe ao ponto, ou anexa a uma
 *                                   OPERAÇÃO.
 *
 * Até 22/09/2026 a primeira etapa era do coordenador e escolhia uma ÁREA; os
 * coordenadores trabalham no e-Salvador e não entram aqui.
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
    /**
     * Chave da recomendação do fiscal → a frase EXPLÍCITA que a Retaguarda
     * mostra. O passo do trâmite traz a chave (é o que o aplicativo do fiscal
     * grava); o catálogo que a traduz vem do servidor.
     */
    recomendacoesDoFiscal: CatalogoDeRecomendacoes;
    motivos: string[];
    destinos: string[];
    equipes: EquipeResumo[];
    areas: string[];
    /** Quem lidera cada equipe — quem encaminha precisa ver para QUEM está mandando. */
    lideres: Record<string, { nome: string; matricula: string | null }>;
    operacoes: Operacao[];
    /** As etapas do fluxo que esta pessoa exerce — quem responde é o servidor. */
    etapas: Etapa[];
    /** As equipes que esta pessoa lidera (vazio para quem não lidera nenhuma). */
    equipesDoLider: string[];
    /** A listagem já veio recortada por essas equipes? Quem recorta é o servidor. */
    recorteDeEquipe: boolean;
    /**
     * As colunas de cada aba — da grade e do arquivo —, declaradas no servidor.
     * Ver `docs/padroes/listagem-clean.md`.
     */
    listagens: Listagens;
}

type Aba = 'encaminhamento' | 'direcionamento' | 'todas' | 'detalhe';

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
    'Direcionada aos fiscais',
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

    if (['Direcionada aos fiscais', 'Em operação'].includes(d.situacao)) {
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
            quem: d.equipe === null ? 'Líder da equipe' : `Líder da Equipe ${d.equipe}`,
            detalhe:
                'O prazo venceu com a situação mantida: cabe ao líder da equipe decidir a medida seguinte.',
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
    recomendacoesDoFiscal,
    motivos,
    destinos,
    equipes,
    areas,
    lideres,
    operacoes,
    etapas,
    equipesDoLider,
    recorteDeEquipe,
    listagens,
}: Props) {
    const { enviando, ocupado, enviar } = useEnvio();

    const encaminha = etapas.includes('encaminhamento');
    const direciona = etapas.includes('direcionamento');

    /** "Equipe C2 — Área 1", como o selo da etapa e os avisos a nomeiam. */
    const nomeDaEquipe = (codigo: string): string => {
        const equipe = equipes.find((e) => e.equipe === codigo);

        return equipe === undefined ? `Equipe ${codigo}` : `Equipe ${codigo} — ${equipe.area}`;
    };

    /** O líder de uma equipe, ou null quando a estrutura não registra nenhum. */
    const liderDa = (codigo: string): string | null => {
        const nome = lideres[codigo]?.nome ?? '';

        return nome.trim() === '' ? null : nome;
    };

    const [aba, setAba] = useState<Aba>(
        encaminha ? 'encaminhamento' : direciona ? 'direcionamento' : 'todas',
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
     * A equipe CONFIRMADA de cada denúncia no encaminhamento. Começa vazia: o
     * valor mostrado é a sugestão do bairro, e só entra aqui o que a pessoa
     * trocou — assim a sugestão continua acompanhando um ajuste na estrutura
     * em vez de ficar congelada no que a tela viu primeiro.
     */
    const [equipePorId, setEquipePorId] = useState<Record<number, string>>({});

    const equipeDe = (d: Denuncia): string =>
        equipePorId[d.id] ?? d.equipe ?? d.area_sugerida?.equipe ?? '';

    /** A área da denúncia como texto — gravada, ou a sugerida pelo bairro. */
    const areaDe = (d: Denuncia): string => d.area ?? d.area_sugerida?.area ?? '';

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
            valor: { tipo: 'situacao', valor: 'Encaminhada ao líder' },
        });
        lista.push({
            expressao: /\bdirecionad\w*\b/,
            valor: { tipo: 'situacao', valor: 'Direcionada aos fiscais' },
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
            encaminhamento: denuncias.filter((d) => AGUARDANDO_ENCAMINHAMENTO.includes(d.situacao)),
            direcionamento: denuncias.filter((d) =>
                AGUARDANDO_DIRECIONAMENTO.includes(d.situacao),
            ),
        }),
        [denuncias],
    );

    const fonte =
        aba === 'encaminhamento'
            ? daEtapa.encaminhamento
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

    // `campo` é a CHAVE da coluna do catálogo, e `acessor` o dado por onde se
    // ordena: com nomes diferentes, a seta de ordenação não acenderia na coluna
    // que está de fato ordenando a lista.
    const ord = useOrdenacao(filtradas, {
        campo: 'recebida',
        dir: 'desc',
        acessor: 'recebida_em_hora',
    });
    const pag = usePaginacao(ord.itens);

    // Os números saem da MESMA lista que a grade desenha — não de uma segunda
    // consulta —, então não há como discordarem do que está logo abaixo.
    const numeros = useMemo(
        () => ({
            total: denuncias.length,
            triar: daEtapa.encaminhamento.length,
            direcionar: daEtapa.direcionamento.length,
            emCampo: denuncias.filter((d) => EM_TRABALHO.includes(d.situacao)).length,
            retornadas: denuncias.filter((d) =>
                ['Devolvida', 'Arquivada'].includes(d.situacao),
            ).length,
            vencidas: denuncias.filter(
                (d) => d.prazo < hoje && AGUARDANDO_ENCAMINHAMENTO.concat(AGUARDANDO_DIRECIONAMENTO).includes(d.situacao),
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
     * isto o Chefe de Setor clicava em "a triar" e chegava numa lista sem aba
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
        (aba === 'encaminhamento' && encaminha) || (aba === 'direcionamento' && direciona);

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
    const [envio, setEnvio] = useState({ orientacao: '' });
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

    /** O resumo do lote: quantas vão para cada equipe. */
    const resumoPorEquipe = useMemo(() => {
        const contagem: Record<string, number> = {};

        for (const d of escolhidas) {
            const equipe = equipeDe(d) || 'sem equipe definida';
            contagem[equipe] = (contagem[equipe] ?? 0) + 1;
        }

        return Object.entries(contagem);
        // `escolhidas` e `equipePorId` são o que muda o resumo; `equipeDe` é
        // derivada dos dois e recriada a cada render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [alvos, denuncias, equipePorId]);

    const semEquipe = escolhidas.filter((d) => equipeDe(d) === '');

    function encaminhar() {
        enviar('encaminhar', rotaEncaminhar().url, {
            destinos: escolhidas.map((d) => ({ id: d.id, equipe: equipeDe(d) })),
            observacao: observacao.trim() === '' ? null : observacao.trim(),
        }, {
            onSuccess: () => {
                setObservacao('');
                setEquipePorId({});
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
            orientacao: envio.orientacao.trim() === '' ? null : envio.orientacao.trim(),
        }, {
            onSuccess: () => setEnvio({ orientacao: '' }),
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

    /*
     * ── A GRADE ENXUTA ───────────────────────────────────────────────────────
     *
     * As colunas — as da tela e as do arquivo — vêm do servidor, uma listagem
     * por aba. A régua está em `docs/padroes/listagem-clean.md`: uma linha por
     * denúncia, altura fixa, cinco colunas, e o ASSUNTO (texto livre, que era o
     * que esticava a linha) fora da grade.
     *
     * Cada aba mostra o dado com que a etapa dela se decide: o encaminhamento
     * olha o BAIRRO e confirma a EQUIPE; o direcionamento olha a área já
     * definida; e "Todas" olha a SITUAÇÃO. Requerente, assunto e destino ficam no clique — e
     * continuam no arquivo exportado.
     */
    const listagem =
        listagens[
            aba === 'encaminhamento'
                ? 'denuncias.encaminhamento'
                : aba === 'direcionamento'
                  ? 'denuncias.direcionamento'
                  : 'denuncias.todas'
        ];

    /** Como ORDENAR por cada coluna. Sem entrada, a coluna não ordena. */
    const acessores: Record<string, AcessorOrd<Denuncia> | undefined> = {
        protocolo: 'protocolo',
        recebida: 'recebida_em_hora',
        bairro: 'bairro',
        situacao: 'situacao',
        prazo: 'prazo',
        area: (d: Denuncia) => areaDe(d),
        // No encaminhamento a célula é um seletor: ordenar por ela ordenaria pela
        // sugestão, que é o que a pessoa está ali para trocar.
        equipe: aba === 'encaminhamento' ? undefined : (d: Denuncia) => equipeDe(d),
    };

    /** Cinza de apoio — o mesmo em toda célula que diz "isto não existe". */
    const fraco = { color: 'var(--sm-texto-fraco)' };

    /**
     * O que cada célula DESENHA, com o texto inteiro para a dica.
     *
     * `interativa` marca a célula que tem controle dentro (o seletor de área):
     * ali o clique não pode subir para a linha, senão escolher a área abriria a
     * denúncia.
     */
    function celula(
        d: Denuncia,
        chave: string,
        vencida: boolean,
    ): { conteudo: ReactNode; dica?: string; interativa?: boolean } {
        if (chave === 'protocolo') {
            return {
                conteudo: d.protocolo,
                dica: `${d.protocolo} · ${canal.nome} ${d.protocolo_origem}`,
            };
        }

        if (chave === 'recebida') {
            return {
                conteudo: dataBR(d.recebida_em_hora),
                dica: `Entregue pela integração em ${dataHoraBR(d.recebida_em_hora)}`,
            };
        }

        if (chave === 'bairro') {
            // O endereço impreciso entra como ÍCONE, não como um segundo chip:
            // a linha já tem um selo (a situação, ou o prazo vencido), e dois
            // chips na mesma linha voltam a empilhar conteúdo na célula.
            return {
                conteudo: (
                    <>
                        {d.endereco_impreciso && (
                            <MapPinOff
                                size={13}
                                aria-hidden
                                style={{ color: 'var(--sm-aviso)', marginRight: 5 }}
                            />
                        )}
                        {d.bairro}
                    </>
                ),
                dica: d.endereco_impreciso
                    ? `${d.bairro} — o canal não entregou número nem referência confiável`
                    : d.bairro,
            };
        }

        if (chave === 'situacao') {
            return {
                conteudo: (
                    <span
                        className={cn(
                            'selo',
                            TOM_DA_SITUACAO[d.situacao] ?? 'selo-neutro',
                        )}
                    >
                        {d.situacao}
                    </span>
                ),
                dica: `${d.situacao} · ${destinoAtual(d)}`,
            };
        }

        if (chave === 'prazo') {
            // Vencido é COR no texto, e não um selo: o selo desta linha é o da
            // situação. A marca laranja na ponta da linha já grita a pendência.
            return {
                conteudo: (
                    <span style={vencida ? { color: 'var(--sm-perigo)', fontWeight: 650 } : undefined}>
                        {dataBR(d.prazo)}
                    </span>
                ),
                dica: vencida
                    ? `Prazo do canal vencido em ${dataBR(d.prazo)}`
                    : `Prazo do canal: ${dataBR(d.prazo)}`,
            };
        }

        // A ÁREA: texto, gravada ou sugerida pelo bairro.
        if (chave === 'area') {
            const area = areaDe(d);

            return {
                conteudo: area === '' ? <span style={fraco}>{VAZIO}</span> : area,
                dica: area === '' ? 'Sem área definida' : area,
            };
        }

        // A EQUIPE. No encaminhamento ela é editável na própria linha — é assim
        // que o lote deixa de ser "manda tudo para o mesmo lugar".
        if (!(emLote && aba === 'encaminhamento')) {
            const equipe = equipeDe(d);

            return {
                conteudo: equipe === '' ? <span style={fraco}>{VAZIO}</span> : `Equipe ${equipe}`,
                dica:
                    equipe === ''
                        ? 'Sem equipe definida'
                        : `Equipe ${equipe}${liderDa(equipe) === null ? '' : ` · ${liderDa(equipe)}`}`,
            };
        }

        const sugerida = d.area_sugerida;

        return {
            interativa: true,
            conteudo: (
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                    <select
                        className="form-control"
                        style={{ minWidth: 170 }}
                        value={equipeDe(d)}
                        aria-label={`Equipe da denúncia ${d.protocolo}`}
                        onChange={(e) =>
                            setEquipePorId((atual) => ({
                                ...atual,
                                [d.id]: e.target.value,
                            }))
                        }
                    >
                        <option value="">Escolha a equipe…</option>
                        {/* O nome do LÍDER vai na opção: encaminhar é entregar
                            trabalho a alguém, e "C2" não diz a quem. Vai aqui, e
                            não numa linha extra, para não dobrar a altura da
                            grade. */}
                        {equipes.map((e) => (
                            <option key={e.equipe} value={e.equipe}>
                                {`${e.equipe} · ${e.area}`}
                                {liderDa(e.equipe) === null ? '' : ` — ${liderDa(e.equipe)}`}
                            </option>
                        ))}
                    </select>

                    {/* Bairro que pertence a duas áreas: o aviso vira ÍCONE com
                        dica, e não o chip que antes ia embaixo do seletor —
                        empilhado, ele dobrava a altura justamente da aba em que
                        se varrem trinta linhas. */}
                    {sugerida != null && sugerida.alternativas.length > 0 && (
                        <Info
                            size={14}
                            aria-hidden
                            style={{ color: 'var(--sm-info)', flexShrink: 0 }}
                        />
                    )}
                </span>
            ),
            dica:
                sugerida != null && sugerida.alternativas.length > 0
                    ? `O bairro ${d.bairro} também é coberto pela ${sugerida.alternativas
                          .map((a) => `Equipe ${a.equipe} (${a.area})`)
                          .join(', ')}`
                    : undefined,
        };
    }

    /** Quantas colunas a grade tem — para o `colSpan` da linha vazia. */
    const colunas = (emLote ? 1 : 0) + listagem.grade.length;

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
        encaminhamento: 'A encaminhar',
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
                            e-Salvador" e "a central Salvador Digital" não aceitam o
                            mesmo artigo, e um fixo erraria em um dos dois. */}
                        Denúncias que {canal.artigo}{' '}
                        <strong>{canal.sistema}</strong> entrega ao SEFAL por
                        integração. O Chefe de Setor{' '}
                        <strong>encaminha à equipe</strong> sugerida pelo bairro; o{' '}
                        <strong>líder da equipe direciona</strong> aos fiscais ou
                        inclui numa operação.
                    </p>

                    {/* Qual é a SUA etapa — o selo que o dono usa para mostrar
                        que a mesma tela serve dois papéis. */}
                    <ul className="rt-chips">
                        {encaminha && (
                            <li className="rt-chip" style={{ color: 'var(--sm-aviso)' }}>
                                <span className="rt-chip-dot" />
                                Sua etapa: encaminhamento — você escolhe a equipe
                            </li>
                        )}
                        {direciona && (
                            <li className="rt-chip" style={{ color: 'var(--sm-primaria)' }}>
                                <span className="rt-chip-dot" />
                                {/* A EQUIPE vai no selo: "sua etapa é direcionamento"
                                    sem dizer de qual deixaria o líder sem saber
                                    por que a lista dele é curta. */}
                                Sua etapa: direcionamento
                                {equipesDoLider.length > 0
                                    ? ` · ${equipesDoLider.map(nomeDaEquipe).join(' e ')}`
                                    : ''}{' '}
                                — você manda os fiscais ao ponto ou inclui em operação
                            </li>
                        )}

                        {/* Líder sem equipe vinculada: ele exerce a etapa e não tem
                            de onde. Dito na cara, e não em lista vazia sem
                            explicação — a lista vazia parece sistema quebrado. */}
                        {direciona && recorteDeEquipe && equipesDoLider.length === 0 && (
                            <li className="rt-chip" style={{ color: 'var(--sm-perigo)' }}>
                                <span className="rt-chip-dot" />
                                Sua conta não está vinculada a nenhuma equipe — procure quem
                                administra o sistema
                            </li>
                        )}
                        {!encaminha && !direciona && (
                            <li className="rt-chip">
                                <span className="rt-chip-dot" />
                                Você acompanha o fluxo; as decisões são do Chefe de Setor e dos líderes de equipe
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
                            recorteDeEquipe
                                ? 'Ver todas as denúncias da sua equipe neste canal'
                                : 'Ver todas as denúncias deste canal'
                        }
                        onClick={() => {
                            setBusca('');
                            trocarAba('todas');
                        }}
                    >
                        <strong>{numeros.total}</strong>
                        <span>{recorteDeEquipe ? 'na sua equipe' : 'recebidas'}</span>
                    </button>

                    {/* O número "a encaminhar" só existe para quem ENCAMINHA. Para o
                        líder ele apareceria em zero — a denúncia recebida ainda não
                        tem equipe, então ela não está na lista dele —, e zero ali
                        leria como "não há nada a encaminhar", que é falso. */}
                    {encaminha && (
                        <>
                            <div className="rt-numeros-separador" />
                            <button
                                type="button"
                                className="rt-numero alerta"
                                title="Ver as que aguardam o encaminhamento"
                                onClick={() => irParaEtapa('encaminhamento', encaminha, 'recebida')}
                            >
                                <strong>{numeros.triar}</strong>
                                <span>a encaminhar</span>
                            </button>
                        </>
                    )}
                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero info"
                        title="Ver as que aguardam o líder da equipe"
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

            {/*
              * O aviso mudou de assunto quando o módulo saiu do protótipo: o que
              * era falso era dizer que nada é gravado — agora tudo é, em banco, e
              * com trâmite. O que continua de mentira são os DADOS, e é disso que
              * quem avalia precisa ser avisado antes de tirar conclusão deles.
              */}
            <SeloPrototipo>
                Ambiente de demonstração: as denúncias são <strong>exemplos</strong>,
                não casos reais, e a integração com o canal{' '}
                <strong>não existe ainda</strong> — nada entra sozinho. O que você
                encaminhar, direcionar ou devolver{' '}
                <strong>é gravado de verdade</strong> e fica no trâmite da denúncia.
            </SeloPrototipo>

            {/* De ONDE a denúncia veio (integração, papel, balcão) não interessa a
                quem trabalha aqui: para o líder tudo chega igual, pelo
                ENCAMINHAMENTO do Chefe de Setor. Quem encaminha vê a origem na
                própria ficha da denúncia. */}

            {/* A lista do líder NÃO é o universo, e a tela diz isso. Sem o aviso,
                ele contaria as denúncias, acharia o número baixo e concluiria que o
                canal está parado. */}
            {recorteDeEquipe && (
                <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                    <Info size={16} aria-hidden />
                    <div>
                        <strong>
                            Você está vendo só o que foi encaminhado à{' '}
                            {equipesDoLider.map(nomeDaEquipe).join(' e à ')}.
                        </strong>
                        <div>
                            As denúncias das outras equipes e as que ainda esperam o
                            encaminhamento do Chefe de Setor não aparecem aqui — e a
                            ação sobre denúncia de outra equipe é recusada pelo
                            sistema, não só escondida.
                        </div>
                    </div>
                </div>
            )}

            <div className="card-premium">
                <div className="abas" role="tablist" aria-label={`Denúncias do ${canal.nome}`}>
                    {encaminha && (
                        <button
                            type="button"
                            role="tab"
                            className="aba"
                            aria-selected={aba === 'encaminhamento'}
                            onClick={() => trocarAba('encaminhamento')}
                        >
                            <Inbox size={16} aria-hidden />
                            <span className="aba-rotulo">
                                A encaminhar ({daEtapa.encaminhamento.length})
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
                        {/*
                          * A PRÉ-TRIAGEM não mora aqui — ela é a etapa ANTERIOR
                          * a esta tela, e vive na aba própria da Caixa de
                          * Entrada. Quando a denúncia chega nesta lista, a
                          * pergunta "isto é o mesmo fato que aquilo?" já foi
                          * respondida; a pergunta daqui é outra: o que se faz
                          * com este fato.
                          *
                          * Tê-la nos dois lugares criaria duas mesas para a
                          * mesma decisão — e um dia elas discordariam sobre o
                          * que já foi consolidado.
                          */}
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
                            {emLote && aba === 'encaminhamento' && (
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
                                        Direcionar aos fiscais
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


                            <div style={{ marginLeft: 'auto' }}>
                                <BotaoExportar
                                    titulo={`Denúncias — ${canal.nome}`}
                                    subtitulo={`Denúncias › ${canal.nome}`}
                                    contexto={
                                        `Aba: ${rotuloDaAba[aba]}`
                                        + (busca.trim() ? ` · busca: "${busca.trim()}"` : '')
                                    }
                                    colunas={listagem.exportacao}
                                    linhas={linhasExportacao}
                                />
                            </div>
                        </div>

                        {pag.visiveis.length > 0 && (
                            <p className="form-ajuda" style={{ marginBottom: 8 }}>
                                Clique numa linha — ou tecle Enter sobre ela — para
                                abrir a denúncia, o requerente, o assunto, o relato e
                                o trâmite dela.
                                {emLote && aba === 'encaminhamento'
                                    ? ' A equipe vem sugerida pelo bairro: confira e troque na própria linha antes de encaminhar.'
                                    : ''}
                            </p>
                        )}

                        <div className="table-wrap">
                            <table className="data-table enxuta">
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
                                        {/* Cabeçalho e células saem da MESMA lista de
                                            colunas: escritos em dois lugares, uma
                                            coluna nova entra só num deles e a grade
                                            passa a mostrar o valor embaixo do título
                                            errado. */}
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
                                            <td colSpan={colunas} className="tabela-vazia">
                                                {fonte.length === 0
                                                    ? aba === 'encaminhamento'
                                                        ? 'Nada a encaminhar: toda denúncia recebida deste canal já foi encaminhada ou retornada.'
                                                        : aba === 'direcionamento'
                                                          ? 'Nada a direcionar: nenhuma denúncia deste canal está esperando o líder da equipe.'
                                                          : 'Nenhuma denúncia recebida deste canal.'
                                                    : 'Nenhuma denúncia casa com a busca. Limpe o campo para ver a lista inteira.'}
                                            </td>
                                        </tr>
                                    )}

                                    {pag.visiveis.map((d) => {
                                        const vencida =
                                            d.prazo < hoje &&
                                            AGUARDANDO_ENCAMINHAMENTO.concat(AGUARDANDO_DIRECIONAMENTO).includes(
                                                d.situacao,
                                            );

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

                                                {listagem.grade.map((coluna) => {
                                                    const { conteudo, dica, interativa } =
                                                        celula(d, coluna.chave, vencida);

                                                    return (
                                                        <Celula
                                                            key={coluna.chave}
                                                            coluna={coluna}
                                                            dica={dica}
                                                            onClick={
                                                                interativa
                                                                    ? (e) => e.stopPropagation()
                                                                    : undefined
                                                            }
                                                            onKeyDown={
                                                                interativa
                                                                    ? (e) => e.stopPropagation()
                                                                    : undefined
                                                            }
                                                        >
                                                            {conteudo}
                                                        </Celula>
                                                    );
                                                })}
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
                                        (aberta.area_sugerida == null
                                            ? 'sem área definida'
                                            : `${aberta.area_sugerida.area} (sugerida pelo bairro)`)}
                                    {/* Quem lidera a equipe — a informação que
                                        falta para "encaminhada" ter destinatário. */}
                                    {aberta.equipe !== null && liderDa(aberta.equipe) !== null && (
                                        <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                            Líder da Equipe {aberta.equipe}: {liderDa(aberta.equipe)}
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
                            recomendacoesDoFiscal={recomendacoesDoFiscal}
                        />

                        {/* A decisão de UM registro usa os MESMOS caminhos do lote:
                            o que muda é o tamanho da lista de alvos. */}
                        {encaminha && AGUARDANDO_ENCAMINHAMENTO.includes(aberta.situacao) && (
                            <>
                                <hr className="rt-regua" />
                                <h3 className="card-titulo">Encaminhamento desta denúncia</h3>
                                <p className="card-sub">
                                    Encaminhe à equipe do bairro — a sugestão vem da
                                    estrutura de áreas e você confirma; quem recebe é o
                                    líder dela — ou retire do fluxo com o motivo por
                                    escrito.
                                </p>

                                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                                    <BotaoAcao
                                        icone={<Send size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        onClick={() => abrirDecisao('encaminhar', [aberta.id])}
                                    >
                                        Encaminhar à equipe
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
                                    Duas saídas: mandar os fiscais da sua equipe ao
                                    ponto, ou incluir numa operação já planejada para
                                    a região.
                                </p>

                                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                                    <BotaoAcao
                                        icone={<Send size={16} aria-hidden />}
                                        ocupado={ocupado}
                                        onClick={() => abrirDecisao('direcionar', [aberta.id])}
                                    >
                                        Direcionar aos fiscais
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
                            onClick={() => trocarAba(encaminha ? 'encaminhamento' : direciona ? 'direcionamento' : 'todas')}
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
                            ? 'Encaminhar a denúncia à equipe?'
                            : `Encaminhar ${alvos.length} denúncias às equipes?`
                    }
                    icone={<Send size={19} aria-hidden />}
                    rotulo="Encaminhar"
                    iconeConfirmar={<Send size={16} aria-hidden />}
                    processando={enviando === 'encaminhar'}
                    impedimento={
                        semEquipe.length > 0
                            ? `${contar(semEquipe.length, 'denúncia', 'denúncias')} sem equipe escolhida. Volte à listagem e confirme a equipe de cada uma.`
                            : null
                    }
                    onCancelar={() => setDecisao(null)}
                    onConfirmar={encaminhar}
                >
                    <p className="sobreposicao-texto" style={{ marginBottom: 12 }}>
                        Cada denúncia vai para a equipe do bairro dela, e passa a
                        esperar o <strong>líder daquela equipe</strong>, que direciona
                        aos fiscais ou inclui numa operação. Confira o resumo:
                    </p>

                    <ul className="rt-chips" style={{ marginBottom: 14 }}>
                        {resumoPorEquipe.map(([equipe, quantas]) => (
                            <li key={equipe} className="rt-chip">
                                <span className="rt-chip-dot" />
                                {/* Equipe E líder: é a última tela antes de o
                                    trabalho sair da mão de quem encaminha, e é aqui
                                    que ele confere para quem está entregando. */}
                                {equipe === 'sem equipe definida' ? equipe : nomeDaEquipe(equipe)}
                                {liderDa(equipe) === null ? '' : ` · ${liderDa(equipe)}`}:{' '}
                                {contar(quantas, 'denúncia', 'denúncias')}
                            </li>
                        ))}
                    </ul>

                    {/* Equipe sem líder registrado na estrutura: a denúncia é
                        encaminhada e fica sem quem a receba. Aviso, não bloqueio —
                        o cadastro do líder é de fora desta tela. */}
                    {resumoPorEquipe.some(([equipe]) => equipe !== 'sem equipe definida' && liderDa(equipe) === null) && (
                        <p className="form-erro" style={{ marginBottom: 12 }}>
                            <TriangleAlert size={15} aria-hidden /> Há equipe sem líder
                            registrado no sistema: a denúncia chega lá e ninguém é
                            avisado. Vale registrar o líder em Sistema › Áreas e
                            Equipes.
                        </p>
                    )}

                    <div className="form-group">
                        <label className="form-label" htmlFor="encaminhar-observacao">
                            Orientação ao líder da equipe
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
                        A denúncia <strong>não chega a nenhuma equipe</strong>: fica
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
                            ? 'Direcionar a denúncia aos fiscais?'
                            : `Direcionar ${alvos.length} denúncias aos fiscais?`
                    }
                    icone={<Send size={19} aria-hidden />}
                    rotulo="Direcionar"
                    iconeConfirmar={<Send size={16} aria-hidden />}
                    processando={enviando === 'direcionar'}
                    impedimento={null}
                    onCancelar={() => setDecisao(null)}
                    onConfirmar={direcionar}
                >
                    <p className="sobreposicao-texto" style={{ marginBottom: 12 }}>
                        A denúncia vira <strong>trabalho dirigido</strong> e aparece
                        no aplicativo dos fiscais da sua equipe. A equipe já é a
                        que o Chefe de Setor escolheu ao encaminhar — se ela veio
                        para a equipe errada, o caminho é devolver ao chefe pela
                        tela de Fiscalizações.
                    </p>

                    <div className="form-group">
                        <label className="form-label" htmlFor="direcionar-orientacao">
                            Orientação aos fiscais (opcional)
                        </label>
                        <textarea
                            id="direcionar-orientacao"
                            className="form-control"
                            rows={3}
                            value={envio.orientacao}
                            maxLength={1000}
                            placeholder="Ex.: ir depois das 18h — as mesas só saem para a calçada à noite"
                            onChange={(e) =>
                                setEnvio((v) => ({ ...v, orientacao: e.target.value }))
                            }
                        />
                        <p className="form-ajuda">
                            Fica no trâmite e chega ao aparelho junto com a denúncia: é
                            o que o fiscal lê antes de sair.
                        </p>
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
                                        {o.nome} · {o.area} ·{' '}
                                        {/* As equipes vêm em LISTA: operação grande
                                            junta equipe de outra área como reforço.
                                            Sem equipe definida é caso possível (a
                                            operação foi planejada antes de a escala
                                            sair), e a opção diz isso em vez de
                                            mostrar "(Equipe )" em branco. */}
                                        {o.equipes.length === 0
                                            ? 'sem equipe definida'
                                            : `${o.equipes.length === 1 ? 'Equipe' : 'Equipes'} ${o.equipes.join(', ')}`}{' '}
                                        — {o.periodo}
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
