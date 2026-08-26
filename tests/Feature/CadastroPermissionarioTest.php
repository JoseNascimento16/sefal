<?php

use App\Models\AtividadeAmbulante;
use App\Models\Permissionario;
use App\Models\Setor;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Cadastro de Permissionário — a identidade de quem é fiscalizado
|--------------------------------------------------------------------------
|
| É a tela-núcleo da fiscalização: sem ela, não há a quem ligar uma vistoria.
| O que se testa aqui é o que a torna utilizável no mundo real da rua, onde o
| alvo muitas vezes NÃO tem documento à mão:
|
|   • cadastro sem documento é caminho normal, não exceção — a identidade
|     prática é foto + apelido, e o código de cadastro nasce sozinho;
|   • documento, quando informado, é validado, NORMALIZADO e único — com
|     máscara de um lado e sem máscara do outro, a mesma pessoa viraria dois;
|   • foto passa pela allowlist de anexos (executável renomeado não entra);
|   • a atividade apontada existe e está em uso — e a que já foi apontada por
|     um cadastro não pode mais ser excluída da parametrização;
|   • quem não tem a tela concedida é mandado de volta com o motivo.
|
| A régua é a spec de design (§4.1) — não há HU escrita neste projeto.
|
*/

beforeEach(function () {
    $this->seed();
    $this->admin = User::factory()->create(['admin' => true]);
    $this->atividade = AtividadeAmbulante::first();
});

/** O endereço da tela (a lista, ou um registro). */
function caminhoDoPermissionario(?int $id = null): string
{
    return '/retaguarda/permissionarios'.($id === null ? '' : "/{$id}");
}

/**
 * Os campos mínimos de um cadastro válido — o que a tela exige e nada mais.
 *
 * @return array<string, mixed>
 */
function cadastroMinimo(int $atividadeId, array $extras = []): array
{
    return [
        'nome' => 'João da Silva',
        'atividade_id' => $atividadeId,
        'situacao' => 'Regular',
        ...$extras,
    ];
}

test('cria permissionario SEM documento (identidade de campo: nome + apelido)', function () {
    /*
     * O caso mais comum da rua, e por isso o primeiro teste: o alvo não tem CPF
     * à mão, nem CNPJ, e às vezes nem quer dizer o nome. Quem o identifica é a
     * foto e o apelido — se o documento fosse obrigatório, o cadastro em campo
     * simplesmente não aconteceria.
     */
    $this->actingAs($this->admin)->post(caminhoDoPermissionario(), [
        'nome' => 'João da Silva',
        'apelido' => 'João do Acarajé',
        'atividade_id' => $this->atividade->id,
        'situacao' => 'Regular',
    ])->assertRedirect()->assertSessionHas('flash.sucesso');

    $p = Permissionario::where('apelido', 'João do Acarajé')->firstOrFail();

    expect($p->documento)->toBeNull()
        ->and($p->codigo)->toStartWith('PER')
        ->and($p->foto)->toBeNull();
});

test('documento quando informado e normalizado e unico', function () {
    // Máscara de um lado e sem máscara do outro fariam a MESMA pessoa virar dois
    // cadastros — e a busca por documento acharia só um deles.
    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($this->atividade->id, ['nome' => 'Maria', 'documento' => '123.456.789-09']),
    )->assertSessionHasNoErrors();

    expect(Permissionario::first()->documento)->toBe('12345678909');

    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($this->atividade->id, ['nome' => 'Outra', 'documento' => '12345678909']),
    )->assertSessionHasErrors('documento');

    expect(Permissionario::count())->toBe(1);
});

test('documento invalido e recusado dizendo o que se espera', function () {
    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($this->atividade->id, ['documento' => '111.111.111-11']),
    )->assertSessionHasErrors('documento');
});

