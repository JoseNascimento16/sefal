<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;

test('em ambiente de teste a conexao default resolve para sqlite', function () {
    expect(config('database.default'))->toBe('sqlite');
    DB::select('select 1 as ok');
})->group('banco');

test('a conexao oracle declara o prefixo LRV_', function () {
    expect(config('database.connections.oracle.prefix'))->toBe('LRV_');
})->group('banco');

test('o seletor de banco nao rouba o sqlite em memoria da suite', function () {
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
})->group('banco');

test('a suite nunca aponta para o oracle, mesmo com o seletor em oracle', function () {
    // Sem o `.env` mandando nada, o seletor cai em `auto` e o teste acima passaria por
    // acidente. Aqui a decisão é reexecutada com o seletor no pior caso possível: um dev
    // com DB_DRIVER=oracle no .env (ou `auto` com DB_HOST preenchido) rodando a suíte.
    config(['database.seletor' => 'oracle']);

    (new AppServiceProvider(app()))->register();

    expect(config('database.default'))->toBe('sqlite');
})->group('banco');
