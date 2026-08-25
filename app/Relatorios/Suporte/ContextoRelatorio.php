<?php

namespace App\Relatorios\Suporte;

/**
 * Contexto de execução de um relatório: os valores de filtro que vieram da tela
 * e o modo escolhido (analítico, sintético ou gerencial).
 */
class ContextoRelatorio
{
    public const MODO_ANALITICO = 'analitico';

    public const MODO_SINTETICO = 'sintetico';

    public const MODO_GERENCIAL = 'gerencial';

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        public array $filtros,
        public string $modo = self::MODO_ANALITICO,
        public bool $incluirGraficos = true,
    ) {}

    /** O valor de um filtro, tratando vazio como ausente (é o que a tela manda). */
    public function filtro(string $nome, mixed $default = null): mixed
    {
        $v = $this->filtros[$nome] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    public function ehSintetico(): bool
    {
        return $this->modo === self::MODO_SINTETICO;
    }

    public function ehGerencial(): bool
    {
        return $this->modo === self::MODO_GERENCIAL;
    }

    /** Gráfico é do modo gerencial: o analítico existe para conferir linha a linha. */
    public function querGraficos(): bool
    {
        return $this->incluirGraficos && $this->ehGerencial();
    }
}
