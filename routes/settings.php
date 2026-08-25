<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Meu Perfil (Retaguarda)
|--------------------------------------------------------------------------
|
| É a área da própria conta: dados, senha e aparência. Herda as telas de
| "settings" do starter kit, agora dentro da Retaguarda e em português.
|
| O que NÃO está aqui, de propósito:
|
| - `verified`: não há verificação de e-mail neste sistema (ver
|   `config/fortify.php`). Exigir o middleware travaria a própria tela de
|   senha para todo mundo, porque ninguém nunca confirma e-mail nenhum.
| - autoexclusão de conta: quem cria a conta é a administração, e quem a
|   encerra desliga o acesso com a marca `ativo` — apagar a linha levaria
|   embora o histórico de trabalho ligado a ela.
|
*/

Route::middleware(['auth'])->group(function () {
    Route::get('retaguarda/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('retaguarda/perfil', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('retaguarda/perfil/senha', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('retaguarda/perfil/senha', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('retaguarda/perfil/aparencia', 'settings/appearance')->name('appearance.edit');
});
