import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { PainelDeDenuncias } from '@/components/retaguarda/painel-de-denuncias';
import { index } from '@/routes/retaguarda/denuncias/e-salvador';

/**
 * Denúncias do e-Salvador — PROTÓTIPO.
 *
 * Casca fina de propósito: a mecânica do módulo (as duas etapas, o lote, a
 * derivação bairro → área, o trâmite) vive em `PainelDeDenuncias`, e as duas
 * telas de canal a compartilham. Escrever a tela duas vezes daria dois donos à
 * mesma regra, e um dia só uma delas ganharia o campo novo.
 *
 * O que esta casca declara é o que SÓ ela sabe: o título da aba do navegador e a
 * trilha de navegação — que a `layout` do Inertia recebe como propriedade
 * estática, e por isso não pode sair de um prop do servidor.
 *
 * ── O que o e-Salvador tem de diferente ─────────────────────────────────────
 *
 * O cidadão abre a denúncia autenticado no portal, então o requerente vem
 * SEMPRE identificado (nome, CPF, e-mail, telefone), o endereço vem estruturado
 * e ele pode anexar foto e documento. O que a tela mostra a mais ou a menos sai
 * da configuração do canal, no servidor — não de uma condição escrita aqui.
 */
export default function ESalvador(props: ComponentProps<typeof PainelDeDenuncias>) {
    return (
        <>
            <Head title="Denúncias do e-Salvador" />
            <PainelDeDenuncias {...props} />
        </>
    );
}

ESalvador.layout = {
    breadcrumbs: [
        { title: 'Denúncias', href: index() },
        { title: 'e-Salvador', href: index() },
    ],
};
