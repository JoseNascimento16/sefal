<?php

namespace App\Relatorios;

use App\Relatorios\Contracts\Relatorio;
use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\FiltroDef;
use App\Relatorios\Suporte\ResultadoRelatorio;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\MapasFicticios;

/**
 * Onde a ocorrência se concentra — o Mapa de Calor traduzido em relatório.
 *
 * A mancha do mapa diz ONDE olhar; ela não diz QUANTO, e não se anexa a um
 * ofício. Este documento entrega o ranking por bairro com a **variação contra o
 * período anterior**, que é a pergunta da operação: *isto está piorando desde a
 * última vez que eu olhei?*
 *
 * ── Por que a janela dobra por baixo dos panos ──────────────────────────────
 * A variação compara a janela escolhida (7, 30 ou 90 dias) com a janela
 * ANTERIOR de mesmo tamanho. É a mesma conta que a tela faz — e é por isso que a
 * fonte carrega 180 dias mesmo quando se pede 90: sem o período anterior, a
 * coluna de variação seria invenção.
 *
 * ⚠️ PROTÓTIPO: mesma fonte do mapa ({@see MapasFicticios::calor()}), então o
 * ranking do documento é o ranking da tela.
 */
class RelatorioConcentracao implements Relatorio
{
    /** As janelas que a tela oferece — o documento fala a mesma língua dela. */
    private const JANELAS = [7, 30, 90];

    /** Abaixo disso a variação é ruído de amostra, e a tela chama de "estável". */
    private const LIMIAR_VARIACAO = 12;

    public function chave(): string
    {
        return 'concentracao';
    }

    public function titulo(): string
    {
        return 'Concentração de ocorrências (Mapa de Calor)';
    }

    public function grupo(): string
    {
        return 'Fiscalização';
    }

    public function descricao(): string
    {
        return 'O ranking de bairros que a mancha do Mapa de Calor desenha, com a fatia de cada um e a variação contra o período anterior. Responde "onde a ocorrência se concentra e o que está piorando".';
    }

    public function filtros(): array
    {
        $areas = [['valor' => '', 'rotulo' => 'Toda a cidade']];

        foreach (EstruturaFicticia::nomesDeArea() as $area) {
            $areas[] = ['valor' => $area, 'rotulo' => $area];
        }

        $janelas = [];

        foreach (self::JANELAS as $dias) {
            $janelas[] = ['valor' => (string) $dias, 'rotulo' => 'Últimos '.$dias.' dias'];
        }

        return [
            FiltroDef::select('janela', 'Janela', $janelas),
            FiltroDef::select('area', 'Área', $areas),
        ];
    }

    public function modos(): array
    {
        return [ContextoRelatorio::MODO_ANALITICO, ContextoRelatorio::MODO_SINTETICO];
    }

