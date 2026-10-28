<?php

namespace App\Support\SaudeFluxos;

use App\Support\SaudeFluxos\Fluxos\FluxoDaFiscalizacao;
use App\Support\SaudeFluxos\Fluxos\FluxoDoAplicativo;

/**
 * Registro dos fluxos da Saúde dos Fluxos — cada entrada é uma ABA da tela.
 *
 * No SEFAL são dois: a fiscalização de ponta a ponta (27/10/2026) e o aplicativo do fiscal visto de
 * dentro (28/10/2026). Um fluxo novo é uma classe {@see Fluxo} registrada aqui, e ganha a sua aba e
 * a sua linha na Visão geral sozinho.
 */
final class CatalogoFluxos
{
    /** O que cada quadradinho é HOJE no sistema. */
    public const ATENDIDO = 'atendido';

    public const PREVISTO = 'previsto';

    /** @var array<string, class-string<Fluxo>> */
    private const FLUXOS = [
        'fiscalizacao' => FluxoDaFiscalizacao::class,
        'aplicativo' => FluxoDoAplicativo::class,
    ];

    /** @return list<string> */
    public static function chaves(): array
    {
        return array_keys(self::FLUXOS);
    }

    public static function existe(string $chave): bool
    {
        return isset(self::FLUXOS[$chave]);
    }

    /** @return array<string, mixed> */
    public static function definicao(string $chave): array
    {
        return self::FLUXOS[$chave]::definicao();
    }

    /**
     * A linha de cada fluxo na Visão geral: quantos trechos há, quantos o sistema atende hoje e
     * quais ainda são só previstos.
     *
     * @return list<array{chave: string, titulo: string, subtitulo: string, total_trechos: int, atendidos: int, previstos: list<string>}>
     */
    public static function visaoGeral(): array
    {
        return array_map(function (string $chave): array {
            $fluxo = self::definicao($chave);
            $previstos = array_values(array_filter($fluxo['nos'], fn (array $no) => $no['situacao'] === self::PREVISTO));

            return [
                'chave' => $chave,
                'titulo' => $fluxo['titulo'],
                'subtitulo' => $fluxo['subtitulo'],
                'total_trechos' => count($fluxo['nos']),
                'atendidos' => count($fluxo['nos']) - count($previstos),
                'previstos' => array_map(fn (array $no) => str_replace("\n", ' — ', $no['rotulo']), $previstos),
            ];
        }, self::chaves());
    }
}
