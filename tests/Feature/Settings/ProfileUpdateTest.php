<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Meu Perfil" — o que a própria pessoa mantém: nome e e-mail.
 *
 * Os testes de verificação de e-mail e de autoexclusão de conta que vinham do
 * starter kit saíram: os dois caminhos não existem neste sistema, e a prova
 * disso vive em `tests/Feature/Auth/RecursosDesligadosTest.php`.
 */
class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Fiscal de Teste',
                'email' => 'fiscal@salvador.ba.gov.br',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Fiscal de Teste', $user->name);
        $this->assertSame('fiscal@salvador.ba.gov.br', $user->email);
    }

    public function test_the_matricula_is_not_changed_by_the_user()
    {
        // A matrícula identifica o servidor e é dada pela administração: mandá-la
        // no formulário não muda nada (o `validated()` só traz nome e e-mail).
        $user = User::factory()->create(['login' => 'f7001']);

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'login' => 'f9999',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('f7001', $user->refresh()->login);
    }

    public function test_the_email_must_be_unique()
    {
        $outro = User::factory()->create();
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $outro->email,
            ])
            ->assertSessionHasErrors('email');
    }
}
