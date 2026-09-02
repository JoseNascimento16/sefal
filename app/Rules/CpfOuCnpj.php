<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida um documento que pode ser CPF **ou** CNPJ, escolhendo pelo tamanho normalizado e delegando
 * para as regras já existentes ({@see Cpf} / {@see Cnpj}). Aceita com ou sem máscara e o **CNPJ
 * alfanumérico** (novo formato) — a normalização preserva letras ({@see Documento::normalizar}).
 *
 * Use nos campos que aceitam os dois (ex.: o `documento` do ambulante, onde o front usa
 * `maskCpfCnpj`). Quando o campo aceita só um dos tipos, prefira `new Cpf` ou `new Cnpj`.
 *
 * Passe `$campo` (rótulo da seção/campo) quando a tela tiver mais de um CPF/CNPJ — a mensagem sai
 * prefixada (ex.: `new CpfOuCnpj('Responsável')` → "Responsável: Informe um CPF...") para o usuário
 * saber ONDE está o erro. Sem o rótulo, a mensagem segue genérica.
 */
class CpfOuCnpj implements ValidationRule
{
    public function __construct(private ?string $campo = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Com rótulo, prefixa TODA mensagem (inclusive as delegadas a Cpf/Cnpj) com a seção.
        $reportar = $this->campo === null
            ? $fail
            : fn (string $mensagem) => $fail($this->campo.': '.$mensagem);

        // Normaliza mantendo letras (CNPJ alfanumérico); CPF continua 11 dígitos.
        $doc = Documento::normalizar((string) $value);

        match (strlen($doc)) {
            11 => (new Cpf)->validate($attribute, $doc, $reportar),
            14 => (new Cnpj)->validate($attribute, $doc, $reportar),
            default => $reportar('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.'),
        };
    }
}
