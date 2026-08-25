<?php

namespace App\Http\Controllers\Retaguarda\Parametrizacao;

use App\Models\OrigemOperacao;
use App\Support\Parametrizacao\DefinicaoLookup;

/**
 * Origens de Operação — de onde veio a ordem de fiscalizar.
 */
class OrigensDeOperacaoController extends ControllerDeLookup
{
    protected function definicao(): DefinicaoLookup
    {
        return new DefinicaoLookup(
            modelo: OrigemOperacao::class,
            tela: 'origens-de-operacao',
            componente: 'Retaguarda/Parametrizacao/OrigensDeOperacao',
            titulo: 'Origens de Operação',
            singular: 'Origem de operação',
            genero: 'f',
            descricao: 'Por que a equipe foi até lá — denúncia do cidadão, cobrança de outro órgão, '
                .'planejamento próprio. É o que permite responder ao demandante depois.',
            exemplo: 'Ex.: Denúncia do cidadão',
        );
    }
}
