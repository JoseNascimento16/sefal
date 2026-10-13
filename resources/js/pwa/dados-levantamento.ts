/* ============================================================================
   PROTÓTIPO — o LEVANTAMENTO de ambulantes (censo por rua)
   ----------------------------------------------------------------------------
   Isto não é uma ideia nossa: o SEFAL já faz esse trabalho, em planilha. O
   cliente entregou um exemplar pronto — "LEVANTAMENTO AMBULANTES · RUA BARÃO
   DE MAUÁ · ARENOSO", levantado pelas equipes A1 e C1, encarregados César
   Amaral e Tânia Ornelas — com uma linha por ambulante: item, nome, CPF/RG,
   contato, referência do ponto, atividade e FOTO do equipamento.

   O documento revela duas coisas que a tela precisa respeitar:

   1. É trabalho em LOTE e em SEQUÊNCIA. O fiscal anda a rua e vai somando
      linhas — 11 numa rua só, numeradas 001, 002, 003. Uma tela de cadastro
      "salvar e voltar à lista" mataria o ritmo; o fluxo é adicionar, adicionar,
      adicionar.
   2. É trabalho CONJUNTO. Duas equipes e dois encarregados assinam o mesmo
      levantamento, então o cabeçalho não é do fiscal: é da força-tarefa.

   E é o insumo do mapa de calor: cada linha levantada é um ponto que o mapa
   passa a conhecer. Hoje o calor se alimenta só das fiscalizações; com o
   levantamento ele passa a saber onde estão os ambulantes ANTES da ocorrência.
   ============================================================================ */

export type LinhaLevantamento = {
    id: string;
    /** 001, 002, 003… — a numeração da planilha do cliente. */
    item: string;
    nome: string;
    documento: string;
    contato: string;
    referencia: string;
    atividade: string;
    /** URL da foto tirada na hora, ou null quando é foto encenada. */
    foto: string | null;
    temFoto: boolean;
};

/** As atividades que aparecem na coluna ATIVIDADE do levantamento real. */
export const ATIVIDADES_LEVANTAMENTO = [
    'Barraca de chapa',
    'Baiana de acarajé',
    'Carrinho de lanche',
    'Banca de frutas',
    'Água e refrigerante',
    'Espetinho',
    'Quiosque',
    'Tabuleiro',
    'Food truck',
    'Comércio informal',
];

export type Levantamento = {
    id: string;
    rua: string;
    bairro: string;
    equipes: string[];
    encarregados: string[];
    dataBr: string;
    linhas: number;
    situacao: 'concluido' | 'em-campo';
};

/** Levantamentos que a força-tarefa já fechou — o primeiro é o do cliente. */
export const LEVANTAMENTOS_RECENTES: Levantamento[] = [
    {
        id: 'lev-01',
        rua: 'Rua Barão de Mauá',
        bairro: 'Arenoso',
        equipes: ['A1', 'C1'],
        encarregados: ['César Amaral', 'Tânia Ornelas'],
        dataBr: '19/08/2026',
        linhas: 11,
        situacao: 'concluido',
    },
    {
        id: 'lev-02',
        rua: 'Largo da Mariquita',
        bairro: 'Rio Vermelho',
        equipes: ['C2'],
        encarregados: ['José Roberto'],
        dataBr: '12/08/2026',
        linhas: 23,
        situacao: 'concluido',
    },
    {
        id: 'lev-03',
        rua: 'Av. Oceânica — Farol ao Morro do Cristo',
        bairro: 'Barra',
        equipes: ['C2', 'I1'],
        encarregados: ['José Roberto', 'Roberto Moraes'],
        dataBr: '05/08/2026',
        linhas: 41,
        situacao: 'concluido',
    },
];

/** Ruas que a chefia já indicou para levantar — atalho do cabeçalho. */
export const RUAS_SUGERIDAS = [
    { rua: 'Rua Marquês de Caravelas', bairro: 'Barra' },
    { rua: 'Rua da Paciência', bairro: 'Rio Vermelho' },
    { rua: 'Rua Alfredo de Brito', bairro: 'Centro Histórico' },
    { rua: 'Praça Cairu', bairro: 'Comércio' },
    { rua: 'Rua Carlos Gomes', bairro: 'Barris' },
];

/**
 * Nomes para a foto encenada e para o preenchimento de demonstração.
 *
 * O botão "Preencher exemplo" existe para o dono ver a tela CHEIA sem digitar
 * onze linhas no celular — a mesma razão do botão "Simular" nas fotos do
 * registro rápido.
 */
export const EXEMPLOS_LEVANTAMENTO: Omit<LinhaLevantamento, 'id' | 'item'>[] = [
    { nome: 'Larissa Souza Reis', documento: '084.317.155-37', contato: '(71) 99148-8613', referencia: 'Nº 02', atividade: 'Barraca de chapa', foto: null, temFoto: true },
    { nome: 'Renata Ferreira de Jesus', documento: '292.839.305-68', contato: '(71) 98651-6602', referencia: 'Nº 01', atividade: 'Barraca de chapa', foto: null, temFoto: true },
    { nome: 'Célia Assis Silva', documento: '130.459.705-91', contato: '(71) 98894-3263', referencia: 'Nº 04', atividade: 'Baiana de acarajé', foto: null, temFoto: true },
    { nome: 'Marineide de Jesus de Souza', documento: '811.841.235-00', contato: '(71) 98251-5513', referencia: 'Nº 06', atividade: 'Banca de frutas', foto: null, temFoto: true },
    { nome: 'Erasmo Carlos Lima da Silva', documento: '549.435.745-72', contato: '(71) 99415-9796', referencia: 'Nº 09', atividade: 'Carrinho de lanche', foto: null, temFoto: false },
    { nome: 'Daiane Xavier Carvalho', documento: '864.574.265-90', contato: '(71) 98470-3986', referencia: 'Nº 05', atividade: 'Água e refrigerante', foto: null, temFoto: true },
];

export const numeroDoItem = (indice: number): string => String(indice + 1).padStart(3, '0');

/**
 * "equipe C2" / "equipes C2 e A1".
 *
 * Frase pronta, porque uma força-tarefa pode ser de uma equipe só e o rodapé do
 * levantamento é texto que o encarregado assina — "equipes C2" no singular
 * denuncia software, não órgão.
 */
export const listaEmPortugues = (itens: string[]): string => {
    if (itens.length <= 1) {
        return itens[0] ?? '';
    }

    return `${itens.slice(0, -1).join(', ')} e ${itens[itens.length - 1]}`;
};

export const frasePluralizada = (singular: string, plural: string, itens: string[]): string =>
    itens.length === 0
        ? ''
        : `${itens.length === 1 ? singular : plural} ${listaEmPortugues(itens)}`.trim();
