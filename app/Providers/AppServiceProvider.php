<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configurarConexaoDeBanco();
    }

    /**
     * Seletor de banco. `DB_DRIVER` (config `database.seletor`) decide se o app fala com o
     * Oracle real (via extensão oci8) ou com o SQLite local de desenvolvimento:
     *   - oracle : força o Oracle (exige oci8 + Oracle Instant Client + rede/credenciais).
     *   - sqlite : força database/fiscalizacao_dev.sqlite — útil offline / sem acesso ao Oracle.
     *   - auto   : (padrão) usa o Oracle quando há oci8 E host configurado; senão cai no SQLite.
     *
     * A checagem do host no modo automático evita que uma máquina com oci8 instalado, mas
     * sem credenciais no .env, tente abrir uma conexão Oracle vazia. O caminho do arquivo
     * SQLite é decidido em config/database.php — nunca aqui, para não atropelar o
     * `:memory:` que a suíte de testes usa.
     */
    protected function configurarConexaoDeBanco(): void
    {
        $driver = strtolower(trim((string) config('database.seletor')));

        $usarOracle = match ($driver) {
            'oracle' => true,
            'sqlite' => false,
            default => extension_loaded('oci8')
                && trim((string) config('database.connections.oracle.host')) !== '',
        };

        config(['database.default' => $usarOracle ? 'oracle' : 'sqlite']);

        if ($usarOracle || $this->app->runningUnitTests()) {
            return;
        }

        // Num clone novo o arquivo do SQLite de desenvolvimento ainda não existe, e o
        // conector do Laravel aborta com "Database file ... does not exist" antes de o
        // dev conseguir rodar o migrate.
        $arquivo = (string) config('database.connections.sqlite.database');

        if (str_ends_with($arquivo, '.sqlite') && ! file_exists($arquivo)) {
            touch($arquivo);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
