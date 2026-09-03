/* ============================================================================
   PROTÓTIPO — trabalho DIRIGIDO: as denúncias que a área direcionou à equipe
   ----------------------------------------------------------------------------
   Metade do serviço do fiscal chega empacotada: a ouvidoria entrega a denúncia
   ao SEFAL, o administrativo TRIA e a encaminha à ÁREA, e o GESTOR daquela área
   decide COMO o trabalho acontece — direciona à equipe, ou anexa a uma operação
   já planejada para a região. Só depois disso ela aparece aqui.

   São dois donos e duas etapas, e o aplicativo não participa de nenhuma das
   duas: o fiscal NUNCA vê denúncia em triagem. O que chega a ele é o que foi
   direcionado à equipe dele (ou entrou numa operação dela), mais o que ele já
   trabalhou, no estado em que ficou.

   ── A MESMA denúncia dos dois lados ─────────────────────────────────────────

   As denúncias abaixo são as MESMAS do módulo Denúncias da Retaguarda
   (`config/prototipo_denuncias.php`, branch `feature/prototipo-administrativo`):
   mesmo protocolo interno `DEN-NNNN`, mesmo número que o canal de origem deu,
   mesmo requerente, assunto, endereço, bairro, prazo, situação, área, equipe e
   operação — e, quando a denúncia já andou, o mesmo registro de campo e o mesmo
   documento lavrado. É o roteiro da demonstração: abrir DEN-00NN na Retaguarda
   e achar a mesma denúncia na fila do fiscal daquela equipe.

   ⚠️ FONTE ÚNICA — a dívida conhecida deste protótipo. Esta é uma SEGUNDA cópia
   dos mesmos dados, e duas cópias sempre divergem. Ela existe porque o
   aplicativo de campo roda SEM servidor: não há endpoint que entregue a fila.
   Enquanto for assim, mexer num lado exige mexer no outro — e o de-para foi
   deixado MECÂNICO, campo a campo:

     aplicativo (aqui)      Retaguarda (`config/prototipo_denuncias.php`)
     ─────────────────────  ──────────────────────────────────────────────
     protocolo              `DEN-%04d` do `id` (calculado, não escrito)
     documentoOrigem        protocolo_origem   (ESL-… / 156-…)
     origem                 canal              (`e-salvador` / `fala-salvador`)
     requerente             requerente         (null quando anônima)
     assunto                assunto
     detalhe                relato
     endereco               logradouro + numero
     referencia             referencia
     bairro                 bairro
     situacao               situacao           (o MESMO catálogo de 10)
     area / equipe          area / equipe
     operacao               operacao
     desfecho               o do ÚLTIMO passo do trâmite que declarou um
     campo                  o `campo` do último passo de vistoria/retorno
     documento              o `documento` do passo que o lavrou

   Quando o aplicativo falar com o servidor, este arquivo morre e a fila passa a
   vir de onde a Retaguarda lê. A redação dos impressos (motivos, penalidades,
   prazos) tem a mesma dívida em `dados-documentos.ts` ↔
   `config/prototipo_documentos_campo.php`, e lá a cópia que fica é a do
   servidor.

   ⚠️ AS DATAS SÃO RELATIVAS, e a aritmética é a MESMA do outro lado: recebimento
   é `agora − recebidaHaHoras`, prazo do canal é `agora + prazoDias`, e cada
   passo é `recebimento + haHoras`. É isso que faz as duas telas mostrarem a
   mesma data em qualquer dia que a demonstração aconteça — data fixa envelhece,
   e uma semana depois a fila apareceria inteira vencida.
   ============================================================================ */

import { PRAZOS_NP } from './dados-documentos';
import { EQUIPE } from './sessao';

/**
 * Os dois canais das ouvidorias — e eles não são o mesmo formulário.
 *
 * No e-Salvador o cidadão está autenticado no portal, então o requerente vem
 * sempre identificado e pode haver anexo. No Fala Salvador (Disque 156) o
 * relato é a transcrição do que o atendente ouviu, e a denúncia pode ser
 * anônima. A lista é fechada e é a mesma de `prototipo_denuncias.canais`.
 */
export type Origem = 'esalvador' | 'fala156';

export const ORIGENS: Record<
    Origem,
    { rotulo: string; curto: string; emoji: string; explica: string }
> = {
    esalvador: {
        rotulo: 'e-Salvador',
        curto: 'e-Salvador',
        emoji: '🖥️',
        explica:
            'Denúncia aberta no portal do município. Quem abre está autenticado, então o requerente vem identificado.',
    },
    fala156: {
        rotulo: 'Fala Salvador',
        curto: 'Fala 156',
        emoji: '📞',
        explica:
            'Denúncia recebida por telefone (Disque 156). O relato é a transcrição do atendimento, e pode ser anônima.',
    },
};

/**
 * As situações da denúncia, na ordem do fluxo — o MESMO catálogo fechado da
 * Retaguarda (`prototipo_denuncias.situacoes`).
 *
 * As quatro primeiras e as duas últimas existem aqui de propósito, mesmo o
 * fiscal não as vendo: é o que prova o recorte. Denúncia em triagem, devolvida
 * ou arquivada NÃO é trabalho de campo, e some da fila sem virar tarefa de
 * ninguém.
 */
export const SITUACOES = [
    'Recebida',
    'Encaminhada à área',
    'Direcionada à equipe',
    'Em operação',
    'Em campo',
    'Aguardando regularização',
    'Retorno vencido',
    'Concluída',
    'Devolvida',
    'Arquivada',
] as const;

export type Situacao = (typeof SITUACOES)[number];

/**
 * COMO a vistoria terminou — lista fechada, igual à da Retaguarda
 * (`prototipo_denuncias.desfechos`).
 *
 * Não é texto livre porque é o que o relatório soma: "quantas denúncias se
 * resolveram sem documento" é a pergunta que mede se a fiscalização está sendo
 * educativa. E é o desfecho que, do outro lado, fecha o passo do trâmite — se
 * as listas divergirem, o campo dirá uma coisa e a Retaguarda outra.
 */
export const DESFECHOS = [
    'Regularizado no local',
    'Nada encontrado no local',
    'Notificação Preliminar emitida',
    'Regularizado após notificação',
    'Retorno com a situação mantida',
    'Auto de Apreensão lavrado',
] as const;

export type Desfecho = (typeof DESFECHOS)[number];

/**
 * Os desfechos que cada ato pode produzir — dois recortes da MESMA lista, nunca
 * um vocabulário paralelo.
 *
 * A primeira vistoria não pode terminar em "regularizado após notificação": não
 * havia notificação. E o retorno não volta a "nada encontrado": ele existe para
 * dizer se o notificado cumpriu o prazo ou não. Oferecer os seis nos dois
 * momentos deixaria o fiscal registrar um desfecho que a Retaguarda não sabe
 * encaixar no trâmite.
 */
export const DESFECHOS_DE_VISTORIA: Desfecho[] = [
    'Regularizado no local',
    'Nada encontrado no local',
    'Notificação Preliminar emitida',
    'Auto de Apreensão lavrado',
];

export const DESFECHOS_DE_RETORNO: Desfecho[] = [
    'Regularizado após notificação',
    'Retorno com a situação mantida',
];

/**
 * O que cada desfecho quer dizer, em uma linha — o texto que o aplicativo
 * escreve embaixo da opção.
 *
 * O fiscal escolhe de pé, olhando para a barraca. "Regularizado após
 * notificação" e "Retorno com a situação mantida" são o mesmo ato com resultados
 * opostos, e a diferença entre eles decide se a denúncia encerra ou sobe ao
 * gestor: nomear sem explicar convidaria ao toque errado.
 */
