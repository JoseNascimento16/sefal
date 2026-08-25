<?php

namespace App\Relatorios\Suporte;

/**
 * Resultado NEUTRO de um relatório — o meio de campo entre quem sabe a regra de
 * negócio (o relatório) e quem sabe o formato físico (os exportadores).
 *
 * É o que permite três formatos sem triplicar consulta nem regra: o relatório
 * monta seções (tabelas), gráficos e metadados uma vez; cada exportador desenha
 * isso do seu jeito.
 *
 * Os atalhos `coluna()`/`linha()`/`total()` operam numa seção "corrente". Para
 * várias tabelas, abra cada uma com `secao('Título')`.
 */
class ResultadoRelatorio
{
    /** @var array<int, SecaoRelatorio> */
    public array $secoes = [];

    /** @var array<int, array{tipo: string, titulo: string, labels: list<string>, series: array<int, array{nome: string, valores: list<float|int>}>, cores: list<string>}> */
    public array $graficos = [];

    /** @var array<string, mixed> */
    public array $metadados = [];

    private ?SecaoRelatorio $secaoAtual = null;

    /** Abre (e passa a apontar para) uma nova seção. */
    public function secao(string $titulo = ''): SecaoRelatorio
    {
        $s = new SecaoRelatorio($titulo);
        $this->secoes[] = $s;
        $this->secaoAtual = $s;

        return $s;
    }

    public function coluna(string $chave, string $titulo, string $tipo = 'texto', string $alinhar = 'left'): self
    {
        $this->secaoCorrente()->coluna($chave, $titulo, $tipo, $alinhar);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    public function linha(array $linha): self
    {
        $this->secaoCorrente()->linha($linha);

        return $this;
    }

    /**
     * @param  array<string, string|int|float>  $celulas
     */
    public function total(string $rotulo, string|int|float $valor, array $celulas = []): self
    {
        $this->secaoCorrente()->total($rotulo, $valor, $celulas);

        return $this;
    }

    /**
     * @param  list<string>  $labels
     * @param  array<int, array{nome: string, valores: list<float|int>}>  $series
     */
    public function grafico(string $tipo, string $titulo, array $labels, array $series): self
    {
        // Uma cor DISTINTA por categoria, vinda da fonte única — assim a legenda
        // do documento nunca repete cor.
        $cores = PaletaGrafico::cores(count($labels));

        $this->graficos[] = compact('tipo', 'titulo', 'labels', 'series', 'cores');

        return $this;
    }

    /**
     * @return array{secoes: array<int, array<string, mixed>>, graficos: array<int, array<string, mixed>>, metadados: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'secoes' => array_map(static fn (SecaoRelatorio $s): array => $s->toArray(), $this->secoes),
            'graficos' => $this->graficos,
            'metadados' => $this->metadados,
        ];
    }

    private function secaoCorrente(): SecaoRelatorio
    {
        return $this->secaoAtual ??= $this->secao('');
    }
}
