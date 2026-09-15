<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Uma PROPOSTA de agrupamento — da maquina para o coordenador.
 *
 * Ela nunca agrupa nada sozinha: agrupar e ato de gente. O que esta tabela faz e
 * deixar a maquina opinar, dizendo o quanto confia e POR QUE, e guardar o que o
 * humano respondeu.
 *
 * A sugestao RECUSADA e tao importante quanto a aceita: e ela que impede a
 * proxima varredura de propor o mesmo par de novo, cansando o coordenador ate
 * ele parar de ler as sugestoes — que e como um assistente util vira ruido.
 *
 * @property int $id
 * @property int $demanda_id
 * @property int $principal_id
 * @property string $origem
 * @property float|null $confianca
 * @property string $motivo
 * @property string $estado
 * @property Carbon|null $decidida_em
 */
#[Fillable([
    'demanda_id', 'principal_id', 'origem', 'confianca', 'motivo',
    'estado', 'decidida_por_id', 'decidida_em', 'observacao',
])]
class SugestaoAgrupamento extends Model
{
    /** Proposta por modelo de linguagem, lendo o relato. */
    public const ORIGEM_IA = 'ia';

    /** Proposta por regra determinista: mesmo bairro, enderecos proximos, assunto parecido. */
    public const ORIGEM_REGRA = 'regra';

    public const SUGERIDA = 'sugerida';

    public const ACEITA = 'aceita';

    public const RECUSADA = 'recusada';

    protected $table = 'sugestoes_agrupamento';

    /**
     * O padrao vive no MODELO, e nao so como default de coluna.
     *
     * Com o default apenas no banco, a sugestao recem-criada tem `estado` nulo
     * em memoria ate alguem reler a linha — e quem a inspecionar logo depois de
     * criar ve um estado que nao existe no catalogo.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'estado' => self::SUGERIDA,
        'origem' => self::ORIGEM_REGRA,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'confianca' => 'float',
            'decidida_em' => 'datetime',
        ];
    }

    /** A denuncia que seria agregada. @return BelongsTo<Demanda, $this> */
    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class, 'demanda_id');
    }

    /** O registro que levaria o caso a campo. @return BelongsTo<Demanda, $this> */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(Demanda::class, 'principal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decididaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidida_por_id');
    }

    /**
     * Registra a decisao humana. E o unico caminho de mudanca de estado, para que
     * nenhuma sugestao mude de lado sem dizer quem decidiu e quando.
     */
    public function decidir(string $estado, User $quem, ?string $observacao = null): void
    {
        $this->estado = $estado;
        $this->decidida_por_id = $quem->id;
        $this->decidida_em = Date::now();
        $this->observacao = $observacao;
        $this->save();
    }

    /** As que ainda esperam o olho do coordenador, as mais confiantes primeiro. */
    /** @param  Builder<static>  $query */
    public function scopePendentes(Builder $query): void
    {
        $query->where('estado', self::SUGERIDA)->orderByDesc('confianca');
    }
}
