<?php

use App\Models\LogErro;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Observabilidade — o erro que ninguém viu acontecer
|--------------------------------------------------------------------------
|
| Sem isto, diagnosticar um erro de produção significa entrar no servidor e
| caçar no arquivo de log — que é exatamente o que não acontece quando o
| atendente liga dizendo "deu erro". O que se testa aqui é a corrente inteira:
| a exceção vira linha, a linha carrega o CÓDIGO que o usuário viu na tela, e
| gravar esse registro jamais derruba o pedido que já estava com problema.
|
*/

/** Uma rota que estoura de propósito — o gatilho da corrente inteira. */
function rotaQueEstoura(string $mensagem = 'estouro de teste'): string
{
    Route::middleware('web')
        ->get('teste/estoura', fn () => throw new RuntimeException($mensagem))
        ->name('teste.estoura');

    Route::getRoutes()->refreshNameLookups();

    return '/teste/estoura';
}

test('excecao reportada vira linha em log_erros', function () {
    report(new RuntimeException('erro de teste'));

    $log = LogErro::latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->mensagem)->toContain('erro de teste')
        ->and($log->classe)->toBe(RuntimeException::class);
});

test('o codigo mostrado ao usuario e o MESMO gravado no registro', function () {
    // A ponta solta que isto amarra: a página de erro mostra um código e o
    // suporte precisa achar a ocorrência exata por ele. Se cada lado gerasse o
    // seu, o código informado pelo usuário não acharia nada.
    config(['app.debug' => false]);

    $resposta = $this->get(rotaQueEstoura());

    $codigo = $resposta->headers->get('X-Request-Id');

    expect($codigo)->not->toBeEmpty();

    $resposta->assertStatus(500)->assertSee($codigo, escape: false);

    expect(LogErro::latest('id')->first()->request_id)->toBe($codigo);
});

test('o registro guarda o caminho, o verbo e quem estava logado', function () {
    config(['app.debug' => false]);

    $usuario = User::factory()->create();

    $this->actingAs($usuario)->get(rotaQueEstoura());

    $log = LogErro::latest('id')->first();

    expect($log->caminho)->toContain('teste/estoura')
        ->and($log->metodo)->toBe('GET')
        ->and($log->user_id)->toBe($usuario->id);
});

test('sem ninguem autenticado, o registro nasce sem dono — e nao inventa um', function () {
    config(['app.debug' => false]);

    $this->get(rotaQueEstoura());

    expect(LogErro::latest('id')->first()->user_id)->toBeNull();
});

test('o registro NAO guarda token de redefinicao nem e-mail na consulta', function () {
    /*
     * O caso concreto: uma exceção em `GET /reset-password/{token}?email=…`.
     * O endereço completo levaria o TOKEN de redefinição e o e-mail para uma
     * tabela que qualquer administrador lê — e exporta em PDF. Quem tivesse o
     * token trocaria a senha da conta alheia.
     */
    $token = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';
    $email = 'fiscal@salvador.ba.gov.br';

    LogErro::registrar(
        new RuntimeException('falhou ao redefinir'),
        Request::create("/reset-password/{$token}?email=".urlencode($email), 'GET'),
    );

    $log = LogErro::latest('id')->first();

    expect($log->caminho)->not->toContain($token)
        ->and($log->caminho)->not->toContain('fiscal@')
        ->and($log->caminho)->toBe('reset-password/[token]');
});

test('TODO caminho que carrega segredo entra mascarado — a lista nao pode envelhecer', function () {
    /*
     * Teste-LEI sobre as rotas REAIS. A lista de caminhos sensíveis do `LogErro`
     * é escrita à mão, e lista escrita à mão envelhece calada: basta uma rota
     * nova com token no endereço — confirmação de e-mail, link assinado de
     * validação por QR — para o segredo passar a ser gravado sem ninguém notar.
     *
     * Em vez de conferir a lista, confere-se o UNIVERSO: toda rota cujo endereço
     * tem `{token}`, `{hash}` ou `{signature}` é exercitada com um segredo de
     * amostra, e o que ficou gravado não pode conter esse segredo.
     *
     * O caminho é exercitado por `registrar()`, e não pelo método de máscara
     * direto: o que interessa provar é o que CHEGA À TABELA — é dali que o
     * segredo sairia para a tela de Logs e para o PDF exportado.
     */
    $marca = 'segredodeamostra';

    $sensiveis = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($r): string => $r->uri())
        ->filter(fn (string $uri): bool => preg_match('/\{(token|hash|signature)\??\}/', $uri) === 1)
        ->unique()
        ->values();

    // Hoje é o `reset-password/{token}` do Fortify. Vazio aqui significaria que o
    // teste deixou de exercitar coisa alguma e passaria a dar falso verde.
    expect($sensiveis)->not->toBeEmpty();

    $desprotegidos = [];

    foreach ($sensiveis as $uri) {
        LogErro::registrar(
            new RuntimeException('estouro no caminho sensível'),
            Request::create('/'.preg_replace('/\{[^}]+\}/', $marca, $uri), 'GET'),
        );

        $caminho = (string) LogErro::latest('id')->first()->caminho;

        if (str_contains($caminho, $marca)) {
            $desprotegidos[] = $uri.' (gravou o segredo)';
        }

        // Caminho vazio passaria na conferência acima sem provar nada: seria a
        // asserção verde de um teste que deixou de exercitar o que promete.
        if (trim($caminho) === '') {
            $desprotegidos[] = $uri.' (não gravou caminho algum — o teste não exercitou nada)';
        }
    }

    expect($desprotegidos)->toBe([]);
});

