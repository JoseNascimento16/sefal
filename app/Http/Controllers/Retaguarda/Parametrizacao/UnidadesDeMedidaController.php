<?php

namespace App\Http\Controllers\Retaguarda\Parametrizacao;

use App\Models\UnidadeMedida;
use App\Support\Parametrizacao\CampoLookup;
use App\Support\Parametrizacao\DefinicaoLookup;

/**
 * Unidades de Medida — como se conta o que foi apreendido ou vistoriado.
 */
class UnidadesDeMedidaController extends ControllerDeLookup
{
    protected function definicao(): DefinicaoLookup
    {
        return new DefinicaoLookup(
            modelo: UnidadeMedida::class,
            tela: 'unidades-de-medida',
            componente: 'Retaguarda/Parametrizacao/UnidadesDeMedida',
            titulo: 'Unidades de Medida',
            singular: 'Unidade de medida',
            genero: 'f',
            descricao: 'Como se conta a mercadoria em uma apreensão ou vistoria. A sigla é o que '
                .'sai no documento impresso em rua, onde não cabe o nome por extenso.',
            exemplo: 'Ex.: Quilograma',
            campos: [
                new CampoLookup(
                    chave: 'sigla',
                    rotulo: 'Sigla',
                    maximo: 10,
                    exemplo: 'Ex.: kg',
                    ajuda: 'Até 10 caracteres — é a forma curta impressa no documento.',
                ),
            ],
        );
    }
}
