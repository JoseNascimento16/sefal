import { Head } from '@inertiajs/react';
import {
    Archive,
    Camera,
    Check,
    ClipboardCheck,
    FileText,
    Info,
    Lightbulb,
    MapPin,
    RotateCcw,
    Timer,
    Undo2,
    UserRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { BuscaInteligente } from '@/components/retaguarda/busca-inteligente';
import BotaoExportar from '@/components/retaguarda/exportar';
import type { Listagens } from '@/components/retaguarda/grade-enxuta';
import { CabecaDaGrade, Celula } from '@/components/retaguarda/grade-enxuta';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import { Sobreposicao } from '@/components/retaguarda/sobreposicao';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import type { AcessorOrd } from '@/components/retaguarda/th-ordenavel';
import {
    Paginacao,
    useOrdenacao,
    usePaginacao,
} from '@/components/retaguarda/th-ordenavel';
import { useEnvio } from '@/hooks/use-envio';
import { casaTermos, parseConsulta } from '@/lib/busca';
import { dataBR, dataHoraBR, VAZIO } from '@/lib/datas';
import { linhaClicavel } from '@/lib/linha-clicavel';
import { contar, plural } from '@/lib/plural';
import type { CatalogoDeRecomendacoes } from '@/lib/recomendacoes';
import { textoDaRecomendacao, textosDasRecomendacoes } from '@/lib/recomendacoes';
import { cn } from '@/lib/utils';
import { ciencia, index, novaVistoria, reiniciar } from '@/routes/retaguarda/fiscalizacoes';

/**
 * Fiscalizações — TODO registro de fiscalização concluído, numa tela só.
 * PROTÓTIPO.
 *
 * ── Por que UMA tela, e não duas ─────────────────────────────────────────────
 *
 * Isto era duas coisas: "Retorno de Campo", construída, com a fila do Chefe de
 * Setor; e "Fiscalizações", um andaime que prometia a consulta por ambulante,
 * área e período. Duas telas sobre o MESMO registro — a fiscalização concluída —,
 * e o gestor tinha de pular de menu para juntar as duas metades da mesma
 * informação. Unificadas por decisão do dono (09/09/2026).
 *
 * ── As duas abas, e a pergunta que cada uma responde ────────────────────────
 *
 *  · **A decidir** — "o que eu tenho para fazer agora?". É a FILA: o que voltou da
 *    rua e espera a leitura da chefia. Tela de TRABALHO: seleção, comando
 *    flutuante, janela de decisão.
 *  · **Acervo** — "o que foi feito naquele ponto?". É a CONSULTA, sem ação: tudo
 *    o que passou por aqui, inclusive o já lido, com o alvo encontrado, as fotos,
 *    a coordenada, o documento que saiu na hora e o PRAZO de quem foi notificado.
 *
 * ⚠️ Não há uma terceira aba, e isso foi decidido: "por operação" e "por prazo
 * vencido" não são conjuntos diferentes — são recortes do acervo, e a BUSCA os
 * entrega ("vencido", "operação"). Aba que só filtra o mesmo conjunto seria um
 * segundo filtro concorrendo com a barra, contra o padrão de busca do projeto.
 *
 * ── A grade é ENXUTA; o resto está no clique ─────────────────────────────────
 *
 * Padrão do sistema, em `docs/padroes/listagem-clean.md`: uma linha por
 * registro, altura fixa, no máximo cinco colunas, texto livre fora da grade.
 * As colunas — as da tela e as do arquivo — vêm do servidor
 * (`config/listagens_da_retaguarda.php`), porque enxugar é da TELA: o arquivo
 * exportado continua levando equipe, fiscal, documento, considerações e tudo o
 * mais que desceu para a ficha.
 *
 * ── A RECOMENDAÇÃO do fiscal é a coluna que decide ───────────────────────────
 *
 * O desfecho diz como a vistoria terminou; a recomendação diz o que quem esteve
 * no ponto está PEDINDO. É por ela que a chefia direciona, então ela tem coluna
 * própria na fila — não uma linha no detalhe. Quem precisa varrer trinta retornos
 * com o olho não abre trinta detalhes. Na linha vai a PRIMEIRA, com o quanto
 * falta ("+2"); a lista inteira está na dica e na ficha, e é a dica que devolve
 * ao leitor de tela o que a linha resumiu.
 *
 * ── A lista não é o universo, e a tela avisa ─────────────────────────────────
 *
 * O recorte por área é feito no SERVIDOR (ver o controller). A tela diz de quais
 * áreas é a lista, porque sem o aviso a chefia contaria os registros, acharia o
 * número baixo e concluiria que a equipe não trabalhou.
 *
 * ⚠️ A busca é o filtro ÚNICO — não há chip de filtro paralelo. Os números do
 * topo são o resumo da mesma lista e, clicados, escrevem a faceta na busca. A
 * ABA, sim, troca a FONTE dos dados, e é por isso que ela entra no contexto da
 * exportação.
 */

interface Decisao {
    em: string;
    quem: string;
    o_que: string;
    detalhe: string;
}

/** O prazo de retorno de quem foi notificado — só a Notificação Preliminar tem. */
interface Prazo {
    /** ISO — quem escreve dd/mm/aaaa é a tela. */
    vence_em: string;
    /** Dias até o vencimento; NEGATIVO quando já venceu. Conta do servidor. */
    dias: number;
    vencido: boolean;
    notificado: string | null;
}

interface Registro {
    id: number;
    protocolo: string;
    /** 'Denúncia', 'Operação planejada', 'Ronda da equipe', 'Pedido de outro órgão'. */
    origem: string;
    /** O que originou a ida ao ponto: o canal e o protocolo, ou o nome da operação. */
    referencia: string;
    /** O protocolo da denúncia, quando o registro veio de uma. */
    denuncia_protocolo: string | null;
    /** ISO com hora — quem escreve dd/mm/aaaa é a tela. */
    concluida_em: string;
    area: string;
    equipe: string;
    /** Quem assinou a vistoria, com a equipe. */
    fiscal: string;
    endereco: string;
    bairro: string;
    ponto_de_referencia: string | null;
    gps: string | null;
    precisao_m: number | null;
    /**
     * Quem a equipe encontrou no ponto. NULO é caso previsto, e não dado
     * faltando: "nada encontrado no local" é desfecho legítimo — a foto do ponto
     * vazio é a prova da ida.
     */
    alvo: string | null;
    equipamento: string | null;
    /** Nomes dos arquivos de foto: o protótipo não guarda imagem. */
    fotos: string[];
    desfecho: string;
    /** `np` = Notificação Preliminar; `aa` = Auto de Apreensão. */
    documento: {
        tipo: 'np' | 'aa';
        numero: string;
        notificado: string | null;
        /** ISO. Vem PRONTO do servidor: a duração do prazo tem um dono só. */
        vence_em: string | null;
        /** "48 horas", "05 dias" — a redação do impresso. */
        prazo_rotulo: string | null;
    } | null;
    consideracoes: string | null;
    /**
     * As **CHAVES** dos atalhos que o fiscal assinalou (`retorno`, `sgci`…),
     * nunca a frase: é a chave que o aplicativo dele grava, e é por ela que o
     * relatório soma. A frase sai do catálogo `recomendacoesDoFiscal`.
     */
    recomendacoes: string[];
    /** A situação em que a denúncia de origem ficou — nulo na fiscalização avulsa. */
    situacao_da_origem: string | null;
    estado: string;
    decisao: Decisao | null;
    /** Dias esperando a leitura da chefia. Nulo depois de decidido. */
    dias_parado: number | null;
    prazo: Prazo | null;
}

type Aba = 'a-decidir' | 'acervo';

/** O tom do selo de cada estado da fila. */
const TOM_DO_ESTADO: Record<string, string> = {
    'Aguardando leitura': 'selo-aviso',
    Ciente: 'selo-neutro',
    'Nova vistoria determinada': 'selo-info',
};

/** As expressões do domínio que a busca reconhece e retira do texto livre. */
type Faceta =
    | 'com-documento'
    | 'sem-documento'
    | 'de-denuncia'
    | 'avulsa'
    | 'com-recomendacao'
    | 'nao-identificado'
    | 'com-foto'
    | 'prazo-vencido'
    | 'prazo-correndo'
    | 'ultimos-7'
    | 'ultimos-30';

/*
 * ⚠️ A ORDEM importa: a expressão mais específica vem antes, senão a genérica come
 * a outra ("prazo vencido" antes de "prazo").
 */
const FACETAS: { expressao: RegExp; valor: Faceta }[] = [
    { expressao: /\bsem documento\b|\bsem papel\b/, valor: 'sem-documento' },
    { expressao: /\bcom documento\b|\bnotificad\w*\b|\bautuad\w*\b/, valor: 'com-documento' },
    { expressao: /\bde denuncia\b|\bdenuncia\b/, valor: 'de-denuncia' },
    { expressao: /\bavuls\w*\b|\brond\w*\b|\boperacao\b/, valor: 'avulsa' },
    { expressao: /\bcom recomendacao\b|\brecomendad\w*\b/, valor: 'com-recomendacao' },
    // O alvo: "não identificado" é caso previsto do domínio, e quem procura por
    // ele está procurando exatamente os registros sem alvo — não uma falha.
    { expressao: /\bnao identificad\w*\b|\bsem alvo\b/, valor: 'nao-identificado' },
    { expressao: /\bcom foto\w*\b/, valor: 'com-foto' },
    { expressao: /\bprazo vencid\w*\b|\bvencid\w*\b/, valor: 'prazo-vencido' },
    { expressao: /\bprazo corrend\w*\b|\bcom prazo\b/, valor: 'prazo-correndo' },
    // O PERÍODO como faceta, e não como par de campos de data: o padrão do projeto
    // é uma barra só que interpreta a frase. "Nos últimos 7 dias" é como a chefia
    // pergunta; dois seletores de data seriam um segundo filtro ao lado da busca.
    { expressao: /\bultimos 7 dias\b|\bultima semana\b|\besta semana\b/, valor: 'ultimos-7' },
    { expressao: /\bultimos 30 dias\b|\bultimo mes\b|\beste mes\b/, valor: 'ultimos-30' },
];

/** O documento em uma linha: "Notificação nº 194903". */
function nomeDoDocumento(d: NonNullable<Registro['documento']>): string {
    return `${d.tipo === 'np' ? 'Notificação' : 'Apreensão'} nº ${d.numero}`;
}

/** Quantos dias inteiros separam a conclusão de hoje — para a faceta de período. */
function diasDesde(iso: string): number {
    const [data] = String(iso).replace(' ', 'T').split('T');
    const [ano, mes, dia] = data.split('-').map(Number);

    return Math.floor(
        (Date.now() - Date.UTC(ano, (mes ?? 1) - 1, dia ?? 1)) / 86400000,
    );
}

/** "vence em 3 dias" / "venceu há 2 dias" / "vence hoje". */
function textoDoPrazo(prazo: Prazo): string {
    if (prazo.dias === 0) {
        return 'vence hoje';
    }

    return prazo.dias > 0
        ? `vence em ${contar(prazo.dias, 'dia', 'dias')}`
        : `venceu há ${contar(-prazo.dias, 'dia', 'dias')}`;
}

export default function Fiscalizacoes({
    registros,
    estados,
    recomendacoesDoFiscal,
    chefias,
    decide,
    areasDoChefe,
    recorteDeArea,
    listagens,
    alterada,
}: {
    registros: Registro[];
    estados: string[];
    /**
     * Chave da recomendação → a frase EXPLÍCITA que a chefia lê — catálogo do
     * servidor. A pílula curta é do celular do fiscal; aqui quem decide precisa
     * da frase inteira. Chave que o catálogo não conhece aparece CRUA (ver
     * `@/lib/recomendacoes`): recomendação que evapora em silêncio faria a
     * chefia decidir sem saber que o fiscal pediu algo.
     */
    recomendacoesDoFiscal: CatalogoDeRecomendacoes;
    chefias: Record<string, { nome: string; matricula: string | null }>;
    /** Esta pessoa DECIDE aqui, ou apenas consulta? Quem responde é o servidor. */
    decide: boolean;
    areasDoChefe: string[];
    /** A listagem já veio recortada por essas áreas? Quem recorta é o servidor. */
    recorteDeArea: boolean;
    /**
     * As colunas de cada aba — da grade e do arquivo —, resolvidas no servidor
     * (a de ÁREA só existe para quem varre mais de uma). Ver
     * `docs/padroes/listagem-clean.md`.
     */
    listagens: Listagens;
    alterada: boolean;
}) {
    const { enviando, ocupado, enviar } = useEnvio();

    const [aba, setAba] = useState<Aba>('a-decidir');
    const [busca, setBusca] = useState('');
    const [abertoId, setAbertoId] = useState<number | null>(null);
    const [marcados, setMarcados] = useState<number[]>([]);
    const [observacao, setObservacao] = useState('');
    const [justificativa, setJustificativa] = useState('');
    const [confirmandoVolta, setConfirmandoVolta] = useState(false);

    /*
     * O estado da FILA, e ele vem do servidor: o catálogo chega na ordem em que a
     * fila anda, e o primeiro é o que espera a leitura da chefia. Escrito na tela,
     * "Aguardando leitura" seria a mesma palavra com dois donos — e no dia em que
     * ela mudasse, a aba "A decidir" ficaria vazia sem nada acusar.
     */
    const aguardando = estados[0];

    /** O Chefe de Setor de uma área, ou null quando a estrutura não registra nenhum. */
    const chefeDa = (area: string): string | null => {
        const nome = chefias[area]?.nome ?? '';

        return nome.trim() === '' ? null : nome;
    };

    // A ABA troca a FONTE: "A decidir" é a fila propriamente dita, "Acervo" é
    // tudo o que passou por aqui — inclusive o já lido e o devolvido à equipe.
    // Não é filtro paralelo à busca: é outro conjunto de partida, e é por isso que
    // ela entra no contexto da exportação.
    const daAba = useMemo(
        () =>
            aba === 'a-decidir'
                ? registros.filter((r) => r.estado === aguardando)
                : registros,
        [registros, aba, aguardando],
    );

    const filtrados = useMemo(() => {
        const { facetas, termos } = parseConsulta<Faceta>(busca, FACETAS);

        return daAba.filter((r) => {
            if (facetas.includes('sem-documento') && r.documento !== null) {
                return false;
            }

            if (facetas.includes('com-documento') && r.documento === null) {
                return false;
            }

            if (facetas.includes('de-denuncia') && r.denuncia_protocolo === null) {
                return false;
            }

            if (facetas.includes('avulsa') && r.denuncia_protocolo !== null) {
                return false;
            }

            if (facetas.includes('com-recomendacao') && r.recomendacoes.length === 0) {
                return false;
            }

            if (facetas.includes('nao-identificado') && r.alvo !== null) {
                return false;
            }

            if (facetas.includes('com-foto') && r.fotos.length === 0) {
                return false;
            }

            if (facetas.includes('prazo-vencido') && !(r.prazo?.vencido ?? false)) {
                return false;
            }

            if (
                facetas.includes('prazo-correndo') &&
                (r.prazo === null || r.prazo.vencido)
            ) {
                return false;
            }

            if (facetas.includes('ultimos-7') && diasDesde(r.concluida_em) > 7) {
                return false;
            }

            if (facetas.includes('ultimos-30') && diasDesde(r.concluida_em) > 30) {
                return false;
            }

            return casaTermos(termos, [
                r.protocolo,
                r.referencia,
                r.endereco,
                r.bairro,
                r.ponto_de_referencia,
                r.equipe,
                r.fiscal,
                r.area,
                r.desfecho,
                r.consideracoes,
                // O ALVO entra na busca: "consultar por ambulante" é a pergunta que
                // o acervo existe para responder, e o nome de quem foi encontrado
                // no ponto é como se procura por ele.
                r.alvo,
                r.equipamento,
                r.documento?.numero,
                r.documento?.notificado,
                // Contra a FRASE, e não contra a chave: quem procura por
                // "operação" tem de achar o registro em que o fiscal pediu
                // operação — a chave `operacao` casaria por acidente, e
                // `passagem` não casaria com "passagem semanal".
                textosDasRecomendacoes(r.recomendacoes, recomendacoesDoFiscal).join(' '),
                r.estado,
            ]);
        });
    }, [daAba, busca, recomendacoesDoFiscal]);

    const ord = useOrdenacao(filtrados, {
        campo: 'concluida_em',
        dir: 'desc',
        acessor: 'concluida_em',
    });
    const pag = usePaginacao(ord.itens);

    /*
     * A SELEÇÃO só existe na aba "A decidir". O acervo é leitura — oferecer
     * caixinha lá prometeria uma ação que a aba não tem.
     */
    const selecionavel = decide && aba === 'a-decidir';

    /*
     * Trocar de aba, filtrar ou receber a lista de volta do servidor deixaria
     * marcado um registro que já não está à vista — e a decisão em lote alcançaria
     * o que a pessoa não está vendo. A seleção é do RECORTE VISÍVEL, e some com
     * ele.
     */
    useEffect(() => {
        setMarcados((atuais) =>
            atuais.filter((id) => filtrados.some((r) => r.id === id)),
        );
    }, [filtrados]);

    /* Ir para o acervo desfaz a seleção: ela pertence à fila. */
    useEffect(() => {
        if (!selecionavel) {
            setMarcados([]);
        }
    }, [selecionavel]);

    const selecionados = registros.filter((r) => marcados.includes(r.id));
    const podeDecidir = selecionavel && selecionados.length > 0;

    /* A janela de decisão fica fechada até o comando flutuante ser tocado. */
    const [decidindo, setDecidindo] = useState(false);

    /* Seleção esvaziada — pela ação que acabou de gravar, por troca de aba ou
       por desmarcar a última linha — fecha a janela. Deixá-la no ar sem alvo
       ofereceria "dar ciência" de nada. */
    useEffect(() => {
        if (selecionados.length === 0) {
            setDecidindo(false);
        }
    }, [selecionados.length]);

    function alternarMarca(id: number) {
        setMarcados((atuais) =>
            atuais.includes(id) ? atuais.filter((i) => i !== id) : [...atuais, id],
        );
    }

    /** Marca ou desmarca TODO o recorte filtrado — não só a página à vista. */
    function alternarTodos() {
        const doRecorte = filtrados.map((r) => r.id);
        const todosMarcados = doRecorte.every((id) => marcados.includes(id));

        setMarcados(todosMarcados ? [] : doRecorte);
    }

    function limpar() {
        setMarcados([]);
        setObservacao('');
        setJustificativa('');
    }

    function darCiencia() {
        enviar(
            'ciencia',
            ciencia().url,
            { ids: marcados, observacao: observacao.trim() || null },
            { onSuccess: limpar },
        );
    }

    function mandarVoltar() {
        enviar(
            'nova-vistoria',
            novaVistoria().url,
            { ids: marcados, justificativa },
            {
                onSuccess: () => {
                    setConfirmandoVolta(false);
                    limpar();
                },
                onError: () => setConfirmandoVolta(false),
            },
        );
    }

    const numeros = {
        total: registros.length,
        aLer: registros.filter((r) => r.estado === aguardando).length,
        comRecomendacao: registros.filter(
            (r) => r.estado === aguardando && r.recomendacoes.length > 0,
        ).length,
        comDocumento: registros.filter((r) => r.documento !== null).length,
        prazoVencido: registros.filter((r) => r.prazo?.vencido ?? false).length,
    };

    /*
     * As COLUNAS da aba — da grade e do arquivo. Vêm do servidor porque as duas
     * listas têm de ser conferidas uma contra a outra: a grade é enxuta por
     * ordem do dono, e o arquivo continua completo. Ver
     * `docs/padroes/listagem-clean.md` e `config/listagens_da_retaguarda.php`.
     */
    const listagem =
        listagens[aba === 'a-decidir' ? 'fiscalizacoes.a-decidir' : 'fiscalizacoes.acervo'];

    /** Como ORDENAR por cada coluna. Sem entrada, a coluna não ordena. */
    const acessores: Record<string, AcessorOrd<Registro> | undefined> = {
        concluida_em: 'concluida_em',
        ponto: (r) => r.endereco,
        area: 'area',
        alvo: 'alvo',
        desfecho: 'desfecho',
        // Pelo VENCIMENTO, e não pelo texto exibido: ordenar por "vence em 3
        // dias" ordenaria alfabeticamente pela palavra "vence".
        prazo: (r) => r.prazo?.vence_em ?? '',
        recomendacoes: undefined,
    };

    /** Cinza de apoio — o mesmo em toda célula que diz "isto não existe". */
    const fraco = { color: 'var(--sm-texto-fraco)' };

    /**
     * O que cada célula DESENHA, e o texto inteiro para a dica.
     *
     * `resumida` marca o único caso em que a tela realmente omite conteúdo (a
     * primeira recomendação com "+2"): aí a dica também é anunciada, porque é o
     * único lugar onde o que ficou de fora volta a existir.
     */
    function celula(
        r: Registro,
        chave: string,
    ): { conteudo: ReactNode; dica?: string; resumida?: boolean } {
        if (chave === 'concluida_em') {
            return {
                conteudo: dataBR(r.concluida_em),
                dica:
                    `Concluída em ${dataHoraBR(r.concluida_em)}`
                    + (r.dias_parado !== null && r.dias_parado > 0
                        ? ` · há ${contar(r.dias_parado, 'dia', 'dias')} na fila`
                        : ''),
            };
        }

        if (chave === 'ponto') {
            return {
                conteudo: r.endereco,
                dica: [r.endereco, r.bairro, r.area]
                    .filter((parte) => parte !== null && String(parte).trim() !== '')
                    .join(' · '),
            };
        }

        if (chave === 'area') {
            return { conteudo: r.area || VAZIO, dica: r.area || undefined };
        }

        if (chave === 'alvo') {
            // "não identificado" e não um travessão: o alvo nulo é INFORMAÇÃO —
            // a equipe foi e não achou ninguém —, e o traço a leria como falta
            // de dado.
            return r.alvo === null
                ? {
                      conteudo: <span style={fraco}>não identificado</span>,
                      dica: 'Ninguém foi identificado no ponto — a foto do local é a prova da ida.',
                  }
                : {
                      conteudo: r.alvo,
                      dica: [r.alvo, r.equipamento].filter(Boolean).join(' · '),
                  };
        }

        if (chave === 'desfecho') {
            return { conteudo: r.desfecho, dica: r.desfecho };
        }

        if (chave === 'prazo') {
            if (r.prazo === null) {
                return { conteudo: <span style={fraco}>sem prazo correndo</span> };
            }

            return {
                conteudo: (
                    <span
                        className={cn(
                            'selo',
                            r.prazo.vencido ? 'selo-perigo' : 'selo-aviso',
                        )}
                    >
                        <Timer size={11} aria-hidden /> {dataBR(r.prazo.vence_em)}
                        {r.prazo.vencido ? ' · vencido' : ''}
                    </span>
                ),
                dica: [
                    `${dataBR(r.prazo.vence_em)} — ${textoDoPrazo(r.prazo)}`,
                    r.prazo.notificado,
                ]
                    .filter(Boolean)
                    .join(' · '),
            };
        }

        const frases = textosDasRecomendacoes(r.recomendacoes, recomendacoesDoFiscal);

        if (frases.length === 0) {
            return { conteudo: <span style={fraco}>o fiscal não recomendou nada</span> };
        }

        return {
            conteudo: (
                /*
                 * O "+N" mora DENTRO do selo, e não ao lado: fora dele, ele é
                 * outra caixa na linha e — com o selo ocupando a largura toda da
                 * célula — descia para uma terceira linha, estourando a altura
                 * da grade. Dentro, ele flui junto das palavras e cabe nas duas
                 * linhas que a coluna declara.
                 */
                <span className="selo selo-info">
                    <Lightbulb size={11} aria-hidden /> {frases[0]}
                    {frases.length > 1 && ` (+${frases.length - 1})`}
                </span>
            ),
            dica: frases.join(' · '),
            resumida: frases.length > 1,
        };
    }

    const linhasExportacao = ord.itens.map((r) => ({
        protocolo: r.protocolo,
        concluida_em: dataHoraBR(r.concluida_em),
        area: r.area || VAZIO,
        equipe: r.equipe || VAZIO,
        fiscal: r.fiscal,
        ponto: [r.endereco, r.bairro].filter(Boolean).join(' — '),
        // "não identificado" e não vazio: o alvo nulo é uma INFORMAÇÃO (a equipe
        // foi e não achou ninguém), e um travessão a esconderia como dado faltando.
        alvo: r.alvo ?? 'não identificado',
        desfecho: r.desfecho,
        documento: r.documento === null ? 'nenhum' : nomeDoDocumento(r.documento),
        prazo:
            r.prazo === null
                ? VAZIO
                : `${dataBR(r.prazo.vence_em)} (${textoDoPrazo(r.prazo)})`,
        provas: [
            r.fotos.length > 0 ? contar(r.fotos.length, 'foto', 'fotos') : null,
            r.gps === null ? 'sem coordenada' : r.gps,
        ]
            .filter(Boolean)
            .join(' · '),
        // O arquivo vai EXPLÍCITO: quem o abre é quem decide, não o aparelho —
        // uma célula com `sgci` não é resposta para ninguém.
        recomendacoes:
            r.recomendacoes.length === 0
                ? VAZIO
                : textosDasRecomendacoes(r.recomendacoes, recomendacoesDoFiscal).join('; '),
        consideracoes: r.consideracoes ?? VAZIO,
        origem: r.referencia,
        estado: r.estado,
    }));

    /** Quantas colunas a grade tem — para o `colSpan` da linha vazia e do detalhe. */
    const colunas = (selecionavel ? 1 : 0) + listagem.grade.length;

    return (
        <>
            <Head title="Fiscalizações" />

            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Fiscalização</p>
                    <h1>Fiscalizações</h1>
                    <p>
                        Tudo que a equipe <strong>concluiu em rua</strong>: o que
                        acabou de voltar e espera a sua leitura, na aba{' '}
                        <strong>A decidir</strong>, e o histórico consultável do
                        ponto, no <strong>Acervo</strong>.
                    </p>

                    {/* Qual é o SEU papel aqui. O dono usa o selo para mostrar que
                        a mesma tela serve dois papéis: um decide, o outro consulta. */}
                    <ul className="rt-chips">
                        {decide && (
                            <li className="rt-chip" style={{ color: 'var(--sm-primaria)' }}>
                                <span className="rt-chip-dot" />
                                {/* A ÁREA vai no selo: "você decide" sem dizer sobre o
                                    quê deixaria a chefia sem saber por que a fila é curta. */}
                                Sua fila
                                {areasDoChefe.length > 0
                                    ? ` · ${areasDoChefe.join(' e ')}`
                                    : ''}{' '}
                                — você dá ciência ou manda a equipe voltar
                            </li>
                        )}

                        {/* Chefe de Setor sem área vinculada: ele decide e não tem
                            sobre o quê. Dito na cara, e não em lista vazia sem
                            explicação — lista vazia parece sistema quebrado. */}
                        {decide && areasDoChefe.length === 0 && (
                            <li className="rt-chip" style={{ color: 'var(--sm-perigo)' }}>
                                <span className="rt-chip-dot" />
                                Sua conta não está vinculada a nenhuma área — procure
                                quem administra o sistema
                            </li>
                        )}

                        {!decide && (
                            <li className="rt-chip">
                                <span className="rt-chip-dot" />
                                Você consulta o que a fiscalização registrou; a decisão
                                sobre o retorno é do Chefe de Setor da área
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
                                ? 'Ver o acervo da sua área'
                                : 'Ver o acervo inteiro'
                        }
                        onClick={() => {
                            setBusca('');
                            setAba('acervo');
                        }}
                    >
                        <strong>{numeros.total}</strong>
                        <span>{recorteDeArea ? 'da sua área' : 'concluídas'}</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero alerta"
                        title="Ver os que ainda esperam a leitura da chefia"
                        onClick={() => {
                            setBusca('');
                            setAba('a-decidir');
                        }}
                    >
                        <strong>{numeros.aLer}</strong>
                        <span>a decidir</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero info"
                        title="Ver os que esperam leitura e trazem recomendação do fiscal"
                        onClick={() => {
                            setAba('a-decidir');
                            setBusca('com recomendação');
                        }}
                    >
                        <strong>{numeros.comRecomendacao}</strong>
                        <span>com recomendação</span>
                    </button>

                    <div className="rt-numeros-separador" />
                    <button
                        type="button"
                        className="rt-numero"
                        title="Ver os que tiveram documento lavrado"
                        onClick={() => {
                            setAba('acervo');
                            setBusca('com documento');
                        }}
                    >
                        <strong>{numeros.comDocumento}</strong>
                        <span>com documento</span>
                    </button>

                    {/* Prazo VENCIDO é o único número desta tela que cobra ação de
                        quem não está na fila: a notificação sem retorno fica no
                        papel. Ele mora no acervo porque continua correndo depois de
                        a chefia dar ciência. */}
                    {numeros.prazoVencido > 0 && (
                        <>
                            <div className="rt-numeros-separador" />
                            <button
                                type="button"
                                className="rt-numero alerta"
                                title="Ver os notificados cujo prazo de retorno já venceu"
                                onClick={() => {
                                    setAba('acervo');
                                    setBusca('prazo vencido');
                                }}
                            >
                                <strong>{numeros.prazoVencido}</strong>
                                <span>prazo vencido</span>
                            </button>
                        </>
                    )}
                </div>
            </div>

            <SeloPrototipo>
                Esta tela é a proposta das duas abas, para conferência da forma antes
                de virar sistema. Os registros são de exemplo e{' '}
                <strong>nada é gravado</strong>: a ciência e o pedido de nova
                vistoria valem só nesta sessão do navegador.
            </SeloPrototipo>

            {/* O aviso que separa esta tela das duas portas de ENTRADA. Fica em
                cima, e não numa coluna da grade, porque é a natureza da tela
                inteira. */}
            <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                <Undo2 size={16} aria-hidden />
                <div>
                    <strong>
                        O trabalho VOLTANDO da rua — ninguém registra fiscalização
                        aqui.
                    </strong>
                    <div>
                        Quem registra é o fiscal, em rua, pelo aplicativo. Esta tela
                        é o outro lado da cadeia: o que chega em papel ao balcão é
                        assunto da <strong>Caixa de Entrada</strong>, e o que chega
                        das ouvidorias é assunto de <strong>Denúncias</strong>.
                    </div>
                </div>
            </div>

            {/* A lista da chefia NÃO é o universo, e a tela diz isso. */}
            {recorteDeArea && (
                <div className="rt-sugestao" style={{ marginBottom: 18 }}>
                    <Info size={16} aria-hidden />
                    <div>
                        {/* A frase evita concordar com a lista de áreas de
                            propósito: "as equipes de Área 5 concluiu/concluíram"
                            erra o número em um dos dois casos, porque o sujeito é
                            "as equipes" e a lista é o complemento. */}
                        <strong>
                            Você está vendo só o que voltou de{' '}
                            {areasDoChefe.join(' e ')}.
                        </strong>
                        <div>
                            As fiscalizações das outras áreas não aparecem aqui — e a
                            decisão sobre registro de outra área é recusada pelo
                            sistema, não só escondida.
                        </div>
                    </div>
                </div>
            )}

            <div className="card-premium">
                {/* A ABA troca a FONTE dos dados (a fila × o acervo da área), e por
                    isso ela é aba e não chip de filtro — a busca continua sendo o
                    filtro único dentro do conjunto escolhido. */}
                <div className="abas" role="tablist" aria-label="Recorte das fiscalizações">
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'a-decidir'}
                        onClick={() => setAba('a-decidir')}
                    >
                        <ClipboardCheck size={16} aria-hidden />
                        <span className="aba-rotulo">A decidir ({numeros.aLer})</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        className="aba"
                        aria-selected={aba === 'acervo'}
                        onClick={() => setAba('acervo')}
                    >
                        <Archive size={16} aria-hidden />
                        <span className="aba-rotulo">Acervo ({numeros.total})</span>
                    </button>
                </div>

                {/* O que a aba do ACERVO é, dito na própria aba: sem isto ela parece
                    a mesma grade com mais linhas, e ninguém procuraria por ponto
                    antigo aqui. */}
                {aba === 'acervo' && (
                    <div className="rt-sugestao" style={{ marginBottom: 4 }}>
                        <Archive size={16} aria-hidden />
                        <div>
                            <strong>
                                O histórico do ponto — consulta, sem ação.
                            </strong>
                            <div>
                                Tudo o que a equipe concluiu, inclusive o que já foi
                                lido: quem foi encontrado, as fotos, a coordenada, o
                                documento que saiu na hora e o prazo de retorno de
                                quem foi notificado. Procure por ambulante, por área
                                ou por período — a barra abaixo entende a frase.
                            </div>
                        </div>
                    </div>
                )}

                <BuscaInteligente
                    busca={busca}
                    setBusca={setBusca}
                    placeholder={
                        aba === 'acervo'
                            ? 'Procure por ambulante, ponto, bairro, área, equipe, documento ou período'
                            : 'Procure por ponto, bairro, equipe, fiscal, desfecho ou o que o fiscal escreveu'
                    }
                    exemplos={
                        aba === 'acervo'
                            ? [
                                  'nos últimos 7 dias',
                                  'prazo vencido',
                                  'não identificado',
                                  'com documento',
                              ]
                            : [
                                  'com recomendação',
                                  'sem documento',
                                  'de denúncia',
                                  'ronda',
                              ]
                    }
                />

                {/* A DECISÃO mora numa janela, aberta pelo comando flutuante.
                    Ela só existe com seleção, e só para quem decide: oferecer o
                    botão a quem o servidor recusa é prometer o que a tela não
                    entrega.

                    Por que janela e não painel na página: a grade é longa, e o
                    painel embaixo dela obrigava quem marcou uma linha do meio da
                    lista a rolar de volta para achar o que fazer com ela. Na
                    janela, a decisão é o único assunto — e a `Sobreposicao`
                    resolve trava de rolagem, fundo inerte e empilhamento com a
                    confirmação que vem depois. */}
                {podeDecidir && decidindo && (
                  <Sobreposicao clicandoFora={ocupado ? undefined : () => setDecidindo(false)}>
                    <div
                        className="card-premium"
                        style={{
                            width: '100%',
                            maxWidth: 860,
                            maxHeight: 'min(92vh, 100% - 8px)',
                            overflowY: 'auto',
                        }}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Decidir sobre as fiscalizações selecionadas"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 className="sobreposicao-titulo">
                            <ClipboardCheck size={18} aria-hidden /> Decidir sobre{' '}
                            {contar(selecionados.length, 'retorno', 'retornos')}
                        </h2>
                        <p className="sobreposicao-texto">
                            Os dois caminhos da leitura: encerrar na sua fila, ou
                            devolver o ponto à equipe dizendo o que procurar.
                        </p>

                        <div className="rt-escolha" style={{ marginBottom: 4 }}>
                        <div className="card-premium" style={{ margin: 0 }}>
                            <h3 className="card-titulo">
                                <Check size={16} aria-hidden /> Dar ciência
                            </h3>
                            <p className="card-sub">
                                O retorno sai da sua fila e fica no acervo.{' '}
                                {contar(selecionados.length, 'registro', 'registros')}{' '}
                                {plural(selecionados.length, 'selecionado', 'selecionados')}.
                            </p>

                            <div className="form-group">
                                <label className="form-label" htmlFor="observacao">
                                    Observação (opcional)
                                </label>
                                <textarea
                                    id="observacao"
                                    className="form-control"
                                    rows={2}
                                    maxLength={1000}
                                    value={observacao}
                                    onChange={(e) => setObservacao(e.target.value)}
                                    placeholder="O que você quer que fique registrado na leitura"
                                />
                                <p className="form-ajuda">
                                    Opcional de propósito: o ato de ler já é a
                                    informação, e exigir texto para dar ciência de
                                    vários faria escrever frases vazias.
                                </p>
                            </div>

                            <BotaoAcao
                                icone={<Check size={16} aria-hidden />}
                                carregando={enviando === 'ciencia'}
                                ocupado={ocupado}
                                rotuloCarregando="Registrando…"
                                onClick={darCiencia}
                            >
                                Dar ciência
                            </BotaoAcao>
                        </div>

                        <div className="card-premium" style={{ margin: 0 }}>
                            <h3 className="card-titulo">
                                <RotateCcw size={16} aria-hidden /> Mandar a equipe voltar
                            </h3>
                            <p className="card-sub">
                                O ponto volta para a equipe, com o que ela deve
                                procurar desta vez.
                            </p>

                            <div className="form-group">
                                <label className="form-label" htmlFor="justificativa">
                                    Justificativa
                                </label>
                                <textarea
                                    id="justificativa"
                                    className="form-control"
                                    rows={3}
                                    maxLength={1000}
                                    value={justificativa}
                                    onChange={(e) => setJustificativa(e.target.value)}
                                    placeholder="O que a equipe deve procurar, e em que dia ou horário"
                                />
                                <p className="form-ajuda">
                                    Obrigatória: mandar a equipe de volta gasta o
                                    trabalho dela outra vez, e &quot;voltar lá&quot;
                                    não diz o que procurar.
                                </p>
                            </div>

                            <BotaoAcao
                                icone={<RotateCcw size={16} aria-hidden />}
                                carregando={enviando === 'nova-vistoria'}
                                ocupado={ocupado}
                                disabled={justificativa.trim().length < 15}
                                rotuloCarregando="Devolvendo…"
                                onClick={() => setConfirmandoVolta(true)}
                            >
                                Determinar nova vistoria
                            </BotaoAcao>
                        </div>
                        </div>

                        <div className="sobreposicao-acoes">
                            <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                onClick={() => setDecidindo(false)}
                                disabled={ocupado}
                            >
                                Voltar à lista
                            </button>
                        </div>
                    </div>
                  </Sobreposicao>
                )}

                <div style={{ display: 'flex', alignItems: 'center', marginBottom: 10, gap: 12 }}>
                    {marcados.length > 0 && (
                        <p className="form-ajuda" style={{ margin: 0 }}>
                            {contar(marcados.length, 'registro', 'registros')}{' '}
                            {plural(marcados.length, 'selecionado', 'selecionados')}.
                        </p>
                    )}

                    <BotaoExportar
                        titulo="Fiscalizações"
                        subtitulo="Fiscalização › Fiscalizações"
                        contexto={[
                            `Aba: ${aba === 'a-decidir' ? 'A decidir' : 'Acervo'}`,
                            recorteDeArea
                                ? `Áreas: ${areasDoChefe.join(' e ')}`
                                : 'Todas as áreas',
                            busca.trim() ? `busca: "${busca.trim()}"` : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                        colunas={listagem.exportacao}
                        linhas={linhasExportacao}
                    />
                </div>

                <div className="table-wrap">
                    <table className="data-table enxuta">
                        <thead>
                            <tr>
                                {selecionavel && (
                                    <th style={{ width: 34 }}>
                                        <input
                                            type="checkbox"
                                            aria-label="Selecionar todos os registros filtrados"
                                            title="Selecionar todos os registros do filtro — não só os desta página"
                                            checked={
                                                filtrados.length > 0 &&
                                                filtrados.every((r) =>
                                                    marcados.includes(r.id),
                                                )
                                            }
                                            onChange={alternarTodos}
                                        />
                                    </th>
                                )}
                                {/* O cabeçalho e as células saem da MESMA lista de
                                    colunas: escritos em dois lugares, um dia uma
                                    coluna nova entra só num deles e a grade passa a
                                    mostrar o valor embaixo do título errado.

                                    O ALVO é coluna do ACERVO e a RECOMENDAÇÃO é da
                                    fila — quem decide o retorno decide sobre o
                                    PONTO, e quem consulta o histórico procura pela
                                    PESSOA. Quem declara isso é o catálogo. */}
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
                                        {registros.length === 0
                                            ? 'Nenhuma fiscalização por aqui ainda. Quando a equipe concluir uma em rua, ela aparece nesta tela.'
                                            : aba === 'a-decidir' && busca.trim() === ''
                                              ? 'Nada a decidir: todos os retornos desta fila já foram lidos. Veja a aba “Acervo” para o histórico.'
                                              : 'Nenhum registro casa com a busca. Limpe o campo para ver a lista inteira.'}
                                    </td>
                                </tr>
                            )}

                            {pag.visiveis.map((r) => (
                                <Fragment key={r.id}>
                                    <tr
                                        {...linhaClicavel(
                                            () =>
                                                setAbertoId(
                                                    abertoId === r.id ? null : r.id,
                                                ),
                                            'Abrir ou fechar o que o fiscal registrou neste ponto',
                                            aba === 'a-decidir' &&
                                                r.estado === aguardando &&
                                                'pendente',
                                        )}
                                    >
                                        {selecionavel && (
                                            <td
                                                onClick={(e) => e.stopPropagation()}
                                                onKeyDown={(e) => e.stopPropagation()}
                                            >
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Selecionar o registro ${r.protocolo}`}
                                                    checked={marcados.includes(r.id)}
                                                    onChange={() => alternarMarca(r.id)}
                                                />
                                            </td>
                                        )}
                                        {listagem.grade.map((coluna) => {
                                            const { conteudo, dica, resumida } = celula(
                                                r,
                                                coluna.chave,
                                            );

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

                                    {abertoId === r.id && (
                                        <tr className="linha-detalhe">
                                            <td colSpan={colunas}>
                                                <dl className="rt-ficha">
                                                    <div>
                                                        <dt>Registro</dt>
                                                        <dd>{r.protocolo}</dd>
                                                    </div>
                                                    {/* A HORA da conclusão e o tempo na
                                                        fila moram aqui: na grade a coluna
                                                        leva só dd/mm/aaaa, e a espera já é
                                                        dita pela marca laranja na ponta da
                                                        linha. */}
                                                    <div>
                                                        <dt>Concluída em</dt>
                                                        <dd>
                                                            {dataHoraBR(r.concluida_em)}
                                                            {r.dias_parado !== null &&
                                                                r.dias_parado > 0 && (
                                                                    <div style={fraco}>
                                                                        há{' '}
                                                                        {contar(
                                                                            r.dias_parado,
                                                                            'dia',
                                                                            'dias',
                                                                        )}{' '}
                                                                        na fila
                                                                    </div>
                                                                )}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt>Estado</dt>
                                                        <dd>
                                                            <span
                                                                className={cn(
                                                                    'selo',
                                                                    TOM_DO_ESTADO[r.estado] ??
                                                                        'selo-neutro',
                                                                )}
                                                            >
                                                                {r.estado}
                                                            </span>
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt>Ponto</dt>
                                                        <dd>
                                                            {r.endereco}
                                                            {/* O BAIRRO desceu da grade: na
                                                                coluna ele era a sub-linha que
                                                                dobrava a altura, e a área
                                                                tem linha própria logo abaixo. */}
                                                            <div style={fraco}>{r.bairro}</div>
                                                        </dd>
                                                    </div>
                                                    {/* Equipe e fiscal desceram da grade: a
                                                        decisão da chefia é sobre o PONTO, e
                                                        a assinatura de quem foi importa ao
                                                        abrir o registro. O arquivo exportado
                                                        continua levando as duas. */}
                                                    <div>
                                                        <dt>Equipe e fiscal</dt>
                                                        <dd>
                                                            {r.equipe ? `Equipe ${r.equipe}` : VAZIO}
                                                            <div style={fraco}>{r.fiscal}</div>
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt>Desfecho</dt>
                                                        <dd>{r.desfecho}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Origem da ida ao ponto</dt>
                                                        <dd>
                                                            {r.origem} · {r.referencia}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt>Área e chefia</dt>
                                                        <dd>
                                                            {r.area || VAZIO}
                                                            {chefeDa(r.area) === null
                                                                ? ''
                                                                : ` · ${chefeDa(r.area)}`}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt>Quem foi encontrado</dt>
                                                        <dd>
                                                            {r.alvo ?? 'não identificado'}
                                                            {r.equipamento === null
                                                                ? ''
                                                                : ` · ${r.equipamento}`}
                                                        </dd>
                                                    </div>
                                                    {r.situacao_da_origem !== null && (
                                                        <div>
                                                            <dt>Situação da denúncia</dt>
                                                            <dd>{r.situacao_da_origem}</dd>
                                                        </div>
                                                    )}
                                                    {r.documento !== null && (
                                                        <div>
                                                            <dt>Documento lavrado</dt>
                                                            <dd>
                                                                {nomeDoDocumento(r.documento)}
                                                                {r.documento.notificado === null
                                                                    ? ''
                                                                    : ` · ${r.documento.notificado}`}
                                                            </dd>
                                                        </div>
                                                    )}
                                                    {r.prazo !== null && (
                                                        <div>
                                                            <dt>Prazo de retorno</dt>
                                                            <dd>
                                                                {dataBR(r.prazo.vence_em)} —{' '}
                                                                {textoDoPrazo(r.prazo)}
                                                                {/* A redação do IMPRESSO ("48 horas"),
                                                                    e não os dias que a conta usou: é
                                                                    o que está escrito na via que o
                                                                    notificado tem na mão. */}
                                                                {r.documento?.prazo_rotulo == null ? (
                                                                    ''
                                                                ) : (
                                                                    <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                                                        prazo de {r.documento.prazo_rotulo} na via entregue
                                                                    </div>
                                                                )}
                                                            </dd>
                                                        </div>
                                                    )}
                                                    {r.ponto_de_referencia !== null && (
                                                        <div style={{ gridColumn: '1 / -1' }}>
                                                            <dt>Ponto de referência</dt>
                                                            <dd>{r.ponto_de_referencia}</dd>
                                                        </div>
                                                    )}
                                                </dl>

                                                {/* A coordenada vem SEMPRE com a
                                                    precisão: um ponto ruim é pior que
                                                    um ponto ausente disfarçado de bom. */}
                                                {r.gps !== null && (
                                                    <p className="form-ajuda" style={{ marginTop: 8 }}>
                                                        <MapPin size={14} aria-hidden /> {r.gps}
                                                        {r.precisao_m !== null
                                                            ? ` · precisão de ±${r.precisao_m} m`
                                                            : ''}
                                                    </p>
                                                )}

                                                {/* As FOTOS entram como nome de
                                                    arquivo: o protótipo não guarda
                                                    imagem, e miniatura falsa
                                                    prometeria o que a tela não
                                                    entrega. O que importa aqui é
                                                    saber QUANTAS provas existem. */}
                                                {r.fotos.length > 0 && (
                                                    <p className="form-ajuda" style={{ marginTop: 6 }}>
                                                        <Camera size={14} aria-hidden />{' '}
                                                        {contar(r.fotos.length, 'foto', 'fotos')}{' '}
                                                        {plural(
                                                            r.fotos.length,
                                                            'registrada',
                                                            'registradas',
                                                        )}{' '}
                                                        no ponto: {r.fotos.join(', ')}
                                                    </p>
                                                )}

                                                <div className="rt-sugestao" style={{ marginTop: 12 }}>
                                                    <Lightbulb size={16} aria-hidden />
                                                    <div>
                                                        <strong>
                                                            {r.recomendacoes.length === 0
                                                                ? 'Considerações finais do fiscal'
                                                                : `${plural(r.recomendacoes.length, 'Recomendação', 'Recomendações')} do fiscal`}
                                                        </strong>

                                                        {r.recomendacoes.length > 0 && (
                                                            <div style={{ margin: '6px 0 2px' }}>
                                                                {r.recomendacoes.map((rec) => (
                                                                    <span
                                                                        key={rec}
                                                                        className="selo selo-info"
                                                                        style={{
                                                                            marginRight: 6,
                                                                            marginBottom: 4,
                                                                        }}
                                                                    >
                                                                        {textoDaRecomendacao(
                                                                            rec,
                                                                            recomendacoesDoFiscal,
                                                                        )}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        )}

                                                        <div>
                                                            {r.consideracoes ?? (
                                                                <span
                                                                    style={{
                                                                        color: 'var(--sm-texto-fraco)',
                                                                    }}
                                                                >
                                                                    O fiscal não escreveu
                                                                    considerações neste retorno.
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                {r.denuncia_protocolo !== null && (
                                                    <p className="form-ajuda" style={{ marginTop: 10 }}>
                                                        <FileText size={14} aria-hidden /> O percurso
                                                        inteiro — relato, fotos e o documento
                                                        lavrado — está no trâmite da denúncia{' '}
                                                        <strong>{r.denuncia_protocolo}</strong>, em
                                                        Denúncias.
                                                    </p>
                                                )}

                                                {r.decisao !== null && (
                                                    <p className="form-ajuda" style={{ marginTop: 10 }}>
                                                        <UserRound size={14} aria-hidden />{' '}
                                                        <strong>{r.decisao.o_que}</strong> ·{' '}
                                                        {dataHoraBR(r.decisao.em)} ·{' '}
                                                        {r.decisao.quem} — {r.decisao.detalhe}
                                                    </p>
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

            {/* Reiniciar existe porque é PROTÓTIPO: quem demonstra precisa poder
                recomeçar a cena. Só aparece depois de a sessão ter decidido algo. */}
            {alterada && (
                <p className="form-ajuda" style={{ marginTop: 14 }}>
                    <BotaoAcao
                        className="btn btn-secondary btn-sm"
                        icone={<RotateCcw size={15} aria-hidden />}
                        carregando={enviando === 'reiniciar'}
                        ocupado={ocupado}
                        rotuloCarregando="Reiniciando…"
                        onClick={() => enviar('reiniciar', reiniciar().url, {}, { onSuccess: limpar })}
                    >
                        Reiniciar a demonstração
                    </BotaoAcao>
                </p>
            )}

            {/* O comando FLUTUANTE: nasce com a primeira linha marcada e acompanha
                a rolagem. Sem seleção ele não existe — botão que não tem sobre o
                que agir é enfeite que engana. */}
            {podeDecidir && !decidindo && (
                <button
                    type="button"
                    className="rt-acao-flutuante"
                    onClick={() => setDecidindo(true)}
                >
                    <ClipboardCheck size={17} aria-hidden />
                    Decidir
                    <span className="rt-acao-flutuante-conta">
                        {selecionados.length}
                    </span>
                </button>
            )}

            {confirmandoVolta && (
                <ModalConfirm
                    titulo="Mandar a equipe voltar ao ponto?"
                    mensagem={
                        <>
                            {contar(selecionados.length, 'registro', 'registros')}{' '}
                            {plural(selecionados.length, 'volta', 'voltam')} para a
                            equipe com a sua justificativa. Isso gasta o trabalho dela
                            outra vez — confirme se é mesmo caso de nova ida.
                        </>
                    }
                    rotuloConfirmar="Determinar nova vistoria"
                    iconeConfirmar={<RotateCcw size={16} aria-hidden />}
                    processando={enviando === 'nova-vistoria'}
                    onCancelar={() => setConfirmandoVolta(false)}
                    onConfirmar={mandarVoltar}
                />
            )}
        </>
    );
}

Fiscalizacoes.layout = {
    breadcrumbs: [{ title: 'Fiscalizações', href: index() }],
};
