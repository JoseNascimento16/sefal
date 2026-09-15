<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Um ponto da TRILHA do ambulante — onde ele esteve, e quando.
 *
 * Nao e "a localizacao dele": e um evento. O ambulante de rua se move, e e
 * justamente a movimentacao que a fiscalizacao precisa ver — o ponto que migrou
 * duas quadras depois da notificacao e o caso classico. Uma coluna no cadastro
 * responderia "onde ele esta" sobrescrevendo "onde ele estava".
 *
 * `fonte` diz de onde o ponto veio, porque a confianca e diferente: o da
 * fiscalizacao tem GPS e testemunha; o de cadastro e o que alguem informou.
 *
 * @property int $id
 * @property int $ambulante_id
 * @property float $latitude
 * @property float $longitude
 * @property int|null $precisao_m
 * @property string $fonte
 * @property int|null $fiscalizacao_id
 * @property string|null $bairro
 * @property Carbon $registrada_em
 */
#[Fillable([
    'ambulante_id', 'latitude', 'longitude', 'precisao_m',
    'fonte', 'fiscalizacao_id', 'bairro', 'registrada_em',
])]
class LocalizacaoAmbulante extends Model
{
    /** A equipe esteve no ponto: ha GPS e ha quem assine. */
    public const FONTE_FISCALIZACAO = 'fiscalizacao';

    /** Alguem registrou a posicao do ponto de trabalho, sem ida a campo. */
    public const FONTE_CADASTRO = 'cadastro';

    protected $table = 'localizacoes_ambulante';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'registrada_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ambulante, $this> */
    public function ambulante(): BelongsTo
    {
        return $this->belongsTo(Ambulante::class);
    }

    /** @return BelongsTo<Fiscalizacao, $this> */
    public function fiscalizacao(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacao::class);
    }
}
