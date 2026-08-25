<?php

namespace App\Relatorios\Suporte;

/**
 * FONTE ÚNICA das cores de categoria dos gráficos de relatório.
 *
 * O backend gera a lista (uma cor DISTINTA por categoria, sem repetição) e a
 * envia junto do gráfico, então qualquer desenho do mesmo gráfico — hoje o SVG
 * do PDF, amanhã uma prévia na tela — usa exatamente as mesmas cores. Duas
 * listas de cores dariam ao mesmo dado duas aparências, e a legenda de uma
 * contradiria a da outra.
 *
 * A base começa pelas cores da identidade do SEFAL (petróleo e âmbar).
 */
class PaletaGrafico
{
    /** Cores base institucionais, em ordem fixa. */
    private const BASE = [
        '#0d5c63', '#f4a300', '#0b6f8c', '#0f7a52', '#b3261e', '#6b8082',
        '#8a5300', '#0a474d', '#7c3aed', '#0d9488', '#d946ef', '#84cc16',
    ];

    /**
     * N cores distintas: usa a base e, se precisar de mais, gera matizes
     * espaçados pelo ângulo áureo — nunca repete, para cada categoria ficar com
     * uma cor própria.
     *
     * @return list<string>
     */
    public static function cores(int $n): array
    {
        $cores = array_slice(self::BASE, 0, max($n, 0));

        for ($i = count($cores); $i < $n; $i++) {
            $cores[] = self::hslParaHex(fmod($i * 137.508, 360.0), 0.62, 0.45);
        }

        return $cores;
    }

    private static function hslParaHex(float $h, float $s, float $l): string
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        );
    }
}