test('alterar um cadastro nao esbarra no proprio documento', function () {
    // Sem ignorar a si mesmo, salvar só para corrigir o apelido seria recusado.
    $p = Permissionario::factory()->create([
        'documento' => '12345678909',
        'atividade_id' => $this->atividade->id,
    ]);

    $this->actingAs($this->admin)->put(
        caminhoDoPermissionario($p->id),
        cadastroMinimo($this->atividade->id, [
            'nome' => $p->nome,
            'documento' => '123.456.789-09',
            'apelido' => 'Apelido novo',
        ]),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->apelido)->toBe('Apelido novo')
        ->and($p->fresh()->documento)->toBe('12345678909');
});

test('foto passa por ArquivoSeguro (exe renomeado e recusado)', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($this->atividade->id, [
            'foto' => UploadedFile::fake()->create('foto.jpg.exe', 100),
        ]),
    )->assertSessionHasErrors('foto');

    expect(Permissionario::count())->toBe(0);
});

test('a foto enviada e guardada, trocada e removida — sempre sem deixar arquivo orfao', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($this->atividade->id, [
            'foto' => UploadedFile::fake()->image('rosto.jpg', 300, 300),
        ]),
    )->assertSessionHasNoErrors();

    $p = Permissionario::firstOrFail();
    $primeira = $p->foto;

    expect($primeira)->toStartWith('permissionarios/');
    Storage::disk('public')->assertExists($primeira);

    // Trocar a foto não pode deixar a antiga ocupando disco para sempre.
    $this->actingAs($this->admin)->put(
        caminhoDoPermissionario($p->id),
        cadastroMinimo($this->atividade->id, [
            'nome' => $p->nome,
            'foto' => UploadedFile::fake()->image('rosto2.jpg', 300, 300),
        ]),
    )->assertSessionHasNoErrors();

    $segunda = $p->fresh()->foto;

    expect($segunda)->not->toBe($primeira);
    Storage::disk('public')->assertMissing($primeira);
    Storage::disk('public')->assertExists($segunda);

    // Remover a foto apaga o arquivo e devolve o cadastro às iniciais.
    $this->actingAs($this->admin)->put(
        caminhoDoPermissionario($p->id),
        cadastroMinimo($this->atividade->id, ['nome' => $p->nome, 'remover_foto' => true]),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->foto)->toBeNull();
    Storage::disk('public')->assertMissing($segunda);
});

test('salvar sem mandar foto nenhuma NAO apaga a foto ja cadastrada', function () {
    /*
     * O formulário só manda o arquivo quando alguém escolhe um novo. Tratar
     * "campo ausente" como "remover" apagaria a foto de quem entrou só para
     * corrigir o telefone — e a identidade de campo é justamente a foto.
     */
    Storage::fake('public');

    $p = Permissionario::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => 'permissionarios/ja-existente.jpg',
    ]);

    Storage::disk('public')->put('permissionarios/ja-existente.jpg', 'conteudo');

    $this->actingAs($this->admin)->put(
        caminhoDoPermissionario($p->id),
        cadastroMinimo($this->atividade->id, ['nome' => $p->nome, 'telefone' => '(71) 99999-0000']),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->foto)->toBe('permissionarios/ja-existente.jpg');
    Storage::disk('public')->assertExists('permissionarios/ja-existente.jpg');
});

test('o codigo nasce do gerador de protocolo e nao recomeca quando o contador do dia se perde', function () {
    /*
     * O código é a identidade do cadastro no papel. O contador do dia pode não
     * existir (banco restaurado, carga anterior) — e, sem a rede do model, o
     * próximo cadastro recomeçaria em 001 e colidiria com o que já está gravado.
     */
    $hoje = now()->format('Ymd');

    Permissionario::factory()->create([
        'codigo' => 'PER'.$hoje.'001',
        'atividade_id' => $this->atividade->id,
    ]);

    DB::table('protocolo_contadores')->delete();

    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($this->atividade->id, ['nome' => 'Segundo cadastro']),
    )->assertSessionHasNoErrors();

    expect(Permissionario::where('nome', 'Segundo cadastro')->value('codigo'))->toBe('PER'.$hoje.'002');
});

