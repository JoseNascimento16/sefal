<?php

use App\Http\Controllers\Retaguarda\CadastroAmbulanteController;
use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Ambulantes — quem grava o quê, e por onde a foto sai
|--------------------------------------------------------------------------
|
| Duas coisas que o cadastro em si não responde, e que decidem se a quarentena
| existe de verdade:
|
|   • o FISCAL apenas consulta. Ele cadastra em RUA, pelo aplicativo, e o que
|     nasce em rua espera a conferência do chefe de setor. Se ele pudesse alterar pela
|     Retaguarda, tiraria da fila o registro que ele mesmo acabou de criar — a
|     situação é campo do mesmo formulário, então "pode alterar" e "pode
|     validar" são a mesma coisa aqui;
|   • a FOTO é retrato de cidadão fiscalizado, exibido ao lado do documento
|     dele. Sai por rota autenticada, nunca por URL de disco público — lá o
|     arquivo é servido fora das guardas, e nome difícil de adivinhar não é
|     controle de acesso.
|
| São sempre DUAS garantias por regra, e uma não substitui a outra: o servidor
| barra (é a fronteira) e a tela não oferece (é o que evita descobrir a recusa
| depois de preencher o formulário inteiro).
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

/**
 * Os campos de um cadastro válido.
 *
 * @param  array<string, mixed>  $extras
 * @return array<string, mixed>
 */
function camposDoCadastro(int $atividadeId, array $extras = []): array
{
    return [
        'nome' => 'João da Silva',
        'atividade_id' => $atividadeId,
        'situacao' => Ambulante::SITUACAO_REGULAR,
        // Sem permissão da SEMOP: o caso comum, e a resposta é obrigatória.
        'permissionario' => false,
        ...$extras,
    ];
}

/** Um cadastro esperando a conferência do chefe de setor. */
function cadastroEmQuarentena(int $atividadeId): Ambulante
{
    return Ambulante::factory()->create([
        'atividade_id' => $atividadeId,
        'situacao' => Ambulante::SITUACAO_CAMPO,
    ]);
}

test('o fiscal NAO tira da quarentena o cadastro que ele mesmo fez em rua', function () {
    /*
     * O cenário que dá sentido a este teste: o fiscal cadastra alguém de pé na
     * calçada, com o que a pessoa disse e sem documento conferido. O registro
     * nasce em quarentena JUSTAMENTE para o chefe de setor conferir depois.
     *
     * Com a alteração liberada, ele trocava a situação para "Regular" e a
     * conferência simplesmente não acontecia — sem nada no sistema registrando
     * que ela foi pulada.
     */
    $fiscal = contaDoSetor('fiscal');
    $p = cadastroEmQuarentena($this->atividade->id);

    $this->actingAs($fiscal)->put(
        enderecoDoAmbulante($p->id),
        camposDoCadastro($this->atividade->id, [
            'nome' => $p->nome,
            'situacao' => Ambulante::SITUACAO_REGULAR,
        ]),
    )->assertSessionHas('flash.erro');

    expect($p->fresh()->situacao)->toBe(Ambulante::SITUACAO_CAMPO);
});

test('o fiscal nao inclui nem exclui cadastro pela Retaguarda', function () {
    $fiscal = contaDoSetor('fiscal');
    $p = cadastroEmQuarentena($this->atividade->id);

    $this->actingAs($fiscal)
        ->post(enderecoDoAmbulante(), camposDoCadastro($this->atividade->id))
        ->assertSessionHas('flash.erro');

    $this->actingAs($fiscal)
        ->delete(enderecoDoAmbulante($p->id))
        ->assertSessionHas('flash.erro');

    // Um só: o que já existia. Nada foi criado nem apagado.
    expect(Ambulante::count())->toBe(1);
});

test('o fiscal ABRE a tela — barrar a consulta seria mandá-lo para a rua às cegas', function () {
    $this->actingAs(contaDoSetor('fiscal'))
        ->get(enderecoDoAmbulante())
        ->assertOk();
});

