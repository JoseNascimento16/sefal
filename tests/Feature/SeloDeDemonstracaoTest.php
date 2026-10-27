<?php

use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| O aviso de "dados de exemplo" só existe na demonstração (dono, 27/10/2026)
|--------------------------------------------------------------------------
|
| As telas da Retaguarda traziam o selo "PROTÓTIPO · DADOS FICTÍCIOS" em todo
| ambiente, de quando ainda não liam o banco. Em homologação e em produção os
| registros são os do banco e o aviso era falso; só o Render (APP_ENV =
| demonstracao) roda com cadastro de exemplo.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

afterEach(function () {
    app()->detectEnvironment(fn () => 'testing');
});

it('fora da demonstração as telas não dizem que os registros são exemplos', function (string $ambiente) {
    app()->detectEnvironment(fn () => $ambiente);

    $this->actingAs(User::factory()->create(['admin' => true, 'ativo' => true]))
        ->get(route('retaguarda.inicio'))
        ->assertInertia(fn (Assert $tela) => $tela->where('demonstracao', false));
})->with(['testing', 'local', 'homolog', 'production']);

it('na demonstração as telas avisam que os registros são exemplos', function () {
    app()->detectEnvironment(fn () => 'demonstracao');

    $this->actingAs(User::factory()->create(['admin' => true, 'ativo' => true]))
        ->get(route('retaguarda.inicio'))
        ->assertInertia(fn (Assert $tela) => $tela->where('demonstracao', true));
});
