import type { KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';

/**
 * Uma linha de grade que ABRE o registro — o mesmo comportamento em toda a
 * Retaguarda, num lugar só.
 *
 * O clique na linha inteira é confortável e fica. O que faltava era o resto:
 *
 *  - **teclado.** A linha passa a receber foco (`tabIndex`) e a responder a
 *    `Enter` e `Espaço`. Sem isso a única porta para o registro existia apenas
 *    para quem usa mouse — e a árvore de acessibilidade via a grade como texto
 *    parado, sem nenhum elemento acionável dentro dela;
 *  - **pista.** O `title` diz o que o clique faz. Ponteiro em forma de mão é
 *    dica de mouse: some no teclado, no leitor de tela e no toque.
 *
 * ⚠️ De propósito **não** se declara `role="button"` aqui. Trocar o papel de uma
 * `<tr>` tira as células do contexto de linha e o leitor de tela para de dizer a
 * que coluna cada valor pertence — a grade fica navegável e ilegível. A linha
 * continua sendo linha; o que ela ganhou é foco e tecla.
 *
 * `classes` acrescenta classes de ESTADO da linha (por exemplo `pendente`, que
 * põe a marca laranja na ponta). Elas entram por aqui, e não num `className` ao
 * lado do espalhamento, porque `className` declarado duas vezes no mesmo elemento
 * é sobrescrita silenciosa — o `clicavel` se perderia e a linha deixaria de
 * mostrar que abre.
 *
 * @example
 *   <tr {...linhaClicavel(() => abrir(item), 'Abrir o cadastro', pendente && 'pendente')}>
 */
export function linhaClicavel(
    abrir: () => void,
    descricao = 'Abrir o registro',
    ...classes: (string | false | null | undefined)[]
): {
    className: string;
    tabIndex: number;
    title: string;
    onClick: () => void;
    onKeyDown: (evento: KeyboardEvent<HTMLTableRowElement>) => void;
} {
    return {
        className: cn('clicavel', ...classes),
        tabIndex: 0,
        title: descricao,
        onClick: abrir,
        onKeyDown: (evento) => {
            if (evento.key !== 'Enter' && evento.key !== ' ') {
                return;
            }

            // O Espaço rola a página por padrão: sem isto, quem abrisse pelo
            // teclado veria a tela pular junto.
            evento.preventDefault();
            abrir();
        },
    };
}