export const DESFECHOS_EXPLICADOS: Record<Desfecho, string> = {
    'Regularizado no local': 'Orientou e a irregularidade cessou na hora. Sem documento.',
    'Nada encontrado no local': 'A equipe esteve no endereço e não constatou nada.',
    'Notificação Preliminar emitida': 'Aponta o que sanar e dá prazo. A Notificação é lavrada em seguida.',
    'Regularizado após notificação': 'O prazo foi cumprido: o ponto está resolvido. Encerra a denúncia.',
    'Retorno com a situação mantida': 'Prazo vencido e ponto igual. Volta ao gestor da área para a próxima medida.',
    'Auto de Apreensão lavrado': 'Recolhe material e mercadoria, com guarda no SEGUB.',
};

/**
 * Como o LOCAL fica depois de cada desfecho — a leitura curta do mapa e da
 * lista de registros ("regular" / "irregular").
 *
 * É DERIVADA, e não escolhida ao lado do desfecho: perguntar as duas coisas ao
 * fiscal deixaria ele registrar "Auto de Apreensão lavrado" num ponto marcado
 * como regular. Regular aqui quer dizer ponto LIBERADO — foi o que "regularizado
 * no local" e "nada encontrado" significam.
 */
export const LOCAL_APOS_O_DESFECHO: Record<Desfecho, 'regular' | 'irregular'> = {
    'Regularizado no local': 'regular',
    'Nada encontrado no local': 'regular',
    'Notificação Preliminar emitida': 'irregular',
    'Regularizado após notificação': 'regular',
    'Retorno com a situação mantida': 'irregular',
    'Auto de Apreensão lavrado': 'irregular',
};

/** O documento que cada desfecho obriga a lavrar — `null` quando não há papel. */
export const DOCUMENTO_DO_DESFECHO: Record<Desfecho, 'np' | 'aa' | null> = {
    'Regularizado no local': null,
    'Nada encontrado no local': null,
    'Notificação Preliminar emitida': 'np',
    'Regularizado após notificação': null,
    'Retorno com a situação mantida': null,
    'Auto de Apreensão lavrado': 'aa',
};

/**
 * O que cada situação cobra, e de quem — a frase que o aplicativo escreve
 * embaixo do selo.
 *
 * Sem ela o selo seria só uma palavra colorida: "Aguardando regularização" e
 * "Retorno vencido" parecem a mesma coisa, e são opostas — numa a bola está com
 * o notificado, na outra com o gestor da área.
 */
export const SITUACOES_EXPLICADAS: Record<Situacao, string> = {
    'Recebida': 'Esperando a triagem do administrativo. Não é trabalho de campo ainda.',
    'Encaminhada à área': 'Na mesa do gestor da área, que decide equipe ou operação.',
    'Direcionada à equipe': 'Vistoria dirigida à sua equipe. Ir ao local e registrar o desfecho.',
    'Em operação': 'Entra na varredura da operação, em vez de uma ida isolada ao local.',
    'Em campo': 'Vistoria aberta: relato e fotos registrados, desfecho ainda não decidido.',
    'Aguardando regularização': 'Prazo da Notificação correndo — a bola está com o notificado. A equipe volta ao vencer.',
    'Retorno vencido': 'Prazo vencido com a situação mantida. Voltou ao gestor da área para a próxima medida.',
    'Concluída': 'A vistoria teve desfecho e a denúncia se encerrou.',
    'Devolvida': 'A triagem devolveu ao canal de origem. Não chega ao campo.',
    'Arquivada': 'A triagem arquivou. Não chega ao campo.',
};

export const TOM_DA_SITUACAO: Record<Situacao, 'ok' | 'alerta' | 'perigo' | 'info' | 'neutro'> = {
    'Recebida': 'neutro',
    'Encaminhada à área': 'neutro',
    'Direcionada à equipe': 'info',
    'Em operação': 'info',
    'Em campo': 'alerta',
    'Aguardando regularização': 'alerta',
    'Retorno vencido': 'perigo',
    'Concluída': 'ok',
    'Devolvida': 'neutro',
    'Arquivada': 'neutro',
};

/**
 * As situações que chegam à MÃO DA EQUIPE — a régua do que o aplicativo mostra.
 *
 * As duas pontas importam. Sem esta lista o fiscal veria denúncia em triagem e
 * escolheria o próprio trabalho (é a razão de a Retaguarda não dar aquelas
 * telas a ele); e sem `Concluída`/`Retorno vencido` ele perderia de vista o que
 * a própria equipe já fez.
 */
export const SITUACOES_NA_MAO_DA_EQUIPE: Situacao[] = [
    'Direcionada à equipe',
    'Em operação',
    'Em campo',
    'Aguardando regularização',
    'Retorno vencido',
    'Concluída',
];

/** Ainda por fazer: a equipe precisa ir ao local (ou terminar a vistoria aberta). */
export const SITUACOES_A_VISTORIAR: Situacao[] = ['Direcionada à equipe', 'Em operação', 'Em campo'];

/**
 * A ficha da PERMISSÃO da SEMOP no SGCI — existe só para quem tem uma.
 *
 * Quem o sistema fiscaliza é o **ambulante**; ser **permissionário** é um
 * atributo dele. Esta ficha é a prova desse atributo: quando ela vem, o
 * ambulante é permissionário; quando não vem, ele trabalha sem permissão — que
 * é o caso da maioria, e não um cadastro faltando.
 *
 * ⚠️ Não há integração: o SGCI é o cadastro de comércio informal do município e
 * o aplicativo, hoje, não fala com ele. Aqui a ficha é DERIVADA do documento
 * lavrado na vistoria (inscrição, atividade e equipamento estão impressos nele),
 * e não inventada ao lado — inventá-la daria dois donos ao mesmo dado.
 */
export type FichaSgci = {
    inscricao: string;
    nome: string;
    atividade: string;
    equipamento: string;
    situacao: 'Ativo' | 'Suspenso' | 'Em análise';
    damEmDia: boolean;
    desde: string;
};

/** O que a equipe registrou no ponto — o passo de vistoria (ou de retorno). */
export type RegistroDeCampo = {
    /** Quando foi, em data BR — calculada do recebimento, como no outro lado. */
    quandoBr: string;
    encontrado: string;
    /** Quem estava no ponto, com a permissão quando havia. */
    ambulante: string | null;
    equipamento: string | null;
    relato: string;
    fotos: number;
    gps: string | null;
    precisaoM: number;
};

/** O documento lavrado em rua, do jeito que a fila precisa dele. */
export type DocumentoDeCampo = {
    tipo: 'np' | 'aa';
    numero: string;
    /** "NP 194903" — como o registro do turno o nomeia. */
    rotulo: string;
    lavradoBr: string;
    /** Prazo impresso na Notificação ("05 dias"); nulo no Auto de Apreensão. */
    prazoRotulo: string | null;
    /** Vencimento do prazo, em data BR — nulo quando o documento não dá prazo. */
    venceBr: string | null;
    /** Positivo = dias que faltam; negativo = dias vencidos; 0 = vence hoje. */
    venceEmDias: number | null;
    notificado: string;
    inscricao: string | null;
    atividade: string | null;
    equipamento: string | null;
    /** Chaves dos motivos assinalados — o texto mora em `dados-documentos.ts`. */
    motivos: string[];
    assinaturas: { rotulo: string; estado: 'assinada' | 'recusada' | 'pendente'; nome?: string }[];
};

