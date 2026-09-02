/* ============================================================================
   PROTÓTIPO — trabalho DIRIGIDO: a equipe, a área e as demandas que chegam
   ----------------------------------------------------------------------------
   Até aqui o protótipo só encenava o trabalho AVULSO: o fiscal anda a rua, vê,
   registra. A reunião com o cliente (02/09/2026) mostrou que metade do serviço
   é o oposto disso — chega EMPACOTADO do administrativo, com número de
   processo, endereço e prazo.

   E quem recebe não é o fiscal: é a EQUIPE. A SEFAL divide Salvador em seis
   áreas geográficas mais duas transversais (Itinerante e Noturna), e cada área
   tem uma equipe com um encarregado e uma lista fechada de bairros. A demanda
   cai na equipe cujo bairro bate com o endereço. Por isso a identidade de
   equipe passa a aparecer no aplicativo: é ela que define a caixa de entrada.

   Os bairros e os nomes dos encarregados abaixo são REAIS — vêm da planilha
   "ÁREAS DAS EQUIPES ATUALIZADA - 17042026" que o cliente entregou. As
   demandas, os cidadãos que reclamaram e os números de processo são fictícios.
   ============================================================================ */

export type Origem = 'esalvador' | 'fala156' | 'licenca';

export const ORIGENS: Record<
    Origem,
    { rotulo: string; curto: string; emoji: string; prefixo: string; explica: string }
> = {
    esalvador: {
        rotulo: 'e-Salvador',
        curto: 'e-Salvador',
        emoji: '🖥️',
        prefixo: 'ESAL',
        explica: 'Processo aberto no portal de serviços do município.',
    },
    fala156: {
        rotulo: 'Fala Salvador · 156',
        curto: 'Fala 156',
        emoji: '📞',
        prefixo: '156',
        explica: 'Reclamação do cidadão pela central de atendimento 156.',
    },
    licenca: {
        rotulo: 'Nova licença',
        curto: 'Nova licença',
        emoji: '📝',
        prefixo: 'LIC',
        explica: 'Pedido de licença novo — a vistoria precede o deferimento.',
    },
};

/**
 * A equipe do fiscal que está com o aparelho na mão.
 *
 * Área 1 (Centro), equipe C2, encarregado José Roberto — escolhida porque é a
 * área que cobre os bairros onde o mapa do protótipo já tem pontos (Barra,
 * Rio Vermelho, Comércio, Centro Histórico, Ondina).
 */
export const EQUIPE = {
    codigo: 'C2',
    nome: 'Equipe C2',
    area: 'Área 1',
    areaNome: 'Centro',
    encarregado: 'José Roberto',
    /** A lista fechada da planilha do cliente, na ordem dela. */
    bairros: [
        'Alto das Pombas',
        'Barbalho',
        'Barra',
        'Barris',
        'Calabar',
        'Canela',
        'Centro Histórico',
        'Chame-Chame',
        'Comércio',
        'Dois de Julho',
        'Eng. Velho da Federação',
        'Federação',
        'Garcia',
        'Graça',
        'Macaúbas',
        'Mares',
        'Nazaré',
        'Ondina',
        'Rio Vermelho',
        'Santo Agostinho',
        'Saúde',
        'Tororó',
        'Vasco da Gama',
        'Vitória',
    ],
};

/** As oito frentes da SEFAL — aparecem no levantamento e no perfil. */
export const EQUIPES_SEFAL = [
    { codigo: 'C2', area: 'Área 1', areaNome: 'Centro', encarregado: 'José Roberto' },
    { codigo: 'A1', area: 'Área 2', areaNome: 'Itapagipe', encarregado: 'Marco Gonçalves' },
    { codigo: 'A2', area: 'Área 3', areaNome: 'Brotas', encarregado: 'Nonato Silva' },
    { codigo: 'B2', area: 'Área 4', areaNome: 'Liberdade', encarregado: 'Andréa Rocha' },
    { codigo: 'C1', area: 'Área 5', areaNome: 'Boca do Rio', encarregado: 'César Amaral' },
    { codigo: 'B1', area: 'Área 6', areaNome: 'Pau da Lima', encarregado: 'José Antonio' },
    { codigo: 'I1', area: 'Itinerante', areaNome: 'Avenida Sete', encarregado: 'Roberto Moraes' },
    { codigo: 'N1', area: 'Noturna', areaNome: 'Toda Salvador', encarregado: 'Alcione Brandão' },
];

