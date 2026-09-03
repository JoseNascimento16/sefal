<?php

use App\Support\AlvoSqlite;

/**
 * O crivo do alvo do SQLite.
 *
 * O defeito que estes testes travam já aconteceu: com o `.env` apontado para o
 * Oracle (a configuração padrão da equipe, onde `DB_DATABASE` é o SID `SEFAL`),
 * subir o app com `DB_DRIVER=sqlite` abria um banco NOVO e VAZIO num arquivo
 * chamado `SEFAL` na raiz do projeto. O app meio funcionava — login abria, e as
 * telas iam estourando "no such table" uma a uma — enquanto o `migrate` criava
 * tabela naquele arquivo perdido e o banco de desenvolvimento ficava para trás.
 */
it('recusa nome de banco Oracle como arquivo SQLite', function () {
    $padrao = database_path(AlvoSqlite::PADRAO);

    // O SID do projeto. É o caso real que quebrou.
    expect(AlvoSqlite::arquivo('SEFAL', $padrao))->toBe($padrao);

    // Qualquer nome de serviço/instância cai no mesmo crivo.
    expect(AlvoSqlite::arquivo('ORCL', $padrao))->toBe($padrao);
    expect(AlvoSqlite::arquivo('WEBRUN_CODECON', $padrao))->toBe($padrao);

    // Vazio e só espaço significam "não configurado".
    expect(AlvoSqlite::arquivo('', $padrao))->toBe($padrao);
    expect(AlvoSqlite::arquivo('   ', $padrao))->toBe($padrao);
    expect(AlvoSqlite::arquivo(null, $padrao))->toBe($padrao);
});

it('preserva os alvos que são de fato SQLite', function () {
    $padrao = database_path(AlvoSqlite::PADRAO);

    /*
     * `:memory:` é o que o phpunit.xml fixa: se o crivo o descartasse, a suíte
     * inteira passaria a rodar contra o arquivo de desenvolvimento — gravando
     * nele e mentindo sobre isolamento.
     */
    expect(AlvoSqlite::arquivo(':memory:', $padrao))->toBe(':memory:');

    expect(AlvoSqlite::arquivo('/var/www/html/database/sefal_demo.sqlite', $padrao))
        ->toBe('/var/www/html/database/sefal_demo.sqlite');
    expect(AlvoSqlite::arquivo('C:\\dev\\x\\database\\dev.sqlite3', $padrao))
        ->toBe('C:\\dev\\x\\database\\dev.sqlite3');
    expect(AlvoSqlite::arquivo('teste.db', $padrao))->toBe('teste.db');
});

/**
 * A suíte não pode nunca escrever no banco de desenvolvimento — e é o
 * phpunit.xml quem garante isso, via `:memory:`. O teste olha o valor EFETIVO da
 * conexão, e não o do arquivo de configuração: é o efeito que interessa.
 */
it('roda a propria suite em memoria', function () {
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
});

/**
 * O crivo tem de estar LIGADO no config, não apenas existir.
 *
 * Este é o teste que morre sem a correção: ele avalia o próprio
 * `config/database.php` com o ambiente da equipe (o `.env` do Oracle, onde
 * `DB_DATABASE` é o SID) e exige que a conexão SQLite não aponte para ele. Com a
 * expressão antiga (`env('DB_DATABASE') ?: ...`) o valor voltava como `SEFAL`.
 */
it('nao deixa o SID do Oracle virar o arquivo do SQLite no config', function () {
    $anterior = getenv('DB_DATABASE');

    putenv('DB_DATABASE=SEFAL');
    $_ENV['DB_DATABASE'] = 'SEFAL';
    $_SERVER['DB_DATABASE'] = 'SEFAL';

    try {
        $config = require base_path('config/database.php');

        expect($config['connections']['sqlite']['database'])
            ->toBe(database_path(AlvoSqlite::PADRAO));
    } finally {
        if ($anterior === false) {
            putenv('DB_DATABASE');
            unset($_ENV['DB_DATABASE'], $_SERVER['DB_DATABASE']);
        } else {
            putenv("DB_DATABASE={$anterior}");
            $_ENV['DB_DATABASE'] = $anterior;
            $_SERVER['DB_DATABASE'] = $anterior;
        }
    }
});
