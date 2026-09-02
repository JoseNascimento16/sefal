/*
|------------------------------------------------------------------------------
| PROTÓTIPO — o vocabulário e as CONTAS das duas telas de mapa
|------------------------------------------------------------------------------
|
| ⚠️ Módulo único do protótipo dos mapas: tipos, cores de situação, rótulos e as
| agregações que os painéis mostram (a cidade agora, o foco do dia, o ranking de
| regiões, a frase de leitura).
|
| Os DADOS não moram aqui — moram em `App\Support\Prototipo\MapasFicticios`, que
| deriva a área/equipe de cada bairro da MESMA fonte que a tela de Áreas e
| Equipes mostra (`EstruturaFicticia`). Uma segunda lista bairro→equipe no front
| discordaria dela no primeiro ajuste, e o mapa passaria a mandar o gestor cobrar
| a equipe errada.
|
| As CONTAS, essas, moram aqui de propósito: é a RN-06 do desenho da Retaguarda —
| número de painel sai da mesma lista que o mapa desenha, e não de um segundo
| cálculo no servidor. Assim o painel não pode discordar da mancha ao lado dele,
| e ele reage ao filtro do gestor no mesmo instante.
|
| Quando o protótipo virar produção, os tipos passam a descrever o retorno real
| do controller e as contas continuam onde estão.
|
*/

import { GRADIENTE_CALOR } from '@/lib/mapa';

// ── Vocabulário ─────────────────────────────────────────────────────────────

/** A situação de um ponto conhecido — a MESMA do aplicativo do fiscal. */
export type Situacao = 'regular' | 'irregular';

/** Uma equipe, como o filtro do gestor precisa dela. */
export interface Equipe {
    equipe: string;
    area: string;
    regiao: string;
    encarregado: string;
    /** `cidade` (a Noturna) filtra por TURNO, não por geografia — ver `noRecorte`. */
    recorte: 'bairros' | 'corredores' | 'cidade';
    turno: string;
}

/** Um ponto conhecido do mapa: quem trabalha ali, e em que situação. */
export interface Ponto {
    id: string;
    nome: string;
    apelido: string;
    atividade: string;
    emoji: string;
    situacao: Situacao;
    /** Dias VENCIDOS desde o retorno prometido — `null` quando não há retorno. */
    retorno_ha_dias: number | null;
    permissao: string | null;
    bairro: string;
    area: string;
    regiao: string;
    equipe: string;
    encarregado: string;
    /** Outras equipes que também cobrem o bairro — aviso, não erro. */
    tambem_de: string[];
    lat: number;
    lng: number;
    turno: string;
    ultima_em: string;
}

/** Uma fiscalização que entrou hoje, vinda do aplicativo do fiscal. */
export interface Registro {
    id: string;
    protocolo: string;
    apelido: string;
    atividade: string;
    emoji: string;
    situacao: Situacao;
    ocorrencia: string;
    fiscal: string;
    bairro: string;
    area: string;
    regiao: string;
    equipe: string;
    lat: number;
    lng: number;
    turno: string;
    ha_minutos: number;
}

/** Um fiscal em campo agora, com o último ponto conhecido. */
export interface FiscalEmCampo {
    id: string;
    nome: string;
    curto: string;
    matricula: string;
    iniciais: string;
    bairro: string;
    area: string;
    equipe: string;
    lat: number;
    lng: number;
    turno: string;
    em_campo_ha: number;
    registros_hoje: number;
}

/** Um bairro do mapa de calor, com a estrutura que responde por ele. */
export interface BairroDoCalor {
    bairro: string;
    lat: number;
    lng: number;
    peso: number;
    area: string;
    regiao: string;
    equipe: string;
    encarregado: string;
    tambem_de: string[];
}

/**
 * Um ponto de calor, como tupla: `[bairro, lat, lng, dias, noturno]`.
 *
 * Tupla e não objeto porque são ~700 pontos e o nome do bairro, a área e o
 * encarregado viajariam repetidos setecentas vezes. O índice aponta para a lista
 * de bairros, que vem uma vez só.
 */
export type PontoDeCalor = [number, number, number, number, number];

/** As janelas de leitura do mapa de calor — as mesmas do aplicativo do fiscal. */
export const JANELAS = [7, 30, 90] as const;

export type Janela = (typeof JANELAS)[number];

/**
 * A partir de quantos por cento uma variação vira NOTÍCIA (subiu / caiu).
 *
 * Abaixo disso a tela diz "estável", e é honestidade: numa janela curta, uma
 * ocorrência a mais ou a menos move a conta alguns por cento sem que nada tenha
 * mudado na rua. Chamar isso de "subiu" mandaria equipe atrás de ruído.
 *
 * ⚠️ O limiar é UM para as três leituras — a frase, o texto da coluna e a cor
 * dela. Com dois valores, a frase diria "estável" ao lado de um "+11%" pintado
 * de laranja, e a tela se contradiria em dois centímetros.
 */
export const LIMIAR_VARIACAO = 12;

