<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * De onde veio a ordem de fiscalizar — "Denúncia do cidadão", "Demanda do
 * Ministério Público", "Planejamento da SEMOP". É o que responde "por que
 * fomos lá?".
 */
#[Fillable(['nome', 'ativo'])]
class OrigemOperacao extends ListaDeEscolha
{
    protected $table = 'origens_operacao';
}
