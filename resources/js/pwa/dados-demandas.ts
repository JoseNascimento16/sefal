/* ============================================================================
   PROTÓTIPO — trabalho DIRIGIDO: as demandas que o administrativo encaminhou
   ----------------------------------------------------------------------------
   Até aqui o protótipo só encenava o trabalho AVULSO: o fiscal anda a rua, vê,
   registra. A reunião com o cliente (02/09/2026) mostrou que metade do serviço
   é o oposto disso — chega EMPACOTADO do administrativo, com número de
   documento, endereço e prazo.

   E quem recebe não é o fiscal: é a EQUIPE. A SEFAL divide Salvador em seis
   áreas geográficas mais duas transversais (Itinerante e Noturna), e a demanda
   cai na equipe cujo bloco de bairros bate com o endereço. Por isso a fila
   deste aplicativo é a fila da equipe de quem entrou — nunca "todas".

   ── A MESMA caixa dos dois lados ────────────────────────────────────────────

   As demandas abaixo são as MESMAS da Caixa de Entrada do Administrativo, na
   Retaguarda (`config/prototipo_caixa_entrada.php`): mesmo protocolo interno
   `CXE-NNNN`, mesmo número do documento de origem, mesmo requerente, assunto,
   endereço, bairro, prazo e situação. Foi o pedido do dono e é o roteiro da
   demonstração: o administrativo cadastra, encaminha à equipe, e o fiscal
   daquela equipe recebe no aplicativo. Dados independentes nos dois lados
   contariam duas histórias.

   ⚠️ FONTE ÚNICA — a dívida conhecida deste protótipo. Esta é uma SEGUNDA cópia
   dos mesmos dados, e duas cópias sempre divergem. Ela existe porque o
   aplicativo de campo roda sem servidor: não há endpoint que entregue a caixa.
   Enquanto for assim, mexer numa das duas exige mexer na outra — os nomes de
   campo foram mantidos iguais (`protocolo`, `documentoOrigem`, `origem`,
   `requerente`, `assunto`, `endereco`, `bairro`, `situacao`, `equipe`) para o
   de-para ser mecânico. Quando o aplicativo falar com o servidor, este arquivo
   morre e a fila passa a vir de onde a Retaguarda lê.

   ⚠️ AS DATAS SÃO RELATIVAS, como no outro lado: `diasAtras` e `prazoDias` viram
   data na hora de montar a tela. Data fixa envelhece — uma semana depois da
   demonstração a fila apareceria inteira vencida, e o dono leria isso como
   comportamento do sistema.
   ============================================================================ */

import { EQUIPE } from './sessao';

export type Origem = 'esalvador' | 'fala156' | 'licenca' | 'oficio';

/**
 * As quatro origens da caixa, com o rótulo que o administrativo usa.
 *
 * A lista é fechada e é a mesma de `prototipo_caixa_entrada.origens` — é de
 * onde o documento veio, não texto livre.
 */
export const ORIGENS: Record<
    Origem,
    { rotulo: string; curto: string; emoji: string; explica: string }
> = {
    esalvador: {
        rotulo: 'e-Salvador',
        curto: 'e-Salvador',
        emoji: '🖥️',
        explica: 'Processo aberto no portal de serviços do município.',
    },
    fala156: {
        rotulo: 'Fala Salvador · 156',
        curto: 'Fala 156',
        emoji: '📞',
        explica: 'Reclamação do cidadão pela central de atendimento 156.',
    },
    licenca: {
        rotulo: 'Nova licença',
        curto: 'Nova licença',
        emoji: '📝',
        explica: 'Pedido de licença novo — a vistoria precede o deferimento.',
    },
    oficio: {
        rotulo: 'Ofício',
        curto: 'Ofício',
        emoji: '📄',
        explica: 'Pedido formal de órgão, associação ou entidade, recebido em papel.',
    },
};

/** As situações da demanda na caixa do administrativo, na ordem do trâmite. */
export type Situacao = 'Aguardando triagem' | 'Encaminhada' | 'Devolvida' | 'Arquivada';

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

