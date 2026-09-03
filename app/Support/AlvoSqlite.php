<?php

namespace App\Support;

/**
 * Qual arquivo a conexão SQLite deve abrir.
 *
 * ── Por que isto não é uma linha no config ──────────────────────────────────
 * `DB_DATABASE` é do ORACLE: lá o valor é o SID/serviço (neste projeto, `SEFAL`).
 * A conexão `sqlite` também lia essa variável, apostando que ela "costuma vir
 * vazia no .env" — e a aposta é falsa justamente na configuração PADRÃO da
 * equipe, que é o Oracle real. Quem tem credencial no `.env` e cai no seletor
 * `DB_DRIVER=sqlite` (offline, sem VPN) recebia um banco NOVO e VAZIO num
 * arquivo chamado `SEFAL` na raiz do projeto.
 *
 * O estrago não é o arquivo: é que o app meio funciona. A tela de login abre,
 * o que já foi migrado ali responde, e o resto estoura "no such table" tela por
 * tela — enquanto o `migrate` cria as tabelas nesse arquivo perdido e o banco de
 * desenvolvimento de verdade fica para trás, sem nada acusando. Foi o que
 * aconteceu: o `SEFAL` da raiz tem a tabela `permissionarios`, de antes do
 * rename, e por isso `ambulantes` não existia.
 *
 * O crivo abaixo aceita `DB_DATABASE` só quando ele nomeia de fato um alvo
 * SQLite — `:memory:` (que o phpunit.xml fixa, e a suíte depende disso) ou um
 * arquivo `.sqlite`/`.sqlite3`/`.db`. Qualquer outra coisa é nome de banco
 * Oracle, e aí vale o banco de desenvolvimento.
 *
 * ⚠️ Método estático, e não closure no config: closure quebra o
 * `php artisan config:cache` ("configuration files are not serializable").
 */
class AlvoSqlite
{
    /** O arquivo de desenvolvimento, quando `DB_DATABASE` não serve. */
    public const PADRAO = 'fiscalizacao_dev.sqlite';

    public static function arquivo(?string $configurado, string $padrao): string
    {
        $alvo = trim((string) $configurado);

        if ($alvo === '') {
            return $padrao;
        }

        return self::ehAlvoSqlite($alvo) ? $alvo : $padrao;
    }

    /**
     * `:memory:` ou caminho de arquivo SQLite — o resto é nome de banco de outro
     * gerenciador.
     */
    public static function ehAlvoSqlite(string $alvo): bool
    {
        return $alvo === ':memory:'
            || preg_match('/\.(sqlite3?|db)$/i', $alvo) === 1;
    }
}