test('o chefe de setor continua fazendo tudo — a restricao e do fiscal, nao da tela', function () {
    $chefe = contaDoSetor('chefe-de-setor');
    $p = cadastroEmQuarentena($this->atividade->id);

    $this->actingAs($chefe)->put(
        enderecoDoAmbulante($p->id),
        camposDoCadastro($this->atividade->id, [
            'nome' => $p->nome,
            'situacao' => Ambulante::SITUACAO_REGULAR,
        ]),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->situacao)->toBe(Ambulante::SITUACAO_REGULAR);

    $this->actingAs($chefe)
        ->post(enderecoDoAmbulante(), camposDoCadastro($this->atividade->id))
        ->assertSessionHasNoErrors();

    expect(Ambulante::count())->toBe(2);
});

test('a tela recebe do servidor o que a pessoa pode fazer nela', function () {
    /*
     * É o que impede a tela de oferecer o que o servidor recusa. Sem isto o
     * fiscal via "Incluir", preenchia nome, apelido, atividade, situação, anexava
     * a foto — e só então era barrado.
     *
     * A resposta vem do MESMO serviço que as guardas consultam: uma segunda conta
     * feita no navegador acabaria discordando da que barra.
     */
    $this->actingAs(contaDoSetor('fiscal'))->get(enderecoDoAmbulante())
        ->assertInertia(fn (Assert $page) => $page
            ->where('acoes.visivel', true)
            ->where('acoes.apenas_leitura', true)
            ->where('acoes.habilitado', false)
            ->where('acoes.incluir', false)
            ->where('acoes.excluir', false));

    $this->actingAs(contaDoSetor('chefe-de-setor'))->get(enderecoDoAmbulante())
        ->assertInertia(fn (Assert $page) => $page
            ->where('acoes.habilitado', true)
            ->where('acoes.incluir', true)
            ->where('acoes.excluir', true));
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

test('gravacao que falha na INCLUSAO nao deixa a foto orfa no disco', function () {
    /*
     * O arquivo é guardado antes da linha (é ele que preenche a coluna), então a
     * falha da gravação tem de levá-lo junto. Sem isso a imagem fica no disco sem
     * nada apontando para ela, e nada a recolhe depois.
     *
     * A falha é provocada onde ela realmente acontece: a coluna do código tem
     * índice único, e aqui ela já está ocupada pelo protocolo que este cadastro
     * receberia.
     */
    Storage::fake('local');

    $ocupado = Ambulante::factory()->create(['atividade_id' => $this->atividade->id]);

    /*
     * O contador volta a apontar para o código que este cadastro JÁ tem, então a
     * próxima inclusão pede um número que o índice único vai recusar.
     *
     * O recuo é feito depois de criar, e não antes: a factory tira o código do
     * mesmo gerador de protocolo (é a fonte única da numeração), então criar o
     * registro consome o contador — semeá-lo antes seria gastar o número que se
     * queria reservar para a colisão.
     */
    DB::table('protocolo_contadores')
        ->where('prefixo', 'AMB')
        ->update(['proximo' => (int) substr($ocupado->codigo, -3)]);

    expect(fn () => $this->withoutExceptionHandling()->actingAs($this->admin)->post(
        enderecoDoAmbulante(),
        camposDoCadastro($this->atividade->id, [
            'foto' => UploadedFile::fake()->image('retrato.jpg'),
        ]),
    ))->toThrow(QueryException::class);

    // Nenhum arquivo sobrou: a pasta está como estava antes da tentativa.
    expect(Storage::disk('local')->allFiles('ambulantes'))->toBe([]);
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
        Storage::disk(CadastroAmbulanteController::DISCO_DAS_FOTOS)->path(''),
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

test('razao social com virgula e E comercial e aceita — o campo aceita CNPJ', function () {
    /*
     * O documento aceita CPF **ou CNPJ**, então existe ambulante pessoa
     * jurídica. Recusar a pontuação de razão social obrigava quem cadastrava a
     * alterá-la para o cadastro passar — e aí o nome deixava de bater com o
     * documento que ele representa.
     */
    $this->actingAs($this->admin)->post(
        enderecoDoAmbulante(),
        camposDoCadastro($this->atividade->id, [
            'nome' => 'Silva & Filhos Comercio de Alimentos, ME',
            'documento' => '11.222.333/0001-81',
        ]),
    )->assertSessionHasNoErrors();

    expect(Ambulante::firstOrFail()->nome)
        ->toBe('Silva & Filhos Comercio de Alimentos, ME');
});
