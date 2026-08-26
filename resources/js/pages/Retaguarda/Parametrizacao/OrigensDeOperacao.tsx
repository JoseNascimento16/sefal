import CrudLookup, {
    type DefinicaoLookup,
    type ItemLookup,
} from '@/components/retaguarda/crud-lookup';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/retaguarda/parametrizacao/origens-de-operacao';

/** Origens de Operação — Parametrização. Ver {@see CrudLookup}. */
export default function OrigensDeOperacao({
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

OrigensDeOperacao.layout = {
    breadcrumbs: [{ title: 'Origens de Operação', href: index() }],
};
