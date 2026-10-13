<?php

use App\Support\TravaDeComandosDestrutivos;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| A trava dos comandos que apagam o banco (dono, 13/10/2026)
|--------------------------------------------------------------------------
|
| migrate:fresh/refresh/reset/rollback e db:wipe ficam bloqueados em todos os
| ambientes — antes só em produção, e um migrate:fresh por descuido apagou o
| banco de desenvolvimento. Só a suíte de testes e o SQLite local, com a
| liberação explícita do dono, passam.
|
*/

afterEach(function () {
    // A trava é estado ESTÁTICO do comando: devolve a suíte ao estado liberado.
    DB::prohibitDestructiveCommands(false);
});

it('fica fechada em homologação, produção e local sem a liberação do dono', function (string $ambiente, string $driver, bool $liberacao, bool $outraConexao) {
    expect(TravaDeComandosDestrutivos::liberados($ambiente, $driver, $liberacao, $outraConexao))->toBeFalse();
})->with([
    'local no SQLite, sem liberação' => ['local', 'sqlite', false, false],
    'local no Oracle, mesmo com liberação' => ['local', 'oracle', true, false],
    'local no SQLite com liberação, mas mirando outra conexão' => ['local', 'sqlite', true, true],
    'homologação com liberação' => ['homolog', 'sqlite', true, false],
    'homologação no Oracle' => ['homolog', 'oracle', false, false],
    'produção com liberação' => ['production', 'sqlite', true, false],
]);

it('abre só na suíte de testes e no SQLite local com a liberação do dono', function () {
    expect(TravaDeComandosDestrutivos::liberados('testing', 'sqlite', false, false))->toBeTrue()
        ->and(TravaDeComandosDestrutivos::liberados('local', 'sqlite', true, false))->toBeTrue();
});

it('com a trava fechada, os comandos que apagam o banco são recusados e nada é apagado', function (string $comando) {
    DB::prohibitDestructiveCommands(true);

    $this->artisan($comando, ['--force' => true])->assertFailed();

    expect(DB::getSchemaBuilder()->hasTable('users'))->toBeTrue();
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe']);

it('no ambiente local, a regra do processo fecha a trava', function () {
    app()->detectEnvironment(fn () => 'local');

    expect(TravaDeComandosDestrutivos::liberadosAgora())->toBeFalse();

    TravaDeComandosDestrutivos::aplicar();
    $this->artisan('db:wipe', ['--force' => true])->assertFailed();

    app()->detectEnvironment(fn () => 'testing');
});
