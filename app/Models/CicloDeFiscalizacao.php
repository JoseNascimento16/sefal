<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A FISCALIZAÇÃO — o ciclo que põe uma demanda em prática (dono, 24/09/2026).
 *
 * Nasce quando o Chefe de Setor encaminha a demanda ao líder (ou quando o líder
 * registra o Fala Salvador, que já nasce na mesa dele). Com a EQUIPE, o líder a
 * envia aos fiscais, recebe o retorno de campo e pode mandá-los voltar — cada ida
 * ao ponto é uma VISTORIA ({@see Fiscalizacao}), todas do mesmo ciclo. Quando o
 * líder encaminha o resultado, a posse passa ao CHEFE (aba "Encaminhadas"); quando
 * o chefe devolve o processo à origem, ela vai para o ARQUIVO.
 *
 * Um novo encaminhamento do chefe ao líder abre uma Fiscalização IRMÃ — a anterior
 * fica como estava, e as duas são consultáveis pela demanda.
 *
 * O nome da classe diz "ciclo" porque `Fiscalizacao` já era o nome da vistoria
 * (e é o que o aplicativo do fiscal grava). Na tela, isto é a "Fiscalização".
 *
 * @property int $id
 * @property string $protocolo
 * @property string $origem
 * @property int|null $demanda_id
 * @property int|null $equipe_id
 * @property string $posse
 * @property Carbon $aberto_em
 * @property Carbon|null $encaminhado_ao_chefe_em
 * @property string|null $motivo_do_encaminhamento
 * @property Carbon|null $arquivado_em
 */
#[Fillable([
    'protocolo', 'origem', 'demanda_id', 'equipe_id', 'posse',
    'aberto_em', 'aberto_por_id', 'encaminhado_ao_chefe_em', 'encaminhado_por_id',
    'motivo_do_encaminhamento', 'arquivado_em',
])]
class CicloDeFiscalizacao extends Model
{
    public const POSSE_EQUIPE = 'equipe';

    public const POSSE_CHEFE = 'chefe';

    /** As abas da tela Fiscalizações. */
    public const ABA_ANDAMENTO = 'andamento';

    public const ABA_ENCAMINHADAS = 'encaminhadas';

    public const ABA_ARQUIVO = 'arquivo';

