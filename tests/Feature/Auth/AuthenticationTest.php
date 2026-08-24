<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create(['login' => 'F1234']);

        $response = $this->post(route('login.store'), [
            'login' => 'F1234',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('retaguarda.inicio', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'login' => $user->login,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_the_email_address_is_not_a_valid_username()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [strtolower($user->login), '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'login' => $user->login,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