/**
 * O cadastro do permissionário, quando existe.
 *
 * ⚠️ Não há integração: o SGCI é o cadastro de comércio informal do município e
 * o aplicativo, hoje, não fala com ele. O que as telas mostram é uma FICHA
 * FICTÍCIA rotulada "dados do cadastro SGCI", para o dono ver o lugar onde a
 * integração vai entrar — e para que ninguém a confunda com dado digitado pelo
 * fiscal, que é o ponto: o que vem do cadastro não se retoca em campo.
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

export type StatusDemanda = 'nova' | 'andamento' | 'concluida';

export type Demanda = {
    id: string;
    protocolo: string;
    origem: Origem;
    assunto: string;
    detalhe: string;
    endereco: string;
    bairro: string;
    /** Amarra a demanda ao mapa do protótipo — é a região onde o pino cai. */
    regiao: string;
    prazoBr: string;
    /** Positivo = dias que faltam; negativo = dias vencidos; 0 = vence hoje. */
    prazoDias: number;
    status: StatusDemanda;
    /** Quem abriu, quando a origem carrega essa informação. */
    solicitante: string | null;
    /** Preenchida quando o alvo é permissionário cadastrado. */
    sgci: FichaSgci | null;
};

const DEMANDAS_SEMENTE: Omit<Demanda, 'id' | 'protocolo' | 'prazoBr'>[] = [
    {
        origem: 'fala156',
        assunto: 'Barracas obstruindo a calçada',
        detalhe:
            'Munícipe relata três barracas montadas sobre a faixa de pedestres em frente ao Farol, obrigando quem passa a descer para a pista.',
        endereco: 'Av. Oceânica, em frente ao Farol da Barra',
        bairro: 'Barra',
        regiao: 'barra',
        prazoDias: -2,
        status: 'nova',
        solicitante: 'Cidadão — atendimento 156',
        sgci: null,
    },
    {
        origem: 'esalvador',
        assunto: 'Vistoria de renovação de permissão',
        detalhe:
            'Processo de renovação anual. Confirmar padrão do equipamento, área ocupada e quitação do preço público.',
        endereco: 'Praia do Porto da Barra, altura da rampa',
        bairro: 'Barra',
        regiao: 'barra',
        prazoDias: 0,
        status: 'andamento',
        solicitante: 'Josefa Maria dos Santos',
        sgci: {
            inscricao: '00412.7788/2019-04',
            nome: 'Josefa Maria dos Santos',
            atividade: 'Baiana de Acarajé',
            equipamento: 'Barraca 12 · Quadra B',
            situacao: 'Ativo',
            damEmDia: true,
            desde: '2019',
        },
    },
    {
        origem: 'licenca',
        assunto: 'Vistoria prévia — pedido de licença novo',
        detalhe:
            'Pedido de licença para quiosque de água de coco. Vistoriar se o ponto pretendido comporta o equipamento sem fechar passagem.',
        endereco: 'Largo da Mariquita, canteiro central',
        bairro: 'Rio Vermelho',
        regiao: 'rio-vermelho',
        prazoDias: 1,
        status: 'nova',
        solicitante: 'Genivaldo Ramos Filho',
        sgci: null,
    },
    {
        origem: 'fala156',
        assunto: 'Som alto e venda de bebida após o horário',
        detalhe:
            'Reclamação reincidente de moradores: venda de bebida alcoólica depois das 22h, com mesas na via.',
        endereco: 'Largo de Santana, ao lado da feira',
        bairro: 'Rio Vermelho',
        regiao: 'rio-vermelho',
        prazoDias: 2,
        status: 'nova',
        solicitante: 'Cidadão — atendimento 156',
        sgci: {
            inscricao: '00318.4521/2016-11',
            nome: 'Ubiratan Ferreira Gomes',
            atividade: 'Barraca de Chapa',
            equipamento: 'Box 04 · Lote 2',
            situacao: 'Suspenso',
            damEmDia: false,
            desde: '2016',
        },
    },
    {
        origem: 'esalvador',
        assunto: 'Cessão irregular de equipamento a terceiro',
        detalhe:
            'Denúncia protocolada: o permissionário teria repassado a barraca. Verificar quem está operando o ponto.',
        endereco: 'Terreiro de Jesus, em frente à Catedral',
        bairro: 'Centro Histórico',
        regiao: 'pelourinho',
        prazoDias: 4,
        status: 'nova',
        solicitante: 'Denúncia protocolada no e-Salvador',
        sgci: {
            inscricao: '00207.1190/2013-07',
            nome: 'Vera Lúcia Nascimento',
            atividade: 'Comércio Informal',
            equipamento: 'Barraca 07 · Quadra A',
            situacao: 'Ativo',
            damEmDia: true,
            desde: '2013',
        },
    },
    {
        origem: 'fala156',
        assunto: 'Carcaça de veículo na via pública',
        detalhe:
            'Veículo abandonado há semanas na saída do elevador, usado como depósito de mercadoria.',
        endereco: 'Av. da França, saída do Elevador Lacerda',
        bairro: 'Comércio',
        regiao: 'comercio',
        prazoDias: 5,
        status: 'nova',
        solicitante: 'Cidadão — atendimento 156',
        sgci: null,
    },
    {
        origem: 'licenca',
        assunto: 'Vistoria prévia — mesas e cadeiras',
        detalhe:
            'Pedido de alvará para colocação de mesas e cadeiras no logradouro. Medir a área pretendida e a largura livre restante.',
        endereco: 'Av. Adhemar de Barros, mirante de Ondina',
        bairro: 'Ondina',
        regiao: 'ondina',
        prazoDias: 7,
        status: 'nova',
        solicitante: 'Adriana Lopes Figueiredo',
        sgci: null,
    },
];

