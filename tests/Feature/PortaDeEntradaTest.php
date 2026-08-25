<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A porta de entrada do sistema.
 *
 * A raiz não serve página nenhuma: isto é ferramenta de trabalho de um órgão
 * municipal, não site institucional. Quem digita o endereço nu cai na tela de
 * entrar; quem já entrou é levado à tela inicial da Retaguarda.
 *
 * O segundo caso é o que este arquivo mais protege: com o destino de visitante
 * apontando para a raiz (o padrão do framework), a raiz mandaria de volta para a
 * tela de entrar e fecharia um LOOP de redirecionamento — a pessoa logada
 * digitaria o endereço nu e o navegador morreria sem dizer o porquê.
 */
class PortaDeEntradaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_sends_the_visitor_to_the_login_screen()
    {
        $this->get(route('home'))->assertRedirect('/login');

        $this->followingRedirects()
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_whoever_is_already_authenticated_lands_on_the_retaguarda()
    {
        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Retaguarda/Inicio'));
    }

    public function test_the_login_screen_does_not_bounce_authenticated_people_back_to_the_root()
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect(config('fortify.home'));
    }
}