export type Demanda = {
    id: string;
    /** Protocolo interno da denúncia (`DEN-NNNN`) — o mesmo dos dois lados. */
    protocolo: string;
    /** O número que o canal de origem deu (ESL-… no portal, 156-… no telefone). */
    documentoOrigem: string;
    origem: Origem;
    assunto: string;
    detalhe: string;
    endereco: string;
    referencia: string | null;
    bairro: string;
    /** Amarra a demanda ao mapa do protótipo — é a região onde o pino cai. */
    regiao: string;
    /** Data BR em que a integração entregou a denúncia ao SEFAL. */
    recebidaBr: string;
    prazoBr: string;
    /** Positivo = dias que faltam; negativo = dias vencidos; 0 = vence hoje. */
    prazoDias: number;
    situacao: Situacao;
    /** A área que a triagem escolheu — `null` enquanto ela não foi triada. */
    area: string | null;
    /** A equipe que o gestor escolheu — `null` até o direcionamento. */
    equipe: string | null;
    /** A operação a que a denúncia foi anexada, quando foi por esse caminho. */
    operacao: string | null;
    /** O desfecho herdado do último passo do trâmite que declarou um. */
    desfecho: Desfecho | null;
    campo: RegistroDeCampo | null;
    documento: DocumentoDeCampo | null;
    /** Denúncia pode ser anônima: é a realidade do 156. */
    anonima: boolean;
    /** Quem abriu — `null` quando anônima, e a tela escreve "Anônimo". */
    requerente: string | null;
    /**
     * Preenchida quando o ambulante É PERMISSIONÁRIO (tem permissão no SGCI).
     * Nula quando ele não tem permissão — resposta, não lacuna.
     */
    sgci: FichaSgci | null;
};

type SementeCampo = {
    haHoras: number;
    encontrado: string;
    ambulante: string | null;
    equipamento: string | null;
    relato: string;
    fotos: number;
    gps: string | null;
    precisaoM: number;
};

type SementeDocumento = {
    tipo: 'np' | 'aa';
    numero: string;
    haHoras: number;
    /** Chave do prazo do impresso ('48h', '5d'…) — o texto mora nos documentos. */
    prazo: string | null;
    notificado: string;
    inscricao: string | null;
    atividade: string | null;
    equipamento: string | null;
    motivos: string[];
    assinaturas: { rotulo: string; estado: 'assinada' | 'recusada' | 'pendente'; nome?: string }[];
};

type Semente = {
    /** O `id` da denúncia no outro lado — é dele que sai o protocolo. */
    id: number;
    canal: Origem;
    documentoOrigem: string;
    recebidaHaHoras: number;
    prazoDias: number;
    anonima: boolean;
    requerente: string | null;
    assunto: string;
    relato: string;
    endereco: string;
    referencia: string | null;
    bairro: string;
    regiao: string;
    situacao: Situacao;
    area: string | null;
    equipe: string | null;
    operacao: string | null;
    desfecho: Desfecho | null;
    campo: SementeCampo | null;
    documento: SementeDocumento | null;
};

/* ----------------------------------------------------------------------------
   O UNIVERSO — todas as denúncias, de todas as equipes, incluindo as que nunca
   chegam ao campo.
   ----------------------------------------------------------------------------
   O aplicativo guarda o universo e filtra na hora de mostrar. Guardar só as da
   equipe seria mais curto e esconderia o que a demonstração precisa provar: que
   existe recorte, que o fiscal não vê o serviço do vizinho, e que a denúncia em
   triagem — ou devolvida, ou arquivada — NÃO chega à rua.

   As situações avançadas trazem o registro de campo e o documento que a própria
   equipe produziu. Hoje eles são semeados dos dois lados; quando o aplicativo
   falar com o servidor, é ele que passa a acrescentar esses passos.

   A proporção é deliberadamente EDUCATIVA: a maioria dos casos termina sem
   documento — o fiscal orienta, o ambulante desmonta, e acabou. Amostra em que
   todo caso de campo termina em papel desenharia um sistema punitivo que não é
   o do cliente.
---------------------------------------------------------------------------- */

