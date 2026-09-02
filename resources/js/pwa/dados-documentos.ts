/* ============================================================================
   PROTÓTIPO — os documentos oficiais de campo, como o cliente os imprime hoje
   ----------------------------------------------------------------------------
   Este módulo NÃO é invenção nossa: cada lista aqui foi transcrita dos blocos
   de papel que o SEFAL usa em rua — a **Notificação Preliminar** (Cód. 26...)
   e o **Auto de Apreensão** —, mais o "Manual para o NTI" que o cliente
   entregou junto. Quando o aplicativo sair do protótipo, os textos continuam
   valendo: é o formulário legal, não um rascunho de tela.

   ⚠️ Fidelidade ao papel: o bloco de motivos da Notificação tem 20 caixas, das
   quais a última é "Outros" (campo livre) e a primeira pede complemento ("no
   Setor ____"). Não arredondamos para um número redondo nem reescrevemos a
   redação do impresso — se o texto soa estranho, é porque está assim no papel.
   ============================================================================ */

export type MotivoNp = {
    id: string;
    texto: string;
    /** Quando o impresso deixa uma linha para completar à mão. */
    complemento?: string;
};

/** Coluna da esquerda do impresso, na ordem em que está no papel. */
export const MOTIVOS_NP: MotivoNp[] = [
    { id: 'comparecer', texto: 'Comparecer a SEMOP no Setor', complemento: 'Qual setor' },
    { id: 'preco-publico', texto: 'Falta de pagamento do Preço Público' },
    { id: 'dam', texto: 'Apresentar DAM quitado do ano em exercício' },
    { id: 'horario', texto: 'Descumprimento do horário de funcionamento' },
    { id: 'animal', texto: 'Manter animal no equipamento' },
    { id: 'higiene', texto: 'Não zelar pela conservação, asseio e higiene' },
    { id: 'padrao', texto: 'Alterar o padrão do equipamento' },
    { id: 'mesas', texto: 'Retirar mesas e cadeiras do logradouro público' },
    { id: 'equipamento', texto: 'Retirar equipamentos do logradouro público' },
    {
        id: 'alvara-mesas',
        texto: 'Apresentar Alvará para colocação de mesas e cadeiras no logradouro público',
    },
    { id: 'ceder', texto: 'Ceder / locar o equipamento a terceiro' },
    { id: 'ramo', texto: 'Alterar ou mudar o ramo de atividade' },
    { id: 'produtos-fora', texto: 'Manter produtos e objetos fora do equipamento' },
    { id: 'cota', texto: 'Falta de pagamento da cota de despesa (água e luz)' },
    { id: 'bebida', texto: 'Desativar a venda de bebida alcoólica' },
    { id: 'puxada', texto: 'Retirar puxada, sanitário e depósito' },
    { id: 'desativar', texto: 'Desativar suas atividades' },
    { id: 'realocar', texto: 'Realocar equipamento' },
    { id: 'carcaca', texto: 'Retirar carcaça de veículo da via pública' },
];

/** As penalidades da faixa de baixo do impresso — exatamente as cinco. */
export const SANCOES_NP = [
    { id: 'autuacao', rotulo: 'Autuação' },
    { id: 'apreensao', rotulo: 'Apreensão' },
    { id: 'perdas', rotulo: 'Perdas de bens e mercadorias' },
    { id: 'embargo', rotulo: 'Embargo administrativo' },
    { id: 'multa', rotulo: 'Pagamento de multa' },
];

/** Prazos que o manual do cliente lista, na ordem em que ele os escreve. */
export const PRAZOS_NP = [
    { id: 'imediato', rotulo: 'Imediato', dias: 0, nota: 'Para quem não possui cadastro' },
    { id: '24h', rotulo: '24 horas', dias: 1 },
    { id: '48h', rotulo: '48 horas', dias: 2 },
    { id: '72h', rotulo: '72 horas', dias: 3 },
    { id: '5d', rotulo: '05 dias', dias: 5 },
    { id: '10d', rotulo: '10 dias', dias: 10 },
];

/** Exemplos de atividade que o manual do cliente dá para o campo ATIVIDADE. */
export const ATIVIDADES_NP = [
    'Comércio Informal',
    'Barraca de Chapa',
    'Baiana de Acarajé',
    'Food Truck',
    'Quiosque',
    'Ambulante',
];

