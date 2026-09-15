<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A EQUIPE — quem de fato vai à rua. Pertence a uma área e reúne fiscais.
 *
 * É o destino final do direcionamento do Chefe de Setor, e é por ela que o
 * aplicativo sabe o que mostrar a cada fiscal: a fila do fiscal é a fila das
 * equipes de que ele participa.
 *
 * @property int $id
 * @property string $codigo
 * @property string|null $nome
 * @property int $area_id
 * @property string|null $encarregado
 * @property string|null $turno
 * @property bool $ativa
 * @property-read Area $area
 * @property-read Collection<int, User> $fiscais
 */
#[Fillable(['codigo', 'nome', 'area_id', 'encarregado', 'turno', 'ativa'])]
class Equipe extends Model
{
    protected $table = 'equipes';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function fiscais(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'equipe_fiscais')->withTimestamps();
    }

    /** @return HasMany<Fiscalizacao, $this> */
    public function fiscalizacoes(): HasMany
    {
        return $this->hasMany(Fiscalizacao::class);
    }

    /** @return BelongsToMany<Operacao, $this> */
    public function operacoes(): BelongsToMany
    {
        return $this->belongsToMany(Operacao::class, 'operacao_equipes')->withTimestamps();
    }

    /** Como a tela se refere a ela: "Equipe C2 — Área 1". */
    public function rotulo(): string
    {
        return 'Equipe '.$this->codigo;
    }

    /** @param  Builder<static>  $query */
    public function scopeAtivas(Builder $query): void
    {
        $query->where('ativa', true);
    }
}
