<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A FISCALIZAÇÃO — o que aconteceu na rua.
 *
 * Este model é o dono de três regras que não podem ter segunda versão:
 *
 *  1. {@see DESFECHOS} — como a vistoria terminou;
 *  2. {@see exigeDocumento()} — quais desfechos NÃO podem ser concluídos sem
 *     papel lavrado. É a guarda que o aplicativo aplica na tela e o servidor
 *     repete na gravação: tela é conveniência, servidor é garantia;
 *  3. {@see editavel()} — a fronteira do despacho. Depois dela, o registro está
 *     na mesa da chefia e virou peça do processo.
 *
 * @property int $id
 * @property string $protocolo
 * @property string|null $client_id
 * @property string $origem
 * @property int|null $demanda_id
 * @property int|null $operacao_id
 * @property int|null $equipe_id
 * @property int $fiscal_id
 * @property int|null $ambulante_id
 * @property string|null $alvo
 * @property string|null $equipamento
 * @property string|null $logradouro
 * @property string|null $bairro
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $precisao_m
 * @property Carbon $aberta_em
 * @property Carbon|null $concluida_em
 * @property Carbon|null $despachada_em
 * @property string|null $desfecho
 * @property string|null $consideracoes
 * @property string $situacao
 * @property-read Collection<int, FiscalizacaoFoto> $fotos
 * @property-read DocumentoCampo|null $documento
 */
#[Fillable([
    'ciclo_id', 'protocolo', 'client_id', 'origem', 'demanda_id', 'operacao_id', 'equipe_id', 'fiscal_id',
    'ambulante_id', 'alvo', 'equipamento',
    'logradouro', 'numero', 'bairro', 'ponto_de_referencia',
    'latitude', 'longitude', 'precisao_m', 'gps_em',
    'aberta_em', 'concluida_em', 'despachada_em', 'sincronizada_em',
    'desfecho', 'consideracoes', 'situacao', 'assinatura', 'motivo_recusa_id',
    'decidida_por_id', 'decidida_em', 'decisao_detalhe',
])]
class Fiscalizacao extends Model
{
    // ── De onde veio a ordem ────────────────────────────────────────────────

    /** Nasceu de uma demanda direcionada — o caminho normal. */
    public const ORIGEM_DEMANDA = 'demanda';

    /** Nasceu da varredura de uma operação planejada. */
    public const ORIGEM_OPERACAO = 'operacao';

    /**
     * AVULSA: o fiscal passou, viu e registrou. Sem demanda e sem operação.
     *
     * Não é exceção nem gambiarra — é uma das três portas de entrada do trabalho
     * de rua, e a mais frequente na ronda. Quem a tratar como caso raro vai
     * desenhar uma fila que não mostra metade do que a equipe faz.
     */
    public const ORIGEM_AVULSA = 'avulsa';

    /** @var list<string> */
    public const ORIGENS = [self::ORIGEM_DEMANDA, self::ORIGEM_OPERACAO, self::ORIGEM_AVULSA];

    // ── Como a vistoria terminou ────────────────────────────────────────────

    public const REGULARIZADO_NO_LOCAL = 'Regularizado no local';

    public const NADA_ENCONTRADO = 'Nada encontrado no local';

    public const NOTIFICACAO_EMITIDA = 'Notificação Preliminar emitida';

    public const AUTO_LAVRADO = 'Auto de Apreensão lavrado';

    public const REGULARIZADO_APOS_NOTIFICACAO = 'Regularizado após notificação';

    public const SITUACAO_MANTIDA = 'Retorno com a situação mantida';

    /**
     * O catálogo, na ORDEM DO FLUXO — e essa ordem não é decorativa.
     *
     * Primeiro o que encerra na hora, depois o que lavra papel, por fim os dois
     * desfechos de RETORNO (que só existem numa segunda ida ao ponto). É a ordem
     * que a tela usa para oferecer as opções e para a busca reconhecer a faceta;
     * mudá-la reordena o formulário do fiscal sem que ninguém tenha pedido.
     *
     * @var list<string>
     */
    public const DESFECHOS = [
        self::REGULARIZADO_NO_LOCAL,
        self::NADA_ENCONTRADO,
        self::NOTIFICACAO_EMITIDA,
        self::REGULARIZADO_APOS_NOTIFICACAO,
        self::SITUACAO_MANTIDA,
        self::AUTO_LAVRADO,
    ];

    /**
     * Os desfechos que EXIGEM documento lavrado.
     *
     * Concluir "Notificação Preliminar emitida" sem a notificação seria afirmar
     * no sistema um papel que não existe na rua — e a chefia decidiria o retorno
     * contando um prazo que ninguém entregou ao notificado.
     *
     * @var list<string>
     */
    public const DESFECHOS_COM_DOCUMENTO = [self::NOTIFICACAO_EMITIDA, self::AUTO_LAVRADO];

