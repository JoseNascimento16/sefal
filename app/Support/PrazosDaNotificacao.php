<?php

namespace App\Support;

use App\Models\CicloDeFiscalizacao;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Fiscalizacao;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * O prazo da Notificação Preliminar que venceu sem a equipe voltar ao ponto.
 *
 * A notificação deixa o caso com o notificado ("Aguardando regularização"); a
 * equipe volta no fim do prazo e registra o retorno. Quando o prazo passa e
 * ninguém voltou, o caso vira "Retorno vencido" e cai na mesa do LÍDER da equipe
 * (dono, 28/10/2026) — o retorno continua na fila do aplicativo, agora como
 * atrasado.
 *
 * Vence no dia SEGUINTE ao prazo: o próprio dia do prazo ainda é dia de voltar.
 * Roda de hora em hora (`routes/console.php`) e é idempotente: o que já venceu
 * não é tocado de novo.
 */
final class PrazosDaNotificacao
{
    /** Vence o que passou do prazo. Devolve quantas notificações venceram. */
    public static function vencer(?CarbonInterface $agora = null): int
    {
        $hoje = ($agora ?? Date::now())->copy()->startOfDay();

        $vencidas = Fiscalizacao::query()
            ->with(['documento', 'demanda', 'ciclo'])
            ->where('situacao', Fiscalizacao::PRAZO_CORRENDO)
            ->whereHas('documento', static fn (Builder $d) => $d->whereNotNull('prazo_ate')->where('prazo_ate', '<', $hoje))
            // Só a que ainda está com a equipe: encaminhada ao Chefe, o retorno saiu da mão dela.
            ->whereHas('ciclo', static fn (Builder $c) => $c->whereNull('arquivado_em')->where('posse', CicloDeFiscalizacao::POSSE_EQUIPE))
            ->get();

        foreach ($vencidas as $notificacao) {
            DB::transaction(static function () use ($notificacao) {
                $notificacao->situacao = Fiscalizacao::PRAZO_VENCIDO;
                $notificacao->save();

                $demanda = $notificacao->demanda;

                if ($demanda?->situacao === Demanda::AGUARDANDO_REGULARIZACAO) {
                    $demanda->registrar(
                        acao: 'Prazo da notificação vencido sem retorno — alerta ao líder da equipe',
                        situacao: Demanda::RETORNO_VENCIDO,
                        papel: DemandaTramite::PAPEL_INTEGRACAO,
                        campos: array_filter([
                            'Registro de campo' => $notificacao->protocolo,
                            'Documento lavrado' => $notificacao->documento?->rotulo(),
                            'Prazo para regularizar' => $notificacao->documento?->prazo_ate?->format('d/m/Y'),
                        ]),
                    );
                }
            });
        }

        return $vencidas->count();
    }
}
