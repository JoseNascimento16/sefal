/* ============================================================================
   A estrutura da SEFAL no aplicativo: Área > Equipe > recorte
   ----------------------------------------------------------------------------
   A estrutura em si (áreas, equipes, encarregados, bairros, contorno) chega do
   servidor na sessão (`sessao.ts`), lida do mesmo cadastro que a Retaguarda
   mantém. Aqui ficam só os TIPOS e os rótulos de tela do recorte — o que não é
   dado, é vocabulário.

   Até 13/10/2026 este arquivo trazia uma cópia escrita à mão da estrutura e
   fiscais inventados, porque o aplicativo rodava sem servidor; a cópia saiu
   quando o aplicativo passou a falar com a Retaguarda.
   ============================================================================ */

/**
 * Como a área recorta a cidade — são TRÊS formas, não uma.
 *
 * Tratar as áreas como "bloco de bairros" faria a Noturna aparecer com zero
 * bairros, que é a leitura invertida: ela cobre todos. O nome dos valores é o
 * mesmo da parametrização da Retaguarda.
 */
export type Recorte = 'bairros' | 'corredores' | 'cidade';

/** Um anel de coordenadas — o contorno desenhado no mapa. */
export type Anel = [lat: number, lng: number][];

/** O que cada recorte quer dizer na tela — rótulo e explicação, num lugar só. */
export const RECORTES: Record<Recorte, { rotulo: string; explicacao: string }> = {
    bairros: {
        rotulo: 'Bloco de bairros',
        explicacao: 'O contorno é o bloco de bairros da equipe. Fora dele, o ponto é de outra equipe.',
    },
    corredores: {
        rotulo: 'Corredores',
        explicacao: 'A equipe percorre eixos de grande circulação — por isso o mapa traça linhas, e não um bloco.',
    },
    cidade: {
        rotulo: 'Cidade inteira, por turno',
        explicacao: 'A cobertura é toda Salvador: o recorte desta equipe é o TURNO, não a geografia.',
    },
};

/** Quem está no plantão, como a sessão do servidor o descreve. */
export type Identidade = {
    nome: string;
    matricula: string;
    /** O que o aplicativo diz embaixo do nome: setor, não equipe. */
    setor: string;
    papel: 'Encarregado' | 'Fiscal';
    turno: string;
    iniciais: string;
    desde: string;
};
