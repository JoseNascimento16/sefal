import CrudLookup, {
    type DefinicaoLookup,
    type ItemLookup,
} from '@/components/retaguarda/crud-lookup';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/retaguarda/parametrizacao/motivos-de-recusa';

/** Motivos de Recusa — Parametrização. Ver {@see CrudLookup}. */
export default function MotivosDeRecusa({
    definicao,
    itens,
}: {
    definicao: DefinicaoLookup;
    itens: ItemLookup[];
}) {
    return (
        <CrudLookup
            definicao={definicao}
            itens={itens}
            rotas={{ store, update, destroy }}
        />
    );
}

MotivosDeRecusa.layout = {
    breadcrumbs: [{ title: 'Motivos de Recusa', href: index() }],
};
