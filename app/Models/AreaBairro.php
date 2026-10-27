<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um bairro pertencendo a uma área. A tabela existe para que a pergunta inversa
 * — "de que área é este bairro?" — seja índice, e não varredura de texto.
 *
 * @property int $id
 * @property int $area_id
 * @property string $bairro
 * @property float|null $latitude
 * @property float|null $longitude
 * @property-read Area $area
 */
#[Fillable(['area_id', 'bairro', 'latitude', 'longitude'])]
class AreaBairro extends Model
{
    protected $table = 'area_bairros';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
