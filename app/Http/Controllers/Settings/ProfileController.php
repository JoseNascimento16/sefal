<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Meu Perfil" — os dados que a própria pessoa mantém: nome e e-mail.
 *
 * Não há verificação de e-mail (ver `config/fortify.php`) nem autoexclusão de
 * conta: quem abre e quem encerra o acesso de um servidor é a administração.
 */
class ProfileController extends Controller
{
    /**
     * Mostra a tela de dados do usuário.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Atualiza os dados do usuário.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        return to_route('profile.edit')->with('flash.sucesso', 'Dados atualizados.');
    }
}
