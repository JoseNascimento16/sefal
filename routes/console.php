<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * A lixeira da tela de Usuários: todo dia de madrugada, remove de vez as contas
 * excluídas há mais de três dias que não têm histórico no sistema.
 */
Schedule::command('sefal:purgar-usuarios-excluidos')->dailyAt('03:00');

/*
 * O prazo da Notificação Preliminar que passou sem a equipe voltar ao ponto vira
 * "Retorno vencido" e cai na mesa do líder da equipe.
 */
Schedule::command('sefal:vencer-prazos-da-notificacao')->hourly();
