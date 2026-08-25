<?php

namespace App\Relatorios\Suporte;

use Illuminate\Support\Carbon;

/**
 * PERFIL DE DADOS POR FORMATO — deriva, de um MESMO recorte de listagem (colunas
 * + linhas já formatadas como texto), três conjuntos de dados distintos e
 * DISJUNTOS, um por formato de exportação:
 *
 *  - PDF  (`paraPdf`)  → análise de distribuição: contagem, PARTICIPAÇÃO percentual
 *                        e percentual ACUMULADO por categoria;
 *  - XLSX (`paraXlsx`) → planilha analítica: colunas derivadas POR LINHA (Nº =
 *                        posição no recorte; Dias desde <coluna de data>) + PIVÔ
 *                        TEMPORAL (Mês/Ano × categoria);
 *  - DOCX (`paraDocx`) → síntese executiva: registro mais antigo e mais recente,
 *                        amplitude do período, média de registros por dia,
 *                        categoria menos frequente e quantidade de categorias.
 *
 * ## Por que existe (ponto de função IFPUG)
 *
 * Três exportações que entregam o MESMO dado com formatação diferente contam como
 * UMA saída externa. Para que os três sejam reconhecidos como saídas distintas,
 * cada formato precisa de DERs próprios e lógica de processamento própria — e os
 * conjuntos exclusivos são disjuntos por construção, o que está coberto por teste.
 *
 * A derivação é INCONDICIONAL: sem coluna de data os campos temporais saem "—" ou
 * nulos, mas o perfil continua sendo gerado. O processo elementar não muda
 * conforme o dado — muda só o valor. Se um perfil se calasse em algum recorte, o
 * analista teria razão em dizer que "às vezes a saída é igual".
 *
 * ## Genérico por construção
 *
 * Nada aqui conhece a tela: a coluna categórica é eleita por título e
 * cardinalidade, e a coluna de data é detectada pelo FORMATO dos valores
 * (dd/mm/aaaa, hora opcional). Os valores chegam como texto já formatado — é
 * responsabilidade da tela, e é o contrato da exportação de listagens.
 */
class PerfilDadosListagem
{
    /**
     * @param  list<array<string, string>>  $registros
     * @param  array{coluna: string, chave: string, itens: list<array{rotulo: string, total: int}>}  $categoria
     * @param  array{chave: string, titulo: string}|null  $colunaData
     */
    private function __construct(
        private array $registros,
        private array $categoria,
        private ?array $colunaData,
    ) {}

    /**
     * @param  array<int, array{chave: string, titulo: string}>  $colunas
     * @param  list<array<string, string>>  $registros
     */
    public static function analisar(array $colunas, array $registros): self
    {
        return new self(
            $registros,
            self::elegerCategoria($colunas, $registros),
            self::detectarColunaData($colunas, $registros),
        );
    }

    // ── PDF — análise de distribuição (DERs exclusivos: participacao, acumulado) ─────────────

    /**
     * @return array{coluna: string, chave: string, itens: list<array{rotulo: string, total: int, participacao: string, acumulado: string}>}
     */
    public function paraPdf(): array
    {
        $total = max(1, count($this->registros));
        $acumulado = 0.0;
        $itens = [];

        foreach ($this->categoria['itens'] as $i) {
            $pct = $i['total'] * 100 / $total;
            $acumulado += $pct;
            $itens[] = [
                'rotulo' => $i['rotulo'],
                'total' => $i['total'],
                'participacao' => self::pct($pct),
                'acumulado' => self::pct(min(100, $acumulado)),
            ];
        }

        return ['coluna' => $this->categoria['coluna'], 'chave' => $this->categoria['chave'], 'itens' => $itens];
    }

    // ── XLSX — planilha analítica (DERs exclusivos: numero, dias_desde, pivô temporal) ───────

