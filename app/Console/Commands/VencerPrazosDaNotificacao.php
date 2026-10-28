<?php

namespace App\Console\Commands;

use App\Support\PrazosDaNotificacao;
use Illuminate\Console\Command;

/**
 * Marca "Retorno vencido" onde o prazo da Notificação Preliminar passou sem a
 * equipe voltar ao ponto ({@see PrazosDaNotificacao}).
 *
 * Agendado de hora em hora (`routes/console.php`); a demonstração roda também
 * no boot, porque lá não há agendador.
 */
class VencerPrazosDaNotificacao extends Command
{
    protected $signature = 'sefal:vencer-prazos-da-notificacao';

    protected $description = 'Marca como retorno vencido a Notificação Preliminar cujo prazo passou sem retorno.';

    public function handle(): int
    {
        $total = PrazosDaNotificacao::vencer();

        $this->info($total === 0
            ? 'Nenhum prazo de notificação venceu.'
            : ($total === 1 ? '1 prazo de notificação venceu.' : "{$total} prazos de notificação venceram."));

        return self::SUCCESS;
    }
}