/**
 * As cores dos pinos, para a legenda e para os cartões.
 *
 * ⚠️ Elas repetem o que o `.rt-pino-*` do CSS pinta, e a repetição é consciente:
 * a legenda precisa da cor como VALOR (ela desenha um ponto colorido em linha
 * com o texto), e não como classe. Mudou aqui, mude lá — os dois estão no mesmo
 * bloco de protótipo do `retaguarda.css`.
 */
export const COR_DO_PINO = {
    regular: '#4ddba4',
    irregular: '#ff9a4d',
    retorno: '#ff7043',
    hoje: '#4d9fdc',
    fiscal: '#7cc4ff',
} as const;

export const GRADIENTE_CSS = `linear-gradient(90deg, ${Object.values(GRADIENTE_CALOR).join(', ')})`;

// ── O recorte do gestor ─────────────────────────────────────────────────────

/**
 * O filtro está satisfeito por esta linha?
 *
 * A regra tem uma dobra que vale explicar: equipe de recorte `cidade` (a
 * Noturna) NÃO tem bloco de bairros — o recorte dela é o TURNO. Filtrar por ela
 * comparando o código da equipe devolveria uma cidade vazia, que é a leitura
 * exatamente invertida: ela cobre Salvador inteira. Então, para ela, o filtro
 * seleciona o que foi registrado à NOITE, em qualquer bairro.
 */
export function noRecorte(
    linha: { equipe: string; turno: string },
    equipe: string | null,
    equipes: Equipe[],
): boolean {
    if (equipe === null) {
        return true;
    }

    const escolhida = equipes.find((e) => e.equipe === equipe);

    if (escolhida?.recorte === 'cidade') {
        return linha.turno === escolhida.turno;
    }

    return linha.equipe === equipe;
}

/** O recorte EM PALAVRAS — vai no painel e no documento exportado. */
export function recorteEmPalavras(equipe: string | null, equipes: Equipe[]): string {
    if (equipe === null) {
        return 'Toda Salvador';
    }

    const escolhida = equipes.find((e) => e.equipe === equipe);

    if (escolhida === undefined) {
        return `Equipe ${equipe}`;
    }

    if (escolhida.recorte === 'cidade') {
        return `Equipe ${escolhida.equipe} · ${escolhida.area} (turno ${escolhida.turno.toLowerCase()}, toda a cidade)`;
    }

    return `Equipe ${escolhida.equipe} · ${escolhida.area} (${escolhida.regiao})`;
}

// ── As contas do Mapa ao Vivo ───────────────────────────────────────────────

export interface Foco {
    bairro: string;
    area: string;
    equipe: string;
    encarregado: string;
    ocorrencias: number;
    fatia: number;
}

/**
 * A região com maior incidência HOJE — o "foco do dia".
 *
 * Conta só o que é irregular: um bairro com vinte registros todos regulares está
 * bem fiscalizado, não em crise. Mandar operação para lá seria gastar equipe
 * onde o trabalho já foi feito.
 */
export function focoDoDia(registros: Registro[], pontos: Ponto[]): Foco | null {
    const irregulares = registros.filter((r) => r.situacao === 'irregular');

    if (irregulares.length === 0) {
        return null;
    }

    const porBairro = new Map<string, number>();

    for (const r of irregulares) {
        porBairro.set(r.bairro, (porBairro.get(r.bairro) ?? 0) + 1);
    }

    const [bairro, ocorrencias] = [...porBairro.entries()].sort((a, b) => b[1] - a[1])[0];
    // A estrutura do bairro vem dos pontos conhecidos, que já a trazem derivada
    // da fonte única — o registro do dia não precisa repeti-la.
    const ref = pontos.find((p) => p.bairro === bairro) ?? null;

    return {
        bairro,
        area: ref?.area ?? '—',
        equipe: ref?.equipe ?? '—',
        encarregado: ref?.encarregado ?? '—',
        ocorrencias,
        fatia: Math.round((ocorrencias / irregulares.length) * 100),
    };
}

/** "há 12 min" / "há 2 h 20" — o tempo como quem fala, não como quem calcula. */
export function haQuantoTempo(minutos: number): string {
    if (minutos < 60) {
        return `há ${minutos} min`;
    }

    const horas = Math.floor(minutos / 60);
    const resto = minutos % 60;

    // Os minutos vão com dois dígitos: "há 8 h 2" lê como número interrompido,
    // "há 8 h 02" lê como hora.
    return resto === 0
        ? `há ${horas} h`
        : `há ${horas} h ${String(resto).padStart(2, '0')}`;
}

/** "há 3 dias" — o vencimento do retorno, com o plural certo. */
export function haQuantosDias(dias: number): string {
    return dias === 1 ? 'há 1 dia' : `há ${dias} dias`;
}

// ── As contas do Mapa de Calor ──────────────────────────────────────────────

export interface LinhaDoRanking {
    posicao: number;
    bairro: string;
    area: string;
    regiao: string;
    equipe: string;
    encarregado: string;
    ocorrencias: number;
    fatia: number;
    /** Variação percentual contra o período anterior de igual tamanho. */
    variacao: number;
    /** `null` quando não havia nada no período anterior — não é "+∞%". */
    variacao_conhecida: boolean;
}

