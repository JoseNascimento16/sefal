/**
 * Concordância de número nos textos de tela — o par de `App\Support\Texto` no
 * front.
 *
 * A quantidade é conhecida no momento de escrever a frase, então "2 registro(s)"
 * é sempre evitável: os parênteses são o sistema empurrando para o leitor uma
 * conta que ele mesmo já fez, e leem como rascunho.
 *
 * @example
 *   contar(1, 'registro', 'registros')      // '1 registro'
 *   contar(0, 'verificação', 'verificações') // '0 verificações'
 */

/** A forma certa da palavra para a quantidade — o singular só no 1. */
export function plural(
    quantidade: number,
    singular: string,
    formaPlural: string,
): string {
    return quantidade === 1 ? singular : formaPlural;
}

/** A quantidade com a palavra concordando: "1 registro", "2 registros". */
export function contar(
    quantidade: number,
    singular: string,
    formaPlural: string,
): string {
    return `${quantidade} ${plural(quantidade, singular, formaPlural)}`;
}
