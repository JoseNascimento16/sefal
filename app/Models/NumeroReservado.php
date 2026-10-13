<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Um número oficial de Notificação Preliminar ou de Auto de Apreensão separado
 * para um fiscal, antes de ele ir à rua.
 *
 * O papel é impresso no ponto, e no ponto pode não haver sinal: o número tem de
 * estar no aparelho antes. A reserva é o que garante que dois aparelhos nunca
 * imprimam o mesmo número — e que o documento que chega depois traga um número
 * que o servidor de fato entregou àquele fiscal.
 *
 * @property int $id
 * @property string $tipo
 * @property string $numero
 * @property int $user_id
 * @property Carbon $reservado_em
 * @property Carbon|null $usado_em
 * @property int|null $fiscalizacao_id
 */
#[Fillable(['tipo', 'numero', 'user_id', 'reservado_em', 'usado_em', 'fiscalizacao_id'])]
class NumeroReservado extends Model
{
    protected $table = 'numeros_reservados';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['reservado_em' => 'datetime', 'usado_em' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
