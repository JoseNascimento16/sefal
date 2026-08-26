<?php

namespace App\Support;

/**
 * Texto que gente escreve e gente lê — comparação e concordância, num lugar só.
 *
 * Duas coisas moram aqui, e as duas existiam espalhadas antes:
 *
 *  1. **comparar** dois textos digitados por pessoas diferentes ({@see chave}).
 *     "Área não autorizada" e "área nao autorizada" são o MESMO valor para quem
 *     escolhe numa lista, e tratá-los como dois cria gêmeos visuais: o fiscal vê
 *     a mesma opção duas vezes em rua e o histórico se parte entre elas;
 *  2. **plural** ({@see plural} e {@see contar}). O número é conhecido na hora de
 *     escrever a frase, então empurrar a concordância para o leitor entre
 *     parênteses é sempre evitável — e lê como rascunho de sistema inacabado.
 *     (O exemplo não vai escrito aqui de propósito: `PluralDaInterfaceTest`
 *     varre este arquivo junto com os demais, e a forma citada seria acusada.)
 *
 * O par deste arquivo no front é `resources/js/lib/busca.ts` (`semAcento`, mesma
 * regra de dobra) e `resources/js/lib/plural.ts`.
 *
 * ⚠️ A dobra de acento e caixa acontece em **PHP**, nunca em SQL. `LOWER()` do
 * SQLite só rebaixa ASCII, e o do Oracle depende da configuração de idioma da
 * instância: a mesma comparação responderia coisas diferentes em dev e em
 * produção — e foi exatamente assim que um nome repetido com acento passou pela
 * validação e morreu no índice único, como erro 500 em vez de recusa explicada.
 */
class Texto
{
    /**
     * A chave de comparação de um texto: minúsculo, sem acento, sem espaço nas
     * pontas e com os espaços de dentro reduzidos a um.
     *
     * "  ÁREA   não Autorizada " → "area nao autorizada".
     */
    public static function chave(?string $valor): string
    {
        $semAcento = self::semAcento($valor);

        return trim((string) preg_replace('/\s+/u', ' ', $semAcento));
    }

    /**
     * O mesmo texto sem os sinais diacríticos e em minúsculas, preservando o
     * resto (inclusive os espaços).
     *
     * `Normalizer` (intl) separa a letra do acento e o intervalo de marcas
     * combinantes as remove — a mesma regra do `semAcento` do front. Sem a
     * extensão, cai numa tabela de transliteração dos caracteres do português,
     * que é o alfabeto que este sistema recebe: perder o acento é degradar a
     * comparação, mas estourar por causa de uma extensão ausente seria pior.
     */
    public static function semAcento(?string $valor): string
    {
        $texto = (string) $valor;

        if (class_exists(\Normalizer::class)) {
            $decomposto = \Normalizer::normalize($texto, \Normalizer::FORM_D);

            if (is_string($decomposto)) {
                $texto = (string) preg_replace('/\p{Mn}+/u', '', $decomposto);
            }
        } else {
            $texto = strtr($texto, self::ACENTOS);
        }

        return mb_strtolower($texto);
    }

    /**
     * A forma certa da palavra para a quantidade — o singular só no 1.
     *
     * @example plural(1, 'registro', 'registros')  // 'registro'
     */
    public static function plural(int $quantidade, string $singular, string $plural): string
    {
        return $quantidade === 1 ? $singular : $plural;
    }

    /**
     * A quantidade com a palavra concordando: "1 registro", "2 registros".
     *
     * É a forma que quase toda frase de tela quer — daí existir pronta, para
     * ninguém escrever `$n.' '.Texto::plural(...)` em cada lugar.
     */
    public static function contar(int $quantidade, string $singular, string $plural): string
    {
        return $quantidade.' '.self::plural($quantidade, $singular, $plural);
    }

    /**
     * Transliteração de emergência, usada só quando a extensão `intl` não está
     * carregada. Cobre o português — que é o idioma deste sistema.
     *
     * @var array<string, string>
     */
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N',
    ];
}
