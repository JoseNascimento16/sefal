<?php

namespace App\Support;

/**
 * Documento (CPF ou CNPJ) — normalização, validação e formatação, já preparado para o
 * **CNPJ ALFANUMÉRICO** (novo formato da Receita Federal, vigência 2026) e 100% compatível com o
 * CNPJ numérico antigo e com o CPF. É a FONTE ÚNICA desse tratamento no backend.
 *
 * CNPJ novo: 14 posições — as **12 primeiras** podem ser letras (A–Z) e dígitos; as **2 últimas**
 * (dígitos verificadores) continuam **numéricas**. O DV usa módulo 11 sobre o valor `ord(char) - 48`
 * de cada caractere ('0'→0 … '9'→9, 'A'→17 … 'Z'→42). Como para dígitos `ord-48` devolve o próprio
 * número, o mesmo algoritmo valida o CNPJ numérico antigo — retrocompatível de graça.
 *
 * CPF **não muda**: 11 dígitos numéricos.
 *
 * ⚠️ Nenhum campo que possa conter CNPJ pode passar por `preg_replace('/\D/', '', ...)`: o strip
 * apaga as letras e corrompe o documento em silêncio. No front, o par desta classe é
 * `resources/js/lib/masks.ts` (`apenasAlfanumerico`/`maskCpfCnpj`).
 */
class Documento
{
    /**
     * Forma canônica de um documento (CPF ou CNPJ): só `[0-9A-Z]`, **MAIÚSCULO**, sem máscara
     * (`.`/`/`/`-`/espaço). Use no lugar de `preg_replace('/\D/', '', ...)` em campos que podem
     * conter CNPJ — para um CPF (numérico) devolve os mesmos 11 dígitos, então é seguro.
     */
    public static function normalizar(?string $valor): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $valor) ?? '');
    }

    /** Tem 14 posições (formato CNPJ) após normalizar? NÃO valida o DV. */
    public static function ehCnpj(?string $valor): bool
    {
        return strlen(self::normalizar($valor)) === 14;
    }

    /** Tem 11 dígitos (formato CPF) após normalizar? */
    public static function ehCpf(?string $valor): bool
    {
        $d = self::normalizar($valor);

        return strlen($d) === 11 && ctype_digit($d);
    }

    /** Rótulo do tipo de pessoa pelo documento: 'PJ' (CNPJ, 14) ou 'PF' (CPF/demais). */
    public static function tipoPessoa(?string $valor): string
    {
        return self::ehCnpj($valor) ? 'PJ' : 'PF';
    }

    /**
     * Heurística "isto parece um documento (CPF/CNPJ), não um nome?" — para roteirizar caixas de
     * busca que aceitam nome OU documento. True quando o normalizado tem 11 ou 14 posições `[0-9A-Z]`
     * **com ao menos um dígito** — o que cobre o CNPJ alfanumérico sem confundir com um nome digitado
     * (que tem espaço, é só letras, ou não tem 11/14 caracteres exatos).
     */
    public static function pareceDocumento(?string $valor): bool
    {
        $d = self::normalizar($valor);

        return (strlen($d) === 11 || strlen($d) === 14)
            && preg_match('/^[0-9A-Z]+$/', $d) === 1
            && preg_match('/\d/', $d) === 1;
    }

    /**
     * Valida um CNPJ pelos 2 dígitos verificadores. Aceita **alfanumérico** (novo) e **numérico**
     * (antigo), com ou sem máscara.
     */
    public static function cnpjValido(?string $valor): bool
    {
        $cnpj = self::normalizar($valor);

        // 14 posições: 12 primeiras [0-9A-Z], 2 últimas dígitos. Rejeita sequência toda igual.
        if (! preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj) || preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([[12, $pesos1], [13, $pesos2]] as [$tamanho, $pesos]) {
            $soma = 0;
            for ($i = 0; $i < $tamanho; $i++) {
                // Valor do caractere = código ASCII − 48 ('0'→0 … 'A'→17). Dígito devolve ele mesmo.
                $soma += (ord($cnpj[$i]) - 48) * $pesos[$i];
            }
            $resto = $soma % 11;
            $dv = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[$tamanho] !== $dv) {
                return false;
            }
        }

        return true;
    }

    /** Valida um CPF pelos dígitos verificadores (inalterado — CPF continua 11 dígitos). */
    public static function cpfValido(?string $valor): bool
    {
        $cpf = self::normalizar($valor);
        if (strlen($cpf) !== 11 || ! ctype_digit($cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * ($t + 1 - $i);
            }
            $dv = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $dv) {
                return false;
            }
        }

        return true;
    }

    /** True se for um CPF válido OU um CNPJ válido. */
    public static function valido(?string $valor): bool
    {
        return match (strlen(self::normalizar($valor))) {
            11 => self::cpfValido($valor),
            14 => self::cnpjValido($valor),
            default => false,
        };
    }

    /**
     * Formata para exibição — CPF `000.000.000-00`; CNPJ `00.000.000/0000-00` (posicional, aceita
     * letras). Documento de tamanho fora do padrão volta como está (normalizado).
     */
    public static function formatar(?string $valor): string
    {
        $d = self::normalizar($valor);
        if (strlen($d) === 11) {
            return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2);
        }
        if (strlen($d) === 14) {
            return substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12, 2);
        }

        return $d;
    }
}
