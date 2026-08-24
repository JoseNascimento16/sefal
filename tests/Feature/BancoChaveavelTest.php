<?php

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
