/*
|------------------------------------------------------------------------------
| PROTÓTIPO — o vocabulário do módulo de Denúncias
|------------------------------------------------------------------------------
|
| ⚠️ Arquivo SEPARADO do `administrativo.ts` de propósito: aquele descreve o que
| o coordenador DIGITA (Caixa de Entrada) e a estrutura de áreas; este
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

/** Um par rótulo/valor — o formato de qualquer ficha de leitura deste módulo. */
export interface CampoLido {
    rotulo: string;
    valor: string;
}

/**
 * O que o fiscal registrou EM CAMPO num passo do trâmite.
 *
 * `gps` vem sempre acompanhado da `precisao_m` porque um ponto ruim é pior que
 * um ponto ausente disfarçado de bom — a lei do domínio que vale no aplicativo
 * vale na leitura aqui.
 */
export interface RegistroDeCampo {
    /** O que a equipe encontrou, em uma linha ("Ponto irregular", "Nada encontrado…"). */
    encontrado: string | null;
    relato: string;
    fotos: string[];
    gps: string | null;
    precisao_m: number | null;
    /** Quem estava no ponto, quando havia alguém. */
    ambulante: string | null;
    equipamento: string | null;
}

/**
 * O DOCUMENTO lavrado em campo, para leitura.
 *
 * A forma é a do papel — número, campos na ordem do impresso, listas de caixas
 * assinaladas, assinaturas com o estado de cada uma —, a mesma que o aplicativo
 * do fiscal usa para montar a via impressa. A Retaguarda **só lê**: ela não
 * emite documento de campo, e por isso não há aqui nada de formulário.
 *
 * ⚠️ `emitido_em` e `vence_em` chegam em ISO e são formatados pela TELA. Data
 * dentro de `campos` chegaria como texto livre, indistinguível de um nome de
 * rua, e ninguém poderia escrevê-la em dd/mm/aaaa.
 */
export interface DocumentoDeCampo {
    /** `np` = Notificação Preliminar; `aa` = Auto de Apreensão. */
    tipo: 'np' | 'aa';
    numero: string;
    /** O título em caixa alta, como está impresso no alto da folha. */
    titulo: string;
    emitido_em: string;
    /** Quando o prazo de regularização vence — o Auto de Apreensão não tem. */
    vence_em: string | null;
    prazo_rotulo: string | null;
    /** Quem lavrou, com matrícula: é ela que identifica o agente numa defesa. */
    agente: string;
    campos: CampoLido[];
    listas: { titulo: string; itens: string[] }[];
    assinaturas: {
        rotulo: string;
        estado: 'assinada' | 'recusada' | 'pendente';
        nome: string | null;
    }[];
    rodape: string;
}

/**
 * Uma linha do trâmite: o rastro de cada ato, com autor, hora e **o que aquele
 * ato produziu**.
 *
 * Os três últimos campos são o que faz o trâmite ser navegável em vez de uma
 * lista de frases: `campos` traz a decisão tomada (para onde foi, por quê),
 * `campo` traz o registro de campo e `documento` traz o papel lavrado. Vêm
 * sempre declarados pelo servidor — nulos quando aquele passo não os produziu —,
 * e não opcionais, para a tela não precisar de leitura defensiva em cada acesso.
 */
export interface TramiteDenuncia {
    /** ISO com hora (`aaaa-mm-dd hh:mm`) — quem escreve dd/mm/aaaa é a tela. */
    em: string;
    quem: string;
    o_que: string;
    detalhe: string;
    /** A situação em que a denúncia entrou com este passo. */
    situacao: string;
    /** COMO a vistoria terminou, quando foi este passo que a terminou. */
    desfecho: string | null;
    /**
     * As CONSIDERAÇÕES FINAIS do fiscal — o texto que ele escreveu ao fechar a
     * vistoria. `null` quando ele não escreveu nada (é campo livre e opcional no
     * aplicativo).
     *
     * ⚠️ Este nome é o CONTRATO com o aplicativo do fiscal, e é ele que grava.
     * A mesma informação com dois nomes é o começo da divergência.
     */
    consideracoes: string | null;
    /**
     * As RECOMENDAÇÕES que ele assinalou — as **CHAVES** dos atalhos do catálogo
     * do servidor (`recomendacoes_do_fiscal`: `retorno`, `sgci`, `operacao`…),
     * nunca a frase. Vazio quando ele não recomendou nada.
     *
     * A chave é o contrato com o aplicativo do fiscal (é o que ele grava) e é o
     * que o relatório soma; a frase que a tela mostra sai da chave pelo catálogo
     * `recomendacoesDoFiscal`, na redação explícita (ver `@/lib/recomendacoes`).
     *
     * É por elas que o Chefe de Setor entende o que o fiscal está PEDINDO, e é
     * por isso que a leitura as mostra em destaque, e não no meio da ficha.
     */
    recomendacoes: string[];
    campos: CampoLido[];
    campo: RegistroDeCampo | null;
    documento: DocumentoDeCampo | null;
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
    /**
     * COMO a vistoria terminou — o desfecho do último passo do trâmite que
     * produziu um. Nulo enquanto a denúncia não foi a campo.
     */
    desfecho: string | null;

    /** A área que o BAIRRO sugere — calculada na leitura, nunca gravada. */
    area_sugerida: AreaSugerida | null;
    tramites: TramiteDenuncia[];
}

/** Uma operação a que o Chefe de Setor pode anexar denúncia. */
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

/** As situações em que a denúncia espera a TRIAGEM do coordenador. */
export const AGUARDANDO_TRIAGEM = ['Recebida'];

/** As situações em que ela espera o DIRECIONAMENTO do Chefe de Setor da área. */
export const AGUARDANDO_DIRECIONAMENTO = ['Encaminhada à área'];

/**
 * O tom do selo de cada situação.
 *
 * Recebida e Encaminhada à área EXIGEM ação de alguém, então são aviso e info —
 * cada uma chamando o seu dono. Direcionada, Em operação e Em campo são o
 * caminho normal andando. Concluída é fim bom; Devolvida e Arquivada são fim de
 * linha sem erro nenhum: são decisão tomada, com justificativa registrada.
 *
 * As duas situações de pós-vistoria seguem a mesma régua: "Aguardando
 * regularização" é AVISO porque há prazo correndo (a bola está com o notificado,
 * e alguém precisa voltar ao ponto quando ele vencer), e "Retorno vencido" é
 * PERIGO porque o prazo venceu, a situação continua e a denúncia está parada
 * esperando a próxima medida do Chefe de Setor.
 */
export const TOM_DA_SITUACAO: Record<string, string> = {
    Recebida: 'selo-aviso',
    'Encaminhada à área': 'selo-info',
    'Direcionada à equipe': 'selo-ok',
    'Em operação': 'selo-ok',
    'Em campo': 'selo-ok',
    'Aguardando regularização': 'selo-aviso',
    'Retorno vencido': 'selo-perigo',
    Concluída: 'selo-neutro',
    Devolvida: 'selo-neutro',
    Arquivada: 'selo-neutro',
};
