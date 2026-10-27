import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { PainelDeDenuncias } from '@/components/retaguarda/painel-de-denuncias';
import { index } from '@/routes/retaguarda/denuncias/fala-salvador';

/**
 * Caixa de Entrada › Fala Salvador.
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
 * O canal não tem integração e só os líderes o acessam: quem cadastra aqui é o
 * LÍDER, e o caso nasce na mesa dele. A resposta ao cidadão continua no canal.
 */
export default function FalaSalvador(props: ComponentProps<typeof PainelDeDenuncias>) {
    return (
        <>
            <Head title="Fala Salvador" />
            <PainelDeDenuncias {...props} />
        </>
    );
}

FalaSalvador.layout = {
    breadcrumbs: [
        { title: 'Caixa de Entrada', href: index() },
        { title: 'Fala Salvador', href: index() },
    ],
};
