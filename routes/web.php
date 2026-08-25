<?php

use Illuminate\Support\Facades\Route;

/*
 * A raiz não tem conteúdo próprio: este sistema é ferramenta de trabalho, não
 * site. Quem chega pelo endereço nu vai direto para a entrada — e quem já está
 * autenticado é levado do login para a tela inicial pela própria autenticação.
 *
 * O nome `home` fica: é o destino a que o Fortify manda quem sai do sistema.
 */
Route::redirect('/', '/login')->name('home');

// Tela inicial da Retaguarda: é para cá que o login joga quem entrou.
Route::middleware(['auth'])->group(function () {
    Route::inertia('retaguarda/inicio', 'Retaguarda/Inicio')->name('retaguarda.inicio');
});

require __DIR__.'/settings.php';
