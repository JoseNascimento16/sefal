<?php

use App\Models\Setor;
use App\Models\User;
use Database\Seeders\PermissoesSetorSeeder;
use Database\Seeders\SetoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| LEI — quem tria alcança a pré-triagem mesmo sem nenhuma proposta na mesa
|--------------------------------------------------------------------------
|
| Reproduz um defeito real: o painel só era renderizado quando JÁ HAVIA
| sugestões, e o botão que roda a varredura mora dentro dele. Num banco limpo —
| que é exatamente o estado de quem abre o sistema pela primeira vez — a
| funcionalidade ficava inalcançável: sem propostas não havia painel, sem painel
| não havia botão, e sem botão nunca haveria propostas.
|
| O teste olha o PROP que a tela recebe (é ele que decide o que o React
| renderiza) e a etapa de quem entrou — as duas coisas que a condição usa.
|
*/

beforeEach(function () {
    $this->seed(SetoresSeeder::class);
    $this->seed(PermissoesSetorSeeder::class);
});

it('entrega o prop da pré-triagem mesmo quando não há nenhuma proposta', function () {
    $coordenador = User::factory()->create(['admin' => false, 'ativo' => true]);
    $coordenador->setores()->syncWithoutDetaching([Setor::where('slug', 'coordenador')->firstOrFail()->id]);

    $this->actingAs($coordenador)
        ->get(route('retaguarda.denuncias.e-salvador.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            // Vazio, e presente: é a ausência da CHAVE que quebraria a tela, e é
            // o valor vazio que ela precisa saber desenhar.
            ->where('sugestoesDeAgrupamento', [])
            // E quem entrou exerce a triagem — é o que libera o botão da varredura.
            ->where('etapas', ['triagem']),
        );
});

it('a Caixa de Entrada também recebe o prop, pelo mesmo motivo', function () {
    $coordenador = User::factory()->create(['admin' => true, 'ativo' => true]);

    $this->actingAs($coordenador)
        ->get(route('retaguarda.caixa-de-entrada.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('sugestoesDeAgrupamento', []));
});
