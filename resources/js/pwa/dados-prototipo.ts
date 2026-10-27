/* ============================================================================
   Tipos e dados de campo do aplicativo do fiscal
   ----------------------------------------------------------------------------
   O nome do arquivo é herança do protótipo; o conteúdo, hoje, é real. Os
   ambulantes do mapa, o calor, as operações e o catálogo de bairros chegam do
   servidor no `kit` do turno (`carregarCampo`, `carregarBairros`). Ficam aqui
   os TIPOS que as telas leem, os catálogos fechados do aparelho (ocorrências,
   prazos de retorno, documentos de campo) e as REGIÕES da Área 5, que ainda
   servem de rede de segurança quando o catálogo de bairros não carregou.
   ============================================================================ */

/* O desfecho e a leitura que dele se deriva moram no módulo das denúncias, que é
   o espelho do catálogo da Retaguarda. Repetir a lista aqui criaria a segunda
   cópia que a lei da fonte única proíbe. */
import { LOCAL_APOS_O_DESFECHO, type Desfecho } from './dados-demandas';

/**
 * Como o LOCAL ficou — a leitura curta que o mapa e a lista usam para colorir.
 *
 * ⚠️ Não confundir com a `Situacao` da denúncia (em `dados-demandas.ts`), que é
 * o catálogo do trâmite. Esta aqui é do PONTO: liberado ou com ocorrência. Na
 * fiscalização ela é DERIVADA do desfecho (ver `LOCAL_APOS_O_DESFECHO`), nunca
 * escolhida ao lado dele — a mesma coisa decidida duas vezes divergiria.
 */
export type Situacao = 'regular' | 'irregular';

export type Regiao = {
    id: string;
    nome: string;
    lat: number;
    lng: number;
};

export type EventoHistorico = {
    data: string;
    resumo: string;
    status: Situacao;
};

export type Ambulante = {
    id: string;
    /** O id do ambulante no servidor — vai no registro feito a partir do pino. */
    servidorId?: number;
    nome: string;
    apelido: string;
    atividade: string;
    emoji: string;
    situacao: Situacao;
    permissao: string | null;
    regiao: string;
    endereco: string;
    lat: number;
    lng: number;
    ultimaEm: string;
    /** Retorno marcado por um fiscal para este ponto — em dias já vencidos. */
    retornoHaDias: number | null;
    historico: EventoHistorico[];
};

/**
 * De onde a fiscalização nasceu.
 *
 * `avulsa` é o fiscal andando a rua e vendo — o caminho que o protótipo já
 * encenava. `dirigida` é o trabalho que o Coordenador encaminhou à equipe
 * com número de processo e prazo: aí a fiscalização nasce AMARRADA à demanda, e
 * o vínculo tem de ficar visível no registro, no recibo e no documento.
 */
export type OrigemRegistro = 'avulsa' | 'dirigida';

/**
 * A via do documento, CONGELADA no instante em que foi lavrada.
 *
 * O documento não se remonta a partir da tela na hora de imprimir: o que vale é
 * o que o notificado assinou. Por isso o texto inteiro fica guardado no
 * registro e a impressão apenas o repete — dá para tirar a segunda via dias
 * depois e sair igual à primeira.
 */
export type ViaImpressa = {
    tipo: 'np' | 'aa';
    numero: string;
    titulo: string;
    campos: { rotulo: string; valor: string }[];
    listas: { titulo: string; itens: string[] }[];
    assinaturas: { rotulo: string; estado: 'assinada' | 'recusada' | 'pendente'; nome?: string }[];
    rodape: string;
};