test('a consulta NUNCA e gravada, nem em caminho comum', function () {
    // O que vai na consulta é escolha de quem escreveu a tela, e amanhã pode ser
    // um documento ou um termo de busca. Guardar só o caminho tira a decisão do
    // caminho do erro — não há como alguém, sem perceber, mandar segredo para cá.
    LogErro::registrar(
        new RuntimeException('qualquer'),
        Request::create('/retaguarda/logs?de=2026-01-01&segredo=nao-deve-aparecer', 'GET'),
    );

    expect(LogErro::latest('id')->first()->caminho)->toBe('retaguarda/logs');
});

test('o rastro NAO carrega os argumentos passados as funcoes', function () {
    /*
     * O PHP guarda os argumentos de cada quadro da pilha quando
     * `zend.exception_ignore_args` está desligado — e desligado é o default fora
     * do php.ini de produção. Aí a senha digitada no login entra no rastro em
     * texto claro e aparece no detalhe da tela para quem abrir a ocorrência.
     *
     * A ini é ajustada aqui de propósito, para o teste exercitar o pior caso: a
     * primeira asserção prova que o ajuste pegou (o rastro CRU tem o segredo), e
     * a segunda prova que o que gravamos não tem.
     *
     * O segredo é curto porque o PHP corta o argumento em 15 caracteres no rastro
     * — o que NÃO diminui o vazamento (os 15 primeiros de uma senha já bastam
     * para quem tenta adivinhar o resto), mas faria a asserção mentir se o texto
     * do teste fosse mais longo que isso.
     */
    // A ini volta ao valor anterior no fim: ela é estado GLOBAL do processo, e o
    // Pest roda os testes todos no mesmo. Deixá-la ligada aqui mudaria, em
    // silêncio, o comportamento de qualquer teste seguinte que inspecione rastro
    // — e o pior tipo de falha é a que depende da ORDEM dos testes.
    $anterior = ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '0');

    try {
        $entrar = function (string $senha) {
            throw new RuntimeException('falha ao autenticar');
        };

        try {
            $entrar('p4ssw0rd-real');
        } catch (Throwable $e) {
            expect($e->getTraceAsString())->toContain('p4ssw0rd-real');

            report($e);
        }

        expect(LogErro::latest('id')->first()->stack)->not->toContain('p4ssw0rd-real');
    } finally {
        ini_set('zend.exception_ignore_args', $anterior === false ? '1' : $anterior);
    }
});

test('nem gravar no arquivo de log pode derrubar o pedido', function () {
    /*
     * O cenário duplo: o banco fora do ar E o arquivo de log inacessível (disco
     * cheio, diretório sem permissão). A captura cai na reserva, e a reserva
     * também falha — sem a segunda guarda, a exceção escaparia daqui e derrubaria
     * até a página amigável que existe justamente para esse momento.
     *
     * Chama-se `registrar()` direto, e não `report()`: a promessa sob teste é a
     * DESTE método ("nunca lança"). O `report()` do framework grava no log por
     * conta própria depois de nós, e com o log quebrado ele estouraria de
     * qualquer jeito — isso não está sob o nosso controle e não é o que a guarda
     * promete.
     */
    Schema::drop('log_erros');

    Log::shouldReceive('error')->andThrow(new RuntimeException('storage ilegível'));

    LogErro::registrar(new RuntimeException('erro com tudo fora'));
})->throwsNoExceptions();

