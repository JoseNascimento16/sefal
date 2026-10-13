/* ============================================================================
   Quem está com o aparelho na mão, agora
   ----------------------------------------------------------------------------
   Módulo minúsculo e de propósito: é o ÚNICO lugar onde a identidade do fiscal
   e a equipe dele podem mudar. Todo o resto do aplicativo lê `FISCAL` e
   `EQUIPE` daqui (direta ou indiretamente) e nunca decide por conta própria de
   quem é a tela.

   A identidade vem do SERVIDOR (`/app/api/entrar` e `/app/api/sessao`): o
   fiscal, a equipe atual e todas as equipes em que ele está. São `let` porque
   só se conhecem depois do login; em ESM a importação é uma ligação viva, então
   os módulos que importam estes nomes veem o valor novo.

   O CONTORNO da área ainda vem do desenho do aplicativo (`dados-equipes.ts`),
   achado pelo código da equipe: o banco não guarda geometria de área.
   ============================================================================ */

import { RECORTES, areaDaEquipe, type Anel, type Identidade, type Recorte } from './dados-equipes';

/** A equipe do fiscal, do jeito que as telas precisam dela. */
export type EquipeAtual = {
    /** C1, C2, B2… */
    codigo: string;
    /** "Equipe C1" — pronto para escrever na tela. */
    nome: string;
    /** "Área 5", "Itinerante", "Noturna". */
    area: string;
    /** A região que dá nome à área: "Boca do Rio". */
    areaNome: string;
    encarregado: string;
    recorte: Recorte;
    recorteRotulo: string;
    /** A frase de uma linha que explica o contorno no mapa. */
    recorteExplicacao: string;
    turno: string;
    bairros: string[];
    /** O contorno da área no mapa — `null` na Itinerante e na Noturna. */
    contorno: Anel | null;
    corredores: { nome: string; linha: Anel }[] | null;
};

/** O que o servidor devolve no login e em `/app/api/sessao`. */
export type SessaoDoServidor = {
    fiscal: Identidade;
    equipe: {
        codigo: string;
        nome: string;
        area: string;
        areaNome: string;
        encarregado: string;
        recorte: Recorte;
        turno: string;
        bairros: string[];
    } | null;
    equipes: string[];
};

const SEM_EQUIPE: EquipeAtual = {
    codigo: '',
    nome: 'Sem equipe',
    area: '',
    areaNome: '',
    encarregado: '',
    recorte: 'cidade',
    recorteRotulo: RECORTES.cidade.rotulo,
    recorteExplicacao: RECORTES.cidade.explicacao,
    turno: '',
    bairros: [],
    contorno: null,
    corredores: null,
};

export let FISCAL: Identidade = { nome: '', matricula: '', setor: '', papel: 'Fiscal', turno: '', iniciais: '', desde: '' };
export let EQUIPE: EquipeAtual = SEM_EQUIPE;
/** Todas as equipes do fiscal — a fila é a soma delas. */
export let EQUIPES: string[] = [];

/** Guarda quem entrou. Chamado antes de a árvore autenticada montar. */
export function aplicarSessao(sessao: SessaoDoServidor): void {
    FISCAL = sessao.fiscal;
    EQUIPES = sessao.equipes;

    const e = sessao.equipe;

    if (!e) {
        EQUIPE = SEM_EQUIPE;

        return;
    }

    /* O desenho só vale se for o desta equipe: `areaDaEquipe` cai num padrão
       quando não conhece o código, e um contorno alheio enganaria o fiscal. */
    const desenho = areaDaEquipe(e.codigo);
    const doMesmo = desenho.equipe === e.codigo;

    EQUIPE = {
        codigo: e.codigo,
        nome: e.nome,
        area: e.area,
        areaNome: e.areaNome,
        encarregado: e.encarregado,
        recorte: e.recorte,
        recorteRotulo: RECORTES[e.recorte].rotulo,
        recorteExplicacao: RECORTES[e.recorte].explicacao,
        turno: e.turno,
        bairros: e.bairros,
        contorno: doMesmo ? desenho.contorno : null,
        corredores: doMesmo ? desenho.corredores : null,
    };
}

/**
 * O meio da área da equipe — onde o mapa abre.
 *
 * Sem isto o mapa abriria sempre no mesmo ponto, e o fiscal da Itinerante veria
 * a tela centrada na área de outra equipe. `null` quando a área não tem
 * geografia desenhada: lá vale o centro padrão da cidade.
 */
export const centroDaArea = (): { lat: number; lng: number } | null => {
    const pontos = EQUIPE.contorno ?? EQUIPE.corredores?.flatMap((c) => c.linha) ?? null;

    if (!pontos || pontos.length === 0) {
        return null;
    }

    return {
        lat: pontos.reduce((soma, p) => soma + p[0], 0) / pontos.length,
        lng: pontos.reduce((soma, p) => soma + p[1], 0) / pontos.length,
    };
};
