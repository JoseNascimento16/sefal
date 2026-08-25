import CrudLookup, {
    type DefinicaoLookup,
    type ItemLookup,
} from '@/components/retaguarda/crud-lookup';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/retaguarda/parametrizacao/tipos-de-operacao';

/** Tipos de Operação — Parametrização. Ver {@see CrudLookup}. */
export default function TiposDeOperacao({
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

TiposDeOperacao.layout = {
    breadcrumbs: [{ title: 'Tipos de Operação', href: index() }],
};
