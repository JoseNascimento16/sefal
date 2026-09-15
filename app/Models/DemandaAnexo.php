<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O arquivo que veio com a demanda (foto do cidadão, ofício digitalizado).
 *
 * `nome` é como o cidadão o conhece; `caminho` é onde ele de fato está, no disco
 * privado. Servir pelo nome enviado é o caminho conhecido para atravessar
 * diretório — e o nome ainda vai para a URL de download, onde o WAF barra
 * assinatura de SQLi.
 *
 * @property int $id
 * @property int $demanda_id
 * @property string $nome
 * @property string $caminho
 * @property string|null $tipo
 * @property int|null $bytes
 */
#[Fillable(['demanda_id', 'nome', 'caminho', 'tipo', 'bytes'])]
class DemandaAnexo extends Model
{
    protected $table = 'demanda_anexos';

    /** @return BelongsTo<Demanda, $this> */
    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class);
    }
}
