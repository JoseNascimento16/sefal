<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Como se conta o que foi apreendido ou vistoriado — unidade, quilo, litro,
 * caixa. A sigla é o que sai no documento impresso em rua, onde não cabe o nome
 * por extenso.
 *
 * @property string $sigla
 */
#[Fillable(['nome', 'sigla', 'ativo'])]
class UnidadeMedida extends ListaDeEscolha
{
    protected $table = 'unidades_medida';
}
