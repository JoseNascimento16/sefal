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
    /** Nome do arquivo digitalizado — no protótipo, só o nome. */
    anexo: string | null;
    situacao: string;
    /** Preenchida quando a demanda foi encaminhada. */
    equipe: string | null;
    motivo: string | null;
    justificativa: string | null;
    destino: string | null;
    tramites: Tramite[];
}

/** Uma equipe como a Caixa de Entrada precisa dela para escolher o destino. */
export interface EquipeResumo {
    equipe: string;
    area: string;
    regiao: string;
    encarregado: string;
    recorte: Recorte;
    turno: string;
}

/** A equipe que o bairro sugere — com as outras áreas que também o cobrem. */
export interface Sugestao {
    equipe: string;
    area: string;
    regiao: string;
    encarregado: string;
    alternativas: {
        equipe: string;
        area: string;
        regiao: string;
        encarregado: string;
    }[];
}

/**
 * O tom do selo de cada situação.
 *
 * Aguardando triagem é o que EXIGE ação de quem abre a tela, então é aviso;
 * encaminhada é o caminho normal (ok); devolvida e arquivada são fim de linha —
 * neutro, porque não são erro: são decisão tomada.
 */
export const TOM_DA_SITUACAO: Record<string, string> = {
    'Aguardando triagem': 'selo-aviso',
    Encaminhada: 'selo-ok',
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
export interface ChefeDeSetor {
    nome: string;
    matricula: string | null;
}

export interface Area {
    id: number;
    nome: string;
    regiao: string;
    equipe: string;
    encarregado: string;
    /** `null` na área em que a estrutura ainda não registrou chefia nenhuma. */
    chefe_de_setor: ChefeDeSetor | null;
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