export type Registro = {
    id: string;
    protocolo: string;
    dataBr: string;
    hora: string;
    regiao: string;
    endereco: string;
    /** A coordenada MEDIDA pelo aparelho — `null` quando não houve GPS. */
    lat: number | null;
    lng: number | null;
    /** Por que não houve GPS (chave de `MOTIVOS_SEM_GPS`), quando não houve. */
    semGps?: string | null;
    /**
     * COMO a vistoria terminou — da lista fechada que a Retaguarda soma.
     *
     * É o dado que fecha o passo do trâmite do outro lado. Texto livre aqui
     * faria o campo dizer uma coisa e a Retaguarda outra.
     */
    desfecho: Desfecho;
    /** Leitura derivada do desfecho — ponto liberado ou com ocorrência. */
    status: Situacao;
    ocorrencias: string[];
    relato: string;
    fotos: number;
    ambulante: string | null;
    retornoBr: string | null;
    envio: 'enviado' | 'pendente' | 'erro';
    documento: string | null;
    /** 'np' = Notificação Preliminar; 'aa' = Auto de Apreensão. */
    documentoTipo: 'np' | 'aa' | null;
    /** Preenchida quando o documento foi lavrado NESTA sessão do protótipo. */
    via: ViaImpressa | null;
    origem: OrigemRegistro;
    /** Preenchido só quando a origem é dirigida. */
    demandaId: string | null;
    /** O número do processo da demanda — vai para o campo REFERÊNCIA do papel. */
    referencia: string | null;
    /**
     * CONSIDERAÇÕES FINAIS do fiscal, escritas no despacho — texto livre.
     *
     * ⚠️ CONTRATO com o outro lado: o nome do campo é o mesmo dos dois lados
     * (ver o de-para na cabeça de `dados-demandas.ts`). É por este texto e pelas
     * `recomendacoes` que o Chefe de Setor entende o que o fiscal recomenda; sem
     * servidor no meio, renomear um deles quebra o de-para em silêncio.
     */
    consideracoes: string;
    /** As CHAVES das recomendações escolhidas (`RECOMENDACOES`), não os textos. */
    recomendacoes: string[];
    /**
     * Quando o fiscal DESPACHOU o registro, em data BR — `null` enquanto a
     * conclusão não foi confirmada.
     *
     * Registro despachado vai para a caixa de entrada do Chefe de Setor da área
     * da equipe; é isso que a fila de envio leva. Enquanto for `null`, o registro
     * está aberto: a tela de conclusão ainda aceita considerações.
     */
    despachadoBr: string | null;
    /** O ambulante do cadastro, quando o registro saiu de um pino do mapa. */
    ambulanteId?: number | null;
    /** O identificador que o APARELHO deu ao registro — é o que torna o reenvio seguro. */
    clientId: string | null;
    /** Quando o registro foi aberto no ponto (ISO). */
    abertaEm: string | null;
    /** A precisão do GPS, em metros, quando houve GPS. */
    precisaoM: number | null;
    /** O documento lavrado, nos campos que o servidor guarda (chaves dos catálogos). */
    documentoDados: DocumentoParaEnvio | null;
    /** A frase da última recusa do servidor, quando o envio falhou. */
    erroEnvio: string | null;
};

/** O documento (NP ou Auto) como o servidor o recebe. */
export type DocumentoParaEnvio = {
    tipo: 'np' | 'aa';
    numero: string;
    notificado: string | null;
    documento_notificado: string | null;
    prazo: string | null;
    motivos: string[];
    sancoes: string[];
    itens: { quantidade: number; unidade: string; descricao: string }[];
    guarda_prazo: string | null;
    guarda_destinacao: string | null;
    /** O resto do impresso, e a própria via, para a reimpressão. */
    dados: Record<string, unknown>;
};

/* Quem está com o aparelho na mão sai do módulo de sessão: é a matrícula
   digitada na porta que decide, e um segundo lugar guardando a mesma pessoa
   divergiria dele no primeiro ajuste. */
export { FISCAL } from './sessao';

/**
 * Data BR de hoje somada de N dias.
 *
 * O "hoje" é o de verdade. Escrever as datas à mão faria o protótipo envelhecer:
 * uma semana depois da demonstração, o turno "de hoje" apareceria vazio e a fila
 * inteira vencida — e o dono leria isso como comportamento do sistema. É também
 * o que a Caixa de Entrada da Retaguarda faz, e é o que mantém as duas telas
 * mostrando a mesma data.
 */
export const dataBrDaqui = (dias: number): string => {
    const d = new Date();
    /* Meio-dia: somar dias sobre a meia-noite erra na virada do horário de
       verão, e um dia a menos numa data de prazo é um erro que aparece. */
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + dias);

    return d.toLocaleDateString('pt-BR');
};

