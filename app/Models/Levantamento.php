<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * O LEVANTAMENTO de ambulantes de uma rua — o censo que o setor fazia em
 * planilha, agora gravado pelo aplicativo do fiscal.
 *
 * O cabeçalho é da força-tarefa: duas equipes podem levantar a mesma rua, e o
 * documento sai no nome das duas (equipes e encarregados).
 *
 * @property int $id
 * @property string $protocolo
 * @property string $client_id
 * @property int $user_id
 * @property string $rua
 * @property string $bairro
 * @property list<string> $equipes
 * @property list<string> $encarregados
 * @property Carbon $iniciado_em
 * @property Carbon $concluido_em
 * @property-read Collection<int, LevantamentoLinha> $linhas
 */
#[Fillable(['protocolo', 'client_id', 'user_id', 'rua', 'bairro', 'equipes', 'encarregados', 'iniciado_em', 'concluido_em'])]
class Levantamento extends Model
{
    protected $table = 'levantamentos';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'equipes' => 'array',
            'encarregados' => 'array',
            'iniciado_em' => 'datetime',
            'concluido_em' => 'datetime',
        ];
    }

    /** @return HasMany<LevantamentoLinha, $this> */
    public function linhas(): HasMany
    {
        return $this->hasMany(LevantamentoLinha::class)->orderBy('item');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
