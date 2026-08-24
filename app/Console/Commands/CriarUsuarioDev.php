<?php

namespace App\Console\Commands;

use App\Models\Setor;
use App\Models\User;
use Database\Seeders\SetoresSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CriarUsuarioDev extends Command
{
    protected $signature = 'fp:criar-usuario-dev
                            {--login=admin : Matrícula de acesso (default: admin)}
                            {--senha=admin123 : Senha (default: admin123)}
                            {--nome=Administrador Dev : Nome completo}
                            {--email= : E-mail (default: <login>@semop.local)}
                            {--setor=administrador : Setores separados por vírgula (default: administrador)}';

    protected $description = 'Cria/atualiza um usuário local com senha conhecida para testar o login da Retaguarda em desenvolvimento.';

    public function handle(): int
    {
        $login = trim((string) $this->option('login'));
        $senha = (string) $this->option('senha');
        $nome = (string) $this->option('nome');
        $email = strtolower(trim((string) ($this->option('email') ?: "{$login}@semop.local")));
        $slugs = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('setor')))));

        $catalogo = array_keys(config('retaguarda.setores', []));
        foreach ($slugs as $slug) {
            if (! in_array($slug, $catalogo, true)) {
                $this->error("Setor inválido: '{$slug}'. Use um de: ".implode(', ', $catalogo));

                return self::FAILURE;
            }
        }

        // Comando de bootstrap: garante o catálogo antes de vincular, para funcionar
        // num banco recém-migrado. O seeder é idempotente e é a fonte única do catálogo.
        $this->callSilent('db:seed', ['--class' => SetoresSeeder::class, '--force' => true]);

        $user = User::updateOrCreate(
            ['login' => $login],
            [
                'name' => $nome,
                'email' => $email,
                'password' => Hash::make($senha),
                'admin' => in_array('administrador', $slugs, true),
                'ativo' => true,
            ],
        );

        // `sync` deixa o usuário exatamente com os setores pedidos — o comando é de
        // bootstrap, então rodar de novo corrige o vínculo em vez de acumular.
        $user->setores()->sync(Setor::whereIn('slug', $slugs)->pluck('id'));

        $this->info("Usuário '{$login}' pronto para login.");
        $this->line("  Nome:    {$nome}");
        $this->line("  E-mail:  {$email}");
        $this->line("  Senha:   {$senha}");
        $this->line('  Setores: '.($slugs === [] ? '(nenhum)' : implode(', ', $slugs)));

        return self::SUCCESS;
    }
}
