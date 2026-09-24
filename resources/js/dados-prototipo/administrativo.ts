/*
|------------------------------------------------------------------------------
| PROTÓTIPO — o vocabulário compartilhado dos dois módulos administrativos
|------------------------------------------------------------------------------
|
| ⚠️ Este é o módulo ÚNICO do protótipo no front: tipos, rótulos e as facetas da
| busca das telas Caixa de Entrada e Áreas e Equipes.
|
| Os DADOS não moram aqui — moram em `config/prototipo_caixa_entrada.php` e
| `config/prototipo_estrutura.php`, e chegam pelo servidor. O motivo é a lei da
| fonte única: o servidor precisa dos mesmos dados para validar a escolha e para
| derivar a equipe a partir do bairro, e uma segunda cópia no front discordaria
| dela no primeiro ajuste — a tela ofereceria uma equipe que o servidor recusa.
|
| Quando o protótipo virar produção, este arquivo morre: os tipos passam a
| descrever o retorno real do controller.
|
*/

// ── Caixa de Entrada ────────────────────────────────────────────────────────

/** Uma linha do trâmite: o rastro do ato administrativo. */
export interface Tramite {
    /** ISO — quem escreve dd/mm/aaaa é a tela. */
    em: string;
    quem: string;
    o_que: string;
    detalhe: string;
}

export interface Demanda {
    id: number;
    /** Protocolo INTERNO da caixa (`CXE-NNNN`). */
    protocolo: string;
    origem: string;
    /** O número que vem impresso no papel do canal de origem. */
    documento_origem: string;
    recebida_em: string;
    prazo: string;
    /** Denúncia pode ser anônima: é a realidade do 156 e do e-Salvador. */
    anonima: boolean;
    requerente: string | null;
    contato: string | null;
    assunto: string;
    endereco: string;
    /** O bairro é o que SUGERE a equipe responsável. */
    bairro: string;
    descricao: string;
    /**
     * QUEM foi denunciado — nome de fachada e, quando se sabe, a pessoa ou razão
     * social por trás. É o que separa "o mesmo bar" de "dois estabelecimentos na
     * mesma rua", e por isso decide a pré-triagem.
     */
    estabelecimento: string;
    denunciado: string;
    documento_denunciado?: string | null;
    /** Nome do arquivo digitalizado — no protótipo, só o nome. */
    anexo: string | null;
    situacao: string;
    /** Preenchida quando a demanda foi encaminhada. */
    equipe: string | null;
    motivo: string | null;
    justificativa: string | null;
    destino: string | null;
    tramites: Tramite[];
    /**
     * O RETORNO ao canal de origem — o tipo que o canal pede e o que já foi
     * registrado (ver `components/retaguarda/retorno-ao-canal.tsx`).
     */
    /** A situação em três palavras (Recebida, Encaminhada ao líder, Em fiscalização…). */
    situacao_resumida: string;
    /** O ciclo já fechou para o canal? Decide a aba "Respondidas". */
    respondida: boolean;
    passou_por_fiscalizacao: boolean;
    /** Onde o retorno é feito — "e-Salvador", "e-Protocolo" —, ou nulo. */
    retorno_em: string | null;
    retorno_ao_canal: 'tramite' | 'processo' | null;
    resposta_ao_canal: {
        texto: string;
        em: string;
        por: string | null;
        processo: string | null;
        enviado: boolean;
    } | null;
    /**
     * As denúncias que este registro passou a responder.
     *
     * Só vem preenchida depois da pré-triagem: é o resultado dela. Quando tem
     * conteúdo, a resposta da fiscalização vale para todas elas.
     */
    agregadas?: { id: number; protocolo: string; assunto: string; requerente: string | null }[];
}

/** Uma equipe como a Caixa de Entrada precisa dela para escolher o destino. */
export interface EquipeResumo {
    equipe: string;
    area: string;
    regiao: string;
    /** O nome do documento do cliente — o que se mostra quando não há líder com conta. */
    encarregado: string;
    /** Quem RECEBE o encaminhamento: o líder da equipe (nome do usuário, ou o do documento). */
    lider: string;
    lider_matricula?: string | null;
    recorte: Recorte;
    turno: string;
}

