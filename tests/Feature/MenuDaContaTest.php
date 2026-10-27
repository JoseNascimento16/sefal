<?php

use App\Models\Setor;
use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;

/*
|--------------------------------------------------------------------------
| O menu da CONTA no canto superior direito (dono, 25/09/2026)
|--------------------------------------------------------------------------
|
| Meu Perfil, Usuários e Sair saíram do menu lateral e foram para o canto
| superior direito, como no Codecon. Sair do menu lateral não pode fechar as
| telas: o atalho sai, a rota e a permissão ficam.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

/** As rotas dos itens do menu lateral que a pessoa recebe (pastas abertas). */
function rotasDoMenuLateral(User $u): array
{
    $menu = test()->actingAs($u)->get('/retaguarda/inicio')->viewData('page')['props']['menu'];

    return collect($menu)->flatMap(static fn (array $secao): array => $secao['itens'])
        ->flatMap(static fn (array $item): array => [$item, ...($item['filhos'] ?? [])])
        ->pluck('url')->filter()->values()->all();
}

it('Meu Perfil e Usuários não estão mais no menu lateral, e as telas continuam abrindo', function () {
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);

    $rotas = rotasDoMenuLateral($admin);

    expect(collect($rotas)->contains(fn (string $r) => str_contains($r, '/retaguarda/usuarios')))->toBeFalse()
        ->and(collect($rotas)->contains(fn (string $r) => str_contains($r, '/perfil')))->toBeFalse();

    $this->actingAs($admin)->get(route('retaguarda.usuarios.index'))->assertOk();
    $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
});

it('o atalho de Usuários no menu da conta segue a permissão da tela', function () {
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);
    $chefe = User::factory()->create(['admin' => false, 'ativo' => true]);
    $chefe->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    $this->actingAs($admin)->get('/retaguarda/inicio')
        ->assertInertia(fn ($p) => $p->where('auth.user.administra_usuarios', true));

    $this->actingAs($chefe->fresh())->get('/retaguarda/inicio')
        ->assertInertia(fn ($p) => $p->where('auth.user.administra_usuarios', false));
});
