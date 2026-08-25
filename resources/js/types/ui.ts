import type { ReactNode } from 'react';

/**
 * O recado do servidor para a tela — ver `HandleInertiaRequests::recado()`.
 * `chave` muda a cada resposta com recado; é o que faz o aviso reaparecer quando
 * a mesma mensagem chega duas vezes seguidas.
 */
export type Recado = {
    sucesso: string | null;
    erro: string | null;
    chave: string | null;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
