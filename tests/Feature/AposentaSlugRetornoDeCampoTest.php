<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| A migration que aposenta o slug `retorno-de-campo`
|--------------------------------------------------------------------------
|
| Ela mexe em ACESSO, e o que ela evita é uma falha silenciosa: quem tinha a tela
| antiga concedida perderia o acesso sem ninguém notar, porque o slug deixou de
| casar com tela nenhuma quando a rota mudou de caminho.
|
| A suíte roda com o banco já migrado, então o efeito de `up()` já está aplicado
| quando estes testes começam. O que se testa é o EFEITO, e não a execução — e
| `up()` é reexecutado à mão onde o cenário precisa nascer sujo (as duas linhas
| coexistindo), porque é justamente esse cenário que estoura na unicidade se a
| mesclagem estiver errada.
|
| ⚠️ A migration é IDEMPOTENTE de propósito: no OKD o `migrate` é passo manual
| depois do deploy, e quem executa à mão executa duas vezes um dia.
|
*/

/** A migration desta entrega, instanciada para ser reexecutada. */
function migrationDoSlug(): object
{
    return require database_path(
        'migrations/2026_09_09_090000_aposenta_o_slug_retorno_de_campo.php',
    );
}

/** @return array<string, mixed>|null */
function concessao(string $setor, string $slug): ?array
{
    $linha = DB::table('permissoes_setor')->where('setor', $setor)->where('slug', $slug)->first();

    return $linha === null ? null : (array) $linha;
}