    /**
     * O que cada desfecho implica para a DEMANDA que originou a vistoria.
     *
     * É a ponte entre o que aconteceu na rua e o que o cidadão que denunciou vai
     * ler. Mora aqui, num lugar só, porque a mesma pergunta é feita pela leitura
     * da chefia, pelo leque de volta das denúncias agrupadas e (amanhã) pela API
     * do aplicativo — três donos dariam três respostas no primeiro ajuste.
     *
     * A regra em uma frase: o caso ENCERRA quando a irregularidade cessou ou
     * nunca existiu; fica ABERTO quando há prazo correndo ou medida a tomar.
     *
     * @var array<string, string>
     */
    public const EFEITO_NA_DEMANDA = [
        self::REGULARIZADO_NO_LOCAL => Demanda::CONCLUIDA,
        self::NADA_ENCONTRADO => Demanda::CONCLUIDA,
        self::REGULARIZADO_APOS_NOTIFICACAO => Demanda::CONCLUIDA,
        // Os bens foram recolhidos: a ocupação acabou e o caso se encerra aqui.
        self::AUTO_LAVRADO => Demanda::CONCLUIDA,
        // Há prazo correndo na mão do notificado — o caso espera o vencimento.
        self::NOTIFICACAO_EMITIDA => Demanda::AGUARDANDO_REGULARIZACAO,
        // O retorno encontrou tudo como estava: não encerra, pede a próxima medida.
        self::SITUACAO_MANTIDA => Demanda::AGUARDANDO_REGULARIZACAO,
    ];

    // ── Onde o registro está ────────────────────────────────────────────────

    /** A equipe está na rua; nada foi concluído ainda. */
    public const EM_CAMPO = 'Em campo';

    /** Despachada pelo fiscal, esperando a leitura da chefia. */
    public const AGUARDANDO_LEITURA = 'Aguardando leitura';

    public const CIENTE = 'Ciente';

    public const NOVA_VISTORIA = 'Nova vistoria determinada';

    /** O líder encaminhou o resultado ao Chefe de Setor (era "Devolvida à coordenação"). */
    public const DEVOLVIDA = 'Encaminhada ao Chefe de Setor';

    /** @var list<string> */
    public const SITUACOES = [
        self::EM_CAMPO,
        self::AGUARDANDO_LEITURA,
        self::CIENTE,
        self::NOVA_VISTORIA,
        self::DEVOLVIDA,
    ];

    /** As que a chefia ainda precisa decidir — a aba "A decidir". */
    /** @var list<string> */
    public const A_DECIDIR = [self::AGUARDANDO_LEITURA];

    protected $table = 'fiscalizacoes';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'aberta_em' => 'datetime',
            'concluida_em' => 'datetime',
            'despachada_em' => 'datetime',
            'sincronizada_em' => 'datetime',
            'gps_em' => 'datetime',
            'decidida_em' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    // ── Relações ────────────────────────────────────────────────────────────

    /**
     * A FISCALIZAÇÃO (o ciclo) a que esta vistoria pertence — ver
     * {@see CicloDeFiscalizacao}. Cada ida ao ponto é uma vistoria; o ciclo as agrupa.
     *
     * @return BelongsTo<CicloDeFiscalizacao, $this>
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(CicloDeFiscalizacao::class, 'ciclo_id');
    }

    /** @return BelongsTo<Demanda, $this> */
    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class);
    }

    /** @return BelongsTo<Operacao, $this> */
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    /** @return BelongsTo<Equipe, $this> */
    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    /** @return BelongsTo<User, $this> */
    public function fiscal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fiscal_id');
    }

    /** @return BelongsTo<Ambulante, $this> */
    public function ambulante(): BelongsTo
    {
        return $this->belongsTo(Ambulante::class);
    }

    /** Quem leu o registro e decidiu. @return BelongsTo<User, $this> */
    public function decididaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidida_por_id');
    }

    /** @return BelongsTo<MotivoRecusa, $this> */
    public function motivoRecusa(): BelongsTo
    {
        return $this->belongsTo(MotivoRecusa::class, 'motivo_recusa_id');
    }

    /** @return HasMany<FiscalizacaoFoto, $this> */
    public function fotos(): HasMany
    {
        return $this->hasMany(FiscalizacaoFoto::class);
    }

    /** @return HasMany<FiscalizacaoRecomendacao, $this> */
    public function recomendacoes(): HasMany
    {
        return $this->hasMany(FiscalizacaoRecomendacao::class);
    }

    /**
     * O documento lavrado. É `HasOne` porque uma ida a campo produz um papel —
     * notificar e apreender na mesma visita é uma decisão só, com um número.
     *
     * @return HasOne<DocumentoCampo, $this>
     */
    public function documento(): HasOne
    {
        return $this->hasOne(DocumentoCampo::class);
    }

    // ── Regras ──────────────────────────────────────────────────────────────

    /** Este desfecho só vale com documento lavrado? */
    public static function exigeDocumento(?string $desfecho): bool
    {
        return $desfecho !== null && in_array($desfecho, self::DESFECHOS_COM_DOCUMENTO, true);
    }

    /**
     * Ainda dá para o fiscal mexer? Só antes do despacho.
     *
     * A guarda vale no servidor, e não apenas na tela do aplicativo: um envio
     * atrasado da fila offline chega DEPOIS do despacho, e é aqui que ele é
     * recusado — nunca gravando por cima do que a chefia já leu.
     */
    public function editavel(): bool
    {
        return $this->despachada_em === null;
    }

    /** As chaves de recomendação assinaladas pelo fiscal. */
    /** @return list<string> */
    public function chavesDeRecomendacao(): array
    {
        return $this->recomendacoes->pluck('chave')->values()->all();
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    /** @param  Builder<static>  $query */
    public function scopeADecidir(Builder $query): void
    {
        $query->whereIn('situacao', self::A_DECIDIR);
    }

    /** @param  Builder<static>  $query */
    public function scopeDespachadas(Builder $query): void
    {
        $query->whereNotNull('despachada_em');
    }

    /** As que nasceram sem demanda e sem operação. */
    /** @param  Builder<static>  $query */
    public function scopeAvulsas(Builder $query): void
    {
        $query->where('origem', self::ORIGEM_AVULSA);
    }
}