export type Demanda = {
    id: string;
    /** Protocolo interno da caixa do administrativo (`CXE-NNNN`). */
    protocolo: string;
    /** O número impresso no papel do canal de origem. */
    documentoOrigem: string;
    origem: Origem;
    assunto: string;
    detalhe: string;
    endereco: string;
    bairro: string;
    /** Amarra a demanda ao mapa do protótipo — é a região onde o pino cai. */
    regiao: string;
    /** Data BR em que o administrativo recebeu o documento. */
    recebidaBr: string;
    prazoBr: string;
    /** Positivo = dias que faltam; negativo = dias vencidos; 0 = vence hoje. */
    prazoDias: number;
    situacao: Situacao;
    /** A equipe a que foi encaminhada — `null` enquanto não foi triada. */
    equipe: string | null;
    /** Denúncia pode ser anônima: é a realidade do 156 e do e-Salvador. */
    anonima: boolean;
    /** Quem abriu, quando a origem carrega essa informação. */
    solicitante: string | null;
    /** Preenchida quando o alvo é permissionário cadastrado. */
    sgci: FichaSgci | null;
};

type Semente = Omit<Demanda, 'id' | 'recebidaBr' | 'prazoBr'> & { diasAtras: number };

/* ----------------------------------------------------------------------------
   O UNIVERSO da caixa — todas as demandas, de todas as equipes.
   ----------------------------------------------------------------------------
   O aplicativo guarda a caixa INTEIRA e filtra na hora de mostrar. Guardar só
   as da equipe seria mais curto e esconderia o que a demonstração precisa
   provar: que existe recorte, que o fiscal não vê o serviço do vizinho, e que
   a demanda devolvida ou arquivada pelo administrativo NÃO chega ao campo.

   As dez primeiras (CXE-0001 a CXE-0010) são a transcrição da caixa da
   Retaguarda. As seis seguintes (CXE-0011 a CXE-0016) são os encaminhamentos à
   Equipe C1 · Área 5 que a caixa da Retaguarda ainda não tem — ver o relatório
   desta entrega, que traz o bloco pronto para colar em
   `config/prototipo_caixa_entrada.php` e deixar as duas telas idênticas.
---------------------------------------------------------------------------- */

