<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * O feitio da ação de fiscalização — "Fiscalização de rotina", "Operação
 * conjunta", "Feira livre". Diz que tipo de trabalho gerou o registro.
 */
#[Fillable(['nome', 'ativo'])]
class TipoOperacao extends ListaDeEscolha
{
    protected $table = 'tipos_operacao';
}
