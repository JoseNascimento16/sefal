<?php

use App\Http\Controllers\Retaguarda\AmbulantesController;
use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Ambulantes — quem entra na consulta, e por onde a foto sai
|--------------------------------------------------------------------------
|
| ⚠️ Este arquivo tratava de "quem GRAVA o quê". Não há mais o que gravar: em
| 10/09/2026 a tela deixou de ser cadastro (a base é do SGCI, por integração), e
| os testes de inclusão, alteração e exclusão saíram junto com as rotas — a lei de
| que nenhuma mutação mora sob este caminho está em `AmbulantesConsultaTest`.
|
| Sobrou o que continua sendo verdade, e é o que decide se a consulta é utilizável
| e segura:
|
|   • o FISCAL ABRE a tela. Barrar a consulta seria mandá-lo para a rua às cegas —
|     chegar na calçada sem saber quem está cadastrado é trabalhar sem alvo;
|   • a tela recebe do servidor o que a pessoa pode fazer nela, e hoje a resposta é
|     "só consultar" para TODO MUNDO;
|   • a FOTO é retrato de cidadão fiscalizado, exibido ao lado do documento
|     dele. Sai por rota autenticada, nunca por URL de disco público — lá o
|     arquivo é servido fora das guardas, e nome difícil de adivinhar não é
|     controle de acesso;
|   • a persistência declarada no deploy cobre a pasta onde a foto de fato mora.
|
*/

beforeEach(function () {
    $this->seed();
    $this->admin = User::factory()->create(['admin' => true]);
    $this->atividade = AtividadeAmbulante::first();

    // Estas regras só existem no modo que barra de verdade — que é o padrão
    // entregue. Declarado aqui para o teste não depender do ambiente.
    config(['retaguarda.permissao_enforce' => 'block']);
});

/** O endereço da tela (a lista, ou um registro). */
function enderecoDoAmbulante(?int $id = null): string
{
    return '/retaguarda/ambulantes'.($id === null ? '' : "/{$id}");
}

/** Uma conta do setor informado, sem marca de administrador. */
function contaDoSetor(string $slug): User
{
    $u = User::factory()->create(['admin' => false]);
    $u->setores()->attach(Setor::where('slug', $slug)->firstOrFail());

    return $u->fresh();
}

test('o fiscal ABRE a tela — barrar a consulta seria mandá-lo para a rua às cegas', function () {
    $this->actingAs(contaDoSetor('fiscal'))
        ->get(enderecoDoAmbulante())
        ->assertOk();
});

test('a tela recebe do servidor "so consulta" para TODO MUNDO', function () {
    /*
     * A prop `acoes` continua descendo (é o caminho único do front para "o que eu
     * posso fazer aqui"), e o que ela diz mudou: nem o Chefe de Setor opera, inclui
     * ou exclui nesta tela — não porque foi restringido, mas porque não há mais o
     * que operar. As rotas de escrita deixaram de existir em 10/09/2026, quando a
     * base passou a vir do SGCI.
     *
     * ⚠️ Isto é a leitura da MATRIZ, não uma decisão da tela: se a semente
     * voltasse a conceder "Inclui" ao Chefe de Setor, o Modo Gerente passaria a
     * exibir uma marca que não decide nada, e é isso que este teste tranca.
     */
    foreach (['fiscal', 'chefe-de-setor'] as $setor) {
        $this->actingAs(contaDoSetor($setor))->get(enderecoDoAmbulante())
            ->assertInertia(fn (Assert $page) => $page
                ->where('acoes.visivel', true)
                ->where('acoes.apenas_leitura', true)
                ->where('acoes.habilitado', false)
                ->where('acoes.incluir', false)
                ->where('acoes.excluir', false));
    }
});

test('fora do modo que barra, a tela oferece tudo — senao esconderia o que o servidor aceita', function () {
    /*
     * Em `log` as guardas deixam passar e apenas registram. Se a tela escondesse
     * o botão assim mesmo, a ação nem chegaria a ser tentada — e o registro que
     * se quer conferir antes de virar a chave nunca existiria. Esconder botão é
     * conforto; ele acompanha quem barra, não anda na frente.
     */
    config(['retaguarda.permissao_enforce' => 'log']);

    $this->actingAs(contaDoSetor('fiscal'))->get(enderecoDoAmbulante())
        ->assertInertia(fn (Assert $page) => $page->where('acoes.incluir', true));
});

test('tela fora do Modo Gerente nao declara restricao nenhuma', function () {
    // A tela inicial não é controlável de propósito (é para onde a própria
    // negativa manda o usuário). Sem tela a que se referir não há ação a
    // responder — e "sem restrição declarada" não é restrição.
    $this->actingAs($this->admin)->get('/retaguarda/inicio')
        ->assertInertia(fn (Assert $page) => $page->where('acoes', null));
});

test('a foto exige autenticacao e permissao da tela', function () {
    Storage::fake('local');

    $p = Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => 'ambulantes/retrato.jpg',
    ]);
    Storage::disk('local')->put('ambulantes/retrato.jpg', 'conteudo');

    $endereco = enderecoDoAmbulante($p->id).'/foto';

    // Visitante: quem responde é a guarda de autenticação.
    $this->get($endereco)->assertRedirect(route('login'));

    // Autenticado, mas sem a tela concedida: volta ao início com o motivo — a
    // mesma resposta que abrir o cadastro receberia.
    $this->actingAs(User::factory()->create(['admin' => false]))->get($endereco)
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');

    // Quem abre o cadastro vê o retrato de quem está nele — inclusive o fiscal,
    // que só consulta.
    $this->actingAs(contaDoSetor('fiscal'))->get($endereco)->assertOk();
});

