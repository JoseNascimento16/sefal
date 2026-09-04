<?php

use App\Models\Setor;
use App\Models\User;
use Database\Seeders\SetoresSeeder;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Boas-vindas — o splash que aparece UMA vez, na entrada
|--------------------------------------------------------------------------
|
| O que se testa aqui é a MECÂNICA, não o desenho: quem marca a entrada, quando a
| marca é consumida, e as regras que fazem o splash ser embelezamento em vez de
| estorvo (sumir sozinho, nunca capturar clique).
|
| Os testes de FONTE existem porque o gate não executa JavaScript: sem eles,
| alguém tira o `pointer-events: none` ou o desmonte e nada reprova — o defeito só
| aparece com o usuário travado atrás de um splash que não sai.
|
*/

beforeEach(fn () => $this->seed(SetoresSeeder::class));

/** Um usuário que entra pela matrícula, do jeito que o sistema o cria. */
function usuarioQueEntra(string $nome = 'JOSÉ DA SILVA'): User
{
    $u = User::factory()->create([
        'login' => 'f9001',
        'name' => $nome,
        'ativo' => true,
        'password' => Hash::make('senha-correta'),
    ]);
    $u->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    return $u->fresh();
}

test('o login marca a entrada, e a marca nao sobrevive a navegacao seguinte', function () {
    usuarioQueEntra();

    $this->post('/login', ['login' => 'f9001', 'password' => 'senha-correta'])
        ->assertRedirect();

    // 1ª tela depois do login: a marca vem, junto do nome de quem entrou (é dele
    // que sai o primeiro nome da saudação).
    $this->get('/retaguarda/inicio')->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('auth.boas_vindas', true)
            ->where('auth.user.name', 'JOSÉ DA SILVA'),
        );

    // 2ª tela: a marca já não existe — o splash não reaparece a cada tela aberta.
    $this->get('/retaguarda/inicio')->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.boas_vindas', false));
});

test('quem nao acabou de entrar nao recebe a marca', function () {
    // Sessão já em curso (sem passar pelo login): nada a comemorar.
    $this->actingAs(usuarioQueEntra())->get('/retaguarda/inicio')->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.boas_vindas', false));
});

test('a marca ATRAVESSA redirecionamento interno e e gasta na primeira TELA', function () {
    /*
     * ⚠️ Este é o cenário que quebrou em homologação no sistema irmão. A marca era
     * flash de sessão, e flash morre no primeiro salto: quando algo redireciona
     * entre o login e a primeira tela — aqui, a guarda de permissão devolvendo
     * quem não pode abrir uma tela para a inicial —, o splash nunca aparecia.
     *
     * Por isso a marca é consumida na ENTREGA, dentro de uma closure de prop:
     * redirecionamento não serializa props, então a closure não roda nele.
     */
    config(['retaguarda.permissao_enforce' => 'block']);

    usuarioQueEntra();

    $this->post('/login', ['login' => 'f9001', 'password' => 'senha-correta'])
        ->assertRedirect();

    // O salto: o chefe de setor não tem esta tela concedida, e é devolvido à inicial. A
    // marca NÃO pode ser gasta aqui.
    $this->get('/retaguarda/logs')->assertRedirect('/retaguarda/inicio');

    // Na primeira tela de verdade, o splash aparece.
    $this->get('/retaguarda/inicio')->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.boas_vindas', true));

    // E não se repete na seguinte.
    $this->get('/retaguarda/inicio')->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.boas_vindas', false));
});

test('lei: o splash some sozinho e nunca bloqueia a tela', function () {
    /*
     * Teste-LEI de fonte, porque o gate não executa JavaScript. Splash que fica, ou
     * que captura clique, deixa de ser embelezamento e vira estorvo — e o defeito
     * não quebra teste nenhum: aparece com o usuário travado atrás dele.
     */
    $tsx = (string) file_get_contents(resource_path('js/components/retaguarda/overlay-boas-vindas.tsx'));
    $css = (string) file_get_contents(resource_path('css/retaguarda.css'));

    expect($tsx)->toContain('setMontado(false)')
        ->and($tsx)->toContain("setFase('saindo')")
        ->and($css)->toContain('pointer-events: none')
        ->and($css)->toContain('transition: opacity');

    // O brasão do canto superior direito é o arquivo que o produto já serve.
    expect($tsx)->toContain('/images/marca/brasao-salvador-branco.svg')
        ->and(public_path('images/marca/brasao-salvador-branco.svg'))->toBeFile();
});

test('lei: as faixas de saudacao comecam as 6, 12 e 18 — a madrugada e NOITE', function () {
    /*
     * Teste-LEI de fonte sobre a FONTE ÚNICA da saudação. O corte da madrugada é o
     * que importa: com `if (hora < 12)`, quem entra às 3h recebe "Bom dia" — e
     * fiscalização de ambulante acontece de madrugada, em Carnaval e festa de largo.
     */
    $lib = (string) file_get_contents(resource_path('js/lib/saudacao.ts'));

    expect($lib)->toContain("hora >= 6 && hora < 12) return 'Bom dia'")
        ->and($lib)->toContain("hora >= 12 && hora < 18) return 'Boa tarde'");
});

test('lei: a saudacao tem UM dono — nenhuma tela declara as faixas por conta propria', function () {
    /*
     * Teste-LEI de FONTE ÚNICA, e ele nasceu de um defeito real: a tela Início tinha
     * a própria cópia das faixas, cortando só em `hora < 12`. Com o splash aparecendo
     * POR CIMA dela na entrada, às 3h da manhã as duas se contradiziam na mesma tela,
     * no mesmo segundo — uma dizia "Boa noite" e a outra "Bom dia".
     *
     * A busca é por qualquer arquivo do front que escreva as saudações E leia a hora:
     * é essa combinação que caracteriza uma segunda implementação da regra. Consumir
     * o texto que a fonte única devolve não casa aqui.
     */
    $duplicando = [];

    $arquivos = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($arquivos as $arquivo) {
        if (! in_array($arquivo->getExtension(), ['ts', 'tsx'], true)) {
            continue;
        }

        $caminho = str_replace('\\', '/', $arquivo->getPathname());

        // A fonte única é justamente quem tem o direito de declarar as faixas.
        if (str_ends_with($caminho, 'resources/js/lib/saudacao.ts')) {
            continue;
        }

        $conteudo = (string) file_get_contents($arquivo->getPathname());

        if (str_contains($conteudo, 'Bom dia') && str_contains($conteudo, 'getHours()')) {
            $duplicando[] = str_replace(str_replace('\\', '/', base_path()).'/', '', $caminho);
        }
    }

    expect($duplicando)->toBe([]);
});

test('lei: os keyframes do splash ficam no nivel raiz do CSS', function () {
    /*
     * Teste-LEI. @keyframes aninhado em @layer/@media é descartado pelo
     * lightningcss na build de PRODUÇÃO, em silêncio: em dev e no gate a animação
     * funciona, e no ambiente do cliente o splash aparece parado.
     */
    $css = (string) file_get_contents(resource_path('css/retaguarda.css'));

    foreach (['bv-sobe', 'bv-regua-cresce', 'bv-mapa-entra', 'bv-pin-pulso'] as $nome) {
        expect($css)->toMatch('/^@keyframes '.preg_quote($nome, '/').'\b/m');
    }
});
