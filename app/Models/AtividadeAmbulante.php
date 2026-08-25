<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * O que o permissionário vende ou faz — "Alimentos preparados", "Bebidas",
 * "Frutas e verduras". É a atividade autorizada na permissão dele.
 */
#[Fillable(['nome', 'ativo'])]
class AtividadeAmbulante extends ListaDeEscolha
{
    protected $table = 'atividades_ambulante';
}
