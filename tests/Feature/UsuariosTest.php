<?php

use App\Actions\Fortify\AutenticarPorMatricula;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use App\Notifications\LinkDeSenha;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| Sistema › Usuários — a tela do Codecon trazida para o SEFAL (25/09/2026)
|--------------------------------------------------------------------------
|
| O que se testa aqui é o que a tela PROMETE e só o servidor garante: a conta
| nova nasce sem senha conhecida e recebe o convite; quem não definiu senha é
| avisado no login em vez de ouvir "senha inválida"; o Chefe de Setor é um só;
| ninguém se tranca do lado de fora; administrador só se dá entre
| administradores; a lixeira devolve a conta e a limpeza não apaga quem tem
| histórico. A régua é o doc `docs/regras-de-negocio/usuarios.md`.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    Notification::fake();
});

function adminDeUsuarios(): User
{
    return User::factory()->create(['admin' => true, 'ativo' => true, 'login' => 'admin.teste'])->fresh();
}

function contaNoSetor(string $slug, array $atributos = []): User
{
    $u = User::factory()->create(['admin' => false, 'ativo' => true, ...$atributos]);
    $u->setores()->attach(Setor::where('slug', $slug)->firstOrFail());

    return $u->fresh();
}

/** @return array<string, mixed> */
function dadosDeConta(array $extra = []): array
{
    return [
        'login' => 'Maria.Souza',
        'name' => 'Maria Souza',
        'email' => 'maria.souza@salvador.ba.gov.br',
        'setores' => ['fiscal'],
        'ativo' => true,
        ...$extra,
    ];
}

test('o administrador abre a tela e recebe as contas, a lixeira e as colunas do catálogo', function () {
    contaNoSetor('fiscal', ['name' => 'Fiscal Visível']);

    $this->actingAs(adminDeUsuarios())
        ->get('/retaguarda/usuarios')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Retaguarda/Sistema/Usuarios')
            ->has('usuarios', 2)
            ->has('excluidos', 0)
            ->where('listagens', fn ($l) => isset($l['usuarios.ativos'], $l['usuarios.excluidos']))
            ->where('mexeEmAdministrador', true));
});

test('quem não é administrador é barrado, com o motivo — a tela distribui acesso', function () {
    $this->actingAs(contaNoSetor('chefe-de-setor'))
        ->get('/retaguarda/usuarios')
        ->assertRedirect(route('retaguarda.inicio'))
        ->assertSessionHas('flash.erro');

    $this->actingAs(contaNoSetor('chefe-de-setor'))
        ->post('/retaguarda/usuarios', dadosDeConta())
        ->assertSessionHas('flash.erro');

    expect(User::where('login', 'maria.souza')->exists())->toBeFalse();
});

test('incluir cria a conta com o primeiro acesso PENDENTE e manda o convite por e-mail', function () {
    $this->actingAs(adminDeUsuarios())
        ->post('/retaguarda/usuarios', dadosDeConta())
        ->assertRedirect(route('retaguarda.usuarios.index'))
        ->assertSessionHas('flash.sucesso');

    $conta = User::where('login', 'maria.souza')->firstOrFail();

    expect($conta->senhaDefinida())->toBeFalse()
        ->and($conta->setores->pluck('slug')->all())->toBe(['fiscal'])
        ->and($conta->admin)->toBeFalse();

    Notification::assertSentTo($conta, LinkDeSenha::class, fn (LinkDeSenha $n) => $n->tipo === LinkDeSenha::CONVITE);
});

test('quem ainda não definiu a senha é AVISADO no login, e não ouve "senha inválida"', function () {
    $conta = User::criarComPrimeiroAcessoPendente([
        'login' => 'pendente1', 'name' => 'Pendente', 'email' => 'pendente@exemplo.com', 'ativo' => true,
    ]);

    $this->post(route('login.store'), ['login' => 'pendente1', 'password' => 'qualquer-coisa'])
        ->assertSessionHasErrors(['login' => AutenticarPorMatricula::SENHA_NAO_DEFINIDA]);

    $this->assertGuest();
    expect($conta->fresh()->senhaDefinida())->toBeFalse();
});

