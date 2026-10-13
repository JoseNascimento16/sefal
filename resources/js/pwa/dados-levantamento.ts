/* ============================================================================
   O LEVANTAMENTO de ambulantes (censo por rua)
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

   O levantamento concluído vai ao SERVIDOR (`/app/api/levantamentos`), com a
   foto e o GPS de cada linha; sem sinal, espera na fila do aparelho. Os
   encarregados que assinam saem do cadastro das equipes, não do aparelho.
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
    /** URL da foto tirada na hora (para a miniatura). */
    foto: string | null;
    temFoto: boolean;
    /** O arquivo da foto — é ele que sobe ao servidor. */
    arquivo?: Blob | null;
    /** Onde o fiscal estava ao somar a linha. */
    latitude?: number | null;
    longitude?: number | null;
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
    /** O id que o aparelho deu — casa o recente do servidor com o da fila local. */
    clientId?: string;
    protocolo?: string;
    rua: string;
    bairro: string;
    equipes: string[];
    encarregados: string[];
    dataBr: string;
    linhas: number;
    situacao: 'concluido' | 'em-campo';
};

/** Levantamentos que a força-tarefa já fechou — o primeiro é o do cliente. */
/** Os levantamentos recentes das equipes do fiscal, como o servidor entregou no `kit`. */
export let LEVANTAMENTOS_RECENTES: Levantamento[] = [];

/** As equipes ativas da cidade — o cabeçalho escolhe entre elas. */
export let EQUIPES_DA_CIDADE: { equipe: string; regiao: string; encarregado: string }[] = [];

export function carregarLevantamentos(
    recentes: Levantamento[],
    equipes: { equipe: string; regiao: string; encarregado: string }[],
): void {
    LEVANTAMENTOS_RECENTES = recentes;
    EQUIPES_DA_CIDADE = equipes;
}

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
