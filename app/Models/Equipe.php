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
 * A EQUIPE — quem de fato vai à rua. Pertence a uma área, tem um LÍDER e reúne
 * fiscais.
 *
 * É o destino do encaminhamento do Chefe de Setor: ele escolhe a equipe, e quem
 * recebe é o líder dela, que direciona aos fiscais e lê o que volta da rua. E é
 * por ela que o aplicativo sabe o que mostrar a cada fiscal: a fila do fiscal é
 * a fila das equipes de que ele participa.
 *
 * ## `lider_id` e `encarregado` — o usuário e o nome
 *
 * O documento do cliente ("Áreas das equipes — 17/04/2026") nomeia o encarregado
 * de cada equipe; é ESSA pessoa o líder de equipe, e a partir de 22/09/2026 ela
 * é usuário do sistema (setor `lider-de-equipe`). O nome em texto fica como
 * veio do documento e é o que a tela mostra enquanto a equipe não tem líder com
 * conta — o dado do cliente não some porque ainda não virou vínculo.
 *
 * @property int $id
 * @property string $codigo
 * @property string|null $nome
 * @property int $area_id
 * @property string|null $encarregado
 * @property int|null $lider_id
 * @property string|null $turno
 * @property bool $ativa
 * @property-read Area $area
 * @property-read User|null $lider
 * @property-read Collection<int, User> $fiscais
 */
#[Fillable(['codigo', 'nome', 'area_id', 'encarregado', 'lider_id', 'turno', 'ativa'])]
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

    /** Quem responde pela equipe dentro do sistema. */
    /** @return BelongsTo<User, $this> */
    public function lider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lider_id');
    }

    /**
     * O nome de quem lidera, como a tela mostra: o do usuário quando há vínculo,
     * o do documento quando ainda não há.
     */
    public function nomeDoLider(): string
    {
        return (string) ($this->lider?->name ?? $this->encarregado ?? '');
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
