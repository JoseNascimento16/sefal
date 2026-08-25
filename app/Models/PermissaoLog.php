<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * O rastro de quem mexeu em permissão — para se poder perguntar, depois de um
 * incidente, "quem abriu esta porta, e quando?".
 *
 * O nome de quem alterou fica GRAVADO no registro, e não só a chave do usuário:
 * a conta pode ser renomeada ou desligada, e o histórico tem de continuar
 * legível sem depender de quem ainda existe.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_nome
 * @property string $funcionalidade_slug
 * @property string $descricao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'user_nome', 'funcionalidade_slug', 'descricao'])]
class PermissaoLog extends Model
{
    protected $table = 'permissoes_log';
}
