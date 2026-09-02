<?php

use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use App\Models\Setor;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Cadastro de Ambulante — a identidade de quem é fiscalizado
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
function caminhoDoAmbulante(?int $id = null): string
{
    return '/retaguarda/ambulantes'.($id === null ? '' : "/{$id}");
}

/**
 * Os campos mínimos de um cadastro válido — o que a tela exige e nada mais.
 *
 * `permissionario` entra aqui como `false` porque é o caso comum da rua (quem
 * não tem permissão da SEMOP), e porque a resposta é OBRIGATÓRIA: omiti-la seria
 * deixar o servidor adivinhar se a pessoa tem permissão.
 *
 * @return array<string, mixed>
 */
function cadastroMinimo(int $atividadeId, array $extras = []): array
{
    return [
        'nome' => 'João da Silva',
        'atividade_id' => $atividadeId,
        'situacao' => 'Regular',
        'permissionario' => false,
        ...$extras,
    ];
}

test('cria ambulante SEM documento (identidade de campo: nome + apelido)', function () {
    /*
     * O caso mais comum da rua, e por isso o primeiro teste: o alvo não tem CPF
     * à mão, nem CNPJ, e às vezes nem quer dizer o nome. Quem o identifica é a
     * foto e o apelido — se o documento fosse obrigatório, o cadastro em campo
     * simplesmente não aconteceria.
     */
    $this->actingAs($this->admin)->post(caminhoDoAmbulante(), [
        'nome' => 'João da Silva',
        'apelido' => 'João do Acarajé',
        'atividade_id' => $this->atividade->id,
        'situacao' => 'Regular',
        'permissionario' => false,
    ])->assertRedirect()->assertSessionHas('flash.sucesso');

    $p = Ambulante::where('apelido', 'João do Acarajé')->firstOrFail();

    expect($p->documento)->toBeNull()
        ->and($p->codigo)->toStartWith('AMB')
        ->and($p->foto)->toBeNull();
});

test('documento quando informado e normalizado e unico', function () {
    // Máscara de um lado e sem máscara do outro fariam a MESMA pessoa virar dois
    // cadastros — e a busca por documento acharia só um deles.
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['nome' => 'Maria', 'documento' => '123.456.789-09']),
    )->assertSessionHasNoErrors();

    expect(Ambulante::first()->documento)->toBe('12345678909');

    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['nome' => 'Outra', 'documento' => '12345678909']),
    )->assertSessionHasErrors('documento');

    expect(Ambulante::count())->toBe(1);
});

test('documento invalido e recusado dizendo o que se espera', function () {
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['documento' => '111.111.111-11']),
    )->assertSessionHasErrors('documento');
});

test('alterar um cadastro nao esbarra no proprio documento', function () {
    // Sem ignorar a si mesmo, salvar só para corrigir o apelido seria recusado.
    $p = Ambulante::factory()->create([
        'documento' => '12345678909',
        'atividade_id' => $this->atividade->id,
    ]);

    $this->actingAs($this->admin)->put(
        caminhoDoAmbulante($p->id),
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
    Storage::fake('local');

    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, [
            'foto' => UploadedFile::fake()->create('foto.jpg.exe', 100),
        ]),
    )->assertSessionHasErrors('foto');

    expect(Ambulante::count())->toBe(0);
});

test('a foto enviada e guardada, trocada e removida — sempre sem deixar arquivo orfao', function () {
    Storage::fake('local');

    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, [
            'foto' => UploadedFile::fake()->image('rosto.jpg', 300, 300),
        ]),
    )->assertSessionHasNoErrors();

    $p = Ambulante::firstOrFail();
    $primeira = $p->foto;

    expect($primeira)->toStartWith('ambulantes/');
    Storage::disk('local')->assertExists($primeira);

    // Trocar a foto não pode deixar a antiga ocupando disco para sempre.
    $this->actingAs($this->admin)->put(
        caminhoDoAmbulante($p->id),
        cadastroMinimo($this->atividade->id, [
            'nome' => $p->nome,
            'foto' => UploadedFile::fake()->image('rosto2.jpg', 300, 300),
        ]),
    )->assertSessionHasNoErrors();

    $segunda = $p->fresh()->foto;

    expect($segunda)->not->toBe($primeira);
    Storage::disk('local')->assertMissing($primeira);
    Storage::disk('local')->assertExists($segunda);

    // Remover a foto apaga o arquivo e devolve o cadastro às iniciais.
    $this->actingAs($this->admin)->put(
        caminhoDoAmbulante($p->id),
        cadastroMinimo($this->atividade->id, ['nome' => $p->nome, 'remover_foto' => true]),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->foto)->toBeNull();
    Storage::disk('local')->assertMissing($segunda);
});

