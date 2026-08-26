<?php

namespace Tests\Feature;

use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InicioRetaguardaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Os atalhos da tela inicial, indexados pela chave.
     *
     * @return array<string, array<string, mixed>>
     */
    private function atalhosDe(User $usuario): array
    {
        $props = $this->actingAs($usuario)
            ->get(route('retaguarda.inicio'))
            ->assertOk()
            ->viewData('page')['props'];

        return collect($props['atalhos'])->keyBy('chave')->all();
    }

    public function test_o_atalho_de_uma_tela_ja_entregue_leva_a_ela_em_vez_de_dizer_em_construcao()
    {
        /*
         * O defeito que este teste tranca: o cartão de Permissionários continuou
         * anunciando "Em construção" depois de a tela ficar pronta e entrar no
         * menu. A primeira coisa que o usuário enxerga desmentia a entrega — e
         * quem lê "não existe" não procura no menu ao lado.
         *
         * O atalho "em construção" é o cartão sem endereço; então a prova de que
         * o atalho leva a algum lugar é o endereço estar lá.
         */
        $atalhos = $this->atalhosDe(User::factory()->create(['admin' => true]));

        $this->assertSame(
            route('retaguarda.permissionarios.index', absolute: false),
            $atalhos['permissionarios']['href'],
        );
    }

    public function test_atalho_de_tela_que_ainda_nao_existe_nasce_sem_endereco()
    {
        // O outro lado: o cartão de uma tela das próximas entregas fica à vista e
        // sem endereço (é assim que a tela o desenha esmaecido, dizendo "Em
        // construção"). Anunciar como pronto o que não existe é o defeito
        // simétrico.
        $atalhos = $this->atalhosDe(User::factory()->create(['admin' => true]));

        $this->assertNull($atalhos['fiscalizacoes']['href']);
        $this->assertNull($atalhos['areas']['href']);
    }

    public function test_atalho_de_tela_que_a_pessoa_nao_pode_abrir_nao_aparece()
    {
        /*
         * Atalho para tela que a guarda barra é convite para uma recusa que
         * ninguém entende. A regra é a MESMA do menu (o `PermissaoService`), e
         * não uma segunda conta feita aqui.
         */
        config(['retaguarda.permissao_enforce' => 'block']);
        $this->seed();

        $semAcesso = User::factory()->create(['admin' => false]);
        $semAcesso->setores()->attach(Setor::where('slug', 'gestor')->firstOrFail());
        PermissaoSetor::where('setor', 'gestor')->where('slug', 'permissionarios')->delete();

        $this->assertArrayNotHasKey('permissionarios', $this->atalhosDe($semAcesso->fresh()));

        PermissaoSetor::updateOrCreate(
            ['setor' => 'gestor', 'slug' => 'permissionarios'],
            ['visivel' => true, 'habilitado' => true],
        );

        $this->assertArrayHasKey('permissionarios', $this->atalhosDe($semAcesso->fresh()));
    }

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
