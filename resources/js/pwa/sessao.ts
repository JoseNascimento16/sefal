/* ============================================================================
   PROTÓTIPO — quem está com o aparelho na mão, agora
   ----------------------------------------------------------------------------
   Módulo minúsculo e de propósito: é o ÚNICO lugar onde a identidade do fiscal
   e a equipe dele podem mudar. Todo o resto do aplicativo lê `FISCAL` e
   `EQUIPE` daqui (direta ou indiretamente) e nunca decide por conta própria de
   quem é a tela.

   ⚠️ Por que são `let` e não `const`: no protótipo não há servidor nem sessão de
   verdade — quem escolhe a identidade é a matrícula digitada na porta, e ela só
   se conhece depois de o aplicativo já ter carregado. A troca acontece uma vez,
   no `entrarComMatricula`, ANTES de a árvore autenticada ser montada; os
   módulos que importam estes nomes veem o valor novo porque em ESM a
   importação é uma ligação viva, não uma cópia.

   Quando o aplicativo passar a falar com o servidor, este módulo vira o lugar
   onde o retorno do login é guardado — a forma não muda.
   ============================================================================ */

import { RECORTES, identidadePorMatricula, type Anel, type AreaSefal, type Identidade, type Recorte } from './dados-equipes';

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

const montarEquipe = (area: AreaSefal): EquipeAtual => ({
    codigo: area.equipe,
    nome: `Equipe ${area.equipe}`,
    area: area.area,
    areaNome: area.regiao,
    encarregado: area.encarregado,
    recorte: area.recorte,
    recorteRotulo: RECORTES[area.recorte].rotulo,
    recorteExplicacao: RECORTES[area.recorte].explicacao,
    turno: area.turno,
    bairros: area.bairros,
    contorno: area.contorno,
    corredores: area.corredores,
});

const inicial = identidadePorMatricula(null);

export let FISCAL: Identidade = inicial.fiscal;
export let EQUIPE: EquipeAtual = montarEquipe(inicial.area);

/**
 * Troca quem está usando o aplicativo.
 *
 * Chamado pela tela de entrada com o que foi digitado. Mudar a identidade muda
 * a EQUIPE e, com ela, a fila de demandas e o contorno da área no mapa — que é
 * exatamente o ponto: a demanda não chega para o fiscal, chega para a equipe.
 */
/**
 * O meio da área da equipe — onde o mapa abre.
 *
 * Sem isto o mapa abriria sempre na Boca do Rio, e o fiscal da Itinerante veria
 * a tela centrada na área de outra equipe, com os corredores dele fora do
 * quadro. `null` na Noturna, que não tem geografia própria: lá vale o centro
 * padrão da cidade.
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

export function entrarComMatricula(matricula: string | null): void {
    const { fiscal, area } = identidadePorMatricula(matricula);

    FISCAL = fiscal;
    EQUIPE = montarEquipe(area);
}
