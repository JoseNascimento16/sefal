<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

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

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request): User
    {
        $matricula = trim((string) $request->input(Fortify::username()));

        // Quem entra não tropeça em caixa alta/baixa: a matrícula é gravada
        // normalizada (ver `User::normalizarMatricula`) e procurada do mesmo jeito.
        $user = User::porMatricula($matricula);

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            // A senha tentada NÃO vai no evento: qualquer ouvinte que registre em
            // log a tentativa gravaria uma senha em texto puro — inclusive a senha
            // certa de outra conta, quando alguém erra a matrícula.
            $this->falhou($matricula, $user);

            $this->recusar(self::CREDENCIAL_INVALIDA);
        }

        // A conta existe e a senha confere: aqui já dá para ser específico sem
        // entregar nada a quem está chutando credencial.
        if (! $user->ativo) {
            $this->falhou($matricula, $user);

            $this->recusar(self::USUARIO_INATIVO);
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
     * Quem segura a força bruta é o `throttle:login` da rota — o limitador
     * declarado em `fortify.limiters.login`, que conta TODA tentativa, não só as
     * que falham. Não há contador para incrementar aqui: enquanto esse limitador
     * estiver configurado, o Fortify nem coloca o `EnsureLoginIsNotThrottled` no
     * pipeline, e o balde dele ficaria escrito sem nunca ser lido.
     *
     * @throws ValidationException
     */
    private function recusar(string $motivo): never
    {
        throw ValidationException::withMessages([
            Fortify::username() => [$motivo],
        ]);
    }
}
