<?php

use App\Models\AtividadeAmbulante;
use App\Models\PermissaoSetor;
use App\Models\Setor;
use App\Models\TipoInfracao;
use App\Models\User;
use App\Services\Monitoramento\CheckParametrizacao;
use App\Services\Monitoramento\MonitorParametrizacoes;
use App\Services\Monitoramento\ResultadoCheck;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Monitoramento de Parametrizações — os testes-LEI do motor
|--------------------------------------------------------------------------
|
| Estes testes não valem para os checks de hoje: valem para TODO check, inclusive
| os que ainda vão nascer. Eles iteram o catálogo inteiro, então um check novo
| entra já sujeito às cinco regras — id único, saída existente, nunca estourar,
| saída renderizável, e o flip que prova que ele SABE ficar vermelho.
|
| O flip é o que impede o pior defeito desta tela: um check tautológico, que
| nunca acusa, dá a sensação de sistema saudável justamente quando ele não está.
|
*/

/** O check de um id, ou a explicação de que ele sumiu do catálogo. */
function checkDoCatalogo(string $id): CheckParametrizacao
{
    foreach (MonitorParametrizacoes::todosOsChecks() as $check) {
        if ($check->id === $id) {
            return $check;
        }
    }

    throw new RuntimeException("O check '{$id}' não está no catálogo do monitoramento.");
}

test('lei: os ids dos checks sao unicos', function () {
    // A verificação profunda substitui o resultado POR ID: dois checks com o
    // mesmo id fariam um sobrescrever o outro na tela, e o que sumisse seria
    // justamente o que ninguém veria acusar.
    $ids = collect(MonitorParametrizacoes::todosOsChecks())->map(fn (CheckParametrizacao $c) => $c->id);

    expect($ids)->not->toBeEmpty()
        ->and($ids->duplicates()->values()->all())->toBe([]);
});

test('lei: toda rota apontada por um check existe', function () {
    // Link quebrado aqui manda o usuário ao nada, no pior momento possível — ele
    // acabou de descobrir que um fluxo está parado e clicou para corrigir.
    $quebradas = collect(MonitorParametrizacoes::todosOsChecks())
        ->filter(fn (CheckParametrizacao $c) => $c->rota !== null && ! Route::has($c->rota))
        ->map(fn (CheckParametrizacao $c) => "{$c->id} → {$c->rota}")
        ->values()
        ->all();

    expect($quebradas)->toBe([]);
});

test('lei: nenhum check estoura com o banco vazio', function () {
    // Esta tela é o instrumento de diagnóstico: ela precisa abrir JUSTAMENTE
    // quando as coisas estão quebradas. Banco vazio é o pior caso realista.
    foreach (MonitorParametrizacoes::todosOsChecks() as $check) {
        expect($check->executar())->toBeInstanceOf(ResultadoCheck::class);
    }
});

test('lei: check que estoura vira falha legivel, nunca 500', function () {
    $check = new CheckParametrizacao(
        id: 'teste-que-estoura',
        titulo: 'Verificação que quebra',
        verificacao: fn () => throw new RuntimeException('tabela inexistente'),
        instrucao: 'Instrução de teste.',
    );

    $resultado = $check->executar();

    expect($resultado->status)->toBe(ResultadoCheck::FALHA)
        // A mensagem crua do erro carrega SQL, host e porta: informação de
        // infraestrutura que não pode aparecer na tela de ninguém.
        ->and($resultado->detalhe)->not->toContain('tabela inexistente')
        ->and($resultado->detalhe)->toContain('não pôde ser executada');
});

test('lei: todo check tem uma saida, e ela e renderizavel', function () {
    // Alarme sem porta não compila: o construtor recusa check sem rota e sem
    // instrução, e o que vai para a tela sempre traz um dos dois preenchidos.
    foreach (MonitorParametrizacoes::todosOsChecks() as $check) {
        $tela = $check->paraTela($check->executar());

        expect($tela['acao_url'] !== null || $tela['instrucao'] !== null)
            ->toBeTrue("O check '{$check->id}' não diz para onde ir.");
    }

    expect(fn () => new CheckParametrizacao(
        id: 'teste-sem-saida',
        titulo: 'Sem saída',
        verificacao: fn () => ResultadoCheck::ok('nada'),
    ))->toThrow(InvalidArgumentException::class);
});

test('lei: o check do administrador ativo FLIPA — sem admin fica vermelho, com admin fica verde', function () {
    $check = checkDoCatalogo('infra-admin-ativo');

    // Banco vazio: ninguém administra o sistema.
    expect($check->executar()->status)->toBe(ResultadoCheck::FALHA)
        ->and($check->executar()->detalhe)->toContain('administrador');

    User::factory()->create(['admin' => true, 'ativo' => true]);

    expect($check->executar()->status)->toBe(ResultadoCheck::OK);
});

test('administrador INATIVO nao conta: a conta e de quem consegue entrar', function () {
    // Conta desligada não entra no sistema — contá-la faria o check dizer que há
    // quem administre quando não há mais ninguém.
    User::factory()->inativo()->create(['admin' => true]);

    expect(checkDoCatalogo('infra-admin-ativo')->executar()->status)->toBe(ResultadoCheck::FALHA);
});

