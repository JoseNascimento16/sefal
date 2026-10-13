<?php

namespace App\Support\Aplicativo;

use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\Operacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * O pedaço da rua que cabe a quem está com o aparelho na mão.
 *
 * É a régua ÚNICA do aplicativo: as equipes da pessoa (como fiscal ou como
 * líder), as operações em que ela foi posta, e — a partir delas — a fila de
 * demandas. Login, fila e gravação perguntam aqui; ninguém recalcula ao lado,
 * porque a mesma pergunta respondida em dois lugares diverge no primeiro ajuste.
 */
final class CampoDoFiscal
{
    /**
     * As situações em que a demanda está NA MÃO DA EQUIPE — o que chega ao
     * aplicativo. Triagem, devolução e arquivamento não são trabalho de rua.
     */
    public const SITUACOES_DE_CAMPO = [
        Demanda::DIRECIONADA_AOS_FISCAIS,
        Demanda::EM_OPERACAO,
        Demanda::EM_CAMPO,
        Demanda::AGUARDANDO_REGULARIZACAO,
        Demanda::RETORNO_VENCIDO,
    ];

    /** Quantos dias o aplicativo continua mostrando o que a equipe já concluiu. */
    public const DIAS_DE_HISTORICO = 30;

    public function __construct(public readonly User $user) {}

    /**
     * As equipes da pessoa: primeiro aquelas em que ela é FISCAL, depois as que
     * ela lidera. A primeira é a "equipe atual" do aplicativo.
     *
     * @return Collection<int, Equipe>
     */
    public function equipes(): Collection
    {
        $comoFiscal = $this->user->equipesComoFiscal()->where('equipes.ativa', true)->with('area')->orderBy('equipes.codigo')->get();
        $lideradas = $this->user->equipesQueLidera()->where('ativa', true)->with('area')->orderBy('codigo')->get();

        return $comoFiscal->concat($lideradas)->unique('id')->values();
    }

    public function equipePrincipal(): ?Equipe
    {
        return $this->equipes()->first();
    }

    /** Quem não está em equipe nenhuma não tem fila — e o aplicativo diz isso na porta. */
    public function temCampo(): bool
    {
        return $this->equipes()->isNotEmpty();
    }

    /**
     * As operações abertas que chegam a esta pessoa: as das equipes dela e as em
     * que ela foi posta à parte.
     *
     * @return list<int>
     */
    public function operacoes(): array
    {
        $equipes = $this->equipes()->pluck('id')->all();

        return Operacao::query()
            ->abertas()
            ->where(function (Builder $q) use ($equipes) {
                $q->whereHas('equipes', fn (Builder $e) => $e->whereIn('equipes.id', $equipes))
                    ->orWhereHas('fiscais', fn (Builder $f) => $f->where('users.id', $this->user->id));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * A fila: o que está na mão das equipes da pessoa, mais o que elas
     * concluíram nos últimos {@see DIAS_DE_HISTORICO} dias.
     *
     * @return Collection<int, Demanda>
     */
    public function fila(): Collection
    {
        $equipes = $this->equipes()->pluck('id')->all();
        $operacoes = $this->operacoes();
        $desde = Date::now()->subDays(self::DIAS_DE_HISTORICO);

        return Demanda::query()
            ->with(['area', 'equipe', 'operacao', 'fiscalizacoes' => fn ($q) => $q->with(['documento', 'fotos'])->latest('aberta_em')])
            ->where(function (Builder $q) use ($equipes, $operacoes, $desde) {
                $q->where(function (Builder $abertas) use ($equipes, $operacoes) {
                    $abertas->whereIn('situacao', self::SITUACOES_DE_CAMPO)
                        ->where(fn (Builder $dono) => $dono->whereIn('equipe_id', $equipes)->orWhereIn('operacao_id', $operacoes));
                })->orWhere(function (Builder $feitas) use ($equipes, $desde) {
                    $feitas->where('situacao', Demanda::CONCLUIDA)
                        ->where('concluida_em', '>=', $desde)
                        ->whereHas('fiscalizacoes', fn (Builder $f) => $f->whereIn('equipe_id', $equipes));
                });
            })
            ->orderBy('prazo_em')
            ->get();
    }

    /** A demanda pode receber vistoria desta pessoa agora? */
    public function podeVistoriar(Demanda $demanda): bool
    {
        if (! in_array($demanda->situacao, self::SITUACOES_DE_CAMPO, true)) {
            return false;
        }

        $equipes = $this->equipes()->pluck('id')->all();

        return in_array($demanda->equipe_id, $equipes, true)
            || ($demanda->operacao_id !== null && in_array((int) $demanda->operacao_id, $this->operacoes(), true));
    }

    /**
     * Os registros que esta pessoa fez nos últimos {@see DIAS_DE_HISTORICO} dias.
     *
     * @return Collection<int, Fiscalizacao>
     */
    public function registros(): Collection
    {
        return Fiscalizacao::query()
            ->with(['documento', 'fotos', 'recomendacoes', 'demanda'])
            ->where('fiscal_id', $this->user->id)
            ->where('aberta_em', '>=', Date::now()->subDays(self::DIAS_DE_HISTORICO))
            ->latest('aberta_em')
            ->get();
    }
}
