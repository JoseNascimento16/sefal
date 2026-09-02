/*
|------------------------------------------------------------------------------
| PROTÓTIPO — o vocabulário do módulo de Denúncias
|------------------------------------------------------------------------------
|
| ⚠️ Arquivo SEPARADO do `administrativo.ts` de propósito: aquele descreve o que
| o administrativo DIGITA (Caixa de Entrada) e a estrutura de áreas; este
| descreve o que chega por INTEGRAÇÃO das ouvidorias, que é outro fluxo, com
| outros estados e outros dois papéis. Misturar os dois faria um arquivo em que
| metade dos campos nunca se aplica — e é justamente essa distinção que o módulo
| existe para deixar clara.
|
| A `EquipeResumo` vem de lá porque é a MESMA coisa: o retorno de
| `EstruturaFicticia::equipes()`, uma fonte só. Copiá-la aqui daria dois donos ao
| mesmo formato.
|
| Os DADOS não moram aqui — moram em `config/prototipo_denuncias.php` e chegam
| pelo servidor, que é quem valida a escolha e deriva a área a partir do bairro.
| Uma segunda cópia no front discordaria dele no primeiro ajuste, e a tela
| ofereceria uma área que o servidor recusa.
|
| Quando o protótipo virar produção, este arquivo morre: os tipos passam a
| descrever o retorno real do controller.
|
*/

export type { EquipeResumo } from '@/dados-prototipo/administrativo';

/** Uma linha do trâmite: o rastro de cada ato, com autor e hora. */
export interface TramiteDenuncia {
    /** ISO com hora (`aaaa-mm-dd hh:mm`) — quem escreve dd/mm/aaaa é a tela. */
    em: string;
    quem: string;
    o_que: string;
    detalhe: string;
}

/** A área que o bairro sugere — com as outras que também o cobrem. */
export interface AreaSugerida {
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
 * O que o formato de um canal carrega. É o servidor que responde isto (a partir
 * de `config/prototipo_denuncias.php`), e não a tela: é a mesma informação que
 * governa a validação, e escrita nos dois lugares um dia divergiria — a tela
 * pediria CPF de denúncia anônima.
 */
export interface Canal {
    slug: string;
    nome: string;
    sistema: string;
    /** O artigo que combina com `sistema` — "o portal", "a central". */
    artigo: string;
    prefixo_origem: string;
    /** O canal admite denúncia anônima? O e-Salvador exige conta; o 156 não. */
    admite_anonima: boolean;
    tem_anexo: boolean;
    endereco_estruturado: boolean;
    prazo_em_dias: number;
    como_chega: string;
}

export interface Denuncia {
    id: number;
    /** Protocolo INTERNO do SEFAL (`DEN-NNNN`). */
    protocolo: string;
    canal: string;
    /** O número que o canal de origem deu à denúncia — a prova de que veio de fora. */
    protocolo_origem: string;
    recebida_em: string;
    /** Quando a integração ENTREGOU a denúncia, com hora. */
    recebida_em_hora: string;
    prazo: string;

    anonima: boolean;
    requerente: string | null;
    documento: string | null;
    email: string | null;
    telefone: string | null;

    assunto: string;
    relato: string;
    /** A categoria que o atendente do 156 escolheu (só no Fala Salvador). */
    categoria: string | null;
    /** Quem atendeu a ligação (só no Fala Salvador). */
    atendente: string | null;

    logradouro: string;
    numero: string | null;
    referencia: string | null;
    bairro: string;
    /** Endereço sem número nem referência confiável — muda o que dá para fiscalizar. */
    endereco_impreciso: boolean;

    /** Nomes dos arquivos que o cidadão anexou (só no e-Salvador). */
    anexos: string[];

    situacao: string;
    area: string | null;
    equipe: string | null;
    operacao: string | null;
    /** Por que o trabalho saiu da equipe da própria área. */
    justificativa_equipe: string | null;
    motivo: string | null;
    justificativa: string | null;
    destino: string | null;

    /** A área que o BAIRRO sugere — calculada na leitura, nunca gravada. */
    area_sugerida: AreaSugerida | null;
    tramites: TramiteDenuncia[];
}

/** Uma operação a que o gestor pode anexar denúncia. */
export interface Operacao {
    id: number;
    nome: string;
    area: string;
    equipe: string;
    periodo: string;
    foco: string;
}

/** As duas etapas do fluxo, cada uma com o seu dono. */
export type Etapa = 'triagem' | 'direcionamento';

/** As situações em que a denúncia espera a TRIAGEM do administrativo. */
export const AGUARDANDO_TRIAGEM = ['Recebida'];

/** As situações em que ela espera o DIRECIONAMENTO do gestor da área. */
export const AGUARDANDO_DIRECIONAMENTO = ['Encaminhada à área'];

/**
 * O tom do selo de cada situação.
 *
 * Recebida e Encaminhada à área EXIGEM ação de alguém, então são aviso e info —
 * cada uma chamando o seu dono. Direcionada, Em operação e Em campo são o
 * caminho normal andando. Concluída é fim bom; Devolvida e Arquivada são fim de
 * linha sem erro nenhum: são decisão tomada, com justificativa registrada.
 */
export const TOM_DA_SITUACAO: Record<string, string> = {
    Recebida: 'selo-aviso',
    'Encaminhada à área': 'selo-info',
    'Direcionada à equipe': 'selo-ok',
    'Em operação': 'selo-ok',
    'Em campo': 'selo-ok',
    Concluída: 'selo-neutro',
    Devolvida: 'selo-neutro',
    Arquivada: 'selo-neutro',
};