test('salvar sem mandar foto nenhuma NAO apaga a foto ja cadastrada', function () {
    /*
     * O formulário só manda o arquivo quando alguém escolhe um novo. Tratar
     * "campo ausente" como "remover" apagaria a foto de quem entrou só para
     * corrigir o telefone — e a identidade de campo é justamente a foto.
     */
    Storage::fake('local');

    $p = Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => 'ambulantes/ja-existente.jpg',
    ]);

    Storage::disk('local')->put('ambulantes/ja-existente.jpg', 'conteudo');

    $this->actingAs($this->admin)->put(
        caminhoDoAmbulante($p->id),
        cadastroMinimo($this->atividade->id, ['nome' => $p->nome, 'telefone' => '(71) 99999-0000']),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->foto)->toBe('ambulantes/ja-existente.jpg');
    Storage::disk('local')->assertExists('ambulantes/ja-existente.jpg');
});

test('o codigo nasce do gerador de protocolo e nao recomeca quando o contador do dia se perde', function () {
    /*
     * O código é a identidade do cadastro no papel. O contador do dia pode não
     * existir (banco restaurado, carga anterior) — e, sem a rede do model, o
     * próximo cadastro recomeçaria em 001 e colidiria com o que já está gravado.
     */
    $hoje = now()->format('Ymd');

    Ambulante::factory()->create([
        'codigo' => 'AMB'.$hoje.'001',
        'atividade_id' => $this->atividade->id,
    ]);

    DB::table('protocolo_contadores')->delete();

    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['nome' => 'Segundo cadastro']),
    )->assertSessionHasNoErrors();

    expect(Ambulante::where('nome', 'Segundo cadastro')->value('codigo'))->toBe('AMB'.$hoje.'002');
});

test('a atividade tem de existir e estar em uso no cadastro novo', function () {
    $foraDeUso = AtividadeAmbulante::create(['nome' => 'Ramo aposentado', 'ativo' => false]);

    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($foraDeUso->id, ['nome' => 'Com ramo aposentado']),
    )->assertSessionHasErrors('atividade_id');

    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo(999999, ['nome' => 'Com ramo inexistente']),
    )->assertSessionHasErrors('atividade_id');

    expect(Ambulante::count())->toBe(0);
});

test('atividade inativada continua valida no cadastro que ja a apontava', function () {
    /*
     * Inativar tira o valor das ESCOLHAS NOVAS; não invalida o passado. Sem
     * isto, quem entrasse para corrigir um telefone seria obrigado a trocar o
     * ramo do cadastro — mudando um dado que ninguém pediu para mudar.
     */
    $p = Ambulante::factory()->create(['atividade_id' => $this->atividade->id]);

    $this->atividade->update(['ativo' => false]);

    $this->actingAs($this->admin)->put(
        caminhoDoAmbulante($p->id),
        cadastroMinimo($this->atividade->id, ['nome' => $p->nome, 'telefone' => '(71) 98888-1111']),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->telefone)->toBe('(71) 98888-1111');
});

test('a situacao so aceita o que esta no catalogo', function () {
    // Valor inventado é recusado — o catálogo é fechado, e quem o define é o
    // servidor (a tela desenha o que ele manda).
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['situacao' => 'Aprovado']),
    )->assertSessionHasErrors('situacao');

    foreach (Ambulante::SITUACOES_DE_MESA as $i => $situacao) {
        $this->actingAs($this->admin)->post(
            caminhoDoAmbulante(),
            cadastroMinimo($this->atividade->id, ['nome' => "Pessoa {$i}", 'situacao' => $situacao]),
        )->assertSessionHasNoErrors();
    }

    expect(Ambulante::count())->toBe(count(Ambulante::SITUACOES_DE_MESA));
});

