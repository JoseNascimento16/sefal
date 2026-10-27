<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * A TRAVA dos comandos que APAGAM o banco: `migrate:fresh`, `migrate:refresh`,
 * `migrate:reset`, `migrate:rollback` e `db:wipe`.
 *
 * Fechada em TODOS os ambientes. Antes ela só valia em produção, e um
 * `migrate:fresh` rodado por descuido apagou o banco de desenvolvimento
 * (13/10/2026) — o mesmo comando, com o `.env` apontando para o Oracle de
 * homologação, teria apagado a homologação.
 *
 * Abre só em dois casos:
 *  - na suíte de testes (`testing`): o `RefreshDatabase` recria o banco a cada
 *    teste, e o `phpunit.xml` fixa SQLite em memória;
 *  - por decisão do dono, no SQLite LOCAL: `APP_ENV=local`, conexão SQLite,
 *    `LIBERAR_COMANDOS_DESTRUTIVOS=true` no ambiente e sem `--database`
 *    apontando para outra conexão. Oracle nunca abre.
 */
final class TravaDeComandosDestrutivos
{
    /**
     * Fecha ou abre a trava para este processo, conforme o ambiente.
     */
    public static function aplicar(): void
    {
        DB::prohibitDestructiveCommands(! self::liberadosAgora());
    }

    /**
     * Os comandos destrutivos podem rodar neste processo?
     */
    public static function liberadosAgora(): bool
    {
        $conexao = (string) config('database.default');

        return self::liberados(
            (string) app()->environment(),
            (string) config("database.connections.{$conexao}.driver"),
            (bool) config('database.liberar_comandos_destrutivos'),
            self::pediuOutraConexao(),
        );
    }

    /**
     * A regra, sem estado: testes sempre; fora deles, só o SQLite local com a
     * liberação explícita do dono.
     */
    public static function liberados(string $ambiente, string $driver, bool $liberacaoDoDono, bool $outraConexaoPedida): bool
    {
        if ($ambiente === 'testing') {
            return true;
        }

        return $ambiente === 'local'
            && $driver === 'sqlite'
            && $liberacaoDoDono
            && ! $outraConexaoPedida;
    }

    /**
     * `--database=oracle` faria o comando mirar outra conexão que não a padrão
     * — a liberação vale só para o SQLite local, então ela não vale aqui.
     */
    private static function pediuOutraConexao(): bool
    {
        foreach ((array) ($_SERVER['argv'] ?? []) as $argumento) {
            if (str_starts_with((string) $argumento, '--database')) {
                return true;
            }
        }

        return false;
    }
}
