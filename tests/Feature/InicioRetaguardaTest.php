<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InicioRetaguardaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('retaguarda.inicio'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_retaguarda_home()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('retaguarda.inicio'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Retaguarda/Inicio'));
    }

    public function test_the_old_dashboard_route_is_gone()
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertNotFound();
    }
}
