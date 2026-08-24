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

        // O papel de administrador tem duas portas: a flag `admin` na conta e o
        // vínculo com o setor `administrador`. O comando SEMPRE alinha a flag ao
        // estado final do vínculo — inclusive quando não havia vínculo nenhum e só
        // a flag segurava o papel — e a saída relata as DUAS coisas que ele fez.
        // Papel de administrador nunca cai (nem sobe) em silêncio.
        if ($this->option('remover')) {
            $removidos = $user->setores()->detach($setor->id);
            $feito = [$removidos > 0 ? 'vínculo removido' : 'não havia vínculo'];

            if ($slug === 'administrador') {
                $user->update(['admin' => false]);
                $feito[] = $removidos > 0
                    ? 'flag de administrador desligada'
                    : 'flag de administrador desligada por alinhamento';
            }
        } else {
            $resultado = $user->setores()->syncWithoutDetaching([$setor->id]);
            $feito = [$resultado['attached'] === [] ? 'vínculo já existia' : 'vínculo criado'];

            if ($slug === 'administrador') {
                $user->update(['admin' => true]);
                $feito[] = 'flag de administrador ligada';
            }
        }

        $this->info("Setor '{$slug}' em '{$user->login}' ({$user->name}): ".implode('; ', $feito).'.');

        $atuais = $user->setores()->pluck('slug')->all();
        $this->line('Setores atuais: '.($atuais === [] ? '(nenhum)' : implode(', ', $atuais)));

        return self::SUCCESS;
    }
}