/** Data BR de hoje somada de N dias — o "hoje" do protótipo é 26/08/2026. */
const dataDaqui = (dias: number): string => {
    const d = new Date(2026, 7, 26);
    d.setDate(d.getDate() + dias);

    return d.toLocaleDateString('pt-BR');
};

const numeroDoProcesso = (origem: Origem, ordem: number): string => {
    const { prefixo } = ORIGENS[origem];

    if (origem === 'fala156') {
        return `${prefixo}-2026/${String(93400 + ordem * 37).padStart(7, '0')}`;
    }

    return `${prefixo}-2026/${String(1200 + ordem * 419).padStart(6, '0')}`;
};

export const DEMANDAS: Demanda[] = DEMANDAS_SEMENTE.map((s, i) => ({
    ...s,
    id: `dem-${String(i + 1).padStart(2, '0')}`,
    protocolo: numeroDoProcesso(s.origem, i + 1),
    prazoBr: dataDaqui(s.prazoDias),
}));

export const acharDemanda = (id: string | null): Demanda | null =>
    (id && DEMANDAS.find((d) => d.id === id)) || null;

export const DEMANDAS_ABERTAS = DEMANDAS.filter((d) => d.status !== 'concluida').sort(
    (a, b) => a.prazoDias - b.prazoDias,
);

export const DEMANDAS_VENCIDAS = DEMANDAS_ABERTAS.filter((d) => d.prazoDias < 0);

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

export const tomDoPrazo = (dias: number): 'perigo' | 'alerta' | 'info' =>
    dias < 0 ? 'perigo' : dias <= 1 ? 'alerta' : 'info';