const UNIVERSO: Semente[] = [

    /* ---- Na mão de uma equipe: é isto que chega ao aplicativo ------------- */

    {
        id: 10,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114488',
        recebidaHaHoras: 196,
        prazoDias: 1,
        anonima: false,
        requerente: 'Ana Beatriz Carvalho Pinto',
        assunto: 'Venda de alimentos sem condição de higiene',
        relato: 'O ponto manipula salgados sem água corrente e descarta óleo na boca de lobo.',
        endereco: 'Avenida Vasco da Gama, 480',
        referencia: 'próximo ao acesso do estádio',
        bairro: 'Vasco da Gama',
        regiao: 'fora-da-area',
        situacao: 'Direcionada à equipe',
        area: 'Área 1',
        equipe: 'C2',
        operacao: null,
        desfecho: null,
        campo: null,
        documento: null,
    },

    {
        id: 11,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114402',
        recebidaHaHoras: 244,
        prazoDias: 2,
        anonima: false,
        requerente: 'Márcio Vinícius Torres',
        assunto: 'Barracas de praia além da faixa autorizada',
        relato: 'As barracas avançaram sobre a área de banho e não deixam corredor de acesso ao mar.',
        endereco: 'Avenida Otávio Mangabeira, s/n',
        referencia: 'trecho entre os postos 4 e 5',
        bairro: 'Piatã',
        regiao: 'piata',
        situacao: 'Em operação',
        area: 'Área 5',
        equipe: 'C1',
        operacao: 'Operação Verão — Orla',
        desfecho: null,
        campo: null,
        documento: null,
    },

    {
        id: 12,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114377',
        recebidaHaHoras: 268,
        prazoDias: 1,
        anonima: false,
        requerente: 'Heloísa Prates Damasceno',
        assunto: 'Ponto de venda bloqueando rampa de acessibilidade',
        relato: 'A banca ficou exatamente sobre a rampa da esquina, e cadeirantes não conseguem subir a '
            + 'calçada.',
        endereco: 'Rua Ruy Barbosa, 95',
        referencia: 'esquina com a Ladeira da Praça',
        bairro: 'Nazaré',
        regiao: 'fora-da-area',
        situacao: 'Em campo',
        area: 'Área 1',
        equipe: 'C2',
        operacao: null,
        desfecho: null,
        campo: {
            haHoras: 266,
            encontrado: 'Vistoria em andamento — equipe no local',
            ambulante: 'Nilton Sacramento de Jesus — não apresentou permissão',
            equipamento: 'Banca de madeira, com toldo',
            relato: 'Banca montada sobre o rebaixo da rampa de acessibilidade da esquina, ocupando a '
                + 'travessia inteira. O ocupante foi localizado, informou que não tem a permissão em mãos '
                + 'e pediu para buscá-la em casa. A equipe está medindo o rebaixo e conferindo o cadastro '
                + 'pelo aplicativo antes de registrar o desfecho.',
            fotos: 2,
            gps: '-12.9788, -38.5088',
            precisaoM: 8,
        },
        documento: null,
    },

    {
        id: 13,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114290',
        recebidaHaHoras: 340,
        prazoDias: -6,
        anonima: false,
        requerente: 'Cláudia Menezes Vilar',
        assunto: 'Equipamento abandonado ocupando ponto',
        relato: 'O quiosque está fechado há meses, tomado por lixo, e ninguém retira a estrutura.',
        endereco: 'Praça Nossa Senhora da Luz, s/n',
        referencia: 'canto da praça, perto do coreto',
        bairro: 'Rio Vermelho',
        regiao: 'fora-da-area',
        situacao: 'Concluída',
        area: 'Área 1',
        equipe: 'C2',
        operacao: null,
        desfecho: 'Auto de Apreensão lavrado',
        campo: {
            haHoras: 33,
            encontrado: 'Ponto irregular, sem ocupante no local',
            ambulante: null,
            equipamento: null,
            relato: 'Quiosque de alvenaria leve fechado com tapume, sem qualquer atividade. Acúmulo de lixo '
                + 'e entulho no entorno, com foco de água parada. Nenhum responsável apareceu durante a '
                + 'vistoria, e os comerciantes vizinhos informaram que o ponto está fechado há mais de '
                + 'seis meses. Não há permissão afixada no equipamento.',
            fotos: 3,
            gps: '-13.0106, -38.4917',
            precisaoM: 7,
        },
        documento: {
            tipo: 'aa',
            numero: '160051',
            haHoras: 34,
            prazo: null,
            notificado: 'Não identificado — equipamento abandonado',
            inscricao: null,
            atividade: 'Comércio Informal',
            equipamento: 'Quiosque',
            motivos: [],
            assinaturas: [
                { rotulo: 'Notificado', estado: 'pendente' },
            ],
        },
    },

    {
        id: 24,
        canal: 'fala156',
        documentoOrigem: '156-2026-888655',
        recebidaHaHoras: 142,
        prazoDias: 3,
        anonima: false,
        requerente: 'Antônio Sérgio Barbosa Filho',
        assunto: 'Ocupação da via em dia de evento',
        relato: 'Pede fiscalização no fim de semana do evento, quando a rua inteira é tomada por '
            + 'vendedores e nem ambulância consegue passar.',
        endereco: 'Rua Chile, s/n',
        referencia: 'trecho fechado para o evento',
        bairro: 'Comércio',
        regiao: 'fora-da-area',
        situacao: 'Em operação',
        area: 'Área 1',
        equipe: 'C2',
        operacao: 'Rotina Centro',
        desfecho: null,
        campo: null,
        documento: null,
    },

    {
        id: 25,
        canal: 'fala156',
        documentoOrigem: '156-2026-888590',
        recebidaHaHoras: 190,
        prazoDias: 2,
        anonima: false,
        requerente: 'Rosângela Muniz de Almeida',
        assunto: 'Depósito de mercadoria em área comum de mercado',
        relato: 'Relata caixas empilhadas no corredor do mercado, impedindo a circulação e a limpeza.',
        endereco: 'Mercado do Rio Vermelho, s/n',
        referencia: 'corredor dos fundos',
        bairro: 'Rio Vermelho',
        regiao: 'fora-da-area',
        situacao: 'Direcionada à equipe',
        area: 'Área 1',
        equipe: 'N1',
        operacao: null,
        desfecho: null,
        campo: null,
        documento: null,
    },

    {
        id: 27,
        canal: 'fala156',
        documentoOrigem: '156-2026-888470',
        recebidaHaHoras: 286,
        prazoDias: -5,
        anonima: false,
        requerente: 'Gilmar Teixeira dos Anjos',
        assunto: 'Ponto instalado em faixa de pedestre',
        relato: 'Relata que a banca ficou sobre a faixa de pedestre da esquina e ninguém atravessa com '
            + 'segurança.',
        endereco: 'Rua Cônego Pereira, 199',
        referencia: 'esquina da faixa de pedestre',
        bairro: 'São Caetano',
        regiao: 'fora-da-area',
        situacao: 'Concluída',
        area: 'Área 4',
        equipe: 'B2',
        operacao: null,
        desfecho: 'Regularizado no local',
        campo: {
            haHoras: 30,
            encontrado: 'Ponto irregular, com o ocupante presente',
            ambulante: 'Josenilda Barros da Conceição — permissão 2014/0882, regular',
            equipamento: null,
            relato: 'Banca de frutas montada sobre a faixa de pedestre da esquina, avançando cerca de um '
                + 'metro sobre a travessia. A permissionária tem permissão regular e ponto autorizado a '
                + 'oito metros dali, e havia deslocado a banca por causa de uma obra na calçada. '
                + 'Orientada quanto ao Art. 24 e à obrigação de manter a travessia livre.',
            fotos: 2,
            gps: '-12.9394, -38.4831',
            precisaoM: 11,
        },
        documento: null,
    },

    {
        id: 28,
        canal: 'fala156',
        documentoOrigem: '156-2026-888402',
        recebidaHaHoras: 334,
        prazoDias: -6,
        anonima: true,
        requerente: null,
        assunto: 'Mesa e cadeira na ciclovia',
        relato: 'Anônimo informa que as mesas do bar ocupam a ciclovia nos fins de semana.',
        endereco: 'Avenida Sete de Setembro, 1420',
        referencia: 'trecho da ciclovia',
        bairro: 'Avenida Sete de Setembro',
        regiao: 'fora-da-area',
        situacao: 'Em campo',
        area: 'Itinerante',
        equipe: 'I1',
        operacao: null,
        desfecho: null,
        campo: {
            haHoras: 332,
            encontrado: 'Vistoria em andamento — equipe no local',
            ambulante: 'Responsável pelo estabelecimento não localizado — atendimento no balcão informou que '
                + 'ele está a caminho',
            equipamento: 'Mesas e cadeiras em logradouro público',
            relato: 'Sete mesas dispostas sobre a ciclovia, com os ciclistas desviando para a pista de '
                + 'rolamento. A equipe está fotografando o trecho e medindo a faixa ocupada; aguarda o '
                + 'responsável para colher a identificação e o alvará antes de registrar o desfecho.',
            fotos: 3,
            gps: '-12.9770, -38.5165',
            precisaoM: 14,
        },
        documento: null,
    },

    {
        id: 29,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114930',
        recebidaHaHoras: 96,
        prazoDias: 4,
        anonima: false,
        requerente: 'Danielle Xavier Sampaio',
        assunto: 'Barraca de praia com puxada de madeira e depósito',
        relato: 'A barraca construiu uma puxada de madeira nos fundos, usada como depósito, e ampliou a '
            + 'área de mesas para além do que era antes. A passagem para a areia ficou estreita.',
        endereco: 'Avenida Otávio Mangabeira, 2140',
        referencia: 'em frente ao acesso da praia, ao lado do quiosque azul',
        bairro: 'Costa Azul',
        regiao: 'costa-azul',
        situacao: 'Aguardando regularização',
        area: 'Área 5',
        equipe: 'C1',
        operacao: 'Operação Verão — Orla',
        desfecho: 'Notificação Preliminar emitida',
        campo: {
            haHoras: 30,
            encontrado: 'Ponto irregular, com o permissionário presente',
            ambulante: 'Jailson Pereira dos Santos — permissão 2018/041.887, regular',
            equipamento: 'Barraca de chapa, com puxada de madeira nos fundos',
            relato: 'Puxada de madeira de aproximadamente 2 m × 3 m nos fundos da barraca, usada como '
                + 'depósito de bebidas e botijão, fora do padrão autorizado. Área de mesas avançando '
                + 'sobre a passagem de acesso à areia, que ficou com pouco mais de um metro. '
                + 'Permissionário presente, com permissão regular e DAM do exercício quitado, apresentado '
                + 'no local.',
            fotos: 3,
            gps: '-12.9821, -38.4306',
            precisaoM: 6,
        },
        documento: {
            tipo: 'np',
            numero: '194903',
            haHoras: 31,
            prazo: '5d',
            notificado: 'Jailson Pereira dos Santos',
            inscricao: '2018/041.887',
            atividade: 'Barraca de Chapa',
            equipamento: 'Barraca 12',
            motivos: ['puxada', 'padrao', 'mesas'],
            assinaturas: [
                { rotulo: 'Notificado', estado: 'assinada' },
                { rotulo: '1ª testemunha', estado: 'assinada', nome: 'Marivalda Souza Lima' },
                { rotulo: '2ª testemunha', estado: 'assinada', nome: 'Edson Ribeiro Costa' },
            ],
        },
    },

    {
        id: 30,
        canal: 'fala156',
        documentoOrigem: '156-2026-889120',
        recebidaHaHoras: 340,
        prazoDias: -4,
        anonima: false,
        requerente: 'Cleide Santana de Oliveira',
        assunto: 'Mesas e som em ponto de praia depois do horário',
        relato: 'Moradora informa que o ponto mantém mesas na calçada e caixa de som ligada bem depois '
            + 'do horário autorizado, e que já reclamou com o vendedor sem resultado. Deu o endereço '
            + 'e o horário: das 18h até depois da meia-noite.',
        endereco: 'Rua Doutor Nestor Duarte, 96',
        referencia: 'esquina com a orla, perto do posto salva-vidas',
        bairro: 'Itapuã',
        regiao: 'itapua',
        situacao: 'Retorno vencido',
        area: 'Área 5',
        equipe: 'C1',
        operacao: null,
        desfecho: 'Retorno com a situação mantida',
        campo: {
            haHoras: 130,
            encontrado: 'Ponto na mesma situação, com o ocupante presente',
            ambulante: null,
            equipamento: null,
            relato: 'Retorno após o vencimento das 48 horas. As mesas continuam na calçada, no mesmo '
                + 'número, e o alvará não foi apresentado. O ocupante informou que "não vai tirar". '
                + 'Registrada a reincidência para instruir a medida seguinte.',
            fotos: 2,
            gps: '-12.9507, -38.3607',
            precisaoM: 8,
        },
        documento: {
            tipo: 'np',
            numero: '194902',
            haHoras: 35,
            prazo: '48h',
            notificado: 'Roberto Cerqueira da Paixão',
            inscricao: '2011/007.412',
            atividade: 'Barraca de Chapa',
            equipamento: 'Barraca 04',
            motivos: ['mesas', 'alvara-mesas', 'horario'],
            assinaturas: [
                { rotulo: 'Notificado', estado: 'recusada' },
                { rotulo: '1ª testemunha', estado: 'assinada', nome: 'Ana Paula Trindade' },
                { rotulo: '2ª testemunha', estado: 'pendente' },
            ],
        },
    },

    {
        id: 31,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114865',
        recebidaHaHoras: 220,
        prazoDias: -1,
        anonima: false,
        requerente: 'Ubirajara Lopes de Andrade',
        assunto: 'Venda de bebida em ponto improvisado na orla',
        relato: 'Todos os fins de semana aparece um ponto improvisado com isopor e caixa de som na '
            + 'calçada da orla, vendendo bebida até de madrugada.',
        endereco: 'Rua João Gomes, 415',
        referencia: 'calçada da orla, altura da praça',
        bairro: 'Amaralina',
        regiao: 'fora-da-area',
        situacao: 'Concluída',
        area: 'Área 3',
        equipe: 'A2',
        operacao: null,
        desfecho: 'Nada encontrado no local',
        campo: {
            haHoras: 28,
            encontrado: 'Nada encontrado no local',
            ambulante: null,
            equipamento: null,
            relato: 'Calçada livre no endereço indicado, sem equipamento, isopor ou mercadoria. Nenhum '
                + 'vestígio de ocupação recente. Comerciantes do entorno confirmaram que o ponto aparece '
                + 'apenas em sábados e domingos, à noite.',
            fotos: 1,
            gps: '-13.0086, -38.4581',
            precisaoM: 12,
        },
        documento: null,
    },

    {
        id: 32,
        canal: 'fala156',
        documentoOrigem: '156-2026-888820',
        recebidaHaHoras: 400,
        prazoDias: -9,
        anonima: true,
        requerente: null,
        assunto: 'Mesas de bar ocupando a calçada inteira',
        relato: 'Denúncia anônima de que o bar espalha mesas por toda a calçada em frente, e quem passa '
            + 'com carrinho de bebê precisa descer para a rua. Informou a rua e o número.',
        endereco: 'Rua Silveira Martins, 1740',
        referencia: 'em frente à parada de ônibus',
        bairro: 'Cabula',
        regiao: 'fora-da-area',
        situacao: 'Concluída',
        area: 'Área 3',
        equipe: 'A2',
        operacao: null,
        desfecho: 'Regularizado após notificação',
        campo: {
            haHoras: 130,
            encontrado: 'Situação regularizada',
            ambulante: null,
            equipamento: null,
            relato: 'Retorno após o prazo da notificação. As mesas foram reduzidas a quatro, recuadas para '
                + 'junto da fachada, com faixa livre de aproximadamente 1,60 m para pedestre. O '
                + 'responsável apresentou o protocolo do pedido de Alvará para mesas e cadeiras, aberto '
                + 'no dia seguinte à notificação.',
            fotos: 2,
            gps: '-12.9649, -38.4401',
            precisaoM: 9,
        },
        documento: {
            tipo: 'np',
            numero: '194901',
            haHoras: 33,
            prazo: '72h',
            notificado: 'Genivaldo Alves Rodrigues',
            inscricao: null,
            atividade: 'Comércio Informal',
            equipamento: null,
            motivos: ['mesas', 'alvara-mesas'],
            assinaturas: [
                { rotulo: 'Notificado', estado: 'assinada' },
                { rotulo: '1ª testemunha', estado: 'assinada', nome: 'Jorge Luiz Menezes' },
                { rotulo: '2ª testemunha', estado: 'assinada', nome: 'Sandra Regina Alves' },
            ],
        },
    },

    {
        id: 33,
        canal: 'fala156',
        documentoOrigem: '156-2026-889090',
        recebidaHaHoras: 150,
        prazoDias: 3,
        anonima: false,
        requerente: 'Hildete Moura Vasconcelos',
        assunto: 'Carrinho de lanche fechando a entrada da garagem',
        relato: 'Moradora informa que o carrinho estaciona em frente ao portão da garagem no fim da '
            + 'tarde e ninguém consegue entrar nem sair com o carro. Deu o endereço e disse que já '
            + 'conversou com o vendedor.',
        endereco: 'Rua Territorial, 54',
        referencia: 'em frente ao portão da garagem do prédio',
        bairro: 'Barris',
        regiao: 'fora-da-area',
        situacao: 'Concluída',
        area: 'Área 1',
        equipe: 'C2',
        operacao: null,
        desfecho: 'Regularizado no local',
        campo: {
            haHoras: 26,
            encontrado: 'Ponto irregular, com o ambulante presente',
            ambulante: 'Ademir Batista dos Reis — sem permissão apresentada',
            equipamento: 'Carrinho de mão, com chapa e botijão',
            relato: 'Carrinho posicionado exatamente sobre o rebaixo do portão da garagem, impedindo a '
                + 'entrada de veículos. Ambulante presente, não apresentou permissão e informou que '
                + 'costuma montar ali por causa da tomada. Orientado quanto ao rebaixo de garagem e à '
                + 'necessidade de cadastro.',
            fotos: 2,
            gps: '-12.9862, -38.5136',
            precisaoM: 13,
        },
        documento: null,
    },

    {
        id: 34,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114945',
        recebidaHaHoras: 108,
        prazoDias: 5,
        anonima: false,
        requerente: 'Nívea Machado Sacramento',
        assunto: 'Trailer de lanches com mercadoria no chão e sem condição de higiene',
        relato: 'O trailer guarda caixas de refrigerante e engradados de cerveja no chão da calçada, do '
            + 'lado de fora, e manipula os lanches sem água encanada. À noite vende bebida em mesas '
            + 'improvisadas.',
        endereco: 'Largo da Ribeira, 212',
        referencia: 'em frente ao terminal marítimo',
        bairro: 'Ribeira',
        regiao: 'fora-da-area',
        situacao: 'Concluída',
        area: 'Área 2',
        equipe: 'A1',
        operacao: null,
        desfecho: 'Regularizado após notificação',
        campo: {
            haHoras: 52,
            encontrado: 'Situação regularizada',
            ambulante: null,
            equipamento: null,
            relato: 'Retorno dentro das 24 horas. A mercadoria está guardada dentro do equipamento, a '
                + 'calçada está livre, e o trailer passou a ser abastecido por ponto de água com '
                + 'escoamento ligado à rede. As mesas foram retiradas da via e a venda de bebida '
                + 'alcoólica cessou.',
            fotos: 2,
            gps: '-12.9211, -38.5073',
            precisaoM: 8,
        },
        documento: {
            tipo: 'np',
            numero: '194904',
            haHoras: 31,
            prazo: '24h',
            notificado: 'Edvaldo Nunes de Araújo',
            inscricao: '2016/023.114',
            atividade: 'Trailer de Lanches',
            equipamento: 'Trailer 09',
            motivos: ['higiene', 'produtos-fora', 'bebida'],
            assinaturas: [
                { rotulo: 'Notificado', estado: 'assinada' },
                { rotulo: '1ª testemunha', estado: 'assinada', nome: 'Valdomiro Santos Lima' },
                { rotulo: '2ª testemunha', estado: 'assinada', nome: 'Neuza Maria de Jesus' },
            ],
        },
    },

    {
        id: 35,
        canal: 'fala156',
        documentoOrigem: '156-2026-889210',
        recebidaHaHoras: 40,
        prazoDias: 8,
        anonima: false,
        requerente: 'Marluce Andrade Pinho',
        assunto: 'Carrinho de lanche em frente à escola, com fila na pista',
        relato: 'Informa que o carrinho atende em frente ao portão da escola e a fila avança para a '
            + 'pista, no horário da saída das crianças. Diz também que o freezer é ligado num poste '
            + 'da rua e que o equipamento não tem adesivo da Prefeitura.',
        endereco: 'Rua Direta de Sussuarana, 380',
        referencia: 'em frente à escola municipal',
        bairro: 'Sussuarana',
        regiao: 'fora-da-area',
        situacao: 'Aguardando regularização',
        area: 'Área 6',
        equipe: 'B1',
        operacao: 'Operação Volta às Aulas — Cajazeiras',
        desfecho: 'Notificação Preliminar emitida',
        campo: {
            haHoras: 21,
            encontrado: 'Ponto irregular, com o permissionário presente',
            ambulante: 'Josivaldo Ramos da Purificação — permissão 2013/016.209, com pendência',
            equipamento: 'Carrinho de lanche, com freezer',
            relato: 'Carrinho posicionado em frente ao portão da escola, com a fila de atendimento '
                + 'avançando para a pista de rolamento. Freezer ligado por extensão a um poste da via. A '
                + 'consulta no aplicativo mostrou o DAM do exercício em aberto e a cota de despesa de '
                + 'água e luz sem pagamento há quatro meses. O permissionário não soube informar em que '
                + 'setor está o cadastro dele.',
            fotos: 3,
            gps: '-12.9337, -38.4194',
            precisaoM: 11,
        },
        documento: {
            tipo: 'np',
            numero: '194905',
            haHoras: 22,
            prazo: '10d',
            notificado: 'Josivaldo Ramos da Purificação',
            inscricao: '2013/016.209',
            atividade: 'Carrinho de Lanche',
            equipamento: 'Carrinho 07',
            motivos: ['comparecer', 'dam', 'cota'],
            assinaturas: [
                { rotulo: 'Notificado', estado: 'assinada' },
                { rotulo: '1ª testemunha', estado: 'assinada', nome: 'Cícero Barbosa dos Santos' },
                { rotulo: '2ª testemunha', estado: 'pendente' },
            ],
        },
    },

    {
        id: 36,
        canal: 'fala156',
        documentoOrigem: '156-2026-889055',
        recebidaHaHoras: 200,
        prazoDias: 2,
        anonima: true,
        requerente: null,
        assunto: 'Mercadoria em lona no chão bloqueando a calçada',
        relato: 'Anônimo informa que o vendedor estende uma lona com mercadoria na calçada em frente ao '
            + 'prédio de consultórios, e quem chega de cadeira de rodas ou com criança tem de descer '
            + 'para a rua.',
        endereco: 'Avenida Joana Angélica, 1080',
        referencia: 'em frente ao prédio de consultórios',
        bairro: 'Avenida Joana Angélica',
        regiao: 'fora-da-area',
        situacao: 'Concluída',
        area: 'Itinerante',
        equipe: 'I1',
        operacao: null,
        desfecho: 'Regularizado no local',
        campo: {
            haHoras: 29,
            encontrado: 'Ponto irregular, com o ambulante presente',
            ambulante: 'Genésio Almeida Ferraz — sem permissão apresentada',
            equipamento: 'Lona no chão, com mercadoria de armarinho',
            relato: 'Lona de aproximadamente 3 m estendida no meio da calçada, com a mercadoria disposta no '
                + 'chão, deixando cerca de meio metro de passagem junto ao muro. Ambulante presente, não '
                + 'apresentou permissão e informou que vende ali há duas semanas. Orientado quanto à '
                + 'faixa livre mínima para pedestre e à necessidade de cadastro.',
            fotos: 2,
            gps: '-12.9835, -38.5100',
            precisaoM: 12,
        },
        documento: null,
    },

    {
        id: 37,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114912',
        recebidaHaHoras: 250,
        prazoDias: -1,
        anonima: false,
        requerente: 'Reinaldo Bispo dos Santos',
        assunto: 'Barraca montada dentro do abrigo do ponto de ônibus',
        relato: 'A barraca ocupou o abrigo do ponto de ônibus por dentro, e quem espera o coletivo fica '
            + 'na chuva, do lado de fora. Idosos ficam de pé na calçada.',
        endereco: 'Largo de Pirajá, s/n',
        referencia: 'no abrigo do ponto de ônibus, sentido Centro',
        bairro: 'Pirajá',
        regiao: 'fora-da-area',
        situacao: 'Concluída',
        area: 'Área 4',
        equipe: 'B2',
        operacao: null,
        desfecho: 'Regularizado no local',
        campo: {
            haHoras: 27,
            encontrado: 'Ponto irregular, com a permissionária presente',
            ambulante: 'Marinalva Conceição de Souza — permissão 2019/031.560, regular',
            equipamento: 'Barraca de chapa, montada sob o abrigo',
            relato: 'Barraca montada dentro do abrigo do ponto de ônibus, ocupando os dois bancos e '
                + 'obrigando quem espera o coletivo a ficar na calçada. A permissionária tem permissão '
                + 'regular e ponto autorizado a doze metros dali, na mesma calçada, e havia se abrigado '
                + 'ali por causa da chuva da semana. Orientada quanto ao uso do mobiliário urbano e ao '
                + 'ponto autorizado.',
            fotos: 2,
            gps: '-12.8862, -38.4571',
            precisaoM: 10,
        },
        documento: null,
    },

    {
        id: 38,
        canal: 'fala156',
        documentoOrigem: '156-2026-888975',
        recebidaHaHoras: 310,
        prazoDias: -3,
        anonima: false,
        requerente: 'Tereza Cristina Lopes Bahia',
        assunto: 'Ponto de bebida na calçada com som alto de madrugada',
        relato: 'Moradora informa que um ponto de bebida monta na calçada nas noites de sexta, com '
            + 'caixa de som ligada até de madrugada, e que ele desmonta antes de amanhecer. Deu o '
            + 'endereço e o horário: das 22h até por volta das 4h.',
        endereco: 'Alameda Praia de Guaratuba, 120',
        referencia: 'na esquina com a orla',
        bairro: 'Stella Maris',
        regiao: 'stella-maris',
        situacao: 'Concluída',
        area: 'Área 5',
        equipe: 'N1',
        operacao: null,
        desfecho: 'Nada encontrado no local',
        campo: {
            haHoras: 44,
            encontrado: 'Nada encontrado no local',
            ambulante: null,
            equipamento: null,
            relato: 'Ida na sexta, entre 23h e 0h30, no dia e no horário indicados pela denunciante. '
                + 'Calçada livre, sem equipamento, isopor, mesa ou caixa de som, e sem vestígio de '
                + 'ocupação recente. Os porteiros dos dois prédios da esquina informaram que o ponto '
                + 'deixou de montar há cerca de três semanas, depois de o vendedor se mudar do bairro.',
            fotos: 1,
            gps: '-12.9403, -38.3373',
            precisaoM: 15,
        },
        documento: null,
    },

    /* ---- Triada para fora, ou ainda na triagem: NÃO chega ao campo -------
       Ficam guardadas para a fila poder PROVAR o recorte: uma delas é da
       própria Área 5 e ainda espera o gestor, e as outras duas a triagem
       recusou. Nenhuma aparece para o fiscal. ----------------------------- */

    {
        id: 6,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114688',
        recebidaHaHoras: 76,
        prazoDias: 3,
        anonima: false,
        requerente: 'Alfredo Peixoto Guimarães',
        assunto: 'Ponto de venda de água de coco em canteiro central',
        relato: 'A banca foi montada no canteiro central da avenida, e os clientes atravessam a pista '
            + 'correndo para comprar. Já houve quase atropelamento.',
        endereco: 'Avenida Luís Viana Filho, s/n',
        referencia: 'canteiro central, altura do viaduto',
        bairro: 'Mussurunga',
        regiao: 'mussurunga',
        situacao: 'Encaminhada à área',
        area: 'Área 5',
        equipe: null,
        operacao: null,
        desfecho: null,
        campo: null,
        documento: null,
    },

    {
        id: 14,
        canal: 'esalvador',
        documentoOrigem: 'ESL-2026-114255',
        recebidaHaHoras: 364,
        prazoDias: -7,
        anonima: false,
        requerente: 'Fernando Aguiar Bittencourt',
        assunto: 'Obra em imóvel sem licença',
        relato: 'Estão levantando um segundo pavimento no imóvel da esquina, sem placa de obra nem '
            + 'licença à vista.',
        endereco: 'Rua Aristides Novis, 58',
        referencia: 'esquina com a Politécnica',
        bairro: 'Federação',
        regiao: 'fora-da-area',
        situacao: 'Devolvida',
        area: null,
        equipe: null,
        operacao: null,
        desfecho: null,
        campo: null,
        documento: null,
    },

    {
        id: 16,
        canal: 'fala156',
        documentoOrigem: '156-2026-889011',
        recebidaHaHoras: 5,
        prazoDias: 10,
        anonima: false,
        requerente: 'Josivaldo Ramos Cerqueira',
        assunto: 'Carrinho de lanche em ponto de ônibus',
        relato: 'Morador informa que o carrinho para exatamente no abrigo do ponto de ônibus no fim da '
            + 'tarde, e quem espera fica na chuva. Deu o endereço da esquina e o horário: das 17h às '
            + '23h.',
        endereco: 'Rua Doutor José Peroba, 275',
        referencia: 'abrigo do ponto de ônibus',
        bairro: 'Stiep',
        regiao: 'stiep',
        situacao: 'Encaminhada à área',
        area: 'Área 5',
        equipe: null,
        operacao: null,
        desfecho: null,
        campo: null,
        documento: null,
    },

    {
        id: 26,
        canal: 'fala156',
        documentoOrigem: '156-2026-888511',
        recebidaHaHoras: 238,
        prazoDias: -3,
        anonima: true,
        requerente: null,
        assunto: 'Venda de produto de origem desconhecida',
        relato: 'Anônimo diz que a banca vende celular usado sem nota, mas não soube informar rua, '
            + 'número nem ponto de referência — apenas "no bairro".',
        endereco: 'Sem indicação',
        referencia: null,
        bairro: 'São Marcos',
        regiao: 'fora-da-area',
        situacao: 'Arquivada',
        area: null,
        equipe: null,
        operacao: null,
        desfecho: null,
        campo: null,
        documento: null,
    },

];