test('o nome e obrigatorio', function () {
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['nome' => '   ']),
    )->assertSessionHasErrors('nome');
});

test('nome e apelido nao aceitam markup nem a assinatura que o WAF barra', function () {
    /*
     * O payload abaixo GRAVAVA. Não executava — o React escapa o que renderiza —,
     * mas ficava na base e saía por outras portas: relatório, planilha, documento.
     * E o `--` é a assinatura que o WAF da Prefeitura barra na URL: grava sem
     * reclamar e depois faz a requisição que carregue o valor voltar disfarçada de
     * erro de CORS.
     */
    $recusados = [
        'nome' => '<img src=x onerror=window.__xss=1>Teste',
        'apelido' => 'Ze <b>Rico</b>',
    ];

    foreach ($recusados as $campo => $valor) {
        $this->actingAs($this->admin)->post(
            caminhoDoAmbulante(),
            cadastroMinimo($this->atividade->id, [$campo => $valor]),
        )->assertSessionHasErrors($campo);
    }

    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['nome' => 'Teste -- Rico']),
    )->assertSessionHasErrors('nome');

    expect(Ambulante::count())->toBe(0);
});

test('nome de gente de verdade continua passando — acento, apostrofo, hifen e numero', function () {
    // A guarda não pode ser purista: apelido com número e nome com apóstrofo ou
    // hífen são nomes reais, e recusá-los faria o cadastro não acontecer — que é
    // o oposto do que esta tela existe para permitir.
    foreach (['Maria das Graças de Souza', "Ana D'Ávila", 'Maria-José Ferreira', 'J. Carlos'] as $nome) {
        $this->actingAs($this->admin)->post(
            caminhoDoAmbulante(),
            cadastroMinimo($this->atividade->id, ['nome' => $nome, 'apelido' => 'Zé 2']),
        )->assertSessionHasNoErrors();
    }

    expect(Ambulante::count())->toBe(4);
});

test('a grade entrega os cadastros, as atividades e o catalogo de situacoes', function () {
    Ambulante::factory()->create([
        'apelido' => 'Zé da Água',
        'atividade_id' => $this->atividade->id,
    ]);

    $this->actingAs($this->admin)->get(caminhoDoAmbulante())
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Retaguarda/Fiscalizacao/CadastroDeAmbulante')
            ->has('ambulantes', 1)
            ->where('ambulantes.0.apelido', 'Zé da Água')
            // A tela mostra o documento formatado, e busca pelo normalizado: os
            // dois vêm do servidor para não haver duas verdades sobre o mesmo dado.
            ->has('ambulantes.0.documento_formatado')
            ->has('atividades')
            ->where('situacoes', Ambulante::SITUACOES)
            // O que a INCLUSÃO pode oferecer vem do servidor também: escrito na
            // tela, um dia ela ofereceria o que o servidor recusa.
            ->where('situacoesDeInclusao', Ambulante::SITUACOES_DE_MESA));
});

test('a grade entrega o documento formatado e a validade em forma de data', function () {
    Ambulante::factory()->create([
        'documento' => '12345678909',
        'validade_permissao' => '2027-03-09',
        'atividade_id' => $this->atividade->id,
    ]);

    $this->actingAs($this->admin)->get(caminhoDoAmbulante())
        ->assertInertia(fn (Assert $p) => $p
            ->where('ambulantes.0.documento', '12345678909')
            ->where('ambulantes.0.documento_formatado', '123.456.789-09')
            // ISO só por dentro: quem escreve dd/mm/aaaa é a tela.
            ->where('ambulantes.0.validade_permissao', '2027-03-09')
            ->etc());
});