export const HOJE_BR = dataBrDaqui(0);

/**
 * As regiões do mapa — todas dentro da Área 5, o bloco da Equipe C1.
 *
 * Não são bairros oficiais: são os pontos de referência sobre os quais os pinos
 * do protótipo se distribuem. O nome de cada uma é o do bairro do bloco.
 *
 * ⚠️ As coordenadas da orla estão interpoladas entre Itapuã e Pituaçu (os dois
 * pontos que já vinham conferidos no protótipo) e RECUADAS uns 200 m para
 * dentro da terra. O recuo não é capricho: cada pino tem um desvio próprio de
 * até 0,0026° a partir do centro da região, e com o centro sobre a linha d'água
 * metade dos pinos aparecia no mar — foi o que o mapa mostrou na conferência.
 */
export const REGIOES: Regiao[] = [
    { id: 'costa-azul', nome: 'Costa Azul', lat: -12.9796, lng: -38.4485 },
    { id: 'jardim-armacao', nome: 'Jardim Armação', lat: -12.9722, lng: -38.4302 },
    { id: 'stiep', nome: 'Stiep', lat: -12.9702, lng: -38.436 },
    { id: 'boca-do-rio', nome: 'Boca do Rio', lat: -12.9678, lng: -38.4192 },
    { id: 'imbui', nome: 'Imbuí', lat: -12.9659, lng: -38.4305 },
    { id: 'pituacu', nome: 'Pituaçu', lat: -12.9613, lng: -38.4032 },
    { id: 'patamares', nome: 'Patamares', lat: -12.956, lng: -38.3902 },
    { id: 'piata', nome: 'Piatã', lat: -12.9503, lng: -38.3762 },
    { id: 'itapua', nome: 'Itapuã', lat: -12.9449, lng: -38.3628 },
    { id: 'stella-maris', nome: 'Stella Maris', lat: -12.9378, lng: -38.3452 },
    { id: 'mussurunga', nome: 'Mussurunga', lat: -12.9317, lng: -38.3722 },
];

/**
 * O centro do mapa quando o aparelho não entrega posição.
 *
 * A Boca do Rio, que dá nome à Área 5 — e não mais o Farol da Barra, que fica
 * na área de outra equipe.
 */
export const CENTRO_SALVADOR = { lat: -12.9678, lng: -38.4192 };

export const OCORRENCIAS = [
    { id: 'desarmou', rotulo: 'Desarmou e saiu', emoji: '✅' },
    { id: 'orientado', rotulo: 'Orientado no local', emoji: '🗣️' },
    { id: 'recusou', rotulo: 'Recusou sair', emoji: '🚫' },
    { id: 'vazio', rotulo: 'Local vazio na chegada', emoji: '🌬️' },
    { id: 'reincidente', rotulo: 'Reincidente', emoji: '🔁' },
    { id: 'calcada', rotulo: 'Obstrução de calçada', emoji: '🚧' },
    { id: 'sem-permissao', rotulo: 'Sem permissão', emoji: '📄' },
    { id: 'bebida', rotulo: 'Venda de bebida', emoji: '🍺' },
    { id: 'alimento', rotulo: 'Manipulação de alimento', emoji: '🍢' },
    { id: 'apoio', rotulo: 'Apoio da guarda municipal', emoji: '🚓' },
];

const ATIVIDADES = [
    { nome: 'Acarajé e abará', emoji: '🍢' },
    { nome: 'Água e refrigerante', emoji: '🥤' },
    { nome: 'Cerveja e gelo', emoji: '🍺' },
    { nome: 'Artesanato e fitas', emoji: '🪡' },
    { nome: 'Óculos e bijuteria', emoji: '🕶️' },
    { nome: 'Milho e amendoim', emoji: '🌽' },
    { nome: 'Cocada e mariola', emoji: '🍬' },
    { nome: 'Espetinho', emoji: '🍡' },
    { nome: 'Sorvete e picolé', emoji: '🍦' },
    { nome: 'Capa de chuva e guarda-sol', emoji: '☂️' },
    { nome: 'Frutas', emoji: '🍍' },
    { nome: 'Caldo de cana', emoji: '🍹' },
];

