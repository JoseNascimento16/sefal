<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Por que um cadastro feito em campo não foi aceito pelo Gestor — "Foto
 * ilegível", "Duplicado", "Dados insuficientes". É o que o fiscal lê ao ver o
 * seu cadastro voltar.
 */
#[Fillable(['nome', 'ativo'])]
class MotivoRecusa extends ListaDeEscolha
{
    protected $table = 'motivos_recusa';
}
