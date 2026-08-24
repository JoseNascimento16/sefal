<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Vínculo usuário ↔ setor. Existe como model próprio (e não só como tabela de
 * pivô anônima) porque os comandos de bootstrap manipulam o vínculo direto.
 *
 * @property int $id
 * @property int $user_id
 * @property int $setor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'setor_id'])]
class UserSetor extends Model
{
    protected $table = 'user_setores';
}