/** A equipe que o bairro sugere — com as outras áreas que também o cobrem. */
export interface Sugestao {
    equipe: string;
    area: string;
    regiao: string;
    encarregado: string;
    lider: string;
    alternativas: {
        equipe: string;
        area: string;
        regiao: string;
        encarregado: string;
        lider: string;
    }[];
}

/**
 * O tom do selo de cada situação — as chaves são as do MODEL
 * (`Demanda::SITUACOES`), e não apelidos de tela.
 *
 * O que EXIGE ação de quem abre a tela é aviso (a leva crua, o que espera o
 * chefe, o prazo estourado); o caminho normal em andamento é ok; o que espera
 * outra mesa é info; e o fim de linha é neutro — devolvida e arquivada não são
 * erro, são decisão tomada.
 */
export const TOM_DA_SITUACAO: Record<string, string> = {
    'Em pré-triagem': 'selo-aviso',
    Recebida: 'selo-aviso',
    'Encaminhada ao líder': 'selo-info',
    'Direcionada aos fiscais': 'selo-ok',
    'Em operação': 'selo-ok',
    'Em campo': 'selo-ok',
    'Aguardando regularização': 'selo-info',
    'Retorno vencido': 'selo-aviso',
    Agrupada: 'selo-neutro',
    Concluída: 'selo-ok',
    Devolvida: 'selo-info',
    Arquivada: 'selo-neutro',
};

// ── Áreas e Equipes ─────────────────────────────────────────────────────────

/**
 * Como a área recorta a cidade. São TRÊS, e não um: tratar as oito áreas como
 * "bloco de bairros" faria a Noturna aparecer com zero bairros — leitura
 * invertida, já que ela cobre todos.
 */
export type Recorte = 'bairros' | 'corredores' | 'cidade';

export interface Fiscal {
    matricula: string;
    nome: string;
}

/**
 * O Chefe de Setor da área — quem responde por ela DENTRO do sistema.
 *
 * Não confundir com o `encarregado`, que chefia a equipe em campo: é o Chefe de
 * Setor que recebe a denúncia encaminhada à área, decide se ela vai a uma equipe
 * ou entra numa operação, e recebe de volta o que a equipe concluiu em campo.
 * `matricula` nula = a estrutura sabe o nome, mas essa pessoa ainda não tem acesso
 * ao sistema.
 */
export interface LiderDeEquipe {
    nome: string;
    /** `null` quando o encarregado do documento ainda não virou usuário do sistema. */
    matricula: string | null;
}

export interface Area {
    id: number;
    nome: string;
    regiao: string;
    equipe: string;
    encarregado: string;
    /**
     * O LÍDER DA EQUIPE — quem recebe o que o Chefe de Setor encaminha a esta
     * equipe. `null` quando o encarregado do documento ainda não tem conta.
     * (Até 22/09/2026 aqui ficava o "chefe de setor da área"; o chefe passou a
     * ser um só, e o vínculo por área deixou de existir.)
     */
    lider: LiderDeEquipe | null;
    recorte: Recorte;
    turno: string;
    fiscais: Fiscal[];
    bairros: string[];
    total_bairros: number;
    total_fiscais: number;
    /** Os bairros desta área que também pertencem a outra — aviso, não erro. */
    bairros_compartilhados: string[];
}

/** O que cada recorte quer dizer na tela — rótulo e explicação, num lugar só. */
export const RECORTES: Record<
    Recorte,
    { rotulo: string; unidade: string; explicacao: string }
> = {
    bairros: {
        rotulo: 'Bloco de bairros',
        unidade: 'bairro',
        explicacao: 'A equipe cobre os bairros listados abaixo.',
    },
    corredores: {
        rotulo: 'Corredores',
        unidade: 'corredor',
        explicacao:
            'A equipe percorre eixos de grande circulação, e não um bloco fechado de bairros.',
    },
    cidade: {
        rotulo: 'Cidade inteira, por turno',
        unidade: 'bairro',
        explicacao:
            'A equipe cobre todos os bairros de Salvador. O recorte dela é o TURNO, não a geografia.',
    },
};