test('excluir o cadastro leva junto o arquivo da foto', function () {
    Storage::fake('local');

    $p = Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => 'ambulantes/para-apagar.jpg',
    ]);

    Storage::disk('local')->put('ambulantes/para-apagar.jpg', 'conteudo');

    $this->actingAs($this->admin)->delete(caminhoDoAmbulante($p->id))
        ->assertRedirect()
        ->assertSessionHas('flash.sucesso');

    expect(Ambulante::find($p->id))->toBeNull();
    Storage::disk('local')->assertMissing('ambulantes/para-apagar.jpg');
});

test('a atividade apontada por um cadastro nao pode mais ser excluida, e a recusa diz por que', function () {
    /*
     * Teste-LEI da promessa deixada na parametrização: excluir a atividade
     * deixaria os cadastros apontando para o nada. A recusa acontece na tela de
     * onde a pessoa clicou, com o número de vínculos — e o caminho certo,
     * inativar, continua aberto.
     */
    Ambulante::factory()->count(2)->create(['atividade_id' => $this->atividade->id]);

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
    expect(CatalogoFuncionalidades::contem('ambulantes'))->toBeTrue();

    // É do primeiro trecho do caminho que as guardas deduzem a tela.
    expect(route('retaguarda.ambulantes.index', absolute: false))
        ->toStartWith('/retaguarda/ambulantes');
});

test('quem nao tem a tela concedida e mandado de volta dizendo o porque', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    // Conta sem setor nenhum: a matriz não concede nada a ela, e é o caso que
    // mais aparece na vida real — o usuário recém-criado que ninguém alocou.
    $semAcesso = User::factory()->create(['admin' => false]);

    $this->actingAs($semAcesso->fresh())->get(caminhoDoAmbulante())
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');
});

test('concedida na matriz, a tela abre para quem nao e administrador', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $gestor = User::factory()->create(['admin' => false]);
    $gestor->setores()->attach(Setor::where('slug', 'gestor')->firstOrFail());

    $this->actingAs($gestor->fresh())->get(caminhoDoAmbulante())->assertOk();
});

test('exige autenticacao', function () {
    $this->get(caminhoDoAmbulante())->assertRedirect(route('login'));
    $this->post(caminhoDoAmbulante(), [])->assertRedirect(route('login'));
});

test('o recorte visivel da grade vira documento pelo ponto unico de exportacao', function () {
    // A lei da exportação vale aqui como em qualquer listagem: o documento sai do
    // MESMO endpoint, com as colunas que a tela declara.
    $resposta = $this->actingAs($this->admin)->post(route('retaguarda.exportar-listagem'), [
        'formato' => 'pdf',
        'titulo' => 'Ambulantes',
        'subtitulo' => 'Fiscalização › Ambulantes',
        'contexto' => 'Busca: "acarajé"',
        'colunas' => [
            ['chave' => 'codigo', 'titulo' => 'Código'],
            ['chave' => 'nome', 'titulo' => 'Nome'],
            ['chave' => 'situacao', 'titulo' => 'Situação'],
        ],
        'linhas' => [
            ['codigo' => 'AMB20260902001', 'nome' => 'João da Silva', 'situacao' => 'Regular'],
        ],
    ]);

    $resposta->assertOk();

    expect((string) $resposta->getContent())->toStartWith('%PDF');
});

