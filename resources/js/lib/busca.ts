// Núcleo da BUSCA INTELIGENTE — o padrão de pesquisa do projeto: uma barra só,
// ampla, que interpreta a frase em FACETAS (predicados que a tela conhece) mais
// TERMOS LIVRES. Nada de chips e botões de filtro paralelos.
//
// O parse é determinístico e roda no cliente: sem chamada de rede, sem modelo de
// linguagem. Cada tela declara as suas facetas (as expressões do seu domínio) e
// deixa o resto do texto virar termo livre.
//
// ⚠️ Busca é texto livre do usuário: quando o filtro precisar ir ao servidor,
// mande no CORPO de um POST. Em query string, um `--` ou uma aspa fazem o WAF da
// Prefeitura barrar a requisição, e o navegador mostra isso como erro de CORS.

/**
 * Minúsculas, sem acento, sem espaço nas pontas. `NFD` separa a letra do
 * diacrítico e o intervalo de marcas combinantes o remove: "Fiscalização" →
 * "fiscalizacao", "PÚBLICO" → "publico".
 *
 * Toda comparação de busca passa por aqui, dos dois lados: quem procura "acai"
 * tem de achar "Açaí".
 */
export function semAcento(valor: string | null | undefined): string {
    return String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

/**
 * Palavras de ligação: entram na frase natural ("denúncias do fiscal com
 * pendência") e não devem virar termo de comparação — senão nenhum registro
 * casa, porque "do" não está em campo nenhum.
 */
export const LIGACOES = new Set([
    'a',
    'as',
    'ao',
    'aos',
    'com',
    'da',
    'das',
    'de',
    'do',
    'dos',
    'e',
    'em',
    'na',
    'nas',
    'no',
    'nos',
    'o',
    'os',
    'ou',
    'para',
    'pela',
    'pelo',
    'por',
    'que',
    'sem',
    'um',
    'uma',
]);

/**
 * Uma faceta: se a expressão aparecer na consulta, ela é RETIRADA do texto e o
 * `valor` volta para a tela aplicar como predicado estruturado.
 */
export type Faceta<T> = {
    /** Expressão reconhecida (já em texto normalizado, sem acento). */
    expressao: RegExp;
    /** O que a tela recebe quando a expressão casa. */
    valor: T;
};

export type Consulta<T> = {
    /** Facetas reconhecidas, na ordem em que foram declaradas. */
    facetas: T[];
    /** O que sobrou da frase, já normalizado e sem palavras de ligação. */
    termos: string[];
};

/**
 * Interpreta a consulta: separa as facetas conhecidas dos termos livres.
 *
 * A ordem das facetas importa — declare as expressões mais específicas antes
 * ("mais de um endereço" antes de "endereço"), senão a genérica come a outra.
 *
 * @example
 *   const { facetas, termos } = parseConsulta(busca, [
 *       { expressao: /nao identificad\w*!/, valor: 'sem-alvo' },
 *   ]);
 */
export function parseConsulta<T>(
    consulta: string,
    facetas: Faceta<T>[] = [],
): Consulta<T> {
    let texto = ` ${semAcento(consulta)} `;
    const achadas: T[] = [];

    for (const faceta of facetas) {
        if (faceta.expressao.test(texto)) {
            achadas.push(faceta.valor);
            const global = faceta.expressao.flags.includes('g')
                ? faceta.expressao
                : new RegExp(
                      faceta.expressao.source,
                      `${faceta.expressao.flags}g`,
                  );

            texto = texto.replace(global, ' ');
        }
    }

    const termos = texto
        .split(/\s+/)
        .map((t) => t.trim())
        .filter((t) => t.length > 1 && !LIGACOES.has(t));

    return { facetas: achadas, termos };
}

/**
 * Todo termo tem de aparecer em ALGUM dos campos (E entre termos, OU entre
 * campos) — é o que faz "acai barra" achar o ambulante "Açaí do Barra".
 * Consulta vazia não filtra nada.
 */
export function casaTermos(
    termos: string[],
    campos: (string | null | undefined)[],
): boolean {
    if (termos.length === 0) {
        return true;
    }

    const alvo = campos.map((c) => semAcento(c)).join(' ');

    return termos.every((termo) => alvo.includes(termo));
}
