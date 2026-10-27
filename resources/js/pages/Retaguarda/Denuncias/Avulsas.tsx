import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { PainelDeDenuncias } from '@/components/retaguarda/painel-de-denuncias';
import { index } from '@/routes/retaguarda/denuncias/avulsas';

/**
 * Caixa de Entrada › Avulsas.
 *
 * Casca fina, como as outras três caixas: o fluxo inteiro (as abas, o cadastro,
 * o encaminhamento, o direcionamento, o trâmite e o retorno ao canal) vive em
 * `PainelDeDenuncias`, e o que muda de uma caixa para outra vem do SERVIDOR —
 * as abas, quem cadastra, onde o retorno é feito. Quatro telas escritas à parte
 * dariam quatro donos à mesma regra.
 *
 * O que esta casca declara é o que SÓ ela sabe: o título da aba do navegador e a
 * trilha de navegação, que a `layout` do Inertia recebe como propriedade
 * estática.
 *
 * Lista única, sem abas. Concluída a fiscalização, o chefe delibera: abre
 * processo no e-Salvador ou encerra só com a fiscalização.
 */
export default function Avulsas(props: ComponentProps<typeof PainelDeDenuncias>) {
    return (
        <>
            <Head title="Avulsas" />
            <PainelDeDenuncias {...props} />
        </>
    );
}

Avulsas.layout = {
    breadcrumbs: [
        { title: 'Caixa de Entrada', href: index() },
        { title: 'Avulsas', href: index() },
    ],
};