test('a atividade tem de existir e estar em uso no cadastro novo', function () {
    $foraDeUso = AtividadeAmbulante::create(['nome' => 'Ramo aposentado', 'ativo' => false]);

    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($foraDeUso->id, ['nome' => 'Com ramo aposentado']),
    )->assertSessionHasErrors('atividade_id');

    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo(999999, ['nome' => 'Com ramo inexistente']),
    )->assertSessionHasErrors('atividade_id');

    expect(Permissionario::count())->toBe(0);
});

test('atividade inativada continua valida no cadastro que ja a apontava', function () {
    /*
     * Inativar tira o valor das ESCOLHAS NOVAS; não invalida o passado. Sem
     * isto, quem entrasse para corrigir um telefone seria obrigado a trocar o
     * ramo do cadastro — mudando um dado que ninguém pediu para mudar.
     */
    $p = Permissionario::factory()->create(['atividade_id' => $this->atividade->id]);

    $this->atividade->update(['ativo' => false]);

    $this->actingAs($this->admin)->put(
        caminhoDoPermissionario($p->id),
        cadastroMinimo($this->atividade->id, ['nome' => $p->nome, 'telefone' => '(71) 98888-1111']),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->telefone)->toBe('(71) 98888-1111');
});

test('a situacao so aceita as tres do catalogo', function () {
    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($this->atividade->id, ['situacao' => 'Aprovado']),
    )->assertSessionHasErrors('situacao');

    foreach (Permissionario::SITUACOES as $i => $situacao) {
        $this->actingAs($this->admin)->post(
            caminhoDoPermissionario(),
            cadastroMinimo($this->atividade->id, ['nome' => "Pessoa {$i}", 'situacao' => $situacao]),
        )->assertSessionHasNoErrors();
    }

    expect(Permissionario::count())->toBe(count(Permissionario::SITUACOES));
});

test('o nome e obrigatorio', function () {
    $this->actingAs($this->admin)->post(
        caminhoDoPermissionario(),
        cadastroMinimo($this->atividade->id, ['nome' => '   ']),
    )->assertSessionHasErrors('nome');
});

test('a grade entrega os cadastros, as atividades e o catalogo de situacoes', function () {
    Permissionario::factory()->create([
        'apelido' => 'Zé da Água',
        'atividade_id' => $this->atividade->id,
    ]);

    $this->actingAs($this->admin)->get(caminhoDoPermissionario())
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Retaguarda/Fiscalizacao/CadastroDePermissionario')
            ->has('permissionarios', 1)
            ->where('permissionarios.0.apelido', 'Zé da Água')
            // A tela mostra o documento formatado, e busca pelo normalizado: os
            // dois vêm do servidor para não haver duas verdades sobre o mesmo dado.
            ->has('permissionarios.0.documento_formatado')
            ->has('atividades')
            ->where('situacoes', Permissionario::SITUACOES));
});

test('a grade entrega o documento formatado e a validade em forma de data', function () {
    Permissionario::factory()->create([
        'documento' => '12345678909',
        'validade_permissao' => '2027-03-09',
        'atividade_id' => $this->atividade->id,
    ]);

    $this->actingAs($this->admin)->get(caminhoDoPermissionario())
        ->assertInertia(fn (Assert $p) => $p
            ->where('permissionarios.0.documento', '12345678909')
            ->where('permissionarios.0.documento_formatado', '123.456.789-09')
            // ISO só por dentro: quem escreve dd/mm/aaaa é a tela.
            ->where('permissionarios.0.validade_permissao', '2027-03-09')
            ->etc());
});

test('excluir o cadastro leva junto o arquivo da foto', function () {
    Storage::fake('public');

    $p = Permissionario::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => 'permissionarios/para-apagar.jpg',
    ]);

    Storage::disk('public')->put('permissionarios/para-apagar.jpg', 'conteudo');

    $this->actingAs($this->admin)->delete(caminhoDoPermissionario($p->id))
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    expect(Permissionario::find($p->id))->toBeNull();
    Storage::disk('public')->assertMissing('permissionarios/para-apagar.jpg');
});

