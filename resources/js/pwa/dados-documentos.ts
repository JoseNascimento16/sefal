/* ============================================================================
   Os documentos oficiais de campo, como o cliente os imprime hoje
   ----------------------------------------------------------------------------
   A redação dos blocos de papel que o SEFAL usa em rua — a **Notificação
   Preliminar** e o **Auto de Apreensão** — tem UM dono: o servidor
   (`config/prototipo_documentos_campo.php`). Ela chega no `kit`
   (`impressos`) e mora aqui só enquanto o aplicativo está aberto. Escrevê-la
   de novo neste arquivo daria dois donos ao texto de um formulário legal, e um
   dia o papel que o ambulante recebe diria uma coisa e a Retaguarda outra.

   Fica aqui o que é só conveniência do aparelho (exemplos de atividade,
   tipos de equipamento, bens frequentes): não vai para o papel como texto
   legal, é atalho de digitação.
   ============================================================================ */

export type MotivoNp = {
    id: string;
    texto: string;
    /** Quando o impresso deixa uma linha para completar à mão. */
    complemento?: string;
};

export type PrazoNp = { id: string; rotulo: string; dias: number; nota?: string };
export type PrazoGuarda = { id: string; rotulo: string; extenso: string };
export type Destinacao = { id: string; texto: string };

/** O bloco `impressos` do kit — a forma que o servidor entrega. */
export type Impressos = {
    motivosNp: MotivoNp[];
    sancoesNp: { id: string; rotulo: string }[];
    prazosNp: PrazoNp[];
    fundamentacao: { lei: string; decretos: string[]; artigosPadrao: string; portariaPadrao: string };
    segub: { nome: string; endereco: string };
    prazosGuarda: PrazoGuarda[];
    destinacoesGuarda: Destinacao[];
};

/* Ligações vivas, como a fila e os números: o kit as preenche. */

/** Coluna da esquerda do impresso, na ordem em que está no papel. */
export let MOTIVOS_NP: MotivoNp[] = [];

/** As penalidades da faixa de baixo do impresso. */
export let SANCOES_NP: Impressos['sancoesNp'] = [];

/** Prazos que o manual do cliente lista, na ordem em que ele os escreve. */
export let PRAZOS_NP: PrazoNp[] = [];

/** A fundamentação legal do Auto de Apreensão — o aplicativo já chega com ela preenchida. */
export let FUNDAMENTACAO: Impressos['fundamentacao'] = { lei: '', decretos: [], artigosPadrao: '', portariaPadrao: '' };

/** O destino dos bens está impresso no formulário — endereço inclusive. */
export let SEGUB: Impressos['segub'] = { nome: '', endereco: '' };

export let PRAZOS_SEGUB: PrazoGuarda[] = [];

/** "quando serão ______ de acordo com a Legislação Municipal." */
export let DESTINACOES_SEGUB: Destinacao[] = [];

export function carregarImpressos(impressos: Impressos | undefined): void {
    MOTIVOS_NP = impressos?.motivosNp ?? [];
    SANCOES_NP = impressos?.sancoesNp ?? [];
    PRAZOS_NP = impressos?.prazosNp ?? [];
    FUNDAMENTACAO = impressos?.fundamentacao ?? { lei: '', decretos: [], artigosPadrao: '', portariaPadrao: '' };
    SEGUB = impressos?.segub ?? { nome: '', endereco: '' };
    PRAZOS_SEGUB = impressos?.prazosGuarda ?? [];
    DESTINACOES_SEGUB = impressos?.destinacoesGuarda ?? [];
}

/**
 * O formulário só se monta com a redação do servidor na mão. Sem ela (o kit
 * não chegou), a tela diz o porquê em vez de lavrar um impresso em branco.
 */
export const impressosCarregados = (): boolean =>
    MOTIVOS_NP.length > 0 && PRAZOS_NP.length > 0 && PRAZOS_SEGUB.length > 0 && DESTINACOES_SEGUB.length > 0;

/** Exemplos de atividade que o manual do cliente dá para o campo ATIVIDADE. */
export const ATIVIDADES_NP = [
    'Comércio Informal',
    'Barraca de Chapa',
    'Baiana de Acarajé',
    'Food Truck',
    'Quiosque',
    'Ambulante',
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
 * ⚠️ A faixa começa DEPOIS dos números que o módulo de Denúncias da Retaguarda
 * já mostra nos documentos semeados (Notificações 194901 a 194905 e Auto de
 * Apreensão 160051). Começando neles, a primeira Notificação lavrada na
 * demonstração nasceria com o número de um documento que já existe do outro
 * lado — dois documentos diferentes com o mesmo número é exatamente o que a
 * reserva por aparelho existe para impedir.
 *
 * Os números vêm do SERVIDOR (`numeros` do `kit`): ele separa um pequeno
 * estoque de cada tipo para o fiscal e só o dá por usado quando o documento
 * chega preso a um registro. Lavrar sem estoque não é possível — a tela diz
 * para atualizar o aplicativo com sinal.
 */
const ESTOQUE: Record<'np' | 'aa', string[]> = { np: [], aa: [] };

export type Faixa = 'np' | 'aa';

export function carregarNumeros(numeros: Partial<Record<Faixa, string[]>>): void {
    ESTOQUE.np = [...(numeros.np ?? [])];
    ESTOQUE.aa = [...(numeros.aa ?? [])];
}

/** O próximo número livre — `null` quando o estoque acabou. */
export const numeroReservado = (faixa: Faixa): string | null => ESTOQUE[faixa][0] ?? null;

/** Tira o número do estoque ao lavrar o documento. */
export const consumirNumero = (faixa: Faixa): string | null => ESTOQUE[faixa].shift() ?? null;

export const restamNaFaixa = (faixa: Faixa): number => ESTOQUE[faixa].length;

export const nomeDoDocumento = (faixa: Faixa): string =>
    faixa === 'np' ? 'Notificação Preliminar' : 'Auto de Apreensão';

export const siglaDoDocumento = (faixa: Faixa): string => (faixa === 'np' ? 'NP' : 'AA');
