<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um bairro varrido por uma operação. NÃO é o bairro da área: a operação pega um
 * trecho dela, e é essa lista que responde "o ponto desta denúncia está dentro
 * da operação?".
 *
 * @property int $id
 * @property int $operacao_id
 * @property string $bairro
 */
#[Fillable(['operacao_id', 'bairro'])]
class OperacaoBairro extends Model
{
    protected $table = 'operacao_bairros';

    /** @return BelongsTo<Operacao, $this> */
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }
}
