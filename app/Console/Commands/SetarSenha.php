<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetarSenha extends Command
{
    protected $signature = 'fp:setar-senha
                            {login : Matrícula do usuário}
                            {senha : Nova senha em texto puro (será hasheada)}';

    protected $description = 'Define manualmente a senha de um usuário — override de administração, sem passar pelo e-mail.';

    public function handle(): int
    {
        $login = (string) $this->argument('login');
        $senha = (string) $this->argument('senha');

        $user = User::porMatricula($login);
        if (! $user) {
            $this->error("Usuário com matrícula '{$login}' não encontrado.");

            return self::FAILURE;
        }

        if (mb_strlen($senha) < 8) {
            $this->error('Senha muito curta (mínimo 8 caracteres).');

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($senha)])->save();

        $this->info("Senha definida para '{$user->login}' ({$user->name}).");

        return self::SUCCESS;
    }
}
