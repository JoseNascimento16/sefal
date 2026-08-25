<?php

namespace App\Relatorios\Suporte;

/**
 * Gera o gráfico do PDF como SVG, no servidor.
 *
 * Dois detalhes que parecem arbitrários e não são:
 *
 *  1. **O dompdf NÃO renderiza `<svg>` inline** — ele extrai o texto e descarta as
 *     formas, produzindo um bloco de números soltos. O SVG só é desenhado quando
 *     chega como `<img src="data:image/svg+xml;base64,…">`, caminho em que o
 *     php-svg-lib entra;
 *  2. esse caminho **não usa a extensão GD**, então funciona igual no Windows de
 *     desenvolvimento e no contêiner de produção — nada de gráfico que existe na
 *     máquina de um e não na do outro.
 *
 * Todo `<text>` declara `font-family`: sem isso o php-svg-lib assume serifada, e
 * o número da barra sai com desenho diferente do resto do documento.
 */
class GraficoSvg
{
    /**
     * Família genérica de propósito: o php-svg-lib a resolve em Helvetica e o
     * dompdf, em DejaVu Sans — nos dois casos uma sem-serifa real.
     */
    private const FONTE = 'sans-serif';

    /**
     * @param  array{tipo: string, titulo: string, labels: list<string>, series: array<int, array{nome?: string, valores: list<float|int>}>, cores?: list<string>}  $grafico
     */
    public static function html(array $grafico): string
    {
        return match ($grafico['tipo']) {
            'pie' => self::pizza($grafico),
            default => self::barras($grafico),
        };
    }

    /**
     * @param  array{titulo: string, labels: list<string>, series: array<int, array{valores: list<float|int>}>, cores?: list<string>}  $g
     */
    private static function pizza(array $g): string
    {
        $valores = array_map('floatval', $g['series'][0]['valores'] ?? []);
        $total = array_sum($valores);

        if ($total <= 0) {
            return '';
        }

        $cx = 110;
        $cy = 110;
        $raio = 95;
        $ang = -M_PI / 2;
        $fatias = '';
        $legenda = '';
        $cores = self::cores($g);

        foreach ($valores as $i => $v) {
            $frac = $v / $total;
            $a2 = $ang + $frac * 2 * M_PI;
            $cor = $cores[$i];

            if ($frac >= 0.9999) {
                // Fatia única (100%): arco de volta completa não desenha — é círculo.
                $fatias .= "<circle cx='{$cx}' cy='{$cy}' r='{$raio}' fill='{$cor}'/>";
            } else {
                $x1 = round($cx + $raio * cos($ang), 2);
                $y1 = round($cy + $raio * sin($ang), 2);
                $x2 = round($cx + $raio * cos($a2), 2);
                $y2 = round($cy + $raio * sin($a2), 2);
                $large = $frac > 0.5 ? 1 : 0;
                $fatias .= "<path d='M {$cx} {$cy} L {$x1} {$y1} A {$raio} {$raio} 0 {$large} 1 {$x2} {$y2} Z' fill='{$cor}' stroke='#ffffff' stroke-width='1.5'/>";
            }

            $pct = number_format($frac * 100, 2, ',', '.');
            $lbl = htmlspecialchars((string) ($g['labels'][$i] ?? ''), ENT_QUOTES);
            $legenda .= "<div style='margin-bottom:6px;font-size:11px;color:#35494b;'>"
                ."<span style='display:inline-block;width:11px;height:11px;background:{$cor};border-radius:2px;margin-right:6px;'></span>"
                ."{$lbl} — <strong>".(int) $v."</strong> ({$pct}%)</div>";
            $ang = $a2;
        }

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='220' height='220' viewBox='0 0 220 220'>{$fatias}</svg>";

        return self::moldura($g['titulo'], "<table style='width:100%;border:none;'><tr>"
            ."<td style='border:none;width:210px;padding:0;'>".self::imagem($svg, 200, 200).'</td>'
            ."<td style='border:none;vertical-align:middle;padding:0 0 0 16px;'>{$legenda}</td>"
            .'</tr></table>');
    }

