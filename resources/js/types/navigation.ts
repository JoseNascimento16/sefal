import type { InertiaLinkProps } from '@inertiajs/react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

/**
 * O número vivo ao lado de um item do menu.
 *
 * `neutro` é tamanho ("128 cadastrados") e `alerta` é FILA — aparece em laranja e
 * só existe quando há o que fazer (o servidor não manda alerta em zero). Ver
 * `App\Support\ContadoresDoMenu`.
 */
export type MenuContador = {
    valor: number;
    tom: 'neutro' | 'alerta';
};

/** Item do menu lateral, já resolvido pelo servidor. */
export type MenuItem = {
    rotulo: string;
    url: string;
    /** Chave do ícone — traduzida em `@/lib/icones-menu`. */
    icone: string;
    /**
     * Quando presente, o item ABRE UM PAINEL sobre a tela atual em vez de
     * navegar — o valor é a chave do painel (hoje só `modo-gerente`). A `url`
     * continua vindo: é dela que o painel busca os dados.
     */
    modal: string | null;
    /** Rótulo de uma palavra, para o menu retraído (a doca). */
    curto: string;
    /** `null` quando o item não declara número, ou quando a contagem falhou. */
    contador: MenuContador | null;
};

/** Seção do menu lateral. `vazio` é o recado de "ainda vem por aí". */
export type MenuSecao = {
    rotulo: string;
    vazio: string | null;
    itens: MenuItem[];
};
