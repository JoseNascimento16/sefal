<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * O documento lavrado em RUA — Notificacao Preliminar ou Auto de Apreensao.
 *
 * Ele nasce no aplicativo do fiscal, com numero oficial, e e impresso na hora.
 * A Retaguarda LE o documento; nao o emite. Por isso ele pendura na fiscalizacao
 * e nao numa tela de escritorio.
 *
 * ## Os catalogos viajam por CHAVE
 *
 * `motivos`, `sancoes`, `fundamentacao` e `guarda_destinacao` guardam a chave do
 * catalogo, nunca a frase impressa. E a chave que o relatorio soma, e a redacao
 * do impresso pode ser melhorada sem reescrever o passado — com a frase gravada,
 * o mesmo motivo viraria dois no dia seguinte a uma revisao de texto.
 *
 * ## O prazo tem chave E data
 *
 * A chave diz de que prazo se trata (`48h`); a data diz quando ele vence. As
 * duas sao necessarias: a duracao mora no catalogo (num lugar so), mas o que
 * vence e a data — e ela nao pode depender de recalcular um catalogo que mudou
 * depois que o papel foi entregue ao notificado.
 *
 * @property int $id
 * @property int $fiscalizacao_id
 * @property string $tipo
 * @property string $numero
 * @property string|null $notificado
 * @property string|null $prazo_chave
 * @property Carbon|null $prazo_ate
 * @property array<int, string>|null $motivos
 * @property array<int, string>|null $sancoes
 * @property Carbon $emitido_em
 */
#[Fillable([
    'fiscalizacao_id', 'tipo', 'numero', 'notificado', 'documento_notificado',
    'prazo_chave', 'prazo_ate', 'motivos', 'sancoes', 'fundamentacao', 'itens',
    'guarda_prazo', 'guarda_destinacao', 'observacao', 'dados', 'emitido_em', 'client_id',
])]
class DocumentoCampo extends Model
{
    /** Notificacao Preliminar: da prazo para regularizar. */
    public const NOTIFICACAO = 'np';

    /** Auto de Apreensao: os bens sao recolhidos. Nao da prazo. */
    public const AUTO = 'aa';

    /** @var list<string> */
    public const TIPOS = [self::NOTIFICACAO, self::AUTO];

    protected $table = 'documentos_campo';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'prazo_ate' => 'date',
            'emitido_em' => 'datetime',
            'motivos' => 'array',
            'sancoes' => 'array',
            'fundamentacao' => 'array',
            'itens' => 'array',
            'dados' => 'array',
        ];
    }

    /** @return BelongsTo<Fiscalizacao, $this> */
    public function fiscalizacao(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacao::class);
    }

    /** Como a tela o chama: "NP 194906". */
    public function rotulo(): string
    {
        return mb_strtoupper($this->tipo).' '.$this->numero;
    }

    /**
     * Dias ate o vencimento do prazo. Negativo = vencido; `null` = sem prazo.
     *
     * O Auto de Apreensao nao da prazo, e por isso devolve `null` — nao zero: a
     * tela que confundir os dois mostraria "vence hoje" num papel que nunca vence.
     */
    public function diasDePrazo(?Carbon $hoje = null): ?int
    {
        if ($this->prazo_ate === null) {
            return null;
        }

        return (int) ($hoje ?? Date::now())->startOfDay()->diffInDays($this->prazo_ate->startOfDay(), false);
    }

    public function prazoVencido(?Carbon $hoje = null): bool
    {
        $dias = $this->diasDePrazo($hoje);

        return $dias !== null && $dias < 0;
    }
}
