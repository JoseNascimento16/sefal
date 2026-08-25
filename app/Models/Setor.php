<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Perfil de acesso da Retaguarda. O catálogo é fechado e vem de
 * `config('retaguarda.setores')` — ver SetoresSeeder.
 *
 * @property int $id
 * @property string $slug
 * @property string $nome
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'nome'])]
class Setor extends Model
{
    protected $table = 'setores';
}
