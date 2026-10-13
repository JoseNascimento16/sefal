<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um ambulante encontrado no levantamento da rua — a linha numerada do papel
 * (001, 002…), com a foto do equipamento e o ponto de onde o fiscal estava.
 *
 * @property int $id
 * @property int $levantamento_id
 * @property string $item
 * @property string $nome
 * @property string|null $documento
 * @property string|null $contato
 * @property string|null $referencia
 * @property string|null $atividade
 * @property string|null $foto
 * @property float|null $latitude
 * @property float|null $longitude
 */
#[Fillable(['levantamento_id', 'item', 'nome', 'documento', 'contato', 'referencia', 'atividade', 'foto', 'latitude', 'longitude'])]
class LevantamentoLinha extends Model
{
    protected $table = 'levantamento_linhas';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    /** @return BelongsTo<Levantamento, $this> */
    public function levantamento(): BelongsTo
    {
        return $this->belongsTo(Levantamento::class);
    }
}