/* -------------------------------- As contas -------------------------------- */

/**
 * O instante de N horas atrás.
 *
 * É a MESMA conta do outro lado (`now()->subHours(...)`), e é ela que faz o
 * recebimento, o passo da vistoria e o vencimento do prazo caírem na mesma data
 * nas duas telas. Em horas, e não em dias: arredondar para dias faria a data
 * pular um dia em relação à Retaguarda em metade dos horários.
 */
const agoraMenos = (horas: number): Date => new Date(Date.now() - horas * 3_600_000);

const brDe = (data: Date): string => data.toLocaleDateString('pt-BR');

/**
 * Data BR de hoje somada de N dias — o prazo do canal (`now()->addDays(...)`).
 *
 * Meio-dia: somar dias sobre a meia-noite erra na virada do horário de verão, e
 * um dia a menos numa data de prazo é um erro que aparece.
 */
const dataDaqui = (dias: number): string => {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + dias);

    return brDe(d);
};

/** Quantos dias de CALENDÁRIO separam a data de hoje (negativo = passado). */
const diasAte = (data: Date): number => {
    const alvo = new Date(data);
    alvo.setHours(12, 0, 0, 0);

    const hoje = new Date();
    hoje.setHours(12, 0, 0, 0);

    return Math.round((alvo.getTime() - hoje.getTime()) / 86_400_000);
};

