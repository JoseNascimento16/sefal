<?php

use App\Models\User;

test('login por matricula autentica e redireciona para a retaguarda', function () {
    $u = User::factory()->create(['login' => 'F1000', 'password' => bcrypt('segredo1'), 'ativo' => true]);
    $this->post('/login', ['login' => 'F1000', 'password' => 'segredo1'])
        ->assertRedirect('/retaguarda/inicio');
    $this->assertAuthenticatedAs($u);
});

test('senha errada devolve erro especifico no campo login', function () {
    User::factory()->create(['login' => 'F1000', 'password' => bcrypt('segredo1')]);
    $this->from('/login')->post('/login', ['login' => 'F1000', 'password' => 'errada'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();
});

test('usuario inativo e recusado com motivo explicito', function () {
    User::factory()->create(['login' => 'F2000', 'password' => bcrypt('segredo1'), 'ativo' => false]);
    $resp = $this->from('/login')->post('/login', ['login' => 'F2000', 'password' => 'segredo1']);
    $resp->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain('inativo');
    $this->assertGuest();
});

test('matricula desconhecida e recusada com motivo em portugues, sem revelar se a conta existe', function () {
    $this->from('/login')->post('/login', ['login' => 'F9999', 'password' => 'segredo1'])
        ->assertSessionHasErrors('login');

    expect(session('errors')->first('login'))->toBe('Matrícula ou senha inválida. Confira os dados e tente novamente.');
    $this->assertGuest();
});

test('a mesma recusa serve para senha errada e para matricula inexistente', function () {
    $recusa = 'Matrícula ou senha inválida. Confira os dados e tente novamente.';

    User::factory()->create(['login' => 'F1000', 'password' => bcrypt('segredo1')]);

    // Conta existe, senha errada.
    $this->from('/login')->post('/login', ['login' => 'F1000', 'password' => 'errada'])
        ->assertSessionHasErrors(['login' => $recusa]);

    // Conta não existe: a resposta é palavra por palavra a mesma, para o
    // formulário não dizer quem tem cadastro aqui.
    $this->from('/login')->post('/login', ['login' => 'F0000', 'password' => 'errada'])
        ->assertSessionHasErrors(['login' => $recusa]);
});

test('a matricula e reconhecida independentemente de caixa alta ou baixa', function () {
    $u = User::factory()->create(['login' => 'F1000', 'password' => bcrypt('segredo1')]);

    $this->post('/login', ['login' => 'f1000', 'password' => 'segredo1'])
        ->assertRedirect('/retaguarda/inicio');

    $this->assertAuthenticatedAs($u);
});

test('a tela de login pede matricula, nao e-mail', function () {
    $this->get('/login')->assertOk();

    $html = file_get_contents(resource_path('js/pages/auth/login.tsx'));

    expect($html)->toContain('Matrícula')
        ->and($html)->toContain('name="login"');
});
