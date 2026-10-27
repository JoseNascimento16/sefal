<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma recomendacao que o fiscal assinalou, guardada por CHAVE.
 *
 * Nunca pela frase: o catalogo tem duas redacoes (curta no aparelho, explicita na
 * Retaguarda) e o relatorio soma por chave. Gravar a frase faria o relatorio
 * separar em dois a mesma recomendacao no dia em que alguem melhorasse o texto.
 *
 * @property int $id
 * @property int $fiscalizacao_id
 * @property string $chave
 */
#[Fillable(['fiscalizacao_id', 'chave'])]
class FiscalizacaoRecomendacao extends Model
{
    protected $table = 'fiscalizacao_recomendacoes';

    /** @return BelongsTo<Fiscalizacao, $this> */
    public function fiscalizacao(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacao::class);
    }
}
