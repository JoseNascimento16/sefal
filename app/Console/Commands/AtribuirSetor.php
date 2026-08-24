<?php

namespace App\Console\Commands;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Console\Command;

class AtribuirSetor extends Command
{
    protected $signature = 'fp:atribuir-setor
                            {login : Matrícula do usuário}
                            {setor : Código do setor (administrador, fiscal, gestor)}
                            {--remover : Remove o setor em vez de adicionar}';

    protected $description = 'Atribui (ou remove) um setor a um usuário da Retaguarda.';

    public function handle(): int
    {
        $login = (string) $this->argument('login');
        $slug = (string) $this->argument('setor');

        $catalogo = array_keys(config('retaguarda.setores', []));
        if (! in_array($slug, $catalogo, true)) {
            $this->error("Setor inválido: '{$slug}'. Use um de: ".implode(', ', $catalogo));

            return self::FAILURE;
        }

        $user = User::where('login', $login)->first();
        if (! $user) {
            $this->error("Usuário com matrícula '{$login}' não encontrado.");

            return self::FAILURE;
        }

        $setor = Setor::where('slug', $slug)->first();
        if (! $setor) {
            $this->error("Setor '{$slug}' ainda não foi semeado. Rode: php artisan db:seed --class=SetoresSeeder");

            return self::FAILURE;
        }

        if ($this->option('remover')) {
            $removidos = $user->setores()->detach($setor->id);
            // A flag `admin` é a outra porta para o mesmo papel: deixá-la ligada
            // faria o usuário continuar administrador depois de perder o setor.
            if ($slug === 'administrador') {
                $user->update(['admin' => false]);
            }

            $this->info($removidos > 0
                ? "Setor '{$slug}' removido de '{$user->login}' ({$user->name})."
                : "Setor '{$slug}' não estava atribuído a '{$user->login}'.");
        } else {
            $user->setores()->syncWithoutDetaching([$setor->id]);
            if ($slug === 'administrador') {
                $user->update(['admin' => true]);
            }

            $this->info("Setor '{$slug}' atribuído a '{$user->login}' ({$user->name}).");
        }

        $atuais = $user->setores()->pluck('slug')->all();
        $this->line('Setores atuais: '.($atuais === [] ? '(nenhum)' : implode(', ', $atuais)));

        return self::SUCCESS;
    }
}
