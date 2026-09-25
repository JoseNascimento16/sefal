<?php

use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| O sistema vive no horário de Salvador (dono, 25/09/2026)
|--------------------------------------------------------------------------
|
| Com o fuso em UTC, toda hora mostrada na Retaguarda saía três horas
| adiantada. O banco guarda data e hora SEM fuso: o que se grava é o relógio da
| aplicação, e é esse texto que a tela mostra. Por isso o teste confere o que
| vai PARA O BANCO, e não só a configuração.
|
*/

test('o fuso da aplicação é o de Salvador', function () {
    expect(config('app.timezone'))->toBe('America/Bahia')
        ->and(Date::now()->getTimezone()->getName())->toBe('America/Bahia');
});

test('a hora gravada no banco é a de Salvador — 12h em UTC vira 09h na tela', function () {
    Date::setTestNow(Date::parse('2026-09-25 12:00:00', 'UTC'));

    $u = User::factory()->create();

    expect(DB::table('users')->where('id', $u->id)->value('created_at'))->toStartWith('2026-09-25 09:00');

    Date::setTestNow();
});