test('mensagem e rastro sao cortados no limite declarado', function () {
    // Sem corte, a mensagem de um erro de banco (que carrega o SQL inteiro) e o
    // rastro de uma recursão profunda estouram a coluna — e o registro do erro
    // vira o próximo erro.
    report(new RuntimeException(str_repeat('a', LogErro::LIMITE_MENSAGEM + 500)));

    expect(mb_strlen(LogErro::latest('id')->first()->mensagem))->toBe(LogErro::LIMITE_MENSAGEM);

    $fundo = function (int $n) use (&$fundo) {
        if ($n === 0) {
            throw new RuntimeException('fundo do poço');
        }

        $fundo($n - 1);
    };

    try {
        $fundo(300);
    } catch (Throwable $e) {
        report($e);
    }

    expect(mb_strlen(LogErro::latest('id')->first()->stack))->toBe(LogErro::LIMITE_STACK);
});

test('gravar o registro JAMAIS derruba o pedido', function () {
    // O caso que importa: o próprio banco fora do ar. Registrar o erro não pode
    // virar um segundo erro por cima do primeiro — aí o usuário perde até a
    // página amigável.
    Schema::drop('log_erros');

    report(new RuntimeException('erro com o banco fora'));
})->throwsNoExceptions();

test('a tela lista as ocorrencias com o contexto de cada uma', function () {
    LogErro::create([
        'request_id' => 'REQ-abc123',
        'classe' => 'RuntimeException',
        'mensagem' => 'falha ao gravar a vistoria',
        'caminho' => 'retaguarda/vistorias',
        'metodo' => 'POST',
        'stack' => '#0 /app/Http/Controllers/X.php(10)',
    ]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.logs.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->component('Retaguarda/Sistema/Logs')
                ->has('logs', 1)
                ->where('logs.0.requestId', 'REQ-abc123')
                ->where('logs.0.mensagem', 'falha ao gravar a vistoria')
                ->where('logs.0.metodo', 'POST');
        });
});

test('a listagem NAO carrega o rastro; ele vem quando alguem abre a ocorrencia', function () {
    /*
     * O rastro é um campo longo (CLOB no Oracle), e cada um custa uma ida ao
     * banco por linha: trazê-lo na lista derruba por tempo esgotado justamente a
     * tela que existe para diagnosticar. Uma linha de cada vez não pesa.
     */
    $log = LogErro::create([
        'classe' => 'RuntimeException',
        'mensagem' => 'qualquer coisa',
        'stack' => '#0 rastro completo da ocorrência',
    ]);

    $admin = User::factory()->create(['admin' => true]);

    $this->actingAs($admin)
        ->get(route('retaguarda.logs.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Retaguarda/Sistema/Logs')->missing('logs.0.stack'));

    $this->actingAs($admin)
        ->get(route('retaguarda.logs.detalhe', $log))
        ->assertOk()
        ->assertJson(['stack' => '#0 rastro completo da ocorrência']);
});

test('a janela de periodo manda no que a tela carrega', function () {
    LogErro::create(['classe' => 'A', 'mensagem' => 'recente'])
        ->forceFill(['created_at' => now()->subDay()])->save();

    LogErro::create(['classe' => 'B', 'mensagem' => 'antigo'])
        ->forceFill(['created_at' => now()->subMonths(2)])->save();

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.logs.index', ['de' => now()->subWeek()->toDateString(), 'ate' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('logs', 1)->where('logs.0.mensagem', 'recente'));

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.logs.index', ['de' => now()->subMonths(6)->toDateString(), 'ate' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('logs', 2));
});

test('a tela de logs e SO LEITURA: nenhuma mutacao mora sob o caminho dela', function () {
    // Log de erro é prova do que aconteceu. Editar ou apagar linha daqui apagaria
    // a única trilha de um defeito de produção.
    $mutacoes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'retaguarda/logs')
            && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->map(fn ($r) => $r->uri())
        ->values()
        ->all();

    expect($mutacoes)->toBe([]);
});

test('quem nao tem a tela concedida e mandado de volta dizendo o porque', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $fiscal = User::factory()->create(['admin' => false]);
    $fiscal->setores()->attach(Setor::create(['slug' => 'fiscal', 'nome' => 'Fiscal']));

    $this->actingAs($fiscal->fresh())->get('/retaguarda/logs')
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');
});

test('concedida na matriz, a tela abre para quem nao e administrador', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $gestor = User::factory()->create(['admin' => false]);
    $gestor->setores()->attach(Setor::create(['slug' => 'gestor', 'nome' => 'Gestor']));
    PermissaoSetor::create(['setor' => 'gestor', 'slug' => 'logs', 'visivel' => true]);

    $this->actingAs($gestor->fresh())->get('/retaguarda/logs')->assertOk();
});