/**
 * A fundamentação legal do Auto de Apreensão.
 *
 * O impresso tem quatro linhas (lei, decreto, artigos, portaria) e o manual
 * lista a lei e os cinco decretos. O aplicativo já chega com eles preenchidos,
 * porque digitar "Decreto Nº 26.849/2015" de pé na calçada é onde o papel de
 * hoje mais erra.
 */
export const FUNDAMENTACAO = {
    lei: 'Lei Nº 5.503/1999',
    decretos: [
        'Decreto Nº 11.754/1997',
        'Decreto Nº 12.016/1998',
        'Decreto Nº 24.422/2013',
        'Decreto Nº 26.804/2015',
        'Decreto Nº 26.849/2015',
    ],
    artigosPadrao: 'Art. 21, Art. 24 e Art. 31',
    portariaPadrao: 'Portaria SEMOP Nº 014/2026',
};

/** O destino dos bens está impresso no formulário — endereço inclusive. */
export const SEGUB = {
    nome: 'Setor de Guarda de Bens — SEGUB',
    endereco: 'Av. San Martins, s/n',
};

export const PRAZOS_SEGUB = [
    { id: '30', rotulo: '30 dias', extenso: 'trinta dias' },
    { id: '60', rotulo: '60 dias', extenso: 'sessenta dias' },
    { id: '90', rotulo: '90 dias', extenso: 'noventa dias' },
];

/** "quando serão ______ de acordo com a Legislação Municipal." */
export const DESTINACOES_SEGUB = [
    'devolvidos ao proprietário, mediante regularização e quitação',
    'doados a instituições de assistência social',
    'levados a leilão público',
    'destruídos, por se tratar de material perecível',
];

export const TIPOS_EQUIPAMENTO = [
    'Barraca de chapa',
    'Carrinho de mão',
    'Tabuleiro',
    'Isopor / cooler',
    'Banca desmontável',
    'Food truck / trailer',
    'Mesa e cadeiras',
    'Guarda-sol',
    'Freezer',
    'Chapa / grelha',
];

/** Atalhos da discriminação de bens — o fiscal toca, ajusta a quantidade e vai. */
export const BENS_FREQUENTES = [
    { descricao: 'Barraca de chapa metálica', unidade: 'un' },
    { descricao: 'Isopor com bebidas', unidade: 'un' },
    { descricao: 'Cerveja em lata 350ml', unidade: 'un' },
    { descricao: 'Refrigerante em garrafa 2L', unidade: 'un' },
    { descricao: 'Mesa plástica', unidade: 'un' },
    { descricao: 'Cadeira plástica', unidade: 'un' },
    { descricao: 'Guarda-sol', unidade: 'un' },
    { descricao: 'Chapa de fritura', unidade: 'un' },
    { descricao: 'Botijão de gás P13', unidade: 'un' },
    { descricao: 'Óculos de sol', unidade: 'un' },
];

export type ItemApreendido = {
    id: string;
    quantidade: number;
    unidade: string;
    descricao: string;
};

/* --------------------------- Numeração dos blocos --------------------------- */

/**
 * O bloco de números RESERVADO no aparelho.
 *
 * No papel, o número já vem impresso na folha (a Notificação lida com o cliente
 * traz 194901; o Auto de Apreensão, 160051) — o fiscal não o escolhe, ele
 * simplesmente usa a próxima folha do bloco. O aplicativo copia esse
 * comportamento: cada aparelho carrega uma FAIXA reservada e consome dela,
 * mesmo sem sinal. É o que faz o documento nascer numerado no meio da rua.
 *
 * ⚠️ Aqui é encenação: o contador vive na memória da aba e a faixa é fictícia.
 */
const FAIXAS = {
    np: { inicio: 194901, fim: 195000, usados: 0 },
    aa: { inicio: 160051, fim: 160100, usados: 0 },
};

export type Faixa = keyof typeof FAIXAS;

export const numeroReservado = (faixa: Faixa): string =>
    String(FAIXAS[faixa].inicio + FAIXAS[faixa].usados);

export const consumirNumero = (faixa: Faixa): string => {
    const numero = numeroReservado(faixa);
    FAIXAS[faixa].usados += 1;

    return numero;
};

export const restamNaFaixa = (faixa: Faixa): number =>
    FAIXAS[faixa].fim - FAIXAS[faixa].inicio + 1 - FAIXAS[faixa].usados;

export const nomeDoDocumento = (faixa: Faixa): string =>
    faixa === 'np' ? 'Notificação Preliminar' : 'Auto de Apreensão';

export const siglaDoDocumento = (faixa: Faixa): string => (faixa === 'np' ? 'NP' : 'AA');
