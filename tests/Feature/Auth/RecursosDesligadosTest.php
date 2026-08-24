<?php

use App\Models\User;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Recursos do starter kit que este sistema não usa
|--------------------------------------------------------------------------
|
| Substitui os testes de auto-cadastro (RegistrationTest) e de segundo fator
| (TwoFactorChallengeTest) que vieram do starter kit. Eles provavam que esses
| caminhos funcionavam; aqui provamos o contrário — que eles NÃO existem.
|
| Sistema de governo: a conta nasce por comando/administrador, nunca por um
| formulário público. E o acesso é matrícula + senha: nada de segundo fator
| nem de passkey enquanto ninguém pedir.
|
*/

test('o auto-cadastro publico nao existe', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Invasor',
        'email' => 'invasor@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('nao ha rota de segundo fator', function () {
    $this->get('/two-factor-challenge')->assertNotFound();
    $this->post('/two-factor-challenge', ['code' => '123456'])->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->post('/user/two-factor-authentication')
        ->assertNotFound();

    expect(Features::enabled(Features::twoFactorAuthentication()))->toBeFalse();
});

test('nao ha rota de passkey', function () {
    $this->get('/passkeys/login/options')->assertNotFound();
    $this->post('/passkeys/login')->assertNotFound();
    $this->get('/.well-known/passkey-endpoints')->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get('/user/passkeys/options')
        ->assertNotFound();

    expect(Features::enabled(Features::passkeys()))->toBeFalse();
});