    protected $table = 'fiscalizacao_ciclos';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'aberto_em' => 'datetime',
            'encaminhado_ao_chefe_em' => 'datetime',
            'arquivado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Demanda, $this> */
    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class);
    }

    /** @return BelongsTo<Equipe, $this> */
    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    /** @return BelongsTo<User, $this> */
    public function encaminhadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaminhado_por_id');
    }

    /** As vistorias deste ciclo, na ordem em que aconteceram. @return HasMany<Fiscalizacao, $this> */
    public function vistorias(): HasMany
    {
        return $this->hasMany(Fiscalizacao::class, 'ciclo_id')->orderBy('aberta_em')->orderBy('id');
    }

    /** Em que aba da tela ela está. */
    public function aba(): string
    {
        if ($this->arquivado_em !== null) {
            return self::ABA_ARQUIVO;
        }

        return $this->posse === self::POSSE_CHEFE ? self::ABA_ENCAMINHADAS : self::ABA_ANDAMENTO;
    }

    public function comAEquipe(): bool
    {
        return $this->arquivado_em === null && $this->posse === self::POSSE_EQUIPE;
    }

    /** A vistoria que voltou da rua e espera a decisão do líder, se houver. */
    public function vistoriaPendente(): ?Fiscalizacao
    {
        $vistorias = $this->relationLoaded('vistorias') ? $this->vistorias : $this->vistorias()->get();

        return $vistorias->last(
            static fn (Fiscalizacao $v): bool => $v->situacao === Fiscalizacao::AGUARDANDO_LEITURA,
        );
    }

    /**
     * A Notificação Preliminar que espera a volta da equipe ao ponto — com o
     * prazo correndo ou já vencido —, se houver. Só a da Fiscalização que está
     * com a equipe: encaminhada ao Chefe, o retorno deixou de ser da equipe.
     */
    public function notificacaoEmPrazo(): ?Fiscalizacao
    {
        if (! $this->comAEquipe()) {
            return null;
        }

        $vistorias = $this->relationLoaded('vistorias') ? $this->vistorias : $this->vistorias()->get();

        return $vistorias->last(
            static fn (Fiscalizacao $v): bool => in_array($v->situacao, Fiscalizacao::NOTIFICACAO_EM_PRAZO, true),
        );
    }

    /** O prazo da notificação passou sem retorno — alerta na mesa do líder. */
    public function retornoVencido(): bool
    {
        return $this->notificacaoEmPrazo()?->situacao === Fiscalizacao::PRAZO_VENCIDO;
    }

    /**
     * A Fiscalização SEM demanda (ronda, operação) que está na fila do
     * aplicativo: a notificação dela espera o retorno, ou o líder mandou a
     * equipe voltar. Com demanda, quem leva o caso à fila é a demanda.
     */
    public function naFilaDoAplicativo(): bool
    {
        if ($this->demanda_id !== null || ! $this->comAEquipe()) {
            return false;
        }

        $vistorias = $this->relationLoaded('vistorias') ? $this->vistorias : $this->vistorias()->get();
        $ultima = $vistorias->last();

        return $ultima !== null && in_array($ultima->situacao, [...Fiscalizacao::NOTIFICACAO_EM_PRAZO, Fiscalizacao::NOVA_VISTORIA], true);
    }

    /** Espera o líder mandar a equipe ao ponto? (a demanda está na mesa dele) */
    public function aguardaEnvioAEquipe(): bool
    {
        return $this->comAEquipe() && $this->demanda?->situacao === Demanda::ENCAMINHADA_AO_LIDER;
    }

    /**
     * O DESFECHO como a tela o mostra — ele muda conforme a Fiscalização avança:
     * espera o envio à equipe, está com a equipe em campo, e então o desfecho da
     * última vistoria despachada.
     */
    public function desfechoAtual(): string
    {
        $vistorias = $this->relationLoaded('vistorias') ? $this->vistorias : $this->vistorias()->get();
        $ultima = $vistorias->filter(
            static fn (Fiscalizacao $v): bool => $v->despachada_em !== null && $v->desfecho !== null,
        )->last();

        if ($this->aguardaEnvioAEquipe()) {
            return 'Aguardando envio à equipe';
        }

        if ($this->vistoriaPendente() === null && ($notificacao = $this->notificacaoEmPrazo()) !== null) {
            return $notificacao->situacao === Fiscalizacao::PRAZO_VENCIDO ? 'Retorno vencido' : 'Aguardando regularização';
        }

        // Ronda que o líder mandou refazer: está de novo na fila do aplicativo.
        if ($this->naFilaDoAplicativo()) {
            return 'Nova vistoria em campo';
        }

        $naRua = in_array($this->demanda?->situacao, [
            Demanda::DIRECIONADA_AOS_FISCAIS, Demanda::EM_OPERACAO, Demanda::EM_CAMPO,
        ], true);

        if ($this->comAEquipe() && $naRua && $this->vistoriaPendente() === null) {
            return $ultima === null ? 'Com a equipe em campo' : 'Nova vistoria em campo';
        }

        return $ultima?->desfecho ?? 'Sem vistoria';
    }

    /** O líder tem algo a decidir nela? (enviar à equipe, ler o retorno, ou o retorno que venceu) */
    public function aDecidir(): bool
    {
        return $this->comAEquipe()
            && ($this->aguardaEnvioAEquipe() || $this->vistoriaPendente() !== null || $this->retornoVencido());
    }

    /** @param  Builder<static>  $query */
    public function scopeDaAba(Builder $query, string $aba): void
    {
        match ($aba) {
            self::ABA_ARQUIVO => $query->whereNotNull('arquivado_em'),
            self::ABA_ENCAMINHADAS => $query->whereNull('arquivado_em')->where('posse', self::POSSE_CHEFE),
            default => $query->whereNull('arquivado_em')->where('posse', self::POSSE_EQUIPE),
        };
    }
}
