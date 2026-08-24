<?php

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;

/*
|--------------------------------------------------------------------------
| Desativar um usuário tem que valer AGORA
|--------------------------------------------------------------------------
|
| Recusar no login não basta: quem já estava dentro continuaria dentro, e o
| "Continuar conectado" (cookie de lembrete) entra sem passar pela conferência
| do login. A desativação precisa valer na requisição seguinte, dizendo o motivo
| — a lei do projeto é que ninguém é barrado em silêncio.
|
*/

test('quem e desativado enquanto usa o sistema cai na proxima requisicao, com o motivo', function () {
    $u = User::factory()->create(['ativo' => true]);

    $this->actingAs($u)->get('/retaguarda/inicio')->assertOk();

    $u->update(['ativo' => false]);

    $this->get('/retaguarda/inicio')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['login' => 'Usuário inativo — procure o administrador.']);

    $this->assertGuest();
});

test('quem e barrado cai na tela de login de verdade, sem loop de redirecionamento', function () {
    $u = User::factory()->create(['ativo' => true]);

    $this->actingAs($u);
    $u->update(['ativo' => false]);

    // Seguir o redirecionamento até o fim: se a guarda e o middleware de visitante
    // brigassem, isto estouraria em vez de abrir a tela.
    $this->followingRedirects()
        ->get('/retaguarda/inicio')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

test('a desativacao vale para qualquer tela autenticada, nao so para a inicial', function () {
    $u = User::factory()->create(['ativo' => true]);

    $this->actingAs($u);
    $u->update(['ativo' => false]);

    $this->get(route('profile.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('nem o continuar conectado mantem dentro quem foi desativado', function () {
    $u = User::factory()->create(['login' => 'F3000', 'password' => bcrypt('segredo1'), 'ativo' => true]);

    $resposta = $this->post('/login', [
        'login' => 'F3000',
        'password' => 'segredo1',
        'remember' => 'on',
    ]);

    $lembrete = collect($resposta->headers->getCookies())
        ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web'));

    expect($lembrete)->not->toBeNull();

    // O ajudante de teste cifra sozinho o cookie que a gente manda, então aqui vai
    // o valor decifrado — senão ele seria cifrado duas vezes e chegaria ilegível.
    $valor = CookieValuePrefix::remove(decrypt($lembrete->getValue(), false));

    // O navegador foi fechado: a sessão some e sobra só o cookie de lembrete.
    $esquecerSessao = function () {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
    };

    // Controle: com a conta ativa, o cookie sozinho entra. Sem isto, o teste
    // seguinte passaria por não estar autenticando coisa nenhuma.
    $esquecerSessao();
    $this->withCookie($lembrete->getName(), $valor)
        ->get('/retaguarda/inicio')
        ->assertOk();

    $u->update(['ativo' => false]);

    $esquecerSessao();
    $this->withCookie($lembrete->getName(), $valor)
        ->get('/retaguarda/inicio')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});
