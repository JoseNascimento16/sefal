import CrudLookup, {
    type DefinicaoLookup,
    type ItemLookup,
} from '@/components/retaguarda/crud-lookup';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/retaguarda/parametrizacao/atividades-do-ambulante';

/** Atividades do Ambulante — Parametrização. Ver {@see CrudLookup}. */
export default function AtividadesDoAmbulante({
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

AtividadesDoAmbulante.layout = {
    breadcrumbs: [{ title: 'Atividades do Ambulante', href: index() }],
};