const UNIVERSO: Semente[] = [
    {
        protocolo: 'CXE-0001',
        documentoOrigem: '156-2026-884120',
        origem: 'fala156',
        assunto: 'Barracas ocupando a calçada em frente à feira',
        detalhe:
            'Denunciante relata quatro barracas fixas fechando a passagem de pedestres na altura da feira, com mesas e cadeiras na via. Pede vistoria no período da manhã.',
        endereco: 'Rua Barão de Mauá, altura do nº 120',
        bairro: 'Arenoso',
        regiao: 'fora-da-area',
        diasAtras: 1,
        prazoDias: 9,
        situacao: 'Aguardando triagem',
        equipe: null,
        anonima: true,
        solicitante: null,
        sgci: null,
    },
    {
        protocolo: 'CXE-0002',
        documentoOrigem: 'ESALV-2026-31877',
        origem: 'esalvador',
        assunto: 'Som alto em equipamento de bebidas após as 22h',
        detalhe:
            'Moradora informa que o equipamento mantém venda de bebida alcoólica e som ligado depois do horário autorizado, de quinta a domingo.',
        endereco: 'Rua Manoel Ferreira, 45',
        bairro: 'Pituba',
        regiao: 'fora-da-area',
        diasAtras: 2,
        prazoDias: 8,
        situacao: 'Aguardando triagem',
        equipe: null,
        anonima: false,
        solicitante: 'Marilene Souza dos Santos',
        sgci: null,
    },
    {
        protocolo: 'CXE-0003',
        documentoOrigem: 'PROC-2026-004512',
        origem: 'licenca',
        assunto: 'Pedido de nova licença para carrinho de lanches',
        detalhe:
            'Requerente pede permissão para carrinho de lanches em ponto de grande circulação. Precisa de vistoria do local para conferir recuo e fluxo de pedestres.',
        endereco: 'Avenida Sete de Setembro, em frente ao nº 908',
        bairro: 'Avenida Sete de Setembro',
        regiao: 'fora-da-area',
        diasAtras: 4,
        prazoDias: 6,
        situacao: 'Encaminhada',
        equipe: 'I1',
        anonima: false,
        solicitante: 'Edvaldo Nascimento Lima',
        sgci: null,
    },
    {
        protocolo: 'CXE-0004',
        documentoOrigem: 'ESALV-2026-31644',
        origem: 'esalvador',
        assunto: 'Ambulante vendendo em cima da faixa de travessia',
        detalhe:
            'Relato de venda de água e refrigerante sobre a faixa de travessia, com risco para pedestres no horário de pico.',
        endereco: 'Largo do Tanque, próximo ao terminal',
        bairro: 'Largo do Tanque',
        regiao: 'fora-da-area',
        diasAtras: 6,
        prazoDias: 4,
        situacao: 'Encaminhada',
        equipe: 'B2',
        anonima: true,
        solicitante: null,
        sgci: null,
    },
    {
        protocolo: 'CXE-0005',
        documentoOrigem: '156-2026-880913',
        origem: 'fala156',
        assunto: 'Reclamação sobre feira livre — sem endereço',
        detalhe:
            'Denunciante desligou antes de informar a rua. O atendimento registrou apenas "perto da feira", o que não localiza o ponto. Devolvida ao canal de origem para complementar o endereço.',
        endereco: 'Não informado',
        bairro: 'Mussurunga',
        regiao: 'mussurunga',
        diasAtras: 9,
        prazoDias: 1,
        situacao: 'Devolvida',
        equipe: null,
        anonima: true,
        solicitante: null,
        sgci: null,
    },
    {
        protocolo: 'CXE-0006',
        documentoOrigem: 'OF-SEMOP-2026-771',
        origem: 'oficio',
        assunto: 'Pedido de fiscalização periódica na orla',
        detalhe:
            'Ofício pede rondas semanais no fim de semana por conta do aumento de barracas em período de temporada.',
        endereco: 'Largo da Ribeira, toda a extensão da orla',
        bairro: 'Ribeira',
        regiao: 'fora-da-area',
        diasAtras: 12,
        prazoDias: -2,
        situacao: 'Encaminhada',
        equipe: 'A1',
        anonima: false,
        solicitante: 'Associação de Moradores da Ribeira',
        sgci: null,
    },
    {
        protocolo: 'CXE-0007',
        documentoOrigem: 'ESALV-2026-30901',
        origem: 'esalvador',
        assunto: 'Buraco na via em frente ao ponto de ambulante',
        detalhe:
            'Reclamação sobre pavimento. A demanda não trata de comércio ambulante — o objeto é conservação de via, atribuição de outro órgão. Arquivada na caixa do administrativo.',
        endereco: 'Rua Silveira Martins, 1.240',
        bairro: 'Cabula',
        regiao: 'fora-da-area',
        diasAtras: 16,
        prazoDias: -6,
        situacao: 'Arquivada',
        equipe: null,
        anonima: false,
        solicitante: 'Roberto Carlos Menezes',
        sgci: null,
    },
    {
        protocolo: 'CXE-0008',
        documentoOrigem: 'PROC-2026-004380',
        origem: 'licenca',
        assunto: 'Pedido de nova licença para venda de acarajé',
        detalhe:
            'Requerente já atua no ponto e pede a regularização. Solicita vistoria para medição do espaço e conferência do equipamento.',
        endereco: 'Praça Tiradentes, esquina com a Rua da Poeira',
        bairro: 'Comércio',
        regiao: 'fora-da-area',
        diasAtras: 3,
        prazoDias: 7,
        situacao: 'Aguardando triagem',
        equipe: null,
        anonima: false,
        solicitante: 'Josefa Bispo de Oliveira',
        sgci: null,
    },
    {
        protocolo: 'CXE-0009',
        documentoOrigem: '156-2026-885201',
        origem: 'fala156',
        assunto: 'Venda de bebida em equipamento sem alvará à noite',
        detalhe:
            'Denúncia de funcionamento noturno com mesas na calçada e venda de bebida alcoólica sem autorização. Pede vistoria depois das 21h.',
        endereco: 'Rua Território do Amapá, 78',
        bairro: 'Tancredo Neves',
        regiao: 'fora-da-area',
        diasAtras: 0,
        prazoDias: 10,
        situacao: 'Aguardando triagem',
        equipe: null,
        anonima: true,
        solicitante: null,
        sgci: null,
    },
    {
        protocolo: 'CXE-0010',
        documentoOrigem: 'ESALV-2026-31990',
        origem: 'esalvador',
        assunto: 'Equipamento cedido a terceiro',
        detalhe:
            'Requerente informa que o permissionário do ponto não trabalha mais no local e cedeu o equipamento a outra pessoa.',
        endereco: 'Avenida Cardeal da Silva, 622',
        bairro: 'Federação',
        regiao: 'fora-da-area',
        diasAtras: 0,
        prazoDias: 10,
        situacao: 'Aguardando triagem',
        equipe: null,
        anonima: false,
        solicitante: 'Cleber Andrade Rios',
        sgci: null,
    },

    /* ---- Os encaminhamentos à Equipe C1 · Área 5 (Boca do Rio) ------------- */

    {
        protocolo: 'CXE-0011',
        documentoOrigem: '156-2026-884907',
        origem: 'fala156',
        assunto: 'Barracas fechando o acesso à praia',
        detalhe:
            'Denunciante relata cinco barracas fixas no acesso à areia, com mesas e freezer sobre a calçada, obrigando quem passa a descer para a pista. Pede vistoria no fim de semana.',
        endereco: 'Av. Otávio Mangabeira, acesso à Praia da Boca do Rio',
        bairro: 'Jardim Armação',
        regiao: 'jardim-armacao',
        diasAtras: 5,
        prazoDias: -1,
        situacao: 'Encaminhada',
        equipe: 'C1',
        anonima: true,
        solicitante: null,
        sgci: null,
    },
    {
        protocolo: 'CXE-0012',
        documentoOrigem: 'ESALV-2026-31712',
        origem: 'esalvador',
        assunto: 'Vistoria de renovação de permissão',
        detalhe:
            'Processo de renovação anual. Confirmar padrão do equipamento, área ocupada e quitação do preço público.',
        endereco: 'Praia de Itapuã, altura do Farol',
        bairro: 'Itapuã',
        regiao: 'itapua',
        diasAtras: 3,
        prazoDias: 0,
        situacao: 'Encaminhada',
        equipe: 'C1',
        anonima: false,
        solicitante: 'Terezinha Gomes Rocha',
        sgci: {
            inscricao: '00412.7788/2019-04',
            nome: 'Terezinha Gomes Rocha',
            atividade: 'Baiana de Acarajé',
            equipamento: 'Barraca 12 · Quadra B',
            situacao: 'Ativo',
            damEmDia: true,
            desde: '2019',
        },
    },
    {
        protocolo: 'CXE-0013',
        documentoOrigem: 'PROC-2026-004566',
        origem: 'licenca',
        assunto: 'Pedido de nova licença para quiosque de água de coco',
        detalhe:
            'Requerente pede permissão para quiosque de água de coco na orla. Vistoriar se o ponto pretendido comporta o equipamento sem fechar a passagem.',
        endereco: 'Av. Otávio Mangabeira, orla do Jardim de Alah',
        bairro: 'Costa Azul',
        regiao: 'costa-azul',
        diasAtras: 2,
        prazoDias: 2,
        situacao: 'Encaminhada',
        equipe: 'C1',
        anonima: false,
        solicitante: 'Genivaldo Ramos Filho',
        sgci: null,
    },
    {
        protocolo: 'CXE-0014',
        documentoOrigem: 'ESALV-2026-31860',
        origem: 'esalvador',
        assunto: 'Equipamento cedido a terceiro',
        detalhe:
            'Requerente informa que o permissionário do ponto não trabalha mais no local e cedeu o equipamento a outra pessoa. Verificar quem está operando.',
        endereco: 'Av. Prof. Pinto de Aguiar, em frente ao Parque de Pituaçu',
        bairro: 'Pituaçu',
        regiao: 'pituacu',
        diasAtras: 1,
        prazoDias: 4,
        situacao: 'Encaminhada',
        equipe: 'C1',
        anonima: false,
        solicitante: 'Nadja Ferreira Sampaio',
        sgci: {
            inscricao: '00318.4521/2016-11',
            nome: 'Ademilson Cruz Barbosa',
            atividade: 'Barraca de Chapa',
            equipamento: 'Box 04 · Lote 2',
            situacao: 'Suspenso',
            damEmDia: false,
            desde: '2016',
        },
    },
    {
        protocolo: 'CXE-0015',
        documentoOrigem: '156-2026-885188',
        origem: 'fala156',
        assunto: 'Venda de bebida com som alto depois das 22h',
        detalhe:
            'Reclamação reincidente de moradores: venda de bebida alcoólica depois do horário autorizado, com mesas na via. Pede vistoria depois das 21h.',
        endereco: 'Rua Ewerton Visco, ao lado do canteiro central',
        bairro: 'Stiep',
        regiao: 'stiep',
        diasAtras: 0,
        prazoDias: 6,
        situacao: 'Encaminhada',
        equipe: 'C1',
        anonima: true,
        solicitante: null,
        sgci: null,
    },
    {
        protocolo: 'CXE-0016',
        documentoOrigem: 'OF-SEMOP-2026-802',
        origem: 'oficio',
        assunto: 'Pedido de fiscalização periódica na feira do Imbuí',
        detalhe:
            'Ofício pede rondas no sábado por conta do aumento de barracas no entorno da feira, com ocupação da faixa de pedestres.',
        endereco: 'Rua Ilhéus, entorno da feira',
        bairro: 'Imbuí',
        regiao: 'imbui',
        diasAtras: 7,
        prazoDias: 3,
        situacao: 'Encaminhada',
        equipe: 'C1',
        anonima: false,
        solicitante: 'Associação de Moradores do Imbuí',
        sgci: null,
    },
];

