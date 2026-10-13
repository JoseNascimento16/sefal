<?php

use App\Models\Area;
use App\Models\Equipe;
use App\Models\Levantamento;
use App\Models\LevantamentoLinha;
use App\Models\Setor;
use App\Models\User;
use App\Support\Estrutura;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Fiscalização › Levantamentos (dono, 13/10/2026)
|--------------------------------------------------------------------------
|
| A consulta, na Retaguarda, do censo de ambulantes que o aplicativo grava. O
| líder vê só o que as equipes dele levantaram — inclusive a foto, que não abre
| trocando o número no endereço.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
    Storage::fake('local');
});

function contaDosLevantamentos(string $setor, string $login): User
{
    $u = User::factory()->create(['login' => $login, 'admin' => false, 'ativo' => true]);
    $u->setores()->attach(Setor::where('slug', $setor)->firstOrFail());

    return $u->fresh();
}

function levantamentoDaEquipe(string $codigo, string $rua): Levantamento
{
    $autor = User::factory()->create();
    $levantamento = Levantamento::create([
        'protocolo' => 'LEV-'.$codigo, 'client_id' => 'c-'.$codigo, 'user_id' => $autor->id,
        'rua' => $rua, 'bairro' => 'Arenoso', 'equipes' => [$codigo], 'encarregados' => ['Encarregado '.$codigo],
        'iniciado_em' => Date::now()->subHour(), 'concluido_em' => Date::now(),
    ]);

    Storage::disk('local')->put('levantamentos/'.$levantamento->id.'/foto.jpg', UploadedFile::fake()->image('f.jpg')->getContent());
    LevantamentoLinha::create([
        'levantamento_id' => $levantamento->id, 'item' => '001', 'nome' => 'Ambulante de '.$rua,
        'atividade' => 'Bebidas', 'foto' => 'levantamentos/'.$levantamento->id.'/foto.jpg',
    ]);

    return $levantamento;
}

it('o administrador vê todos os levantamentos, com as linhas e a foto', function () {
    levantamentoDaEquipe('Z1', 'Rua da Z1');
    levantamentoDaEquipe('Z2', 'Rua da Z2');
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);

    $this->actingAs($admin)->get(route('retaguarda.levantamentos.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Retaguarda/Fiscalizacao/Levantamentos')
            ->has('levantamentos', 2)
            ->where('levantamentos.0.linhas.0.foto.disponivel', true));
});

it('o líder vê só os levantamentos das equipes dele, e a foto de outra equipe é recusada', function () {
    $lider = contaDosLevantamentos('lider-de-equipe', 'lider-lev');
    $area = Area::create(['nome' => 'Área L', 'regiao' => 'Teste']);
    Equipe::create(['codigo' => 'Z1', 'nome' => 'Equipe Z1', 'area_id' => $area->id, 'lider_id' => $lider->id]);
    Estrutura::esquecer();

    $minha = levantamentoDaEquipe('Z1', 'Rua da Z1');
    $alheia = levantamentoDaEquipe('Z2', 'Rua da Z2');

    $this->actingAs($lider)->get(route('retaguarda.levantamentos.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('levantamentos', 1)->where('levantamentos.0.rua', 'Rua da Z1'));

    $this->actingAs($lider)->get(route('retaguarda.levantamentos.foto', $minha->linhas()->first()))->assertOk();
    $this->actingAs($lider)->get(route('retaguarda.levantamentos.foto', $alheia->linhas()->first()))->assertForbidden();
});

it('o fiscal não tem a tela na Retaguarda e ouve o porquê', function () {
    $fiscal = contaDosLevantamentos('fiscal', 'fiscal-lev');

    $this->actingAs($fiscal)->get(route('retaguarda.levantamentos.index'))
        ->assertRedirect(route('retaguarda.inicio'))
        ->assertSessionHas('flash.erro', 'Você não tem acesso a essa tela.');
});

it('quem levantou continua nomeado mesmo depois de a conta ser excluída', function () {
    $levantamento = levantamentoDaEquipe('Z1', 'Rua da Z1');
    $levantamento->user->update(['name' => 'Fiscal Desligado']);
    $levantamento->user->delete();
    $admin = User::factory()->create(['admin' => true, 'ativo' => true]);

    $this->actingAs($admin)->get(route('retaguarda.levantamentos.index'))
        ->assertInertia(fn ($p) => $p->where('levantamentos.0.fiscal', 'Fiscal Desligado'));
});