test('definir a senha pelo link do convite carimba o primeiro acesso e libera o login', function () {
    $conta = User::criarComPrimeiroAcessoPendente([
        'login' => 'pendente2', 'name' => 'Pendente Dois', 'email' => 'pendente2@exemplo.com', 'ativo' => true,
    ]);

    $this->post(route('password.update'), [
        'token' => Password::broker()->createToken($conta),
        'email' => $conta->email,
        'password' => 'Senha-Nova-2026!',
        'password_confirmation' => 'Senha-Nova-2026!',
    ])->assertSessionHasNoErrors();

    expect($conta->fresh()->senhaDefinida())->toBeTrue();

    $this->post(route('login.store'), ['login' => 'pendente2', 'password' => 'Senha-Nova-2026!']);
    $this->assertAuthenticatedAs($conta->fresh());
});

test('o "Esqueci minha senha" chega em português, no tom de redefinição', function () {
    $conta = contaNoSetor('fiscal', ['email' => 'esqueci@exemplo.com']);

    $this->post(route('password.email'), ['email' => 'esqueci@exemplo.com']);

    Notification::assertSentTo($conta, LinkDeSenha::class, function (LinkDeSenha $n) use ($conta) {
        return $n->tipo === LinkDeSenha::REDEFINICAO
            && str_contains((string) $n->toMail($conta)->subject, 'redefinição de senha');
    });
});

test('matrícula e e-mail são únicos — inclusive contra a lixeira — e o e-mail é obrigatório', function () {
    $admin = adminDeUsuarios();
    $excluida = contaNoSetor('fiscal', ['login' => 'maria.souza', 'email' => 'outra@exemplo.com']);
    $excluida->delete();

    $this->actingAs($admin)
        ->post('/retaguarda/usuarios', dadosDeConta(['email' => '']))
        ->assertSessionHasErrors(['login', 'email']);

    expect(session('errors')->first('login'))->toContain('Excluídos');
});

test('o Chefe de Setor é UM só: marcar outra pessoa tira o setor de quem tinha, e o recado diz quem saiu', function () {
    $antigo = contaNoSetor('chefe-de-setor', ['name' => 'Chefe Antigo']);
    $novo = contaNoSetor('fiscal', ['name' => 'Chefe Novo']);

    $this->actingAs(adminDeUsuarios())
        ->put("/retaguarda/usuarios/{$novo->id}", [
            'name' => $novo->name, 'email' => $novo->email, 'setores' => ['chefe-de-setor'], 'ativo' => true,
        ])
        ->assertSessionHas('flash.sucesso', fn (string $m) => str_contains($m, 'Chefe Antigo deixou de ser Chefe de Setor'));

    expect($novo->fresh()->setores->pluck('slug')->all())->toBe(['chefe-de-setor'])
        ->and($antigo->fresh()->setores->pluck('slug')->all())->toBe([]);
});

test('ninguém se tranca do lado de fora: não tira o próprio Administrador, não se desativa, não se exclui', function () {
    $admin = adminDeUsuarios();
    $admin->setores()->attach(Setor::where('slug', 'administrador')->firstOrFail());

    $this->actingAs($admin)
        ->put("/retaguarda/usuarios/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'setores' => [], 'ativo' => true,
        ])
        ->assertSessionHasErrors(['setores']);

    $this->actingAs($admin)
        ->put("/retaguarda/usuarios/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'setores' => ['administrador'], 'ativo' => false,
        ])
        ->assertSessionHasErrors(['ativo']);

    $this->actingAs($admin)->delete("/retaguarda/usuarios/{$admin->id}")->assertSessionHas('flash.erro');

    $admin->refresh();
    expect($admin->ativo)->toBeTrue()
        ->and($admin->ehAdmin())->toBeTrue()
        ->and($admin->trashed())->toBeFalse();
});

