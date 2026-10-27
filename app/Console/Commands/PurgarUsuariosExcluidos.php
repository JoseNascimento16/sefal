<?php

namespace App\Console\Commands;

use App\Http\Controllers\Retaguarda\UsuariosController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * Esvazia a lixeira da tela de Usuários: remove DE VEZ as contas excluídas há
 * mais de {@see UsuariosController::DIAS_RETENCAO} dias.
 *
 * Com uma exceção deliberada: a conta que tem HISTÓRICO (trâmite, vistoria,
 * decisão, operação, concessão de acesso) fica. As chaves que apontam para ela
 * são "anular ao apagar" — removê-la deixaria o trâmite e a decisão sem autor,
 * e a da vistoria nem deixaria apagar. Ela segue excluída, sem login, com o nome
 * preservado no que fez.
 *
 * Agendado para rodar todo dia de madrugada (`routes/console.php`).
 */
class PurgarUsuariosExcluidos extends Command
{
    protected $signature = 'sefal:purgar-usuarios-excluidos {--simular : Lista o que seria removido, sem remover}';

    protected $description = 'Remove de vez as contas excluídas há mais de 3 dias que não têm histórico.';

    public function handle(): int
    {
        $corte = Date::now()->subDays(UsuariosController::DIAS_RETENCAO);

        $vencidas = User::onlyTrashed()->where('deleted_at', '<=', $corte)->get();
        [$comHistorico, $removiveis] = $vencidas->partition(fn (User $u): bool => $u->temHistorico());

        foreach ($comHistorico as $u) {
            $this->line("  • mantida (tem histórico): {$u->login} — {$u->name}");
        }

        if ($removiveis->isEmpty()) {
            $this->info('Nenhuma conta a remover.');

            return self::SUCCESS;
        }

        if ($this->option('simular')) {
            foreach ($removiveis as $u) {
                $this->line("  ✘ seria removida: {$u->login} — {$u->name}");
            }

            $this->warn('Simulação: nada foi removido.');

            return self::SUCCESS;
        }

        foreach ($removiveis as $u) {
            // Os vínculos de setor e de equipe caem junto (chaves em cascata).
            $u->forceDelete();
            $this->line("  ✘ removida: {$u->login} — {$u->name}");
        }

        $this->info($removiveis->count() === 1
            ? '1 conta removida de vez.'
            : $removiveis->count().' contas removidas de vez.');

        return self::SUCCESS;
    }
}
