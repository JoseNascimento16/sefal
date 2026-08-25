<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida um CNPJ pelos dígitos verificadores (algoritmo da Receita Federal). Aceita o **CNPJ
 * alfanumérico** (novo formato, 2026) e o numérico antigo, com ou sem máscara. A regra é fina; a
 * lógica mora na FONTE ÚNICA {@see Documento::cnpjValido()}.
 *
 * ⚠️ Tamanho de CNPJ se valida com esta Rule, nunca com `digits:14` — o `digits` recusa as letras
 * do formato novo.
 */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Documento::cnpjValido((string) $value)) {
            $fail('O CNPJ informado não é válido.');
        }
    }
}
