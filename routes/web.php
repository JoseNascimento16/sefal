<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Tela inicial da Retaguarda: é para cá que o login joga quem entrou. Por ora é
// uma página de espera — o layout de verdade chega com o menu da Retaguarda.
Route::middleware(['auth'])->group(function () {
    Route::inertia('retaguarda/inicio', 'Retaguarda/Inicio')->name('retaguarda.inicio');
});

require __DIR__.'/settings.php';