/**
 * O nome do lugar. As regiões do mapa de exemplo têm id próprio; nos registros
 * e demandas reais o "id" já é o nome do bairro, e volta como veio.
 */
export const nomeRegiao = (id: string): string => REGIOES.find((r) => r.id === id)?.nome ?? id;

/** O catálogo de bairros com coordenada, como o servidor entregou no `kit`. */
export let BAIRROS: Regiao[] = [];

export function carregarBairros(lista: Regiao[]): void {
    BAIRROS = lista;
}

/** O bairro do catálogo mais perto do ponto — `null` sem catálogo carregado. */
export const bairroMaisProximo = (lat: number, lng: number): Regiao | null =>
    BAIRROS.reduce<Regiao | null>(
        (perto, b) =>
            perto === null || Math.hypot(lat - b.lat, lng - b.lng) < Math.hypot(lat - perto.lat, lng - perto.lng)
                ? b
                : perto,
        null,
    );

/**
 * O emoji do pino, pelo nome da atividade (o servidor não escolhe ícone). O
 * cadastro usa categorias ("Alimentos preparados", "Bebidas"…); a lista fina do
 * protótipo fica como segunda tentativa.
 */
const EMOJI_POR_PALAVRA: [RegExp, string][] = [
    [/acaraj|abar/, '🍢'],
    [/alimento|lanche|espet|chapa|milho/, '🍢'],
    [/cerveja|gelo/, '🍺'],
    [/bebida|água|agua|refri|caldo/, '🥤'],
    [/fruta|verdura/, '🍍'],
    [/artesan|fita/, '🪡'],
    [/vestu|acess|óculos|oculos|bijut|roupa/, '🕶️'],
    [/doce|cocada|sorvete|picol/, '🍦'],
];

const emojiDaAtividade = (atividade: string): string => {
    const nome = atividade.toLowerCase();

    return (
        EMOJI_POR_PALAVRA.find(([padrao]) => padrao.test(nome))?.[1] ??
        ATIVIDADES.find((a) => a.nome.toLowerCase() === nome)?.emoji ??
        '🧺'
    );
};

/** Os ambulantes com posição conhecida, como o servidor entregou no `kit`. */
export let AMBULANTES: Ambulante[] = [];

/** Os que têm prazo de retorno vencido — o mais antigo primeiro. */
export let RETORNOS_PENDENTES: Ambulante[] = [];

/** As vistorias recentes com GPS — a matéria do mapa de calor. */
export let INCIDENCIAS: IncidenciaCalor[] = [];

/** As operações abertas que chegam ao fiscal. */
export let OPERACOES: Operacao[] = [];

export function carregarCampo(dados: {
    ambulantes: (Omit<Ambulante, 'emoji'> & { emoji: string | null })[];
    incidencias: IncidenciaCalor[];
    operacoes: Operacao[];
}): void {
    AMBULANTES = dados.ambulantes.map((a) => ({ ...a, emoji: a.emoji ?? emojiDaAtividade(a.atividade) }));
    RETORNOS_PENDENTES = AMBULANTES.filter((a) => a.retornoHaDias !== null).sort(
        (a, b) => (b.retornoHaDias ?? 0) - (a.retornoHaDias ?? 0),
    );
    INCIDENCIAS = dados.incidencias;
    OPERACOES = dados.operacoes;
}

/* ------------------------------ Mapa de calor ------------------------------ */

export type PontoCalor = [lat: number, lng: number, peso: number];

export type IncidenciaCalor = {
    lat: number;
    lng: number;
    peso: number;
    /** Há quantos dias a incidência foi registrada — é o que o filtro recorta. */
    haDias: number;
    regiao: string;
};

export type ResumoRegiao = {
    id: string;
    nome: string;
    lat: number;
    lng: number;
    registros: number;
    fatia: number;
};

