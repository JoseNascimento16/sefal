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
    $u = User::porMatricula('admin');
    expect($u)->not->toBeNull()
        ->and(Hash::check('admin123', $u->password))->toBeTrue()
        ->and($u->ehAdmin())->toBeTrue();
});

test('fp:criar-usuario-dev recusa rodar em producao e nao cria o usuario', function () {
    $this->app['env'] = 'production';

    $this->artisan('fp:criar-usuario-dev')
        ->expectsOutputToContain('produção')
        ->assertFailed();

    expect(User::porMatricula('admin') !== null)->toBeFalse();
});

test('fp:criar-usuario-dev roda em producao quando o --force e explicito', function () {
    $this->app['env'] = 'production';

    $this->artisan('fp:criar-usuario-dev --force')->assertSuccessful();

    expect(User::porMatricula('admin')?->ehAdmin())->toBeTrue();
});

test('comando fp:atribuir-setor adiciona e remove o setor do usuario', function () {
    User::factory()->create(['login' => 'F2222']);

    $this->artisan('fp:atribuir-setor F2222 fiscal')->assertSuccessful();
    expect(User::porMatricula('F2222')->setores->pluck('slug')->all())->toBe(['fiscal']);

    $this->artisan('fp:atribuir-setor F2222 fiscal --remover')->assertSuccessful();
    expect(User::porMatricula('F2222')->setores)->toBeEmpty();
});

test('atribuir o setor administrador liga a flag de admin e diz isso na saida', function () {
    User::factory()->create(['login' => 'A1000', 'admin' => false]);

    $this->artisan('fp:atribuir-setor A1000 administrador')
        ->expectsOutputToContain('vínculo criado; flag de administrador ligada')
        ->assertSuccessful();

    expect(User::porMatricula('A1000')->admin)->toBeTrue();
});

test('remover o setor administrador desliga a flag e o vinculo, dizendo as duas coisas', function () {
    $u = User::factory()->create(['login' => 'A2000', 'admin' => true]);
    $u->setores()->attach(Setor::where('slug', 'administrador')->first());

    // As duas coisas numa frase só: o `expectsOutputToContain` casa uma expectativa
    // por linha escrita, e o comando relata vínculo e flag na mesma linha.
    $this->artisan('fp:atribuir-setor A2000 administrador --remover')
        ->expectsOutputToContain('vínculo removido; flag de administrador desligada')
        ->assertSuccessful();

    $u = User::porMatricula('A2000');
    expect($u->admin)->toBeFalse()
        ->and($u->setores)->toBeEmpty();
});

test('remover o setor administrador de quem so tem a flag desliga o papel sem acao silenciosa', function () {
    // O usuário tem a flag mas nunca teve o vínculo: o comando derruba o papel de
    // qualquer jeito, e a saída precisa dizer isso — antes ela dizia só "não estava
    // atribuído", como se nada tivesse acontecido.
    User::factory()->create(['login' => 'A3000', 'admin' => true]);

    $this->artisan('fp:atribuir-setor A3000 administrador --remover')
        ->expectsOutputToContain('não havia vínculo; flag de administrador desligada')
        ->assertSuccessful();

    expect(User::porMatricula('A3000')->admin)->toBeFalse();
});

test('atribuir setor comum nao mexe na flag de admin', function () {
    User::factory()->create(['login' => 'A4000', 'admin' => false]);

    $this->artisan('fp:atribuir-setor A4000 fiscal')->assertSuccessful();

    expect(User::porMatricula('A4000')->admin)->toBeFalse();
});

test('comando fp:atribuir-setor recusa setor fora do catalogo e login inexistente', function () {
    User::factory()->create(['login' => 'F3333']);

    $this->artisan('fp:atribuir-setor F3333 juridico')->assertFailed();
    $this->artisan('fp:atribuir-setor F9999 fiscal')->assertFailed();
});

test('comando fp:setar-senha troca a senha do usuario', function () {
    User::factory()->create(['login' => 'F4444']);

    $this->artisan('fp:setar-senha F4444 novaSenha123')->assertSuccessful();

    expect(Hash::check('novaSenha123', User::porMatricula('F4444')->password))->toBeTrue();
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

    expect(User::query()->count())->toBe(1)
        ->and(User::porMatricula('admin')->setores)->toHaveCount(1);
});

test('seeder de setores e idempotente', function () {
    $this->seed(SetoresSeeder::class);

    expect(Setor::count())->toBe(3);
});
