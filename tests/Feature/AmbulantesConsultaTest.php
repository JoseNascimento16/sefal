<?php

use App\Models\Ambulante;
use App\Models\AtividadeAmbulante;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Ambulantes — CONSULTA da base que o SGCI entrega
|--------------------------------------------------------------------------
|
| ⚠️ Esta tela era um CADASTRO (incluir, alterar, excluir), e deixou de ser em
| 10/09/2026 por decisão do dono: "a tela de Ambulantes não será CRUD, só irá
| receber os registros do SGCI via integração". A base é do SGCI — o sistema do
| comércio informal —, e aqui ela é espelho de LEITURA.
|
| Este arquivo substitui o `CadastroAmbulanteTest`, cujos testes descreviam o
| caminho de escrita: documento único na gravação, foto trocada e removida,
| quarentena recusada na inclusão, número da permissão exigido de quem é marcado.
| Nada disso é comportamento do sistema hoje — o formulário e as rotas não
| existem —, e teste de comportamento que não existe é teste que passa sem provar
| nada.
|
| O que se testa aqui:
|
|   • NÃO HÁ caminho de escrita — nem botão, nem rota. É o primeiro teste do
|     arquivo de propósito: é a regra que a mudança inteira existe para valer;
|   • a tela entrega o que a consulta precisa: os cadastros, o documento nas duas
|     formas, os ramos e o catálogo de situações;
|   • quem não tem a tela concedida é mandado de volta com o motivo;
|   • a semente concede a tela em APENAS LEITURA a todo mundo, e a matriz já
|     gravada é corrigida por migration que não desfaz decisão de gerente;
|   • o recorte visível vira documento pelo ponto único de exportação;
|   • a atividade apontada por um ambulante continua sem poder ser excluída da
|     parametrização — a promessa é da tela de lá, e sobrevive à mudança daqui.
|
| A régua é a spec de design (§4.1) revisada pela decisão de 10/09/2026 — não há
| HU escrita neste projeto.
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

test('lei: NENHUMA mutacao mora sob o caminho da tela — a base nao e nossa', function () {
    /*
     * Teste-LEI, e o mais importante do arquivo. A ordem do dono não era "esconda
     * os botões": era "esta tela não cadastra". Tela sem botão cujas rotas
     * continuassem vivas seria PIOR que a tela antiga — o servidor aceitaria
     * escrita de quem montasse a requisição (e é trivial montar), e o que fosse
     * gravado na base espelho seria desfeito em silêncio pela carga seguinte do
     * SGCI, sem ninguém entender por quê.
     *
     * A varredura é sobre as ROTAS REGISTRADAS, e não sobre o controller: um
     * `Route::post` acrescentado amanhã apontando para qualquer coisa cai aqui.
     */
    $mutacoes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_starts_with($r->uri(), 'retaguarda/ambulantes')
            && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->map(fn ($r): string => implode('|', $r->methods()).' '.$r->uri())
        ->values()
        ->all();

    expect($mutacoes)->toBe([]);
});

test('a escrita montada A MAO e recusada: nao existe rota que a receba', function () {
    /*
     * A outra metade da lei acima, exercitada como o mundo real a exercitaria:
     * quem sabe o endereço tenta o POST. O que importa é que **nada grava** — e a
     * resposta certa é a que o roteador dá naturalmente, sem 403: 403 diria
     * "existe, mas você não pode", insinuando que outra pessoa poderia.
     *
     * Na coleção a resposta é **405**: o endereço existe para LER (é a tela), e o
     * servidor recusa o verbo. No item, onde não há rota nenhuma, é 404. Medido
     * contra o roteador de verdade em vez de suposto — a primeira versão deste
     * teste exigia 404 nos três e reprovava por causa disso.
     *
     * Feito como ADMINISTRADOR de propósito: se sobrasse rota, o admin passaria
     * por qualquer guarda de permissão e gravaria. É o perfil que expõe a brecha.
     */
    $ambulante = Ambulante::factory()->create(['atividade_id' => $this->atividade->id]);
    $antes = $ambulante->only(['nome', 'situacao', 'atividade_id']);

    $this->actingAs($this->admin)
        ->post(caminhoDoAmbulante(), ['nome' => 'Inventado', 'atividade_id' => $this->atividade->id, 'situacao' => 'Regular', 'permissionario' => false])
        ->assertMethodNotAllowed();

    $this->actingAs($this->admin)
        ->put(caminhoDoAmbulante($ambulante->id), ['nome' => 'Renomeado à força'])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete(caminhoDoAmbulante($ambulante->id))
        ->assertNotFound();

    // E nada mudou: nem cadastro novo, nem o que já estava lá.
    expect(Ambulante::count())->toBe(1)
        ->and($ambulante->fresh()->only(['nome', 'situacao', 'atividade_id']))->toBe($antes);
});