    /**
     * @param  array{titulo: string, labels: list<string>, series: array<int, array{valores: list<float|int>}>, cores?: list<string>}  $g
     */
    private static function barras(array $g): string
    {
        $valores = array_map('floatval', $g['series'][0]['valores'] ?? []);
        $max = $valores !== [] ? max($valores) : 0;

        if ($max <= 0) {
            return '';
        }

        $n = count($valores);
        $largura = 520;
        $base = 165;
        $altura = 172;
        $passo = $largura / max($n, 1);
        $larguraBarra = min($passo * 0.6, 56);
        $barras = '';
        $cores = self::cores($g);

        foreach ($valores as $i => $v) {
            $h = round(($v / $max) * 120, 1);
            $x = round($i * $passo + ($passo - $larguraBarra) / 2, 1);
            $y = round($base - $h, 1);
            $meio = round($x + $larguraBarra / 2, 1);
            $barras .= "<rect x='{$x}' y='{$y}' width='{$larguraBarra}' height='{$h}' rx='4' fill='{$cores[$i]}'/>";
            $barras .= "<text x='{$meio}' y='".($y - 5)."' font-family='".self::FONTE."' font-size='12' fill='#35494b' text-anchor='middle'>".(int) $v.'</text>';
        }

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$largura}' height='{$altura}' viewBox='0 0 {$largura} {$altura}'>"
            ."<line x1='0' y1='{$base}' x2='{$largura}' y2='{$base}' stroke='#dfe5e5' stroke-width='1'/>"
            ."{$barras}</svg>";

        // A legenda de cores fica ABAIXO do gráfico, com o nome inteiro: rótulo
        // sob a barra colide e trunca assim que há mais de meia dúzia de
        // categorias — e nome de categoria cortado ao meio não informa nada.
        $itens = '';
        foreach ($g['labels'] as $i => $lbl) {
            $txt = htmlspecialchars((string) $lbl, ENT_QUOTES);
            $itens .= "<span style='display:inline-block;margin:0 12px 5px 0;font-family:".self::FONTE.";font-size:11px;color:#35494b;'>"
                ."<span style='display:inline-block;width:11px;height:11px;background:{$cores[$i]};border-radius:2px;margin-right:5px;'></span>"
                ."{$txt}</span>";
        }

        return self::moldura(
            $g['titulo'],
            "<div style='text-align:center;'>".self::imagem($svg, $largura, $altura).'</div>'
            ."<div style='text-align:center;margin-top:8px;'>{$itens}</div>",
        );
    }

    /**
     * As cores que vieram com o gráfico (fonte única); gráfico montado à mão sem
     * elas cai na paleta gerada.
     *
     * @param  array{labels: list<string>, cores?: list<string>}  $g
     * @return list<string>
     */
    private static function cores(array $g): array
    {
        return $g['cores'] ?? PaletaGrafico::cores(count($g['labels']));
    }

    /** Embute o SVG como imagem data-URI — o único jeito de o dompdf o desenhar. */
    private static function imagem(string $svg, int $largura, int $altura): string
    {
        $data = base64_encode('<?xml version="1.0" encoding="UTF-8"?>'.$svg);

        return "<img src='data:image/svg+xml;base64,{$data}' width='{$largura}' height='{$altura}' alt=''/>";
    }

    private static function moldura(string $titulo, string $conteudo): string
    {
        // `page-break-inside:avoid` — se o bloco (título + gráfico + legenda) não
        // couber no fim da página, ele vai INTEIRO para a próxima em vez de ser
        // cortado no meio.
        return "<div style='border:1px solid #dfe5e5;border-radius:8px;padding:12px 14px;margin-top:12px;page-break-inside:avoid;'>"
            ."<div style='font-family:".self::FONTE.";font-size:13px;font-weight:bold;color:#15292b;margin-bottom:8px;'>"
            .htmlspecialchars($titulo, ENT_QUOTES).'</div>'
            ."{$conteudo}</div>";
    }
}
