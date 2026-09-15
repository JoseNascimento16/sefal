<?php

namespace App\Console\Commands;

use App\Models\Demanda;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

/**
 * Deixa a demonstração pronta para ser percorrida — e só quando ela está vazia.
 *
 * ## Por que este comando existe
 *
 * O ambiente de demonstração roda com filesystem EFÊMERO: a cada boot o banco
 * volta ao arquivo versionado, e o que foi clicado na véspera desaparece. Isso é
 * desejado — a demo sempre abre no mesmo estado. O que NÃO era desejado é o que
 * aconteceu: o arquivo versionado estava vazio, o boot rodava `migrate` (criando
 * as tabelas) e mais nada. O sistema subia íntegro e sem um único registro.
 *
 * O sintoma não pareceu falta de dado: a tela abria normalmente, e a varredura de
 * repetições dizia "nenhuma repetição encontrada" — resposta correta para uma
 * fila vazia, e indistinguível de uma funcionalidade quebrada.
 *
 * ## Por que é IDEMPOTENTE e não destrutivo
 *
 * Ele semeia apenas o que falta e nunca apaga: num ambiente onde alguém pode
 * estar no meio de uma apresentação, um comando de boot que zera a base é pior
 * que a base vazia. Se já há demanda, ele não faz nada e diz por quê.
 *
 * ⚠️ Não roda em produção — o {@see DemonstracaoSeeder} planta cadastro
 * inventado, e lá os ambulantes vêm do SGCI e as demandas, da integração.
 */
class PrepararDemonstracao extends Command
{
    protected $signature = 'sefal:preparar-demonstracao
                            {--senha= : Senha do administrador de demonstração (padrão: sefal123)}';

    protected $description = 'Semeia sistema e demonstração num banco vazio, sem apagar nada do que já existe.';

    public function handle(): int
    {
        if (App::environment('production')) {
            $this->components->error('A demonstração planta cadastros inventados e não roda em produção.');

            return self::FAILURE;
        }

        // O sistema é semeado SEMPRE: setores, permissões, listas de escolha e a
        // estrutura de áreas e equipes são idempotentes e é o que faz o login e a
        // guarda de acesso existirem. Sem isso não há nem por onde entrar.
        $this->components->task('Estrutura do sistema', function () {
            Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

            return true;
        });

        if (Demanda::query()->exists()) {
            $this->components->info(
                'A demonstração já tem demandas — nada semeado. '
                .'Para refazê-la do zero, apague o banco e rode de novo.',
            );

            return self::SUCCESS;
        }

        $this->components->task('Cidade de demonstração', function () {
            Artisan::call('db:seed', ['--class' => DemonstracaoSeeder::class, '--force' => true]);

            return true;
        });

        $this->garantirAdministrador((string) ($this->option('senha') ?: 'sefal123'));

        $this->components->info(sprintf(
            '%d demandas na base, %d delas aguardando pré-triagem.',
            Demanda::count(),
            Demanda::emPreTriagem()->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Uma porta de entrada conhecida.
     *
     * Numa demonstração, banco íntegro e ninguém conseguindo entrar dá no mesmo
     * que banco vazio. A senha é parâmetro para o ambiente decidir — e o padrão é
     * público de propósito: é uma demo, não um sistema com dado de gente real.
     */
    private function garantirAdministrador(string $senha): void
    {
        $admin = User::firstOrNew(['login' => 'admin']);

        $admin->fill([
            'name' => $admin->name ?: 'Administrador da demonstração',
            'email' => $admin->email ?: 'admin@sefal.demo',
            'admin' => true,
            'ativo' => true,
        ]);

        // O cast `hashed` do model cifra na atribuição — passar por `Hash::make`
        // aqui seria cifrar duas vezes. E a senha só é (re)definida quando não há
        // uma: numa demo já em uso, trocar a senha no boot derruba quem está
        // dentro.
        if ($admin->exists === false || ($admin->password ?? '') === '') {
            $admin->password = $senha;
        }

        $admin->save();

        $this->components->twoColumnDetail('Acesso', 'login <fg=yellow>admin</>');
    }
}
