<?php

use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
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

    foreach (['admin' => 'admin123', 'chefe' => 'chefe123', 'lider1' => 'lider123', 'fiscal1' => 'fiscal123'] as $login => $senha) {
        $usuario = User::where('login', $login)->first();

        // Autentica DE VERDADE: existir e deixar entrar são coisas diferentes, e
        // é a segunda que o avaliador precisa.
        expect($usuario)->not->toBeNull("a conta {$login} não existe")
            ->and($usuario->ativo)->toBeTrue("a conta {$login} está inativa")
            ->and(Hash::check($senha, $usuario->password))->toBeTrue("a senha inicial de {$login} não entra");
    }

    // Uma conta por PAPEL (decisão do dono, 24/09/2026): o administrador enxerga
    // tudo, então não revela nem a mesa do chefe nem o recorte do líder.
    $papel = fn (string $login): array => User::where('login', $login)->first()->setores->pluck('slug')->all();

    expect($papel('chefe'))->toContain('chefe-de-setor')
        ->and($papel('lider1'))->toContain('lider-de-equipe')
        ->and($papel('fiscal1'))->toContain('fiscal')
        // O coordenador não entra no SEFAL (decisão do dono, 22/09/2026).
        ->and(User::where('login', 'coordenador')->exists())->toBeFalse();
});

it('com --so-estas-contas ficam SÓ as quatro contas, amarradas à estrutura', function () {
    $this->artisan('sefal:preparar-demonstracao', ['--so-estas-contas' => true])->assertSuccessful();

    expect(User::orderBy('login')->pluck('login')->all())->toBe(['admin', 'chefe', 'fiscal1', 'lider1']);

    $lider = User::where('login', 'lider1')->first();
    $fiscal = User::where('login', 'fiscal1')->first();

    // O líder da demonstração lidera a A1 e só ela (dono, 25/09/2026): as outras
    // ficam sem líder com conta, e o recorte do líder aparece na demonstração.
    expect(Equipe::count())->toBeGreaterThan(1)
        ->and(Equipe::where('lider_id', $lider->id)->pluck('codigo')->all())->toBe(['A1'])
        ->and(Equipe::where('codigo', '!=', 'A1')->whereNotNull('lider_id')->count())->toBe(0)
        // E o fiscal integra todas as equipes, para a fila do aparelho não vir vazia.
        ->and(DB::table('equipe_fiscais')->where('user_id', $fiscal->id)->count())->toBe(Equipe::count())
        // Nenhuma fiscalização ficou assinada por conta apagada.
        ->and(Fiscalizacao::where('fiscal_id', '!=', $fiscal->id)->count())->toBe(0);

    // A senha continua entrando depois do enxugamento.
    expect(Hash::check('lider123', $lider->password))->toBeTrue();
});

it('sem a opção, nenhuma conta é apagada', function () {
    $this->artisan('sefal:preparar-demonstracao')->assertSuccessful();

    // As contas da estrutura (líderes por equipe, fiscais) continuam ao lado das quatro.
    expect(User::where('login', 'lider-c1')->exists())->toBeTrue()
        ->and(User::count())->toBeGreaterThan(4);
});

it('NUNCA troca a senha de uma conta que já existe', function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);

    // Quem administra definiu a própria senha — inclusive uma diferente da que o
    // comando usaria para uma conta nova.
    User::factory()->create(['login' => 'chefe', 'password' => 'a-que-o-dono-definiu', 'ativo' => true]);

    $this->artisan('sefal:preparar-demonstracao')->assertSuccessful();

    // Repor a senha no boot já pôs o dono do lado de fora do próprio sistema, e
    // ele descobriu isso tentando entrar. Senha é decisão de gente.
    expect(Hash::check('a-que-o-dono-definiu', User::where('login', 'chefe')->first()->password))
        ->toBeTrue('o preparo sobrescreveu uma senha definida por quem administra')
        ->and(Hash::check('chefe123', User::where('login', 'chefe')->first()->password))->toBeFalse();
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
    $sem = User::factory()->create(['login' => 'fiscal1', 'ativo' => true]);
    DB::table('users')->where('id', $sem->id)->update(['password' => '']);

    $this->artisan('sefal:preparar-demonstracao')->assertSuccessful();

    expect(Hash::check('fiscal123', User::where('login', 'fiscal1')->first()->password))->toBeTrue();
});
