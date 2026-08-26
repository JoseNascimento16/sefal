import CrudLookup, {
    type DefinicaoLookup,
    type ItemLookup,
} from '@/components/retaguarda/crud-lookup';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/retaguarda/parametrizacao/tipos-de-infracao';

/**
 * Tipos de Infração — Parametrização.
 *
 * A tela inteira é o {@see CrudLookup}, que serve as seis listas de escolha; o
 * que esta página traz é o que só ela sabe: as suas rotas (tipadas) e a sua
 * trilha. O nome dos campos e o texto de apoio vêm do servidor, junto com a
 * validação que os exige.
 */
export default function TiposDeInfracao({
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

TiposDeInfracao.layout = {
    breadcrumbs: [{ title: 'Tipos de Infração', href: index() }],
};