test('a concessao que existia SO na tela antiga e RENOMEADA, preservando o acesso', function () {
    /*
     * O caso do Coordenador antes desta entrega: ele tinha `retorno-de-campo` e não
     * tinha `fiscalizacoes`. Sem a renomeação ele perderia a fila que acompanhava —
     * em silêncio, que é o pior jeito de perder acesso.
     *
     * O cenário é montado à mão porque o banco de teste já nasce migrado: apagamos
     * a linha nova e recriamos a antiga, com uma decisão RECONHECÍVEL (só "vê"), e
     * exigimos que ela chegue do outro lado inteira.
     */
    DB::table('permissoes_setor')->where('setor', 'coordenador')->delete();

    DB::table('permissoes_setor')->insert([
        'setor' => 'coordenador',
        'slug' => 'retorno-de-campo',
        'visivel' => true,
        'habilitado' => false,
        'apenas_leitura' => true,
        'incluir' => false,
        'excluir' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    migrationDoSlug()->up();

    expect(concessao('coordenador', 'retorno-de-campo'))->toBeNull()
        ->and(concessao('coordenador', 'fiscalizacoes'))->not->toBeNull()
        // A DECISÃO veio com ela: renomear é preservar o que se decidiu, não
        // recriar com o pacote de fábrica.
        ->and((bool) concessao('coordenador', 'fiscalizacoes')['apenas_leitura'])->toBeTrue()
        ->and((bool) concessao('coordenador', 'fiscalizacoes')['habilitado'])->toBeFalse();
});

test('o setor que tinha as DUAS perde a duplicata e MANTEM a concessao da tela que ficou', function () {
    /*
     * O caso do Chefe de Setor: ele era concedido nas duas telas. O par (setor,
     * slug) é único, então renomear por cima estouraria e mataria a migration no
     * meio — a linha antiga é apagada, e fica a que a tela unificada consulta.
     *
     * Este é o cenário que prova a idempotência: rodar `up()` de novo num banco em
     * que a migration já passou não pode explodir.
     */
    DB::table('permissoes_setor')->where('setor', 'chefe-de-setor')->where('slug', 'fiscalizacoes')->delete();

    DB::table('permissoes_setor')->insert([
        [
            'setor' => 'chefe-de-setor',
            'slug' => 'fiscalizacoes',
            'visivel' => true,
            'habilitado' => true,
            'apenas_leitura' => false,
            'incluir' => false,
            'excluir' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'setor' => 'chefe-de-setor',
            'slug' => 'retorno-de-campo',
            'visivel' => true,
            'habilitado' => true,
            'apenas_leitura' => false,
            'incluir' => false,
            'excluir' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    migrationDoSlug()->up();
    // De novo: idempotente.
    migrationDoSlug()->up();

    expect(concessao('chefe-de-setor', 'retorno-de-campo'))->toBeNull()
        ->and(concessao('chefe-de-setor', 'fiscalizacoes'))->not->toBeNull()
        ->and((bool) concessao('chefe-de-setor', 'fiscalizacoes')['habilitado'])->toBeTrue();
});

test('o Coordenador ganha o Cadastro de Operacao em APENAS LEITURA', function () {
    /*
     * Concessão NOVA, e por isso ela precisa da migration: a lista `setores` do menu
     * é a SEMENTE, aplicada uma vez — num banco já semeado, acrescentar um setor lá
     * não cria linha nenhuma, e o Coordenador abriria a tela e seria mandado de
     * volta por uma decisão que já havia sido tomada a favor dele.
     *
     * ⚠️ E ela é APENAS LEITURA: quem tria precisa saber que operação existe;
     * montar operação é de quem responde pela área.
     */
    DB::table('permissoes_setor')->where('setor', 'coordenador')->where('slug', 'operacoes')->delete();

    migrationDoSlug()->up();

    $linha = concessao('coordenador', 'operacoes');

    expect($linha)->not->toBeNull()
        ->and((bool) $linha['visivel'])->toBeTrue()
        ->and((bool) $linha['apenas_leitura'])->toBeTrue()
        ->and((bool) $linha['habilitado'])->toBeFalse()
        ->and((bool) $linha['incluir'])->toBeFalse()
        ->and((bool) $linha['excluir'])->toBeFalse()
        // As flags saem do MESMO lugar que a semente usaria: escritas à mão na
        // migration, um ajuste na declaração da tela deixaria as duas discordando.
        ->and(array_map(
            static fn (mixed $v): bool => (bool) $v,
            array_intersect_key($linha, CatalogoFuncionalidades::acoesSemente('operacoes', 'coordenador')),
        ))
        ->toBe(CatalogoFuncionalidades::acoesSemente('operacoes', 'coordenador'));
});

test('a migration NAO passa por cima da decisao de quem administra', function () {
    /*
     * Migration que sobrescreve decisão de gerente é migration que apaga trabalho.
     * Se alguém já tirou a tela do Coordenador na tela do Modo Gerente, rodar o
     * `migrate` de novo não pode devolvê-la.
     */
    DB::table('permissoes_setor')->where('setor', 'coordenador')->where('slug', 'operacoes')->delete();

    DB::table('permissoes_setor')->insert([
        'setor' => 'coordenador',
        'slug' => 'operacoes',
        'visivel' => false,
        'habilitado' => false,
        'apenas_leitura' => true,
        'incluir' => false,
        'excluir' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    migrationDoSlug()->up();

    expect((bool) concessao('coordenador', 'operacoes')['visivel'])->toBeFalse();
});

test('o log de permissoes NAO e reescrito — auditoria nao se maquia', function () {
    /*
     * O log registra ATOS: "em tal dia, tal pessoa mudou tal coisa". Naquele dia a
     * tela se chamava `retorno-de-campo`. Reescrever registro de auditoria para
     * ficar bonito é adulterar a auditoria.
     */
    DB::table('permissoes_log')->insert([
        'user_id' => null,
        'user_nome' => 'Quem administrava naquele dia',
        'funcionalidade_slug' => 'retorno-de-campo',
        'descricao' => 'Concedeu "Vê" ao Coordenador em Retorno de Campo.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    migrationDoSlug()->up();

    expect(DB::table('permissoes_log')->where('funcionalidade_slug', 'retorno-de-campo')->count())
        ->toBe(1);
});
