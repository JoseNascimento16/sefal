<?php

namespace App\Http\Controllers\Retaguarda\Parametrizacao;

use App\Models\TipoOperacao;
use App\Support\Parametrizacao\DefinicaoLookup;

/**
 * Tipos de Operação — o feitio do trabalho que gerou a fiscalização.
 */
class TiposDeOperacaoController extends ControllerDeLookup
{
    protected function definicao(): DefinicaoLookup
    {
        return new DefinicaoLookup(
            modelo: TipoOperacao::class,
            tela: 'tipos-de-operacao',
            componente: 'Retaguarda/Parametrizacao/TiposDeOperacao',
            titulo: 'Tipos de Operação',
            singular: 'Tipo de operação',
            genero: 'm',
            descricao: 'O feitio do trabalho em campo — rotina do dia, mutirão, operação conjunta '
                .'com outro órgão. É o que agrupa as fiscalizações quando se olha o mês inteiro.',
            exemplo: 'Ex.: Fiscalização de rotina',
        );
    }
}