    /**
     * @return array{
     *     coluna_dias_titulo: string|null,
     *     linhas_derivadas: list<array{numero: int, dias_desde: int|null}>,
     *     pivot: array{coluna_categoria: string, categorias: list<string>, linhas: list<array{mes: string, valores: array<string, int>, total: int}>}
     * }
     */
    public function paraXlsx(): array
    {
        $hoje = Carbon::today();
        $derivadas = [];

        foreach ($this->registros as $n => $r) {
            $data = $this->colunaData ? self::data($r[$this->colunaData['chave']] ?? '') : null;
            $derivadas[] = [
                'numero' => $n + 1,
                'dias_desde' => $data !== null ? (int) $data->diffInDays($hoje) : null,
            ];
        }

        // Pivô Mês/Ano × categoria. Sem coluna de data, o recorte inteiro cai numa
        // faixa única — o pivô continua existindo (incondicionalidade), só sem a
        // dimensão temporal.
        $categorias = array_values(array_unique(array_map(
            fn (array $r): string => (string) ($r[$this->categoria['chave']] ?? '—'),
            $this->registros,
        )));

        $porMes = [];
        foreach ($this->registros as $r) {
            $data = $this->colunaData ? self::data($r[$this->colunaData['chave']] ?? '') : null;
            $mes = $data ? $data->format('m/Y') : self::FAIXA_UNICA;
            $cat = (string) ($r[$this->categoria['chave']] ?? '—');
            $porMes[$mes][$cat] = ($porMes[$mes][$cat] ?? 0) + 1;
        }

        uksort($porMes, static function (string $a, string $b): int {
            $ordenavel = static fn (string $m): string => $m === self::FAIXA_UNICA
                ? '9999-99'
                : substr($m, 3).'-'.substr($m, 0, 2);

            return $ordenavel($a) <=> $ordenavel($b);
        });

        $linhas = [];
        foreach ($porMes as $mes => $contagens) {
            /*
             * As contagens vão num sub-array `valores`, e não soltas na linha: uma
             * categoria chamada "mes" ou "total" — nada impede, o valor vem da
             * tela — sobrescreveria a chave reservada e o pivô sairia com o mês
             * errado ou o total trocado por uma contagem.
             */
            $valores = [];
            $soma = 0;
            foreach ($categorias as $cat) {
                $valores[$cat] = $contagens[$cat] ?? 0;
                $soma += $valores[$cat];
            }
            $linhas[] = ['mes' => (string) $mes, 'valores' => $valores, 'total' => $soma];
        }

        return [
            'coluna_dias_titulo' => $this->colunaData ? 'Dias desde '.$this->colunaData['titulo'] : null,
            'linhas_derivadas' => $derivadas,
            'pivot' => [
                'coluna_categoria' => $this->categoria['coluna'],
                'categorias' => $categorias,
                'linhas' => $linhas,
            ],
        ];
    }

    // ── DOCX — síntese executiva (DERs exclusivos: extremos, amplitude, média, menos frequente) ─

    /**
     * @return array{
     *     mais_antigo: string, mais_recente: string, amplitude_dias: int|null, media_por_dia: string,
     *     menos_frequente: string, categorias_distintas: int, coluna_categoria: string, coluna_data: string|null
     * }
     */
    public function paraDocx(): array
    {
        $datas = [];

        if ($this->colunaData) {
            foreach ($this->registros as $r) {
                if (($d = self::data($r[$this->colunaData['chave']] ?? '')) !== null) {
                    $datas[] = $d;
                }
            }
        }

        usort($datas, static fn (Carbon $a, Carbon $b): int => $a <=> $b);

        $antigo = $datas[0] ?? null;
        $recente = $datas !== [] ? $datas[count($datas) - 1] : null;
        $amplitude = ($antigo && $recente) ? (int) $antigo->diffInDays($recente) : null;

        // Média por DIA CORRIDO do período coberto, inclusive — só faz sentido com data.
        $media = $amplitude !== null
            ? number_format(count($this->registros) / ($amplitude + 1), 2, ',', '.')
            : '—';

        $itens = $this->categoria['itens'];

        return [
            'mais_antigo' => $antigo?->format('d/m/Y') ?? '—',
            'mais_recente' => $recente?->format('d/m/Y') ?? '—',
            'amplitude_dias' => $amplitude,
            'media_por_dia' => $media,
            'menos_frequente' => $itens !== [] ? $itens[count($itens) - 1]['rotulo'] : '—',
            'categorias_distintas' => count($itens),
            'coluna_categoria' => $this->categoria['coluna'],
            'coluna_data' => $this->colunaData['titulo'] ?? null,
        ];
    }

    /**
     * A contagem simples por categoria — compartilhada: é ela que alimenta o
     * gráfico do PDF.
     *
     * @return array{coluna: string, chave: string, itens: list<array{rotulo: string, total: int}>}
     */
    public function categoria(): array
    {
        return $this->categoria;
    }

    /** O rótulo da faixa temporal quando o recorte não tem coluna de data. */
    private const FAIXA_UNICA = '(recorte completo)';

    /** Acima disto o excedente é agrupado — legenda com 40 categorias não se lê. */
    private const MAX_CATEGORIAS = 20;

    // ── Detecções genéricas ──────────────────────────────────────────────────────────────────

