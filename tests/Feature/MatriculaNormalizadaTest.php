<?php

use App\Models\User;
use Database\Seeders\SetoresSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| A matrícula é normalizada na ESCRITA
|--------------------------------------------------------------------------
|
| Entrar sem se importar com a caixa só é seguro se não existirem duas contas
| que diferem apenas nela — e nem SQLite nem Oracle garantem isso pela chave
| única. Quem garante é a normalização na gravação: toda matrícula é guardada
| em minúsculo, então 'ADMIN' e 'admin' disputam a MESMA linha e a segunda
| tentativa esbarra na unicidade.
|
*/

test('a matricula e gravada sempre em minusculo, sem espaco sobrando', function () {
    $u = User::factory()->create(['login' => '  ADMIN  ']);

    expect($u->login)->toBe('admin')
        ->and(DB::table('users')->where('id', $u->id)->value('login'))->toBe('admin');
});

test('duas matriculas que so diferem na caixa nao coexistem', function () {
    User::factory()->create(['login' => 'admin']);

    expect(fn () => User::factory()->create(['login' => 'ADMIN']))
        ->toThrow(QueryException::class);
});

test('quem digita a matricula em caixa alta entra na conta certa', function () {
    $u = User::factory()->create(['login' => 'f1000', 'password' => bcrypt('segredo1')]);

    $this->post('/login', ['login' => '  F1000 ', 'password' => 'segredo1'])
        ->assertRedirect('/retaguarda/inicio');

    $this->assertAuthenticatedAs($u);
});

test('o comando de criar usuario nao duplica a conta por causa da caixa da matricula', function () {
    $this->artisan('fp:criar-usuario-dev --login=admin')->assertSuccessful();
    $this->artisan('fp:criar-usuario-dev --login=ADMIN')->assertSuccessful();

    expect(User::count())->toBe(1)
        ->and(User::first()->login)->toBe('admin');
});

test('os comandos de administracao acham o usuario mesmo com a caixa trocada', function () {
    $this->seed(SetoresSeeder::class);
    User::factory()->create(['login' => 'f2222']);

    $this->artisan('fp:atribuir-setor F2222 fiscal')->assertSuccessful();
    $this->artisan('fp:setar-senha F2222 novaSenha123')->assertSuccessful();

    expect(User::porMatricula('F2222')?->setores->pluck('slug')->all())->toBe(['fiscal']);
});