const montarCampo = (s: SementeCampo, recebidaHaHoras: number): RegistroDeCampo => ({
    quandoBr: brDe(agoraMenos(recebidaHaHoras - s.haHoras)),
    encontrado: s.encontrado,
    ambulante: s.ambulante,
    equipamento: s.equipamento,
    relato: s.relato,
    fotos: s.fotos,
    gps: s.gps,
    precisaoM: s.precisaoM,
});

const montarDocumento = (s: SementeDocumento, recebidaHaHoras: number): DocumentoDeCampo => {
    const lavradoEm = agoraMenos(recebidaHaHoras - s.haHoras);

    /* O prazo vem do impresso, por CHAVE: a redação e a quantidade de dias moram
       em `dados-documentos.ts`, que é o espelho do bloco de papel. Escrever "05
       dias" aqui daria dois donos ao mesmo texto. O Auto de Apreensão não dá
       prazo de regularização — o prazo dele é o da guarda dos bens no SEGUB. */
    const prazo = s.tipo === 'np' ? (PRAZOS_NP.find((p) => p.id === s.prazo) ?? null) : null;
    const vence = prazo && prazo.dias > 0 ? new Date(lavradoEm.getTime() + prazo.dias * 86_400_000) : null;

    return {
        tipo: s.tipo,
        numero: s.numero,
        rotulo: `${s.tipo === 'np' ? 'NP' : 'AA'} ${s.numero}`,
        lavradoBr: brDe(lavradoEm),
        prazoRotulo: prazo?.rotulo ?? null,
        venceBr: vence ? brDe(vence) : null,
        venceEmDias: vence ? diasAte(vence) : null,
        notificado: s.notificado,
        inscricao: s.inscricao,
        atividade: s.atividade,
        equipamento: s.equipamento,
        motivos: s.motivos,
        assinaturas: s.assinaturas,
    };
};