test('cadastro sem foto e arquivo sumido respondem 404, nao imagem quebrada', function () {
    Storage::fake('local');

    $semFoto = Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => null,
    ]);

    // A coluna aponta para um arquivo que não está mais no disco.
    $sumida = Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => 'ambulantes/nao-esta-la.jpg',
    ]);

    $this->actingAs($this->admin)
        ->get(enderecoDoAmbulante($semFoto->id).'/foto')->assertNotFound();

    $this->actingAs($this->admin)
        ->get(enderecoDoAmbulante($sumida->id).'/foto')->assertNotFound();
});

test('a grade aponta a foto para a rota, e nunca para o disco publico', function () {
    Storage::fake('local');

    $p = Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => 'ambulantes/retrato.jpg',
    ]);

    $this->actingAs($this->admin)->get(enderecoDoAmbulante())
        ->assertInertia(fn (Assert $page) => $page
            ->where('ambulantes.0.foto_url', "/retaguarda/ambulantes/{$p->id}/foto"));
});

test('a persistencia declarada no deploy cobre a pasta onde a foto realmente mora', function () {
    /*
     * A MESMA decisão tem três donos: o controller escolhe o disco, o compose de
     * homologação declara o volume e o doc do OKD declara o PVC. Enquanto o
     * disco era o público os três diziam a mesma coisa por coincidência; quando
     * a foto passou para o disco privado — porque é retrato de cidadão
     * fiscalizado —, a provisão continuou apontando só para `storage/app/public`.
     *
     * O estrago é mudo: nada quebra no deploy, nada aparece em log; as fotos
     * simplesmente somem no `up` da imagem seguinte, e só se descobre quando um
     * chefe de setor abre um cadastro antigo. Por isso a amarração é asserção, e não
     * comentário: quem trocar o disco de novo é avisado aqui.
     */
    $raiz = rtrim(str_replace('\\', '/', base_path()), '/');
    $absoluto = rtrim(str_replace(
        '\\',
        '/',
        Storage::disk(AmbulantesController::DISCO_DAS_FOTOS)->path(''),
    ), '/');

    expect($absoluto)->toStartWith($raiz.'/');

    // Ex.: `storage/app/private` — o caminho do disco relativo à raiz do projeto,
    // que é como o volume e o PVC o enxergam dentro do contêiner.
    $pastaDaFoto = ltrim(substr($absoluto, strlen($raiz)), '/');

    /** Um caminho declarado cobre a pasta quando é ela mesma ou um ancestral. */
    $cobre = static fn (array $declarados): bool => (bool) array_filter(
        $declarados,
        static fn (string $d): bool => $d === $pastaDaFoto || str_starts_with($pastaDaFoto, rtrim($d, '/').'/'),
    );

    // 1) Compose de homologação: volume nomeado montado em algum ponto de `storage/`.
    preg_match_all(
        '#^\s*-\s*\w+:/var/www/html/(storage/[^:\s]+)#m',
        (string) file_get_contents(base_path('docker-compose.homolog.yml')),
        $noCompose,
    );

    expect($noCompose[1])->not->toBeEmpty('o compose de homolog nao declara volume nenhum sob storage/');
    expect($cobre($noCompose[1]))->toBeTrue(
        'nenhum volume do docker-compose.homolog.yml cobre '.$pastaDaFoto,
    );

    // 2) Doc do OKD: as linhas que declaram o PVC. Só elas — uma menção solta a
    //    `storage/app/public` em outro contexto não é declaração de persistência.
    $linhasDePvc = array_filter(
        explode("\n", (string) file_get_contents(base_path('docs/deploy/okd.md'))),
        static fn (string $l): bool => str_contains($l, 'PVC'),
    );

    expect($linhasDePvc)->not->toBeEmpty('o doc do OKD nao declara PVC nenhum');

    foreach ($linhasDePvc as $linha) {
        preg_match_all('#storage/app[a-z/]*#', $linha, $noDoc);

        if ($noDoc[0] === []) {
            continue;
        }

        expect($cobre($noDoc[0]))->toBeTrue(
            'o PVC declarado em docs/deploy/okd.md nao cobre '.$pastaDaFoto.': '.trim($linha),
        );
    }
});

test('razao social com virgula e E comercial e exibida inteira — a base tem pessoa juridica', function () {
    /*
     * O documento do ambulante pode ser CPF **ou CNPJ**, então existe ambulante
     * pessoa jurídica, e razão social é escrita com vírgula e `&` o tempo todo.
     *
     * ⚠️ Este teste ERA sobre a gravação (a Rule `NomeDeCadastro` recusava essa
     * pontuação e obrigava a adulterar o nome para o cadastro passar). Sem
     * cadastro, o que resta é o outro lado da mesma preocupação: o nome que vem do
     * SGCI chega INTEIRO à tela, sem nada pelo caminho recortando pontuação. A
     * regra de escrita continua coberta em `tests/Unit/NomeDeCadastroTest.php`,
     * onde a Rule ainda serve as telas que gravam nome.
     */
    Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'nome' => 'Silva & Filhos Comercio de Alimentos, ME',
        'documento' => '11222333000181',
    ]);

    $this->actingAs($this->admin)->get(enderecoDoAmbulante())
        ->assertInertia(fn (Assert $page) => $page
            ->where('ambulantes.0.nome', 'Silva & Filhos Comercio de Alimentos, ME')
            ->where('ambulantes.0.documento_formatado', '11.222.333/0001-81')
            ->etc());
});
