import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { PainelDeDenuncias } from '@/components/retaguarda/painel-de-denuncias';
import { index } from '@/routes/retaguarda/denuncias/fala-salvador';

/**
 * Denúncias do Fala Salvador (Disque 156) — PROTÓTIPO.
 *
 * Casca fina, como a irmã do e-Salvador: a mecânica do módulo vive em
 * `PainelDeDenuncias`. Ver o cabeçalho de `ESalvador.tsx` para o porquê.
 *
 * ── O que o Fala Salvador tem de diferente ──────────────────────────────────
 *
 * O atendimento é por TELEFONE, e isso muda o dado: a denúncia pode ser
 * ANÔNIMA, o relato é a transcrição do que o atendente ouviu — texto mais solto,
 * às vezes sem número nem ponto de referência —, a categoria é a que o atendente
 * escolheu, e não há anexo (ninguém anexa foto por telefone).
 *
 * O endereço impreciso não é defeito de cadastro: é a realidade do canal, e a
 * tela o marca porque é ele que decide se dá para mandar equipe ao local.
 */
export default function FalaSalvador(props: ComponentProps<typeof PainelDeDenuncias>) {
    return (
        <>
            <Head title="Denúncias do Fala Salvador" />
            <PainelDeDenuncias {...props} />
        </>
    );
}

FalaSalvador.layout = {
    breadcrumbs: [
        { title: 'Denúncias', href: index() },
        { title: 'Fala Salvador', href: index() },
    ],
};