/**
 * O ranking das regiões: quanto cada bairro concentra na janela, e como isso
 * mudou em relação ao período anterior de igual tamanho.
 *
 * A comparação é com o período ANTERIOR, e não com a média do ano, porque é a
 * pergunta da operação: "isto está piorando desde a última vez que olhei?". Por
 * isso o servidor manda 180 dias mesmo quando a janela é de 90 — sem os 180, a
 * coluna de variação seria invenção.
 */
export function ranking(
    pontos: PontoDeCalor[],
    bairros: BairroDoCalor[],
    janela: Janela,
): LinhaDoRanking[] {
    const agora = new Map<number, number>();
    const antes = new Map<number, number>();

    for (const [indice, , , dias] of pontos) {
        if (dias <= janela) {
            agora.set(indice, (agora.get(indice) ?? 0) + 1);
        } else if (dias <= janela * 2) {
            antes.set(indice, (antes.get(indice) ?? 0) + 1);
        }
    }

    const total = [...agora.values()].reduce((s, n) => s + n, 0);

    return [...agora.entries()]
        .sort((a, b) => b[1] - a[1])
        .map(([indice, ocorrencias], i) => {
            const bairro = bairros[indice];
            const anterior = antes.get(indice) ?? 0;

            return {
                posicao: i + 1,
                bairro: bairro.bairro,
                area: bairro.area,
                regiao: bairro.regiao,
                equipe: bairro.equipe,
                encarregado: bairro.encarregado,
                ocorrencias,
                fatia: total > 0 ? Math.round((ocorrencias / total) * 100) : 0,
                variacao:
                    anterior > 0 ? Math.round(((ocorrencias - anterior) / anterior) * 100) : 0,
                variacao_conhecida: anterior > 0,
            };
        });
}

/**
 * A LEITURA EM UMA FRASE — o que a tela precisa dizer a quem tem trinta
 * segundos entre duas reuniões.
 *
 * Ela compara o líder com a MÉDIA da cidade, e não com o segundo colocado: "3×
 * a média" diz que aquilo é fora do normal; "o dobro do segundo" só diz que ele
 * é maior que o vizinho, o que todo primeiro colocado é.
 */
export function leituraEmUmaFrase(
    linhas: LinhaDoRanking[],
    janela: Janela,
): { destaque: string; resto: string } | null {
    const lider = linhas[0];

    if (lider === undefined) {
        return null;
    }

    const total = linhas.reduce((s, l) => s + l.ocorrencias, 0);
    const media = total / linhas.length;
    const vezes = media > 0 ? lider.ocorrencias / media : 0;

    const comparacao =
        vezes >= 1.6
            ? ` — ${vezes.toFixed(1).replace('.', ',')}× a média da cidade`
            : ' — em linha com a média da cidade';

    const tendencia = lider.variacao_conhecida
        ? lider.variacao >= LIMIAR_VARIACAO
            ? `, e subiu ${lider.variacao}% contra os ${janela} dias anteriores`
            : lider.variacao <= -LIMIAR_VARIACAO
              ? `, mas caiu ${Math.abs(lider.variacao)}% contra os ${janela} dias anteriores`
              : ', estável contra o período anterior'
        : '';

    /*
     * A frase volta PARTIDA em duas, e não como texto único: o nome do bairro
     * sai em negrito na tela, e recortá-lo depois por busca de texto ("split no
     * ' concentra'") quebraria no dia em que a redação da frase mudasse.
     */
    return {
        destaque: lider.bairro,
        resto:
            ` concentra ${lider.fatia}% das ocorrências dos últimos ${janela} dias` +
            `${comparacao}${tendencia}.`,
    };
}

/**
 * A recomendação de operação: onde mandar equipe, e por quê.
 *
 * Ela olha o líder do ranking, mas não só: um bairro em SUBIDA forte na segunda
 * posição costuma ser a melhor aposta, porque o líder já é conhecido e
 * provavelmente já tem operação de rotina. A frase diz qual dos dois motivos
 * está mandando — recomendação sem motivo escrito é adivinhação com aparência de
 * dado.
 */
export function recomendacao(
    linhas: LinhaDoRanking[],
    janela: Janela,
): { alvo: LinhaDoRanking; motivo: string } | null {
    if (linhas.length === 0) {
        return null;
    }

    const subindo = linhas
        .slice(0, 6)
        .filter((l) => l.variacao_conhecida && l.variacao >= 30)
        .sort((a, b) => b.variacao - a.variacao)[0];

    if (subindo !== undefined && subindo.posicao > 1) {
        return {
            alvo: subindo,
            motivo:
                `Está em ${subindo.posicao}º lugar, mas cresceu ${subindo.variacao}% contra os ` +
                `${janela} dias anteriores — é onde a situação está mudando, e não onde ela já é conhecida.`,
        };
    }

    return {
        alvo: linhas[0],
        motivo:
            `É a maior concentração da janela (${linhas[0].fatia}% das ocorrências dos últimos ` +
            `${janela} dias) e responde à Equipe ${linhas[0].equipe}, de ${linhas[0].encarregado}.`,
    };
}
