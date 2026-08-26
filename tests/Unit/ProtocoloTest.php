<?php

use App\Models\User;
use App\Support\Protocolo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O protocolo é `<PREFIXO><YYYYMMDD><NNN>` e o sequencial REINICIA todo dia, por prefixo. A reserva
 * é atômica (contador travado por linha), não `count()+1` — é isso que impede dois registros com o
 * mesmo número. Concorrência real não se prova em processo único; o que se prova aqui é o
 * incremento, a unicidade em sequência e o reinício na virada do dia e na troca de prefixo.
 *
 * Precisa de banco (o contador é uma tabela), por isso este arquivo pede TestCase + RefreshDatabase
 * — o `Pest.php` só os aplica em `Feature`.
 */
uses(TestCase::class, RefreshDatabase::class);

test('protocolo tem prefixo, data e sequencial diario', function () {
    $p1 = Protocolo::proximo('PER');
    $p2 = Protocolo::proximo('PER');

    expect($p1)->toStartWith('PER'.now()->format('Ymd'))
        ->and((int) substr($p2, -3))->toBe((int) substr($p1, -3) + 1);
});

test('sequencial e unico e reinicia por dia e por prefixo', function () {
    $dia = Carbon::create(2026, 6, 1);

    $gerados = [];
    for ($i = 0; $i < 5; $i++) {
        $gerados[] = Protocolo::proximo('XX', $dia);
    }

    expect($gerados)->toBe([
        'XX20260601001', 'XX20260601002', 'XX20260601003', 'XX20260601004', 'XX20260601005',
    ])
        ->and(array_unique($gerados))->toHaveCount(5)
        ->and(Protocolo::proximo('XX', Carbon::create(2026, 6, 2)))->toBe('XX20260602001')
        ->and(Protocolo::proximo('YY', $dia))->toBe('YY20260601001');
});

test('nao duplica em chamadas repetidas e o contador aponta o proximo', function () {
    $dia = Carbon::create(2026, 6, 4);

    $gerados = collect(range(1, 30))->map(fn () => Protocolo::proximo('ZZ', $dia));

    expect($gerados->unique())->toHaveCount(30)
        ->and((int) DB::table('protocolo_contadores')->where('prefixo', 'ZZ')->where('data', '20260604')->value('proximo'))->toBe(31);
});

test('inicializa a partir dos protocolos ja gravados quando o model e informado', function () {
    $dia = Carbon::create(2026, 6, 3);

    // Registros que já carregam protocolo do dia, mas sem linha de contador (dado anterior ao
    // contador, ou banco restaurado). O contador nasce a partir deles em vez de recomeçar do 001.
    foreach (['PC20260603001', 'PC20260603002', 'PC20260603003'] as $protocolo) {
        User::factory()->create(['name' => $protocolo]);
    }

    expect(Protocolo::proximo('PC', $dia, User::class, 'name'))->toBe('PC20260603004');
});

test('sem model informado o contador do dia comeca em 001', function () {
    expect(Protocolo::proximo('PC', Carbon::create(2026, 6, 5)))->toBe('PC20260605001');
});

test('formatar monta o protocolo a partir de um sequencial conhecido', function () {
    expect(Protocolo::formatar('per', Carbon::create(2026, 8, 25), 7))->toBe('PER20260825007')
        ->and(Protocolo::formatar('AI', Carbon::create(2026, 12, 31), 1234))->toBe('AI202612311234');
});

test('prefixo minusculo sai maiusculo', function () {
    expect(Protocolo::proximo('per', Carbon::create(2026, 7, 10)))->toBe('PER20260710001');
});
