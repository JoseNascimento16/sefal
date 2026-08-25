import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/** Item do menu lateral, já resolvido pelo servidor. */
export type MenuItem = {
    rotulo: string;
    url: string;
    /** Chave do ícone — traduzida em `@/lib/icones-menu`. */
    icone: string;
};

/** Seção do menu lateral. `vazio` é o recado de "ainda vem por aí". */
export type MenuSecao = {
    rotulo: string;
    vazio: string | null;
    itens: MenuItem[];
};