test('a tela entrega os cadastros, os ramos e o catalogo de situacoes', function () {
    Ambulante::factory()->create([
        'apelido' => 'Zé da Água',
        'atividade_id' => $this->atividade->id,
    ]);

    $this->actingAs($this->admin)->get(caminhoDoAmbulante())
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Retaguarda/Fiscalizacao/Ambulantes')
            ->has('ambulantes', 1)
            ->where('ambulantes.0.apelido', 'Zé da Água')
            // A tela mostra o documento formatado, e busca pelo normalizado: os
            // dois vêm do servidor para não haver duas verdades sobre o mesmo dado.
            ->has('ambulantes.0.documento_formatado')
            ->has('atividades')
            // O catálogo de situações continua descendo mesmo sem formulário: é
            // dele que saem os números do cabeçalho e a marca da fila de
            // conferência na linha.
            ->where('situacoes', Ambulante::SITUACOES)
            ->has('listagens'));
});

test('o catalogo de situacoes DE MESA nao desce mais — nenhum cadastro nasce aqui', function () {
    /*
     * `SITUACOES_DE_MESA` existia para dizer com que situação um cadastro podia
     * NASCER pela Retaguarda (sem a quarentena, que é estado de origem de rua).
     * Sem inclusão, ela é promessa de formulário: mandá-la faria a próxima pessoa
     * que abrisse a tela procurar o `<select>` que a consome.
     */
    $this->actingAs($this->admin)->get(caminhoDoAmbulante())
        ->assertInertia(fn (Assert $p) => $p->missing('situacoesDeInclusao')->etc());
});

