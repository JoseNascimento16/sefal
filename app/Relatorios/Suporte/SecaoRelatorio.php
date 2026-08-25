<?php

namespace App\Relatorios\Suporte;

/**
 * Uma seção (tabela) de um relatório. Um relatório pode ter várias — a relação
 * detalhada e, abaixo dela, o quadro de totais por categoria.
 */
class SecaoRelatorio
{
    /** @var array<int, array{chave: string, titulo: string, tipo: string, alinhar: string}> */
    public array $colunas = [];

    /** @var array<int, array<string, mixed>> */
    public array $linhas = [];

    /** @var array<int, array{rotulo: string, valor: string, celulas: array<string, string>}> */
    public array $totais = [];

    public function __construct(public string $titulo = '') {}

    public function coluna(string $chave, string $titulo, string $tipo = 'texto', string $alinhar = 'left'): self
    {
        $this->colunas[] = compact('chave', 'titulo', 'tipo', 'alinhar');

        return $this;
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    public function linha(array $linha): self
    {
        $this->linhas[] = $linha;

        return $this;
    }

    /**
     * @param  array<string, string|int|float>  $celulas  Valores da linha de total por chave de
     *                                                    coluna (ex.: `['registros' => 12]`),
     *                                                    para o número cair SOB a coluna certa.
     */
    public function total(string $rotulo, string|int|float $valor, array $celulas = []): self
    {
        $this->totais[] = [
            'rotulo' => $rotulo,
            'valor' => (string) $valor,
            'celulas' => array_map(static fn ($v): string => (string) $v, $celulas),
        ];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'titulo' => $this->titulo,
            'colunas' => $this->colunas,
            'linhas' => $this->linhas,
            'totais' => $this->totais,
        ];
    }
}
