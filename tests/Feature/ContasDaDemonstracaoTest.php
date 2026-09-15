<?php

use App\Models\Demanda;
use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| LEI — quem abre a demonstração consegue ENTRAR nela
|--------------------------------------------------------------------------
|
| Reproduz um defeito real: o banco da demonstração subiu íntegro, com as 55
| demandas no lugar, e ninguém conseguia entrar. A senha de cada conta era a
| própria matrícula (regra do seeder da estrutura), e não havia como adivinhar
| isso — o avaliador erra a senha e conclui que o sistema está fora do ar.
|
| Pior: o preparo pulava a criação das contas quando JÁ HAVIA demanda, que é
| exatamente o caso do boot com o snapshot versionado. Quanto mais pronta a
| demonstração, menos chance de a porta existir.
|
| O teste autentica DE VERDADE — não confere se o usuário existe. Existir e
| deixar entrar são coisas diferentes, e é a segunda que o avaliador precisa.
|
*/

it('cria as contas de demonstração com senha inicial e o papel certo', function () {
    $this->artisan('sefal:preparar-demonstracao')->assertSuccessful();

    foreach (['admin' => 'admin123', 'coordenador' => 'coordenador123', 'gestor1' => 'gestor123'] as $login => $senha) {
        $usuario = User::where('login', $login)->first();

        // Autentica DE VERDADE: existir e deixar entrar são coisas diferentes, e
        // é a segunda que o avaliador precisa.
        expect($usuario)->not->toBeNull("a conta {$login} não existe")
            ->and($usuario->ativo)->toBeTrue("a conta {$login} está inativa")
            ->and(Hash::check($senha, $usuario->password))->toBeTrue("a senha inicial de {$login} não entra");
    }

    // O coordenador existe para a demonstração mostrar o PAPEL, e não só a tela:
    // o administrador enxerga tudo, então não revela o recorte de quem tria.
    expect(User::where('login', 'coordenador')->first()->setores->pluck('slug')->all())
        ->toContain('coordenador')
        ->and(User::where('login', 'gestor1')->first()->setores->pluck('slug')->all())
        ->toContain('chefe-de-setor');
});

it('NUNCA troca a senha de uma conta que já existe', function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);

    // Quem administra definiu a própria senha — inclusive uma diferente da que o
    // comando usaria para uma conta nova.
    User::factory()->create(['login' => 'gestor1', 'password' => 'a-que-o-dono-definiu', 'ativo' => true]);

    $this->artisan('sefal:preparar-demonstracao')->assertSuccessful();

    // Repor a senha no boot já pôs o dono do lado de fora do próprio sistema, e
    // ele descobriu isso tentando entrar. Senha é decisão de gente.
    expect(Hash::check('a-que-o-dono-definiu', User::where('login', 'gestor1')->first()->password))
        ->toBeTrue('o preparo sobrescreveu uma senha definida por quem administra')
        ->and(Hash::check('gestor123', User::where('login', 'gestor1')->first()->password))->toBeFalse();
});

it('preenche a senha quando a conta existe mas está sem nenhuma', function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);

    // Conta importada sem senha (é como o primeiro acesso nasce): aqui preencher
    // não tira nada de ninguém — sem isso ela seria inalcançável na demonstração.
    /*
     * "Sem senha" é a string vazia (a coluna é NOT NULL) e precisa ser gravada
     * pelo BANCO, não pelo model: o cast `hashed` cifraria a string vazia e o
     * resultado seria um hash válido de nada — uma conta que parece ter senha e
     * na qual ninguém consegue entrar. É assim que uma conta importada chega.
     */
    $sem = User::factory()->create(['login' => 'coordenador', 'ativo' => true]);
    DB::table('users')->where('id', $sem->id)->update(['password' => '']);

    $this->artisan('sefal:preparar-demonstracao')->assertSuccessful();

    expect(Hash::check('coordenador123', User::where('login', 'coordenador')->first()->password))->toBeTrue();
});
