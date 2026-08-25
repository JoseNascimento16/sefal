<?php

use App\Models\User;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| O sistema fala português com quem o usa
|--------------------------------------------------------------------------
|
| Não é preciosismo: uma mensagem em inglês numa tela de órgão público é um
| defeito que a Qualidade devolve — e, pior, é o usuário lendo "The login field
| is required." sem saber o que fazer.
|
| O idioma vem da configuração (`config/app.php`, padrão `pt_BR`), então o
| padrão vale mesmo num ambiente cujo `.env` não diga nada.
|
*/

test('o idioma padrao do sistema e o portugues do Brasil', function () {
    expect(app()->getLocale())->toBe('pt_BR');
});

test('as mensagens de validacao saem em portugues, com o nome do campo que a pessoa ve', function () {
    $erros = Validator::make([], [
        'login' => 'required',
        'password' => 'required',
        'email' => 'required|email',
    ])->errors();

    expect($erros->first('login'))->toBe('O campo matrícula é obrigatório.');
    expect($erros->first('password'))->toBe('O campo senha é obrigatório.');
    expect($erros->first('email'))->toBe('O campo e-mail é obrigatório.');
});

test('o login sem matricula recusa em portugues', function () {
    $this->from('/login')
        ->post('/login', ['login' => '', 'password' => ''])
        ->assertSessionHasErrors(['login' => 'O campo matrícula é obrigatório.']);
});

test('a recusa por credencial errada tambem fala portugues', function () {
    User::factory()->create(['login' => 'F5001', 'password' => bcrypt('segredo1')]);

    $this->from('/login')
        ->post('/login', ['login' => 'F5001', 'password' => 'errada'])
        ->assertSessionHasErrors('login');

    expect(session('errors')->first('login'))->not->toContain('These credentials');
});

test('o sistema se apresenta pelo proprio nome, nao pelo nome do framework', function () {
    // O titulo da aba e o remetente dos e-mails saem daqui.
    expect(config('app.name'))->not->toBe('Laravel');
});