/** Ranking das regiões dentro da janela escolhida (7, 30 ou 90 dias). */
export const regioesMaisQuentes = (dias: number): ResumoRegiao[] => {
    const janela = INCIDENCIAS.filter((p) => p.haDias <= dias);
    const total = janela.length || 1;
    const contagem = new Map<string, number>();

    for (const ponto of janela) {
        contagem.set(ponto.regiao, (contagem.get(ponto.regiao) ?? 0) + 1);
    }

    return [...contagem.entries()]
        .map(([id, registros]) => {
            const doBairro = BAIRROS.find((b) => b.id === id);
            const pontos = janela.filter((p) => p.regiao === id);
            const centro = doBairro ?? {
                lat: pontos.reduce((t, p) => t + p.lat, 0) / pontos.length,
                lng: pontos.reduce((t, p) => t + p.lng, 0) / pontos.length,
            };

            return {
                id,
                nome: nomeRegiao(id),
                lat: centro.lat,
                lng: centro.lng,
                registros,
                fatia: Math.round((registros / total) * 100),
            };
        })
        .sort((a, b) => b.registros - a.registros)
        .slice(0, 5);
};

export const pontosDoCalor = (dias: number): PontoCalor[] =>
    INCIDENCIAS.filter((p) => p.haDias <= dias).map((p) => [p.lat, p.lng, p.peso]);

/* --------------------------------- Diversos --------------------------------- */

export const PRAZOS_RETORNO = [
    { id: 'amanha', rotulo: 'Amanhã', dias: 1 },
    { id: 'tres', rotulo: 'Em 3 dias', dias: 3 },
    { id: 'semana', rotulo: 'Em 1 semana', dias: 7 },
];

/**
 * Os DOIS documentos que o SEFAL lavra em campo — e são exatamente estes.
 *
 * O protótipo anterior chutava um par genérico ("Notificação de Termo" e "Auto
 * de Infração"). Os blocos de papel que o cliente entregou dizem outra coisa: o
 * que o agente preenche na calçada é a **Notificação Preliminar** (dá prazo
 * para sanar) e, quando recolhe material, o **Auto de Apreensão** (que manda os
 * bens para o SEGUB). Auto de Infração existe no fluxo da SEMOP, mas não é
 * documento de campo deste setor — não entra aqui.
 */
export const DOCUMENTOS_DE_CAMPO = [
    {
        id: 'np' as const,
        titulo: 'Notificação Preliminar',
        sigla: 'NP',
        descricao: 'Aponta as irregularidades e dá prazo para sanar, sob as penalidades previstas.',
        emoji: '📋',
    },
    {
        id: 'aa' as const,
        titulo: 'Auto de Apreensão',
        sigla: 'AA',
        descricao: 'Recolhe material e mercadoria, com guarda no SEGUB e prazo de permanência.',
        emoji: '📦',
    },
];

export const horaAgora = (): string =>
    new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });


export type Operacao = {
    id: string;
    nome: string;
    local: string;
    regiao: string;
    quando: string;
    horario: string;
    fiscais: number;
    tom: 'agora' | 'proxima' | 'planejada';
    observacao: string;
    /** O tipo da operação, como a Retaguarda o cadastrou. */
    tipo?: string | null;
    /** Quem abriu a operação na Retaguarda. */
    abertaPor?: string | null;
    /** Todos os bairros que a operação cobre (o cartão mostra só três). */
    bairros?: string[];
};




/**
 * Saudação pela hora do aparelho — o aplicativo abre falando com gente. A regra
 * das faixas tem UM dono (`lib/saudacao.ts`, o mesmo da Retaguarda): com a cópia
 * antiga, cortando em `hora < 12`, quem entrava às 3h recebia "Bom dia".
 */
export { saudacaoAgora as saudacao } from '../lib/saudacao';

/**
 * A data de hoje por extenso, com a primeira letra maiúscula.
 *
 * Calculada, não escrita: era uma data fixa, e o aplicativo abria dizendo "26 de
 * agosto" em qualquer dia que a demonstração acontecesse — ao lado de uma fila
 * com prazos de hoje.
 */
export const DATA_POR_EXTENSO = (() => {
    const texto = new Date().toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return texto.charAt(0).toUpperCase() + texto.slice(1);
})();

/** "há 1 dia" / "há 4 dias" — a quantidade é sempre conhecida, então a frase sai pronta. */
export const emDias = (dias: number): string => (dias === 1 ? 'há 1 dia' : `há ${dias} dias`);
