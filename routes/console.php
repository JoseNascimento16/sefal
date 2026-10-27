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
