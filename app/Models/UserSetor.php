<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Vínculo usuário ↔ setor. É o pivô de User::setores() — existe como classe
 * própria para o vínculo ter identidade (id e timestamps de quando o acesso
 * foi concedido), e não só um par anônimo de chaves.
 *
 * @property int $id
 * @property int $user_id
 * @property int $setor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserSetor extends Pivot
{
    protected $table = 'user_setores';

    public $incrementing = true;
}