test('o check do armazenamento nao escreve nada no load; a escrita real e a verificacao profunda', function () {
    $check = checkDoCatalogo('infra-armazenamento-gravavel');

    expect($check->executar()->status)->toBe(ResultadoCheck::OK)
        ->and($check->temVerificacaoProfunda())->toBeTrue()
        ->and($check->executarProfunda()->status)->toBe(ResultadoCheck::OK);
});

test('lei: a atividade do ambulante FLIPA — sem nenhuma em uso, o cadastro para e a tela acusa', function () {
    /*
     * O caso que o monitoramento existe para pegar: inativar a última atividade
     * não avisa ninguém, e dias depois o cadastro de ambulante simplesmente
     * não salva — com uma recusa de campo que ninguém liga a uma decisão tomada
     * em outra tela.
     *
     * É FALHA, e não aviso: sem atividade em uso ninguém é cadastrado, e é do
     * cadastro que a fiscalização parte.
     */
    $check = checkDoCatalogo('parametrizacao-atividade-ativa');

    // Banco vazio: não há o que escolher.
    expect($check->executar()->status)->toBe(ResultadoCheck::FALHA);

    $atividade = AtividadeAmbulante::create(['nome' => 'Alimentos preparados', 'ativo' => true]);

    expect($check->executar()->status)->toBe(ResultadoCheck::OK);

    // Inativar a última é o caminho realista — e o check tem de acusar isso, não
    // só a tabela vazia: "existe cadastrada" não é "pode ser escolhida".
    $atividade->update(['ativo' => false]);

    expect($check->executar()->status)->toBe(ResultadoCheck::FALHA)
        ->and($check->executar()->detalhe)->toContain('fora de uso');
});

test('lei: o tipo de infracao FLIPA, e como AVISO — nada esta parado hoje', function () {
    /*
     * Severidade honesta (a regra da tela): o enquadramento em rua é de entrega
     * futura, então lista vazia aqui não quebra fluxo nenhum HOJE. Marcar
     * vermelho o que não parou ensina a ignorar o vermelho — e aí o dia em que um
     * deles for de verdade, ninguém repara.
     */
    $check = checkDoCatalogo('parametrizacao-tipo-infracao-ativo');

    expect($check->executar()->status)->toBe(ResultadoCheck::AVISO);

    TipoInfracao::create(['nome' => 'Área não autorizada', 'ativo' => true]);

    expect($check->executar()->status)->toBe(ResultadoCheck::OK);
});

test('as quatro listas SEM consumidor ficam fora do monitoramento', function () {
    /*
     * Teste-LEI do critério de admissão. Unidade de medida, tipo de operação,
     * origem de operação e motivo de recusa não são consumidas por tela nenhuma
     * nesta entrega: um check para cada uma seria verde permanente, e é com
     * fileira de verdes que um vermelho passa despercebido. Cada uma entra JUNTO
     * com a tela que a consumir.
     */
    $ids = collect(MonitorParametrizacoes::todosOsChecks())
        ->map(fn (CheckParametrizacao $c): string => $c->id)
        ->all();

    foreach (['unidade', 'operacao', 'origem', 'recusa'] as $semConsumidor) {
        expect($ids)->not->toContain("parametrizacao-{$semConsumidor}-ativo");
    }
});

test('a tela resume o estado do sistema por modulo', function () {
    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.monitoramento.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->component('Retaguarda/Sistema/MonitoramentoDeParametrizacoes')
                ->has('modulos', 2)
                ->where('modulos.0.modulo', 'Infraestrutura e ambiente')
                ->has('modulos.0.checks', 2)
                ->where('modulos.1.modulo', 'Parametrização da fiscalização')
                ->has('modulos.1.checks', 2)
                // A data do carimbo é BR: nada de ISO à vista do usuário. O que
                // se afirma é o FORMATO, e não o minuto — prender o teste ao
                // relógio o faria falhar sozinho na virada do minuto.
                ->where('verificadoEm', fn (string $v) => (bool) preg_match('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#', $v));
        });
});

test('a verificacao profunda so roda sob demanda e devolve o resultado por id', function () {
    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('retaguarda.monitoramento.profundo'))
        ->assertOk()
        ->assertJsonStructure(['resultados' => ['infra-armazenamento-gravavel' => ['id', 'status', 'detalhe']]]);
});

test('quem nao tem a tela concedida e mandado de volta dizendo o porque', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $fiscal = User::factory()->create(['admin' => false]);
    $fiscal->setores()->attach(Setor::create(['slug' => 'fiscal', 'nome' => 'Fiscal']));

    $this->actingAs($fiscal->fresh())->get('/retaguarda/monitoramento')
        ->assertRedirect('/retaguarda/inicio')
        ->assertSessionHas('flash.erro');
});

test('concedida na matriz, a tela abre para quem nao e administrador', function () {
    config(['retaguarda.permissao_enforce' => 'block']);

    $chefe = User::factory()->create(['admin' => false]);
    $chefe->setores()->attach(Setor::create(['slug' => 'chefe-de-setor', 'nome' => 'Chefe de Setor']));
    PermissaoSetor::create(['setor' => 'chefe-de-setor', 'slug' => 'monitoramento', 'visivel' => true]);

    $this->actingAs($chefe->fresh())->get('/retaguarda/monitoramento')->assertOk();
});
