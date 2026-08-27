import type { InertiaLinkProps } from '@inertiajs/react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
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
};

/** Seção do menu lateral. `vazio` é o recado de "ainda vem por aí". */
export type MenuSecao = {
    rotulo: string;
    vazio: string | null;
    itens: MenuItem[];
};