test('gravacao que falha NAO apaga a foto antiga — o registro vivo nunca aponta para arquivo inexistente', function () {
    /*
     * A ordem importa, e a prioridade é clara: **arquivo órfão é lixo, referência
     * quebrada é perda de dado**. Se a foto anterior fosse apagada antes do
     * `save()`, uma falha na gravação deixaria o cadastro VIVO apontando para um
     * arquivo que não existe mais — e a foto é a identidade de campo, o que o
     * fiscal usa para reconhecer a pessoa. O contrário (a foto nova sobrando no
     * disco) custa bytes e nada mais.
     */
    Storage::fake('local');

    $p = Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'foto' => 'ambulantes/original.jpg',
    ]);

    Storage::disk('local')->put('ambulantes/original.jpg', 'conteudo');

    // Uma falha de gravação qualquer (constraint, indisponibilidade, gatilho).
    // Sem o tratador de exceções no caminho: o que se prova é o estado que a
    // falha deixa, não a página de erro — e passar pelo registro de ocorrências
    // tornaria o teste dez vezes mais lento por nada.
    $this->withoutExceptionHandling();

    Ambulante::saving(function (): void {
        throw new RuntimeException('falha simulada na gravação');
    });

    try {
        $this->actingAs($this->admin)->put(
            caminhoDoAmbulante($p->id),
            cadastroMinimo($this->atividade->id, [
                'nome' => $p->nome,
                'foto' => UploadedFile::fake()->image('nova.jpg', 200, 200),
            ]),
        );
    } catch (Throwable) {
        // A falha É o cenário; o que se prova é o estado que ela deixou.
    }

    expect($p->fresh()->foto)->toBe('ambulantes/original.jpg');
    Storage::disk('local')->assertExists('ambulantes/original.jpg');
});

test('a inclusao pela Retaguarda nao oferece a quarentena, e o servidor recusa', function () {
    /*
     * "Cadastrado em campo" é estado de ORIGEM: quer dizer "isto nasceu na rua,
     * sem conferência". Um cadastro feito de mesa, com o gestor lendo documento
     * na tela, não nasce assim — deixar a opção aberta na inclusão sujaria a fila
     * de conferência com registros que ninguém precisa conferir.
     *
     * No UPDATE ela continua disponível: é como o gestor devolve para a fila um
     * cadastro que ele percebeu duvidoso.
     */
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['situacao' => Ambulante::SITUACAO_CAMPO]),
    )->assertSessionHasErrors('situacao');

    expect(Ambulante::count())->toBe(0);

    // De mesa nasce Regular ou Irregular.
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['situacao' => Ambulante::SITUACAO_REGULAR]),
    )->assertSessionHasNoErrors();

    $p = Ambulante::firstOrFail();

    // E o gestor pode devolvê-lo à fila depois.
    $this->actingAs($this->admin)->put(
        caminhoDoAmbulante($p->id),
        cadastroMinimo($this->atividade->id, [
            'nome' => $p->nome,
            'situacao' => Ambulante::SITUACAO_CAMPO,
        ]),
    )->assertSessionHasNoErrors();

    expect($p->fresh()->situacao)->toBe(Ambulante::SITUACAO_CAMPO);
});

/*
|--------------------------------------------------------------------------
| Ser permissionário é ATRIBUTO, não categoria (RN-01-b)
|--------------------------------------------------------------------------
|
| A decisão de 02/09/2026: a entidade é o ambulante, e ter permissão da SEMOP é
| um campo dele. O que estes testes travam é o que dá sentido ao campo — sem
| eles, "é permissionário" seria um clique que ninguém consegue conferir depois,
| e a permissão de quem não a tem ficaria guardada no cadastro.
|
*/

test('ambulante criado sem permissao fica marcado como SEM permissao, e nao exige numero nem validade', function () {
    /*
     * É o caso comum da rua, e por isso é o primeiro: quem a fiscalização mais
     * encontra não tem permissão nenhuma. Exigir número aqui faria o cadastro da
     * maioria não acontecer — o mesmo erro que o documento obrigatório faria.
     */
    $this->actingAs($this->admin)->post(caminhoDoAmbulante(), [
        'nome' => 'Sem Permissão da Silva',
        'atividade_id' => $this->atividade->id,
        'situacao' => Ambulante::SITUACAO_REGULAR,
        'permissionario' => false,
    ])->assertSessionHasNoErrors();

    $a = Ambulante::firstOrFail();

    expect($a->permissionario)->toBeFalse()
        ->and($a->numero_permissao)->toBeNull()
        ->and($a->validade_permissao)->toBeNull()
        // E a situação é OUTRA pergunta: sem permissão pode estar regular (ponto
        // autorizado por outra via). Deduzir uma da outra apagaria as duas.
        ->and($a->situacao)->toBe(Ambulante::SITUACAO_REGULAR);
});