/**
 * A ficha do SGCI DERIVADA do documento lavrado.
 *
 * A inscrição, a atividade e o equipamento estão impressos no documento — quem
 * tem inscrição é permissionário, e é isso que a ficha diz. O ano da permissão
 * sai da própria inscrição (`2018/041.887`), que é como o cliente a numera.
 * Escrever a ficha ao lado do documento faria a mesma informação ter dois donos.
 */
const fichaDoDocumento = (s: SementeDocumento | null): FichaSgci | null => {
    if (!s || !s.inscricao) {
        return null;
    }

    return {
        inscricao: s.inscricao,
        nome: s.notificado,
        atividade: s.atividade ?? 'Comércio Informal',
        equipamento: s.equipamento ?? 'Não informado',
        situacao: 'Ativo',
        damEmDia: true,
        desde: s.inscricao.slice(0, 4),
    };
};

const montar = (s: Semente): Demanda => ({
    id: `den-${String(s.id).padStart(4, '0')}`,
    protocolo: `DEN-${String(s.id).padStart(4, '0')}`,
    documentoOrigem: s.documentoOrigem,
    origem: s.canal,
    assunto: s.assunto,
    detalhe: s.relato,
    endereco: s.endereco,
    referencia: s.referencia,
    bairro: s.bairro,
    regiao: s.regiao,
    recebidaBr: brDe(agoraMenos(s.recebidaHaHoras)),
    prazoBr: dataDaqui(s.prazoDias),
    prazoDias: s.prazoDias,
    situacao: s.situacao,
    area: s.area,
    equipe: s.equipe,
    operacao: s.operacao,
    desfecho: s.desfecho,
    campo: s.campo ? montarCampo(s.campo, s.recebidaHaHoras) : null,
    documento: s.documento ? montarDocumento(s.documento, s.recebidaHaHoras) : null,
    anonima: s.anonima,
    requerente: s.requerente,
    sgci: fichaDoDocumento(s.documento),
});

