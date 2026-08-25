<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Recursos do starter kit que este sistema não usa
|--------------------------------------------------------------------------
|
| Substitui os testes de auto-cadastro (RegistrationTest), de segundo fator
| (TwoFactorChallengeTest), de verificação de e-mail (EmailVerificationTest,
| VerificationNotificationTest) e de autoexclusão de conta que vieram do starter
| kit. Eles provavam que esses caminhos funcionavam; aqui provamos o contrário —
| que eles NÃO existem.
|
| Sistema de governo: a conta nasce por comando/administrador, nunca por um
| formulário público — e por isso também não há e-mail a confirmar nem botão de
| "apagar minha conta". O acesso é matrícula + senha: nada de segundo fator nem
| de passkey enquanto ninguém pedir.
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

test('nao ha verificacao de e-mail', function () {
    // O caminho inteiro sai do ar: o aviso, o reenvio e o link assinado.
    $this->actingAs(User::factory()->create())->get('/email/verify')->assertNotFound();
    $this->actingAs(User::factory()->create())->post('/email/verification-notification')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/email/verify/1/algumhash')->assertNotFound();

    expect(Features::enabled(Features::emailVerification()))->toBeFalse();
});

test('a tela da propria conta continua aberta sem ninguem confirmar e-mail', function () {
    // A guarda `verified` saiu das rotas do Meu Perfil: com ela, e sem existir
    // caminho para confirmar, a tela de senha ficaria trancada para todo mundo.
    $u = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($u)->get(route('profile.edit'))->assertOk();

    $this->actingAs($u)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk();

    $this->actingAs($u)->get(route('appearance.edit'))->assertOk();
});

test('ninguem apaga a propria conta', function () {
    // Desligar o acesso é trabalho da administração (marca `ativo`). Apagar a
    // linha levaria embora o histórico de trabalho ligado a ela.
    $u = User::factory()->create();

    // Não há rota de exclusão, e o endereço do perfil não aceita o verbo DELETE.
    expect(Route::has('profile.destroy'))->toBeFalse();

    $this->actingAs($u)
        ->delete('/retaguarda/perfil', ['password' => 'password'])
        ->assertStatus(405);

    expect(User::find($u->id))->not->toBeNull();
});