    /**
     * Elege UMA coluna categórica: prefere títulos que soam a categoria, depois
     * cardinalidade útil (2 a 20 valores), depois a menor cardinalidade; acima do
     * teto, agrupa o excedente em "Outros (N)".
     *
     * SEMPRE devolve uma coluna — o endpoint exige pelo menos uma —, e a
     * incondicionalidade dos perfis depende disso.
     *
     * @param  array<int, array{chave: string, titulo: string}>  $colunas
     * @param  list<array<string, string>>  $registros
     * @return array{coluna: string, chave: string, itens: list<array{rotulo: string, total: int}>}
     */
    private static function elegerCategoria(array $colunas, array $registros): array
    {
        $preferidas = ['status', 'situa', 'tipo', 'origem', 'setor', 'categoria', 'fase', 'turno', 'porte', 'perfil', 'grupo', 'area', 'área'];

        $melhor = null;
        $melhorScore = PHP_INT_MIN;

        foreach ($colunas as $i => $c) {
            $valores = array_map(static fn (array $r) => $r[$c['chave']] ?? '—', $registros);
            $distintos = max(1, count(array_unique($valores)));
            $tituloNorm = mb_strtolower($c['titulo']);

            $pref = 0;
            foreach ($preferidas as $p) {
                if (str_contains($tituloNorm, $p)) {
                    $pref = 1;
                    break;
                }
            }

            $util = ($distintos >= 2 && $distintos <= self::MAX_CATEGORIAS) ? 1 : 0;
            // Pesos em ordens de grandeza separadas: preferência de título decide
            // primeiro, depois utilidade, depois cardinalidade, e a posição da
            // coluna desempata — sem isso a eleição mudaria de ideia à toa entre
            // duas colunas empatadas.
            $score = $pref * 1_000_000 + $util * 10_000 + (1_000 - $distintos) - $i;

            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = ['chave' => $c['chave'], 'coluna' => $c['titulo']];
            }
        }

        $melhor ??= ['chave' => $colunas[0]['chave'], 'coluna' => $colunas[0]['titulo']];

        $contagem = [];
        foreach ($registros as $r) {
            $v = (string) ($r[$melhor['chave']] ?? '—');
            $contagem[$v] = ($contagem[$v] ?? 0) + 1;
        }
        arsort($contagem);

        $itens = [];
        if (count($contagem) > self::MAX_CATEGORIAS) {
            $topo = array_slice($contagem, 0, self::MAX_CATEGORIAS - 1, true);
            foreach ($topo as $rotulo => $total) {
                $itens[] = ['rotulo' => (string) $rotulo, 'total' => $total];
            }
            $itens[] = [
                'rotulo' => 'Outros ('.(count($contagem) - count($topo)).')',
                'total' => array_sum($contagem) - array_sum($topo),
            ];
        } else {
            foreach ($contagem as $rotulo => $total) {
                $itens[] = ['rotulo' => (string) $rotulo, 'total' => $total];
            }
        }

        return ['coluna' => $melhor['coluna'], 'chave' => $melhor['chave'], 'itens' => $itens];
    }

    /**
     * Detecta a primeira coluna cujos valores têm cara de data BR (dd/mm/aaaa,
     * hora opcional) em pelo menos metade das linhas preenchidas. Os valores vêm
     * formatados pela tela — é o contrato.
     *
     * @param  array<int, array{chave: string, titulo: string}>  $colunas
     * @param  list<array<string, string>>  $registros
     * @return array{chave: string, titulo: string}|null
     */
    private static function detectarColunaData(array $colunas, array $registros): ?array
    {
        foreach ($colunas as $c) {
            $preenchidos = 0;
            $comData = 0;

            foreach ($registros as $r) {
                $v = trim((string) ($r[$c['chave']] ?? ''));

                if ($v === '' || $v === '—') {
                    continue;
                }

                $preenchidos++;

                if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $v) === 1) {
                    $comData++;
                }
            }

            if ($preenchidos > 0 && $comData * 2 >= $preenchidos) {
                return ['chave' => $c['chave'], 'titulo' => $c['titulo']];
            }
        }

        return null;
    }

    /** Data BR (hora opcional ignorada) → Carbon, ou null quando o valor não é data. */
    private static function data(string $valor): ?Carbon
    {
        if (preg_match('/^(\d{2}\/\d{2}\/\d{4})/', trim($valor), $m) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $m[1])->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Percentual em pt-BR com uma casa ("60,0%"). */
    private static function pct(float $v): string
    {
        return number_format($v, 1, ',', '.').'%';
    }
}
