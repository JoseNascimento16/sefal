<?php

use App\Models\Setor;
use App\Models\User;
use App\Models\UserSetor;
use Database\Seeders\SetoresSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->seed(SetoresSeeder::class));

test('seeder cria os tres setores do sistema', function () {
    expect(Setor::pluck('slug')->sort()->values()->all())->toBe(['administrador', 'fiscal', 'gestor']);
});

test('usuario pertence a N setores e ehAdmin reconhece o setor administrador', function () {
    $u = User::factory()->create(['login' => 'F1234', 'admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', 'administrador')->first());
    expect($u->fresh()->ehAdmin())->toBeTrue();
});

test('comando fp:criar-usuario-dev cria admin com senha conhecida', function () {
    $this->artisan('fp:criar-usuario-dev')->assertSuccessful();
    $u = User::where('login', 'admin')->first();
    expect($u)->not->toBeNull()
        ->and(Hash::check('admin123', $u->password))->toBeTrue()
        ->and($u->ehAdmin())->toBeTrue();
});

test('comando fp:atribuir-setor adiciona e remove o setor do usuario', function () {
    User::factory()->create(['login' => 'F2222']);

    $this->artisan('fp:atribuir-setor F2222 fiscal')->assertSuccessful();
    expect(User::where('login', 'F2222')->first()->setores->pluck('slug')->all())->toBe(['fiscal']);

    $this->artisan('fp:atribuir-setor F2222 fiscal --remover')->assertSuccessful();
    expect(User::where('login', 'F2222')->first()->setores)->toBeEmpty();
});

test('comando fp:atribuir-setor recusa setor fora do catalogo e login inexistente', function () {
    User::factory()->create(['login' => 'F3333']);

    $this->artisan('fp:atribuir-setor F3333 juridico')->assertFailed();
    $this->artisan('fp:atribuir-setor F9999 fiscal')->assertFailed();
});

test('comando fp:setar-senha troca a senha do usuario', function () {
    User::factory()->create(['login' => 'F4444']);

    $this->artisan('fp:setar-senha F4444 novaSenha123')->assertSuccessful();

    expect(Hash::check('novaSenha123', User::where('login', 'F4444')->first()->password))->toBeTrue();
});

test('comando fp:setar-senha falha para login inexistente', function () {
    $this->artisan('fp:setar-senha F0000 qualquer123')->assertFailed();
});

test('ehAdmin reconhece a flag admin mesmo sem o setor administrador', function () {
    $u = User::factory()->create(['login' => 'F5555', 'admin' => true]);

    expect($u->ehAdmin())->toBeTrue();
});

test('usuario comum sem setor administrador nao e admin', function () {
    $u = User::factory()->create(['login' => 'F6666', 'admin' => false]);
    $u->setores()->attach(Setor::where('slug', 'fiscal')->first());

    expect($u->fresh()->ehAdmin())->toBeFalse();
});

test('o vinculo usuario-setor registra quando o acesso foi concedido', function () {
    $u = User::factory()->create(['login' => 'F8888']);
    $u->setores()->attach(Setor::where('slug', 'gestor')->first());

    $vinculo = $u->fresh()->setores->first()->pivot;

    expect($vinculo)->toBeInstanceOf(UserSetor::class)
        ->and($vinculo->created_at)->not->toBeNull();
});

test('a matricula e unica entre usuarios', function () {
    User::factory()->create(['login' => 'F7777']);

    expect(fn () => User::factory()->create(['login' => 'F7777']))
        ->toThrow(QueryException::class);
});

test('fp:criar-usuario-dev rodado duas vezes nao duplica usuario nem vinculo', function () {
    $this->artisan('fp:criar-usuario-dev')->assertSuccessful();
    $this->artisan('fp:criar-usuario-dev')->assertSuccessful();

    expect(User::where('login', 'admin')->count())->toBe(1)
        ->and(User::where('login', 'admin')->first()->setores)->toHaveCount(1);
});

test('seeder de setores e idempotente', function () {
    $this->seed(SetoresSeeder::class);

    expect(Setor::count())->toBe(3);
});
