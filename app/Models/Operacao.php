<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A OPERAÇÃO — trabalho de rua planejado, com período, área e equipes.
 *
 * Este model é o dono da regra que decide a SITUAÇÃO: ela é derivada do período
 * e do cancelamento, nunca digitada. {@see situacaoPeloPeriodo()} é a única
 * implementação — o command agendado, o formulário e o seeder chamam esta, e é
 * isso que impede a tela de mostrar "em andamento" para operação que acabou.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nome
 * @property int $area_id
 * @property int|null $tipo_operacao_id
 * @property int|null $origem_operacao_id
 * @property int|null $coordenador_id
 * @property string|null $regiao
 * @property string|null $foco
 * @property string|null $observacao
 * @property Carbon $inicio
 * @property Carbon|null $fim
 * @property string $situacao
 * @property Carbon|null $cancelada_em
 * @property string|null $motivo_cancelamento
 * @property-read Area $area
 * @property-read Collection<int, Equipe> $equipes
 */
#[Fillable([
    'codigo', 'nome', 'area_id', 'tipo_operacao_id', 'origem_operacao_id', 'coordenador_id',
    'regiao', 'foco', 'observacao', 'inicio', 'fim', 'situacao',
    'encerrada_em', 'cancelada_em', 'motivo_cancelamento', 'cancelada_por_id', 'criada_por_id',
])]
class Operacao extends Model
{
    public const PLANEJADA = 'Planejada';

    public const EM_ANDAMENTO = 'Em andamento';

    public const ENCERRADA = 'Encerrada';

    /** Único ato humano da lista — por isso tem data e motivo próprios. */
    public const CANCELADA = 'Cancelada';

    /** @var list<string> */
    public const SITUACOES = [self::PLANEJADA, self::EM_ANDAMENTO, self::ENCERRADA, self::CANCELADA];

    /**
     * As situações em que uma operação ainda RECEBE demanda.
     *
     * É a lista que o Chefe de Setor vê ao decidir entre anexar o caso a uma
     * operação e despachar equipe. Operação encerrada ou cancelada não aparece:
     * anexar a ela seria mandar o caso para um trabalho que não vai acontecer.
     *
     * @var list<string>
     */
    public const ABERTAS = [self::PLANEJADA, self::EM_ANDAMENTO];

    protected $table = 'operacoes';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
            'encerrada_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Quem ABRIU a operação. O nome da coluna é histórico (`coordenador_id`):
     * desde 22/09/2026 quem abre é o líder da equipe ou o Chefe de Setor — o
     * setor `coordenador` não existe mais.
     *
     * @return BelongsTo<User, $this>
     */
    public function coordenador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordenador_id');
    }

    /** @return BelongsTo<TipoOperacao, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoOperacao::class, 'tipo_operacao_id');
    }

    /** @return BelongsTo<OrigemOperacao, $this> */
    public function origem(): BelongsTo
    {
        return $this->belongsTo(OrigemOperacao::class, 'origem_operacao_id');
    }

    /** @return BelongsToMany<Equipe, $this> */
    public function equipes(): BelongsToMany
    {
        return $this->belongsToMany(Equipe::class, 'operacao_equipes')->withTimestamps();
    }

    /**
     * Fiscais de QUALQUER área postos na operação, além dos fiscais das equipes
     * que a executam (dono, 25/09/2026). A fiscalização da operação chega a
     * todos — ver {@see fiscaisQueRecebem()}.
     *
     * @return BelongsToMany<User, $this>
     */
    public function fiscais(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'operacao_fiscais')->withTimestamps();
    }

    /**
     * Quem RECEBE a fiscalização desta operação: os fiscais das equipes que a
     * executam e os postos nela à parte, sem repetição.
     *
     * @return list<int>
     */
    public function fiscaisQueRecebem(): array
    {
        $daEquipe = $this->equipes()->with('fiscais:users.id')->get()
            ->flatMap(static fn (Equipe $e) => $e->fiscais->pluck('id'))->all();

        return array_values(array_unique([...$daEquipe, ...$this->fiscais()->pluck('users.id')->all()]));
    }

    /** @return HasMany<OperacaoBairro, $this> */
    public function bairros(): HasMany
    {
        return $this->hasMany(OperacaoBairro::class);
    }

    /** @return HasMany<Demanda, $this> */
    public function demandas(): HasMany
    {
        return $this->hasMany(Demanda::class);
    }

    /** @return HasMany<Fiscalizacao, $this> */
    public function fiscalizacoes(): HasMany
    {
        return $this->hasMany(Fiscalizacao::class);
    }

    /**
     * A situação que o PERÍODO implica — a fonte única da regra.
     *
     * Cancelamento vence tudo: uma operação cancelada não volta a "em andamento"
     * porque a data de hoje caiu dentro do período dela.
     */
    public function situacaoPeloPeriodo(?Carbon $hoje = null): string
    {
        if ($this->cancelada_em !== null) {
            return self::CANCELADA;
        }

        // Encerramento antecipado: ato de gestão, e vence o calendário.
        if ($this->encerrada_em !== null) {
            return self::ENCERRADA;
        }

        $dia = ($hoje ?? Date::now())->startOfDay();

        if ($this->inicio->startOfDay()->gt($dia)) {
            return self::PLANEJADA;
        }

        // Sem fim é rotina permanente: começou, está em andamento e continua.
        if ($this->fim !== null && $this->fim->startOfDay()->lt($dia)) {
            return self::ENCERRADA;
        }

        return self::EM_ANDAMENTO;
    }

    /** Recalcula e grava a situação, se ela mudou. Devolve se houve mudança. */
    public function sincronizarSituacao(?Carbon $hoje = null): bool
    {
        $nova = $this->situacaoPeloPeriodo($hoje);

        if ($nova === $this->situacao) {
            return false;
        }

        $this->situacao = $nova;
        $this->save();

        return true;
    }

    /** Esta operação varre o bairro informado? É a pergunta que decide o anexo. */
    public function cobreBairro(?string $bairro): bool
    {
        $procurado = Area::chaveDeBairro($bairro);

        if ($procurado === '') {
            return false;
        }

        return $this->bairros->contains(
            static fn (OperacaoBairro $b): bool => Area::chaveDeBairro($b->bairro) === $procurado,
        );
    }

    /** @param  Builder<static>  $query */
    public function scopeAbertas(Builder $query): void
    {
        $query->whereIn('situacao', self::ABERTAS);
    }
}