test('a tela entrega o documento formatado e a validade em forma de data', function () {
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

test('a tela entrega o atributo da permissao — permissionario e um dos dois publicos', function () {
    // Ser permissionário é ATRIBUTO, não categoria: a base tem os dois, e a tela
    // precisa poder distingui-los sem deduzir da situação.
    Ambulante::factory()->create([
        'atividade_id' => $this->atividade->id,
        'permissionario' => true,
        'numero_permissao' => 'PRM-2026-0417',
    ]);

    $this->actingAs($this->admin)->get(caminhoDoAmbulante())
        ->assertInertia(fn (Assert $p) => $p
            ->where('ambulantes.0.permissionario', true)
            ->where('ambulantes.0.numero_permissao', 'PRM-2026-0417')
            ->etc());
});

test('a tela entra no controle de acesso, e o caminho e o slug', function () {
    expect(CatalogoFuncionalidades::contem('ambulantes'))->toBeTrue();

    // É do primeiro trecho do caminho que as guardas deduzem a tela. O slug NÃO
    // mudou quando a tela deixou de ser cadastro: renomeá-lo a tiraria da matriz
    // e mataria a permissão de quem já a tem.
    expect(route('retaguarda.ambulantes.index', absolute: false))
        ->toStartWith('/retaguarda/ambulantes');
});

test('a semente concede a tela em APENAS LEITURA a TODOS os setores', function () {
    /*
     * Consequência direta de a tela deixar de gravar. Antes o Chefe de Setor
     * nascia com o pacote inteiro (`Vê`, `Opera`, `Inclui`, `Exclui`), porque
     * validar e corrigir cadastro de campo era trabalho dele; hoje as três
     * últimas marcas não decidem NADA — não há rota para barrar. Marca que não
     * decide nada ensina errado quem distribui acesso.
     *
     * ⚠️ `apenas_leitura`, e não "incluir e excluir desligados": é ele que
     * derruba OPERAR junto.
     */
    foreach (['chefe-de-setor', 'fiscal'] as $setor) {
        $linha = PermissaoSetor::where('setor', $setor)->where('slug', 'ambulantes')->firstOrFail();

        expect($linha->visivel)->toBeTrue()
            ->and($linha->apenas_leitura)->toBeTrue()
            ->and($linha->habilitado)->toBeFalse()
            ->and($linha->incluir)->toBeFalse()
            ->and($linha->excluir)->toBeFalse();
    }

    // E ninguém mais foi semeado com poder de escrita nesta tela.
    expect(PermissaoSetor::where('slug', 'ambulantes')->where('apenas_leitura', false)->count())->toBe(0);
});

test('a migration corrige a matriz ja gravada sem passar por cima de decisao de gerente', function () {
    /*
     * A semente se aplica UMA VEZ (`firstOrCreate`, de propósito): mudar a
     * declaração do menu não reescreve linha de banco já semeado. Sem a migration,
     * o Modo Gerente continuaria mostrando "Inclui" e "Exclui" marcados numa tela
     * que não inclui nem exclui.
     *
     * E a correção é CONDICIONAL: linha que alguém ajustou na tela fica intacta.
     */
    $migration = require database_path(
        'migrations/2026_09_10_090100_ambulantes_vira_consulta_e_a_matriz_para_de_prometer_escrita.php',
    );

    // (a) A linha como a semente ANTIGA a deixava: vira apenas leitura.
    DB::table('permissoes_setor')->where('setor', 'chefe-de-setor')->where('slug', 'ambulantes')->update([
        'visivel' => true,
        'habilitado' => true,
        'apenas_leitura' => false,
        'incluir' => true,
        'excluir' => true,
    ]);

    $migration->up();
    // De novo: idempotente (no OKD o `migrate` é passo manual depois do deploy).
    $migration->up();

    $chefe = PermissaoSetor::where('setor', 'chefe-de-setor')->where('slug', 'ambulantes')->firstOrFail();

    expect($chefe->apenas_leitura)->toBeTrue()
        ->and($chefe->habilitado)->toBeFalse()
        ->and($chefe->incluir)->toBeFalse()
        ->and($chefe->excluir)->toBeFalse();

    // (b) A linha que alguém MEXEU na tela: fica como está. Aqui, alguém tirou a
    // visibilidade — a migration não pode devolvê-la de tabela.
    DB::table('permissoes_setor')->where('setor', 'chefe-de-setor')->where('slug', 'ambulantes')->update([
        'visivel' => false,
        'habilitado' => true,
        'apenas_leitura' => false,
        'incluir' => true,
        'excluir' => true,
    ]);

    $migration->up();

    expect((bool) PermissaoSetor::where('setor', 'chefe-de-setor')->where('slug', 'ambulantes')->firstOrFail()->incluir)
        ->toBeTrue();
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

    $chefe = User::factory()->create(['admin' => false]);
    $chefe->setores()->attach(Setor::where('slug', 'chefe-de-setor')->firstOrFail());

    $this->actingAs($chefe->fresh())->get(caminhoDoAmbulante())->assertOk();
});

test('exige autenticacao', function () {
    $this->get(caminhoDoAmbulante())->assertRedirect(route('login'));
});

test('o recorte visivel da grade vira documento pelo ponto unico de exportacao', function () {
    // A lei da exportação vale aqui como em qualquer listagem, e continua valendo
    // depois de a tela virar consulta: exportar é LEITURA, e é a única ação que
    // sobrou. O documento sai do MESMO endpoint, com as colunas que a tela declara.
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

test('a atividade apontada por um ambulante nao pode ser excluida, e a recusa diz por que', function () {
    /*
     * Teste-LEI da promessa deixada na PARAMETRIZAÇÃO, e ela sobrevive à mudança
     * desta tela: excluir a atividade deixaria os ambulantes recebidos apontando
     * para o nada, e quem responderia seria a chave estrangeira do banco — com um
     * erro cru na cara de quem está na tela. A recusa acontece onde a pessoa
     * clicou, com o número de vínculos, e o caminho certo (inativar) continua
     * aberto.
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

test('atividade sem nenhum ambulante continua excluivel', function () {
    // A guarda anterior não pode virar "nunca mais se exclui atividade": o valor
    // cadastrado errado, que ninguém usou, tem de sair.
    $nova = AtividadeAmbulante::create(['nome' => 'Ramo digitado errado', 'ativo' => true]);

    $this->actingAs($this->admin)
        ->delete("/retaguarda/parametrizacao/atividades-do-ambulante/{$nova->id}")
        ->assertSessionHas('flash.sucesso');

    expect(AtividadeAmbulante::find($nova->id))->toBeNull();
});
