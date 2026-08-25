<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * O que o fiscal enquadra numa autuação — "Sem permissão para a atividade",
 * "Área não autorizada", "Produto impróprio para consumo".
 *
 * A descrição é apoio à escolha em rua: o nome curto cabe na lista do aparelho,
 * e o texto explica o que aquele enquadramento abrange.
 *
 * @property string|null $descricao
 */
#[Fillable(['nome', 'descricao', 'ativo'])]
class TipoInfracao extends ListaDeEscolha
{
    protected $table = 'tipos_infracao';
}
