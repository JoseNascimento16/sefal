<?php

namespace Database\Seeders;

use App\Console\Commands\PrepararDemonstracao;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Um caso com DENÚNCIAS JUNTADAS na fila da equipe do `lider1`, para a
 * demonstração mostrar o selo "+N denúncias" no aplicativo (dono, 28/10/2026).
 *
 * Feito pelo caminho de verdade: o Chefe de Setor agrega duas denúncias
 * recebidas a um caso já direcionado aos fiscais ({@see Demanda::agruparEm}).
 * Roda no preparo da demonstração, também numa base que já existe, DEPOIS dos
 * exemplos do retorno da notificação (que usam as denúncias recebidas sem
 * Fiscalização). Idempotente: a marca é o motivo do agrupamento no trâmite.
 */
class DenunciasJuntasSeeder extends Seeder
{
    /** O motivo do agrupamento — e a marca de que o semeador já rodou. */
    public const MOTIVO = 'Mesmo ponto e mesmo fato: a triagem juntou as denúncias num caso só.';

    public function run(): void
    {
        if (DemandaTramite::where('detalhe', self::MOTIVO)->exists()) {
            return;
        }

        $equipe = Equipe::where('codigo', PrepararDemonstracao::EQUIPE_DO_LIDER)->first();
        $chefe = User::whereHas('setores', static fn ($q) => $q->where('slug', 'chefe-de-setor'))->first()
            ?? User::where('admin', true)->first();

        if ($equipe === null || $chefe === null) {
            return;
        }

        $principal = Demanda::where('equipe_id', $equipe->id)
            ->where('situacao', Demanda::DIRECIONADA_AOS_FISCAIS)
            ->whereNull('agrupada_em_id')
            ->whereDoesntHave('agregadas')
            ->orderBy('prazo_em')
            ->first();

        if ($principal === null) {
            return;
        }

        // De preferência do mesmo bairro: é o que torna o agrupamento crível.
        $juntas = Demanda::where('situacao', Demanda::RECEBIDA)
            ->whereNull('agrupada_em_id')
            ->whereDoesntHave('agregadas')
            ->whereKeyNot($principal->id)
            ->orderByRaw('case when bairro = ? then 0 else 1 end', [$principal->bairro])
            ->orderBy('recebida_em')
            ->limit(2)
            ->get();

        foreach ($juntas as $junta) {
            $junta->agruparEm($principal, $chefe, self::MOTIVO);
        }
    }
}
