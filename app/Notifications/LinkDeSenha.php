<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * O e-mail com o link para escolher a senha — em DOIS tons, um texto só.
 *
 *  - CONVITE: a conta acabou de ser criada na tela de Usuários (ou o
 *    administrador reenviou), e a pessoa ainda não tem senha;
 *  - REDEFINIÇÃO: a própria pessoa pediu em "Esqueci minha senha".
 *
 * O link é o mesmo nos dois casos — a tela de redefinição do Fortify, com o
 * token do broker de senhas —, e por isso é UMA notificação: duas cópias do
 * mesmo e-mail divergiriam no primeiro ajuste de texto.
 *
 * Substitui o e-mail padrão do framework, que chegava em inglês.
 */
class LinkDeSenha extends Notification
{
    public const CONVITE = 'convite';

    public const REDEFINICAO = 'redefinicao';

    public function __construct(
        public readonly string $token,
        public readonly string $tipo = self::REDEFINICAO,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $validade = $minutos >= 60 && $minutos % 60 === 0
            ? ($minutos === 60 ? '1 hora' : ($minutos / 60).' horas')
            : $minutos.' minutos';

        $mensagem = (new MailMessage)->greeting('Olá, '.$notifiable->name.'!');

        if ($this->tipo === self::CONVITE) {
            return $mensagem
                ->subject('SEFAL — defina a sua senha de acesso')
                ->line('Uma conta foi criada para você na Retaguarda do SEFAL.')
                ->line('A sua matrícula de acesso é: **'.$notifiable->login.'**.')
                ->line('Para entrar pela primeira vez, escolha a sua senha pelo botão abaixo.')
                ->action('Definir minha senha', $url)
                ->line("O link vale por {$validade}. Se ele expirar, use \"Esqueci minha senha\" na tela de entrada.")
                ->salutation('SEMOP · SEFAL — Prefeitura de Salvador');
        }

        return $mensagem
            ->subject('SEFAL — redefinição de senha')
            ->line('Recebemos um pedido para redefinir a senha da sua conta na Retaguarda do SEFAL.')
            ->action('Escolher uma nova senha', $url)
            ->line("O link vale por {$validade}.")
            ->line('Se não foi você quem pediu, ignore este e-mail: a sua senha continua a mesma.')
            ->salutation('SEMOP · SEFAL — Prefeitura de Salvador');
    }
}