/**
 * Data BR de hoje somada de N dias.
 *
 * O "hoje" é o de verdade, e não uma data escrita à mão: é assim que a caixa da
 * Retaguarda resolve as dela, e é o que faz as duas telas mostrarem a MESMA
 * data em qualquer dia que a demonstração aconteça.
 */
const dataDaqui = (dias: number): string => {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + dias);

    return d.toLocaleDateString('pt-BR');
};

/** A caixa inteira, de todas as equipes. */
export const DEMANDAS: Demanda[] = UNIVERSO.map((s) => ({
    ...s,
    id: `dem-${s.protocolo.replace('CXE-', '')}`,
    recebidaBr: dataDaqui(-s.diasAtras),
    prazoBr: dataDaqui(s.prazoDias),
}));

/**
 * A demanda pelo id, procurada na caixa INTEIRA.
 *
 * De propósito não filtra por equipe: um registro antigo do aparelho pode
 * apontar para uma demanda que hoje não está mais na fila, e a tela de detalhe
 * precisa continuar abrindo — sumir sem explicação é pior do que mostrar.
 */
export const acharDemanda = (id: string | null): Demanda | null =>
    (id && DEMANDAS.find((d) => d.id === id)) || null;

/**
 * O que chega ao campo: encaminhada, e encaminhada À MINHA EQUIPE.
 *
 * As duas condições são a régua inteira. Sem a primeira, a demanda que o
 * administrativo devolveu ou arquivou apareceria como serviço a fazer; sem a
 * segunda, o fiscal veria o serviço do vizinho e não entenderia por que um
 * endereço fora da área dele está na lista.
 */
export const demandasDaEquipe = (): Demanda[] =>
    DEMANDAS.filter((d) => d.situacao === 'Encaminhada' && d.equipe === EQUIPE.codigo).sort(
        (a, b) => a.prazoDias - b.prazoDias,
    );

export const demandasVencidas = (): Demanda[] => demandasDaEquipe().filter((d) => d.prazoDias < 0);

/** Quantas a caixa encaminhou para OUTRAS equipes — o recorte, em número. */
export const demandasDeOutrasEquipes = (): number =>
    DEMANDAS.filter((d) => d.situacao === 'Encaminhada' && d.equipe !== EQUIPE.codigo).length;

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

/* A equipe e a estrutura moram nos módulos deles; reexportar aqui evita que as
   telas que já liam a fila tenham de aprender dois caminhos novos. */
export { AREAS_SEFAL } from './dados-equipes';
export { EQUIPE, FISCAL } from './sessao';