test('com a tela concedida a outro setor, dar ou mexer em ADMINISTRADOR continua só de administrador', function () {
    PermissaoSetor::create([
        'setor' => 'chefe-de-setor', 'slug' => 'usuarios',
        'visivel' => true, 'habilitado' => true, 'incluir' => true, 'excluir' => true,
    ]);
    $chefe = contaNoSetor('chefe-de-setor');
    $outroAdmin = adminDeUsuarios();

    // Criar alguém como administrador: recusado.
    $this->actingAs($chefe)
        ->post('/retaguarda/usuarios', dadosDeConta(['setores' => ['administrador']]))
        ->assertSessionHasErrors(['setores']);

    // Alterar a conta de um administrador: recusado.
    $this->actingAs($chefe)
        ->put("/retaguarda/usuarios/{$outroAdmin->id}", [
            'name' => 'Trocado', 'email' => $outroAdmin->email, 'setores' => [], 'ativo' => false,
        ])
        ->assertSessionHasErrors(['setores']);

    // Excluir a conta de um administrador: recusado.
    $this->actingAs($chefe)->delete("/retaguarda/usuarios/{$outroAdmin->id}")->assertSessionHas('flash.erro');

    expect(User::where('login', 'maria.souza')->exists())->toBeFalse()
        ->and($outroAdmin->fresh()->name)->not->toBe('Trocado')
        ->and($outroAdmin->fresh()->trashed())->toBeFalse();

    // O resto da tela funciona para ele: criar um fiscal passa.
    $this->actingAs($chefe)->post('/retaguarda/usuarios', dadosDeConta())->assertSessionHas('flash.sucesso');
});

test('excluir manda para a LIXEIRA: some do login e da lista, aparece em Excluídos, e restaurar devolve', function () {
    $admin = adminDeUsuarios();
    $conta = contaNoSetor('fiscal', ['login' => 'vai.e.volta']);

    $this->actingAs($admin)->delete("/retaguarda/usuarios/{$conta->id}")->assertSessionHas('flash.sucesso');

    expect(User::porMatricula('vai.e.volta'))->toBeNull();

    $this->actingAs($admin)->get('/retaguarda/usuarios')
        ->assertInertia(fn ($p) => $p
            ->where('excluidos.0.login', 'vai.e.volta')
            ->where('excluidos.0.temHistorico', false)
            ->where('excluidos.0.diasRestantes', 3));

    $this->actingAs($admin)->post("/retaguarda/usuarios/{$conta->id}/restaurar")->assertSessionHas('flash.sucesso');

    expect(User::porMatricula('vai.e.volta')?->setores->pluck('slug')->all())->toBe(['fiscal']);
});

test('a limpeza da lixeira remove de vez só o que venceu E não tem histórico', function () {
    $semHistorico = contaNoSetor('fiscal', ['login' => 'sem.historico']);
    $comHistorico = contaNoSetor('fiscal', ['login' => 'com.historico']);
    $recente = contaNoSetor('fiscal', ['login' => 'recente']);

    $demanda = new Demanda;
    $demanda->forceFill([
        'protocolo' => 'TST-USR-1', 'canal' => Demanda::CANAL_AVULSA, 'situacao' => Demanda::RECEBIDA,
        'assunto' => 'Teste', 'recebida_em' => Date::now(),
    ])->save();
    // A autoria de um passo do trâmite — é o que faz a conta ter histórico.
    $demanda->registrar(acao: 'Registrada', situacao: Demanda::RECEBIDA, papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR, autor: $comHistorico);

    foreach ([$semHistorico, $comHistorico] as $u) {
        $u->delete();
        DB::table('users')->where('id', $u->id)->update(['deleted_at' => Date::now()->subDays(4)]);
    }
    $recente->delete();

    $this->artisan('sefal:purgar-usuarios-excluidos')->assertSuccessful();

    expect(User::withTrashed()->find($semHistorico->id))->toBeNull()
        ->and(User::withTrashed()->find($comHistorico->id))->not->toBeNull()
        ->and(User::withTrashed()->find($recente->id))->not->toBeNull();
});

test('reenviar o convite vale para quem está pendente; quem já tem senha é orientado a usar "Esqueci minha senha"', function () {
    $admin = adminDeUsuarios();
    $pendente = User::criarComPrimeiroAcessoPendente([
        'login' => 'pendente3', 'name' => 'Pendente Três', 'email' => 'pendente3@exemplo.com', 'ativo' => true,
    ]);
    $comSenha = contaNoSetor('fiscal');

    $this->actingAs($admin)->post("/retaguarda/usuarios/{$pendente->id}/convite")->assertSessionHas('flash.sucesso');
    Notification::assertSentTo($pendente, LinkDeSenha::class);

    $this->actingAs($admin)->post("/retaguarda/usuarios/{$comSenha->id}/convite")
        ->assertSessionHas('flash.erro', fn (string $m) => str_contains($m, 'Esqueci minha senha'));
    Notification::assertNotSentTo($comSenha, LinkDeSenha::class);
});
