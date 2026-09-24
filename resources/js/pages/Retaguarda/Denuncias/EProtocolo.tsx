import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { PainelDeDenuncias } from '@/components/retaguarda/painel-de-denuncias';
import { index } from '@/routes/retaguarda/denuncias/e-protocolo';

/**
 * Caixa de Entrada › e-Protocolo.
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
 * O atendimento presencial na sede da SEFAL. Sem integração: o Chefe de Setor
 * cadastra. Ainda não se sabe se passa pelo e-Salvador antes de chegar a ele.
 */
export default function EProtocolo(props: ComponentProps<typeof PainelDeDenuncias>) {
    return (
        <>
            <Head title="e-Protocolo" />
            <PainelDeDenuncias {...props} />
        </>
    );
}

EProtocolo.layout = {
    breadcrumbs: [
        { title: 'Caixa de Entrada', href: index() },
        { title: 'e-Protocolo', href: index() },
    ],
};
