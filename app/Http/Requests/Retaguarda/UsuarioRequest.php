<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * O formulário da tela de Usuários — o mesmo para incluir e alterar.
 *
 * A MATRÍCULA só é informada na inclusão: é a identidade da pessoa no login e
 * nos registros, e trocá-la depois seria trocar o nome de quem assinou cada
 * passo. Nome e e-mail se corrigem; matrícula não.
 */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Inclusão = rota sem `{usuario}`. */
    private function incluindo(): bool
    {
        return $this->route('usuario') === null;
    }

    protected function prepareForValidation(): void
    {
        $dados = [
            'setores' => array_values(array_unique(array_map('strval', (array) $this->input('setores', [])))),
            'ativo' => $this->boolean('ativo'),
            'name' => trim((string) $this->input('name', '')),
            'email' => mb_strtolower(trim((string) $this->input('email', ''))),
        ];

        if ($this->incluindo()) {
            // A mesma forma canônica do login: minúsculo e sem espaço nas pontas.
            $dados['login'] = User::normalizarMatricula((string) $this->input('login', ''));
        }

        $this->merge($dados);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $usuario = $this->route('usuario');

        $regras = [
            'name' => ['required', 'string', 'max:255'],
            // Obrigatório porque é por ele que sai o convite de primeiro acesso e a
            // redefinição de senha: conta sem e-mail é conta que ninguém consegue abrir.
            // A unicidade olha também a lixeira — o e-mail de uma conta excluída
            // continua sendo dela até a remoção definitiva.
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario)],
            'setores' => ['array'],
            'setores.*' => ['string', Rule::in(Setor::query()->pluck('slug')->all())],
            'ativo' => ['boolean'],
        ];

        if ($this->incluindo()) {
            $regras['login'] = [
                'required',
                'string',
                'max:30',
                // Letras, números, ponto, hífen e sublinhado: a matrícula aparece em
                // endereços e documentos, e espaço ou acento nela vira dor depois.
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'login'),
            ];
        }

        return $regras;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'login.required' => 'Informe a matrícula.',
            'login.max' => 'A matrícula tem no máximo 30 caracteres.',
            'login.regex' => 'A matrícula aceita só letras, números, ponto, hífen e sublinhado — sem espaço nem acento.',
            'login.unique' => 'Já existe uma conta com esta matrícula (confira também a aba Excluídos).',
            'name.required' => 'Informe o nome.',
            'email.required' => 'Informe o e-mail — é por ele que a pessoa recebe o convite para definir a senha.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Já existe uma conta com este e-mail (confira também a aba Excluídos).',
            'setores.*.in' => 'Setor desconhecido.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['login' => 'matrícula', 'name' => 'nome', 'email' => 'e-mail'];
    }
}
