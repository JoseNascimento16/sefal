<?php

use App\Http\Controllers\Retaguarda\ModoGerenteController;
use Illuminate\Support\Facades\Route;

/*
 * A raiz não tem conteúdo próprio: este sistema é ferramenta de trabalho, não
 * site. Quem chega pelo endereço nu vai direto para a entrada — e quem já está
 * autenticado é levado do login para a tela inicial pela própria autenticação.
 *
 * O nome `home` fica: é o destino a que o Fortify manda quem sai do sistema.
 */
Route::redirect('/', '/login')->name('home');

Route::middleware(['auth'])->group(function () {
    // Tela inicial da Retaguarda: é para cá que o login joga quem entrou — e é
    // para cá que a guarda de permissão manda quem foi barrado, então ela não
    // pode ser controlada por permissão (fecharia um loop). Ver o cabeçalho de
    // `config/retaguarda_menu.php`.
    Route::inertia('retaguarda/inicio', 'Retaguarda/Inicio')->name('retaguarda.inicio');

    /*
     * Modo Gerente — quem entra onde.
     *
     * O caminho começa com o slug da tela (`modo-gerente`) porque é dele que as
     * guardas deduzem a que tela cada endereço pertence: assim a rota que nascer
     * aqui amanhã já chega protegida, sem ninguém declarar nada.
     */
    Route::prefix('retaguarda/modo-gerente')->name('retaguarda.modo-gerente.')->group(function () {
        Route::get('/', [ModoGerenteController::class, 'index'])->name('index');
        Route::post('/', [ModoGerenteController::class, 'salvar'])->name('salvar');
    });
});

require __DIR__.'/settings.php';
