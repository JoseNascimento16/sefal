<?php

namespace App\Providers;

use App\Models\PermissaoSetor;
use App\Services\PermissaoService;
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

        /*
         * Uma instância por requisição, e não uma por quem a injeta.
         *
         * Três consumidores perguntam a mesma coisa na mesma requisição — as duas
         * guardas de acesso e a montagem do menu. Com uma instância cada, a
         * memória interna do serviço nunca era aproveitada e a consulta de
         * permissões repetia; com uma só, ela vale de verdade.
         *
         * O contêiner é reconstruído a cada requisição, então não há risco de a
         * memória atravessar requisições. (Sob um servidor persistente — Octane,
         * que este projeto não usa — seria preciso descartá-la entre elas.)
         */
        $this->app->singleton(PermissaoService::class);
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
     * `:memory:` que a suíte de testes usa. Durante os testes o seletor não roda: quem
     * decide o banco é o phpunit.xml.
     */
    protected function configurarConexaoDeBanco(): void
    {
        // A suíte manda no próprio banco: o phpunit.xml fixa sqlite `:memory:` e o seletor não
        // tem voz aqui. Sem esta saída, um dev com DB_DRIVER=oracle no .env (ou `auto` com
        // DB_HOST preenchido — a configuração-alvo do projeto) rodaria os testes contra o
        // Oracle 19c de verdade, criando e derrubando tabelas LRV_.
        if ($this->app->runningUnitTests()) {
            return;
        }

        $driver = strtolower(trim((string) config('database.seletor')));

        $usarOracle = match ($driver) {
            'oracle' => true,
            'sqlite' => false,
            default => extension_loaded('oci8')
                && trim((string) config('database.connections.oracle.host')) !== '',
        };

        config(['database.default' => $usarOracle ? 'oracle' : 'sqlite']);

        if ($usarOracle) {
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

        // A memória do serviço de permissão morre quando a matriz muda. A fiação
        // fica aqui, ao lado da declaração do singleton, para as duas decisões
        // (compartilhar a instância e invalidá-la) serem lidas juntas.
        PermissaoSetor::saved(fn () => app(PermissaoService::class)->esquecer());
        PermissaoSetor::deleted(fn () => app(PermissaoService::class)->esquecer());
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
