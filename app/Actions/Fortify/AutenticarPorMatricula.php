<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Autenticação da Retaguarda: entra-se pela matrícula, não pelo e-mail.
 *
 * Toda recusa sai com motivo escrito no campo da matrícula — a lei do projeto é
 * que ninguém fica olhando para uma tela que "não faz nada". O que ela NÃO diz é
 * se a matrícula existe: credencial errada devolve sempre a mesma frase, para o
 * formulário não virar uma lista de quem trabalha aqui.
 */
class AutenticarPorMatricula
{
    /**
     * Recusa deliberadamente ambígua: serve tanto para matrícula inexistente
     * quanto para senha errada.
     */
    public const CREDENCIAL_INVALIDA = 'Matrícula ou senha inválida. Confira os dados e tente novamente.';

    public const USUARIO_INATIVO = 'Usuário inativo — procure o administrador.';

    public function __construct(private readonly LoginRateLimiter $limiter) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request): User
    {
        $matricula = trim((string) $request->input(Fortify::username()));

        // A matrícula é gravada como o administrador digitou ('F1000', 'admin'),
        // mas quem entra não deveria tropeçar em caixa alta/baixa — daí o lower()
        // dos dois lados. A unicidade da coluna é que impede duas matrículas que
        // só diferem na caixa.
        $user = User::query()
            ->whereRaw('lower(login) = ?', [Str::lower($matricula)])
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            // A senha tentada NÃO vai no evento: qualquer ouvinte que registre em
            // log a tentativa gravaria uma senha em texto puro — inclusive a senha
            // certa de outra conta, quando alguém erra a matrícula.
            $this->falhou($matricula, $user);

            $this->recusar($request, self::CREDENCIAL_INVALIDA);
        }

        // A conta existe e a senha confere: aqui já dá para ser específico sem
        // entregar nada a quem está chutando credencial.
        if (! $user->ativo) {
            $this->falhou($matricula, $user);

            $this->recusar($request, self::USUARIO_INATIVO);
        }

        return $user;
    }

    /**
     * Anuncia a tentativa recusada, para quem quiser auditar acessos.
     */
    private function falhou(string $matricula, ?User $user): void
    {
        event(new Failed(config('fortify.guard'), $user, [
            Fortify::username() => $matricula,
        ]));
    }

    /**
     * Recusa com motivo visível no campo da matrícula.
     *
     * O contador de tentativas é incrementado à mão porque, ao devolver a recusa
     * daqui, o Fortify não chega no ponto em que ele mesmo faria isso.
     *
     * @throws ValidationException
     */
    private function recusar(Request $request, string $motivo): never
    {
        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            Fortify::username() => [$motivo],
        ]);
    }
}
