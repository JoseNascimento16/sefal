import CrudLookup, {
    type DefinicaoLookup,
    type ItemLookup,
} from '@/components/retaguarda/crud-lookup';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/retaguarda/parametrizacao/unidades-de-medida';

/** Unidades de Medida — Parametrização. Ver {@see CrudLookup}. */
export default function UnidadesDeMedida({
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

UnidadesDeMedida.layout = {
    breadcrumbs: [{ title: 'Unidades de Medida', href: index() }],
};