test('a atividade apontada por um cadastro nao pode mais ser excluida, e a recusa diz por que', function () {
    /*
     * Teste-LEI da promessa deixada na parametrização: excluir a atividade
     * deixaria os cadastros apontando para o nada. A recusa acontece na tela de
     * onde a pessoa clicou, com o número de vínculos — e o caminho certo,
     * inativar, continua aberto.
     */
    Permissionario::factory()->count(2)->create(['atividade_id' => $this->atividade->id]);

    $this->actingAs($this->admin)
        ->from('/retaguarda/parametrizacao/atividades-do-ambulante')
        ->delete("/retaguarda/parametrizacao/atividades-do-ambulante/{$this->atividade->id}")
        ->assertRedirect('/retaguarda/parametrizacao/atividades-do-ambulante')
        ->assertSessionHas('flash.erro');

    expect(AtividadeAmbulante::find($this->atividade->id))->not->toBeNull()
        ->and((string) session('flash.erro'))->toContain('2');

    // Inativar continua sendo o caminho — é o que a recusa manda fazer.
    $this->actingAs($this->admin)->put(
        "/retaguarda/parametrizacao/atividades-do-ambulante/{$this->atividade->id}",
        ['nome' => $this->atividade->nome, 'ativo' => false],
    )->assertSessionHasNoErrors();

    expect($this->atividade->fresh()->ativo)->toBeFalse();
});

test('atividade sem nenhum cadastro continua excluivel', function () {
    // A guarda anterior não pode virar "nunca mais se exclui atividade": o valor
    // cadastrado errado, que ninguém usou, tem de sair.
    $nova = AtividadeAmbulante::create(['nome' => 'Ramo digitado errado', 'ativo' => true]);

    $this->actingAs($this->admin)
        ->delete("/retaguarda/parametrizacao/atividades-do-ambulante/{$nova->id}")
        ->assertSessionHas('flash.sucesso');

    expect(AtividadeAmbulante::find($nova->id))->toBeNull();
});

test('a tela entra no controle de acesso, e o caminho e o slug', function () {
    expect(CatalogoFuncionalidades::contem('permissionarios'))->toBeTrue();

    // É do primeiro trecho do caminho que as guardas deduzem a tela.
    expect(route('retaguarda.permissionarios.index', absolute: false))
        ->toStartWith('/retaguarda/permissionarios');
});

test('quem nao tem a tela concedida e mandado de volta dizendo o porque', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    // Conta sem setor nenhum: a matriz não concede nada a ela, e é o caso que
    // mais aparece na vida real — o usuário recém-criado que ninguém alocou.
    $semAcesso = User::factory()->create(['admin' => false]);

    $this->actingAs($semAcesso->fresh())->get(caminhoDoPermissionario())
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');
});

test('concedida na matriz, a tela abre para quem nao e administrador', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $gestor = User::factory()->create(['admin' => false]);
    $gestor->setores()->attach(Setor::where('slug', 'gestor')->firstOrFail());

    $this->actingAs($gestor->fresh())->get(caminhoDoPermissionario())->assertOk();
});

test('exige autenticacao', function () {
    $this->get(caminhoDoPermissionario())->assertRedirect(route('login'));
    $this->post(caminhoDoPermissionario(), [])->assertRedirect(route('login'));
});

test('o recorte visivel da grade vira documento pelo ponto unico de exportacao', function () {
    // A lei da exportação vale aqui como em qualquer listagem: o documento sai do
    // MESMO endpoint, com as colunas que a tela declara.
    $resposta = $this->actingAs($this->admin)->post(route('retaguarda.exportar-listagem'), [
        'formato' => 'pdf',
        'titulo' => 'Permissionários',
        'subtitulo' => 'Fiscalização › Permissionários',
        'contexto' => 'Busca: "acarajé"',
        'colunas' => [
            ['chave' => 'codigo', 'titulo' => 'Código'],
            ['chave' => 'nome', 'titulo' => 'Nome'],
            ['chave' => 'situacao', 'titulo' => 'Situação'],
        ],
        'linhas' => [
            ['codigo' => 'PER20260825001', 'nome' => 'João da Silva', 'situacao' => 'Regular'],
        ],
    ]);

    $resposta->assertOk();

    expect((string) $resposta->getContent())->toStartWith('%PDF');
});
