<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\LinkDeSenha;
use Illuminate\Support\Facades\Password;

/**
 * Manda à pessoa o link para escolher a senha do primeiro acesso.
 *
 * Usa o broker de senhas do framework — o mesmo token e a mesma tela do
 * "Esqueci minha senha" —, só que com o e-mail no tom de CONVITE. Um caminho
 * só: a conta nova, o reenvio pelo botão e a redefinição acabam na mesma tela
 * de escolher senha, que carimba o primeiro acesso ao gravar.
 */
class ConviteDePrimeiroAcesso
{
    public const ENVIADO = 'enviado';

    /** O broker segura um novo envio para a mesma conta por alguns segundos. */
    public const AGUARDE = 'aguarde';

    public const FALHOU = 'falhou';

    /** @return self::ENVIADO|self::AGUARDE|self::FALHOU */
    public static function enviar(User $user): string
    {
        $status = Password::broker()->sendResetLink(
            ['email' => $user->email],
            static function (User $destinatario, string $token): void {
                $destinatario->notify(new LinkDeSenha($token, LinkDeSenha::CONVITE));
            },
        );

        return match ($status) {
            Password::RESET_LINK_SENT => self::ENVIADO,
            Password::RESET_THROTTLED => self::AGUARDE,
            default => self::FALHOU,
        };
    }
}
