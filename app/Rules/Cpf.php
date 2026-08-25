<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida um CPF pelos dígitos verificadores (algoritmo da Receita Federal).
 * Apenas validação local — sem consulta a API. Aceita o valor com ou sem máscara.
 * Reutilizável em qualquer FormRequest que receba CPF.
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/\D/', '', (string) $value);

        // 11 dígitos e não pode ser uma sequência repetida (ex.: 111.111.111-11).
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('O CPF informado não é válido.');

            return;
        }

        // Confere os dois dígitos verificadores.
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                $fail('O CPF informado não é válido.');

                return;
            }
        }
    }
}