/** O universo inteiro, de todas as equipes e de todas as etapas. */
export const DEMANDAS: Demanda[] = UNIVERSO.map(montar);

/**
 * A demanda pelo id, procurada no universo INTEIRO.
 *
 * De propósito não filtra por equipe: um registro antigo do aparelho pode
 * apontar para uma denúncia que hoje não está mais na fila, e a tela de detalhe
 * precisa continuar abrindo — sumir sem explicação é pior do que mostrar.
 */
export const acharDemanda = (id: string | null): Demanda | null =>
    (id && DEMANDAS.find((d) => d.id === id)) || null;

/**
 * A ordem da fila: primeiro o que cobra ATO DO FISCAL, depois o que espera
 * outra pessoa, e por fim o que já se encerrou. Dentro de cada grupo, por prazo.
 *
 * Ordenar só por prazo — como antes — misturaria uma denúncia concluída na
 * semana passada com a vistoria que a equipe tem de fazer hoje.
 */
const PESO_DA_SITUACAO: Record<Situacao, number> = {
    'Em campo': 0,
    'Direcionada à equipe': 1,
    'Em operação': 2,
    'Aguardando regularização': 3,
    'Retorno vencido': 4,
    'Concluída': 5,
    'Recebida': 9,
    'Encaminhada à área': 9,
    'Devolvida': 9,
    'Arquivada': 9,
};

const naOrdemDaFila = (a: Demanda, b: Demanda): number =>
    PESO_DA_SITUACAO[a.situacao] - PESO_DA_SITUACAO[b.situacao] || a.prazoDias - b.prazoDias;

/**
 * O que chega ao campo: a denúncia da MINHA EQUIPE, já na mão dela.
 *
 * As duas condições são a régua inteira. Sem a primeira, o fiscal veria o
 * serviço do vizinho e não entenderia por que um endereço fora da área dele
 * está na lista; sem a segunda, veria denúncia em triagem — e escolher o próprio
 * trabalho, ou arquivar o que não quisesse atender, é exatamente o que a
 * Retaguarda não lhe dá.
 */
export const demandasDaEquipe = (): Demanda[] =>
    DEMANDAS.filter(
        (d) => d.equipe === EQUIPE.codigo && SITUACOES_NA_MAO_DA_EQUIPE.includes(d.situacao),
    ).sort(naOrdemDaFila);

/** O que ainda pede ida ao local (ou o fim de uma vistoria já aberta). */
export const demandasAVistoriar = (): Demanda[] =>
    demandasDaEquipe().filter((d) => SITUACOES_A_VISTORIAR.includes(d.situacao));

/** Notificação lavrada, prazo correndo: a equipe volta quando ele vencer. */
export const demandasEmRegularizacao = (): Demanda[] =>
    demandasDaEquipe().filter((d) => d.situacao === 'Aguardando regularização');

/** O que a equipe já fechou — e o que subiu ao gestor por retorno frustrado. */
export const demandasEncerradas = (): Demanda[] =>
    demandasDaEquipe().filter((d) => d.situacao === 'Concluída' || d.situacao === 'Retorno vencido');

/** Vencidas são as que ainda pedem ato do fiscal — encerrada não vence mais. */
export const demandasVencidas = (): Demanda[] =>
    demandasAVistoriar().filter((d) => d.prazoDias < 0);

/** Quantas foram para OUTRAS equipes — o recorte, em número. */
export const demandasDeOutrasEquipes = (): number =>
    DEMANDAS.filter(
        (d) => d.equipe !== EQUIPE.codigo && SITUACOES_NA_MAO_DA_EQUIPE.includes(d.situacao),
    ).length;

/** Quantas o aplicativo guarda e NÃO mostra: triagem, devolução, arquivamento. */
export const demandasForaDoCampo = (): number =>
    DEMANDAS.filter((d) => !SITUACOES_NA_MAO_DA_EQUIPE.includes(d.situacao)).length;

export const podeVistoriar = (d: Demanda): boolean => SITUACOES_A_VISTORIAR.includes(d.situacao);

export const podeRegistrarRetorno = (d: Demanda): boolean =>
    d.situacao === 'Aguardando regularização';

/** Os desfechos que o ato em curso pode produzir — vistoria ou retorno. */
export const desfechosOferecidos = (d: Demanda | null): Desfecho[] =>
    d && podeRegistrarRetorno(d) ? DESFECHOS_DE_RETORNO : DESFECHOS_DE_VISTORIA;

/** "Anônimo" é resposta, não espaço em branco: é a realidade do 156. */
export const rotuloDoRequerente = (d: Demanda): string =>
    d.anonima ? 'Anônimo' : (d.requerente ?? 'Não informado');

/** A frase do prazo, pronta — sem plural preguiçoso e sem conta na tela. */
export const prazoEmPalavras = (dias: number): string => {
    if (dias < -1) {
        return `vencida há ${-dias} dias`;
    }

    if (dias === -1) {
        return 'vencida há 1 dia';
    }

    if (dias === 0) {
        return 'vence hoje';
    }

    return dias === 1 ? 'vence amanhã' : `vence em ${dias} dias`;
};

/** A frase do prazo do DOCUMENTO — quem vence aqui é o prazo do notificado. */
export const prazoDoRetornoEmPalavras = (dias: number): string => {
    if (dias < -1) {
        return `prazo vencido há ${-dias} dias`;
    }

    if (dias === -1) {
        return 'prazo vencido há 1 dia';
    }

    if (dias === 0) {
        return 'prazo vence hoje';
    }

    return dias === 1 ? 'prazo vence amanhã' : `prazo vence em ${dias} dias`;
};

export const tomDoPrazo = (dias: number): 'perigo' | 'alerta' | 'info' =>
    dias < 0 ? 'perigo' : dias <= 1 ? 'alerta' : 'info';

/* A equipe e a estrutura moram nos módulos deles; reexportar aqui evita que as
   telas que já liam a fila tenham de aprender dois caminhos novos. */
export { AREAS_SEFAL } from './dados-equipes';
export { EQUIPE, FISCAL } from './sessao';