    public function gerar(ContextoRelatorio $contexto): ResultadoRelatorio
    {
        $janela = (int) $contexto->filtro('janela', 30);
        $janela = in_array($janela, self::JANELAS, true) ? $janela : 30;
        $area = trim((string) $contexto->filtro('area', ''));

        $calor = MapasFicticios::calor();
        $bairros = $calor['bairros'];

        /*
         * A mesma conta da tela: conta as ocorrências DENTRO da janela e as da
         * janela anterior de mesmo tamanho, e a variação sai da comparação. O
         * ponto do calor é uma tupla [índice do bairro, lat, lng, dias, ...].
         */
        $agora = [];
        $antes = [];

        foreach ($calor['pontos'] as $ponto) {
            $indice = (int) $ponto[0];
            $dias = (int) $ponto[3];

            if ($dias <= $janela) {
                $agora[$indice] = ($agora[$indice] ?? 0) + 1;
            } elseif ($dias <= $janela * 2) {
                $antes[$indice] = ($antes[$indice] ?? 0) + 1;
            }
        }

        $linhas = [];
        $total = 0;

        foreach ($agora as $indice => $ocorrencias) {
            $bairro = $bairros[$indice] ?? null;

            if ($bairro === null) {
                continue;
            }

            if ($area !== '' && (string) $bairro['area'] !== $area) {
                continue;
            }

            $anterior = $antes[$indice] ?? 0;
            $total += $ocorrencias;

            $linhas[] = [
                'bairro' => (string) $bairro['bairro'],
                'area' => (string) $bairro['area'],
                'regiao' => (string) $bairro['regiao'],
                'equipe' => (string) $bairro['equipe'],
                'encarregado' => (string) $bairro['encarregado'],
                'ocorrencias' => $ocorrencias,
                'anterior' => $anterior,
                'variacao' => $anterior > 0 ? (int) round(($ocorrencias - $anterior) / $anterior * 100) : 0,
                'variacao_conhecida' => $anterior > 0,
            ];
        }

        usort($linhas, static fn (array $a, array $b): int => $b['ocorrencias'] <=> $a['ocorrencias']);

        $resultado = new ResultadoRelatorio;
        $resultado->metadados['recorte'] = implode(' · ', [
            'últimos '.$janela.' dias',
            $area === '' ? 'toda a cidade' : $area,
            'comparado com os '.$janela.' dias anteriores',
        ]);

        // ── O ranking ───────────────────────────────────────────────────────
        $ranking = $resultado->secao('Ranking de bairros')
            ->coluna('posicao', '#', 'numero', 'right')
            ->coluna('bairro', 'Bairro')
            ->coluna('area', 'Área')
            ->coluna('equipe', 'Equipe')
            ->coluna('ocorrencias', 'Ocorrências', 'numero', 'right')
            ->coluna('fatia', 'Fatia', 'texto', 'right')
            ->coluna('variacao', 'Contra o período anterior', 'texto', 'right');

        foreach ($linhas as $i => $linha) {
            $ranking->linha([
                'posicao' => $i + 1,
                'bairro' => $linha['bairro'],
                'area' => $linha['area'],
                'equipe' => $linha['equipe'],
                'ocorrencias' => $linha['ocorrencias'],
                'fatia' => $total > 0 ? round($linha['ocorrencias'] / $total * 100).'%' : '—',
                'variacao' => $this->variacaoEmPalavras($linha),
            ]);
        }

        $ranking->total('Ocorrências no recorte', $total);
        $ranking->total('Bairros com ocorrência', count($linhas));

        if ($contexto->querGraficos() && $linhas !== []) {
            $dez = array_slice($linhas, 0, 10);

            $resultado->grafico(
                'barra',
                'Os dez bairros com mais ocorrências',
                array_map(static fn (array $l): string => $l['bairro'], $dez),
                [['nome' => 'Ocorrências', 'valores' => array_map(static fn (array $l): int => $l['ocorrencias'], $dez)]],
            );
        }

        if ($contexto->ehSintetico()) {
            return $resultado;
        }

        // ── Por área ────────────────────────────────────────────────────────
        // Onde a chefia lê a própria área sem procurar bairro por bairro.
        $porArea = [];

        foreach ($linhas as $linha) {
            $porArea[$linha['area']] ??= ['ocorrencias' => 0, 'bairros' => 0];
            $porArea[$linha['area']]['ocorrencias'] += $linha['ocorrencias'];
            $porArea[$linha['area']]['bairros']++;
        }

        uasort($porArea, static fn (array $a, array $b): int => $b['ocorrencias'] <=> $a['ocorrencias']);

        $areas = $resultado->secao('Por área')
            ->coluna('area', 'Área')
            ->coluna('bairros', 'Bairros com ocorrência', 'numero', 'right')
            ->coluna('ocorrencias', 'Ocorrências', 'numero', 'right')
            ->coluna('fatia', 'Fatia', 'texto', 'right');

        foreach ($porArea as $nome => $dados) {
            $areas->linha([
                'area' => (string) $nome,
                'bairros' => $dados['bairros'],
                'ocorrencias' => $dados['ocorrencias'],
                'fatia' => $total > 0 ? round($dados['ocorrencias'] / $total * 100).'%' : '—',
            ]);
        }

        return $resultado;
    }

    /**
     * A variação dita em palavras.
     *
     * "+300%" para uma ocorrência que passou de 1 para 4 é verdade aritmética e
     * mentira de leitura — por isso abaixo do limiar o documento diz "estável", e
     * bairro sem período anterior diz que é novo em vez de mostrar 0%.
     *
     * @param  array{variacao: int, variacao_conhecida: bool}  $linha
     */
    private function variacaoEmPalavras(array $linha): string
    {
        if (! $linha['variacao_conhecida']) {
            return 'novo no período';
        }

        if (abs($linha['variacao']) < self::LIMIAR_VARIACAO) {
            return 'estável';
        }

        return ($linha['variacao'] > 0 ? '+' : '−').abs($linha['variacao']).'%';
    }
}
