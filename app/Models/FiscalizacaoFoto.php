<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A foto que o fiscal tirou. Vive no disco PRIVADO e sai por rota autenticada:
 * retrato de pessoa fiscalizada e de mercadoria apreendida não fica atrás de URL
 * adivinhavel.
 *
 * A coordenada e possivelmente diferente da do registro — o fiscal anda enquanto
 * fotografa —, e e ela que prova o enquadramento.
 *
 * @property int $id
 * @property int $fiscalizacao_id
 * @property string $caminho
 * @property string|null $legenda
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $client_id
 */
#[Fillable(['fiscalizacao_id', 'caminho', 'legenda', 'latitude', 'longitude', 'capturada_em', 'client_id'])]
class FiscalizacaoFoto extends Model
{
    protected $table = 'fiscalizacao_fotos';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capturada_em' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** @return BelongsTo<Fiscalizacao, $this> */
    public function fiscalizacao(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacao::class);
    }
}