test('marcado como permissionario, o numero da permissao passa a ser exigido', function () {
    // Sem esta amarra, marcar a opção era só um clique: o cadastro passava a
    // dizer "tem permissão" sem nada que se pudesse conferir depois.
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, ['permissionario' => true]),
    )->assertSessionHasErrors('numero_permissao');

    expect(Ambulante::count())->toBe(0);

    // Com o número, entra.
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, [
            'permissionario' => true,
            'numero_permissao' => 'SEMOP-01234',
        ]),
    )->assertSessionHasNoErrors();

    $a = Ambulante::firstOrFail();

    expect($a->permissionario)->toBeTrue()
        ->and($a->numero_permissao)->toBe('SEMOP-01234');
});

test('a validade da permissao continua OPCIONAL — data inventada faria a busca acusar quem esta em dia', function () {
    /*
     * Decisão registrada na RN-01-b: em rua o papel está desbotado, rasgado ou
     * não está com a pessoa. Exigir a data faria alguém inventar uma — e aí a
     * busca por "permissão vencida" passaria a acusar com base nela. Ausente, ela
     * não acusa ninguém.
     */
    $this->actingAs($this->admin)->post(
        caminhoDoAmbulante(),
        cadastroMinimo($this->atividade->id, [
            'permissionario' => true,
            'numero_permissao' => 'SEMOP-55555',
        ]),
    )->assertSessionHasNoErrors();

    expect(Ambulante::firstOrFail()->validade_permissao)->toBeNull();
});

test('desmarcar permissionario LIMPA o numero e a validade', function () {
    /*
     * O caso do gestor que corrige um cadastro: a pessoa não é permissionária
     * (ou nunca foi). Guardar a permissão de quem o cadastro diz não ter deixaria
     * a base afirmando duas coisas contrárias — e a busca por "permissão vencida"
     * acusaria alguém por um papel que o próprio sistema diz que não existe.
     */
    $a = Ambulante::factory()->permissionario('SEMOP-77777', '2026-01-10')->create([
        'atividade_id' => $this->atividade->id,
    ]);

    expect($a->numero_permissao)->toBe('SEMOP-77777');

    $this->actingAs($this->admin)->put(
        caminhoDoAmbulante($a->id),
        cadastroMinimo($this->atividade->id, [
            'nome' => $a->nome,
            'permissionario' => false,
            // Mandados de propósito: é o que um formulário que só esconde os
            // campos, sem limpá-los, enviaria de volta.
            'numero_permissao' => 'SEMOP-77777',
            'validade_permissao' => '2026-01-10',
        ]),
    )->assertSessionHasNoErrors();

    $depois = $a->fresh();

    expect($depois->permissionario)->toBeFalse()
        ->and($depois->numero_permissao)->toBeNull()
        ->and($depois->validade_permissao)->toBeNull();
});

test('a resposta sobre a permissao e OBRIGATORIA — em branco nao e o mesmo que "nao tem"', function () {
    // Deixar o campo cair no padrão da coluna faria um permissionário virar
    // sem-permissão em silêncio, levando embora o número que ele tinha.
    $dados = cadastroMinimo($this->atividade->id);
    unset($dados['permissionario']);

    $this->actingAs($this->admin)->post(caminhoDoAmbulante(), $dados)
        ->assertSessionHasErrors('permissionario');

    expect(Ambulante::count())->toBe(0);
});

test('a grade entrega o atributo da permissao', function () {
    // A tela desenha a coluna e as facetas "permissionários"/"sem permissão" a
    // partir DESTE campo. Sem ele no payload, a grade não sabe distinguir os dois
    // públicos que a base passou a ter.
    Ambulante::factory()->permissionario('SEMOP-90001')->create([
        'nome' => 'Com Permissao',
        'atividade_id' => $this->atividade->id,
    ]);

    $this->actingAs($this->admin)->get(caminhoDoAmbulante())
        ->assertInertia(fn (Assert $p) => $p
            ->where('ambulantes.0.permissionario', true)
            ->where('ambulantes.0.numero_permissao', 'SEMOP-90001')
            ->etc());
});
