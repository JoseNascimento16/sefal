<?php

namespace App\Relatorios\Suporte;

/**
 * Definição de um filtro de relatório — descreve o campo que a tela de
 * Relatórios renderiza. O relatório declara o que precisa; a tela não conhece
 * relatório nenhum por dentro.
 */
class FiltroDef
{
    /**
     * @param  'texto'|'data'|'select'|'numero'  $tipo
     * @param  array<int, array{valor: string, rotulo: string}>  $opcoes
     */
    public function __construct(
        public string $nome,
        public string $label,
        public string $tipo = 'texto',
        public bool $obrigatorio = false,
        public array $opcoes = [],
        public ?string $padrao = null,
        public ?string $ajuda = null,
    ) {}

    public static function data(string $nome, string $label, bool $obrigatorio = false): self
    {
        return new self($nome, $label, 'data', $obrigatorio);
    }

    public static function texto(string $nome, string $label, bool $obrigatorio = false): self
    {
        return new self($nome, $label, 'texto', $obrigatorio);
    }

    /**
     * @param  array<int, array{valor: string, rotulo: string}>  $opcoes
     */
    public static function select(string $nome, string $label, array $opcoes, bool $obrigatorio = false): self
    {
        return new self($nome, $label, 'select', $obrigatorio, $opcoes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'label' => $this->label,
            'tipo' => $this->tipo,
            'obrigatorio' => $this->obrigatorio,
            'opcoes' => $this->opcoes,
            'padrao' => $this->padrao,
            'ajuda' => $this->ajuda,
        ];
    }
}
