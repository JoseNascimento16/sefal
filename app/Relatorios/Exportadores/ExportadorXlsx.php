<?php

namespace App\Relatorios\Exportadores;

use App\Relatorios\Suporte\ResultadoRelatorio;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta um {@see ResultadoRelatorio} para XLSX (PhpSpreadsheet): uma seção
 * após a outra (título, cabeçalho, linhas, totais), separadas por linha em
 * branco.
 *
 * Dois comportamentos são opt-in por metadado, para não mudar os relatórios que
 * já usam este exportador:
 *
 *  - `listagem_grade` → congela o cabeçalho da tabela e liga o autofiltro;
 *  - `pivot_aba`      → cria a aba "Resumo" com o pivô temporal.
 */
class ExportadorXlsx
{
    /** Petróleo da identidade do SEFAL — o mesmo cabeçalho de tabela dos outros formatos. */
    private const COR_CABECALHO = '0D5C63';

    public function baixar(ResultadoRelatorio $resultado, string $nomeArquivo): StreamedResponse
    {
        $dados = $resultado->toArray();

        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Relatório');

        if (($dados['metadados']['orientacao'] ?? '') === 'landscape') {
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        }

        $maxColunas = 1;
        foreach ($dados['secoes'] as $s) {
            $maxColunas = max($maxColunas, count($s['colunas']));
        }
        $ultimaCol = self::col($maxColunas);

        // Título e resumo do recorte, cada um numa faixa mesclada do topo.
        $sheet->setCellValue('A1', (string) ($dados['metadados']['titulo'] ?? 'Relatório'));
        $sheet->mergeCells("A1:{$ultimaCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);

        $geradoEm = ($dados['metadados']['gerado_em'] ?? '') !== ''
            ? 'Gerado em '.(string) $dados['metadados']['gerado_em']
            : '';
        $sheet->setCellValue('A2', implode('  ·  ', array_filter([
            (string) ($dados['metadados']['filtros_resumo'] ?? ''),
            $geradoEm,
        ])));
        $sheet->mergeCells("A2:{$ultimaCol}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        $linha = 4;

        $gradeAtiva = ! empty($dados['metadados']['listagem_grade']);
        $gradeCabecalho = null;
        $gradeUltimaLinha = null;

        foreach ($dados['secoes'] as $secao) {
            if (! empty($secao['titulo'])) {
                $sheet->setCellValue('A'.$linha, (string) $secao['titulo']);
                $sheet->mergeCells('A'.$linha.":{$ultimaCol}".$linha);
                $sheet->getStyle('A'.$linha)->getFont()->setBold(true)->setSize(12);
                $linha++;
            }

            if ($gradeAtiva && $gradeCabecalho === null) {
                $gradeCabecalho = $linha;
            }

            foreach ($secao['colunas'] as $i => $c) {
                $celula = self::col($i + 1).$linha;
                $sheet->setCellValue($celula, (string) $c['titulo']);
                $sheet->getStyle($celula)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($celula)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_CABECALHO);
                // Título centralizado; as células de dados seguem o alinhamento da coluna.
                $sheet->getStyle($celula)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $linha++;

            foreach ($secao['linhas'] as $registro) {
                foreach ($secao['colunas'] as $i => $c) {
                    $celula = self::col($i + 1).$linha;
                    $this->escreverCelula($sheet, $celula, $registro[$c['chave']] ?? '');

                    $alinhar = $c['alinhar'] ?? 'left';
                    if ($alinhar === 'right') {
                        $sheet->getStyle($celula)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } elseif ($alinhar === 'center') {
                        $sheet->getStyle($celula)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
                $linha++;
            }

            if ($gradeAtiva && $gradeUltimaLinha === null) {
                $gradeUltimaLinha = $linha - 1;
            }

            foreach ($secao['totais'] as $t) {
                $linha = $this->escreverTotal($sheet, $linha, $secao['colunas'], $t, $maxColunas, $ultimaCol);
            }

            $linha++; // espaço entre seções
        }

        // Grade: o cabeçalho fica congelado (o resto rola por baixo) e o
        // autofiltro nasce ligado — quem exportou uma listagem vai continuar
        // filtrando na planilha.
        if ($gradeAtiva && $gradeCabecalho !== null && $gradeUltimaLinha !== null && $gradeUltimaLinha >= $gradeCabecalho) {
            $sheet->freezePane('A'.($gradeCabecalho + 1));
            $sheet->setAutoFilter('A'.$gradeCabecalho.':'.$ultimaCol.$gradeUltimaLinha);
        }

        foreach (range(1, $maxColunas) as $i) {
            $sheet->getColumnDimension(self::col($i))->setAutoSize(true);
        }

        if (! empty($dados['metadados']['pivot_aba'])) {
            $this->abaPivot($ss, $dados['metadados']['pivot_aba']);
        }

        // A aba de DADOS é a que se deve ver ao abrir: `createSheet()` da aba
        // "Resumo" a deixaria ativa, e o arquivo abriria no lugar errado.
        $ss->setActiveSheetIndex(0);

        $writer = new Xlsx($ss);

        return response()->streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, $nomeArquivo.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Grava o CONTEÚDO de uma célula sem deixar o Excel interpretá-lo como fórmula.
     *
     * ⚠️ SEGURANÇA. `setCellValue()` passa pelo `DefaultValueBinder` do
     * PhpSpreadsheet, que converte qualquer string começando com `=` em fórmula de
     * VERDADE. Como as células vêm do banco — apelido digitado em rua, relato de
     * fiscalização, razão social —, bastava alguém gravar
     * `=HYPERLINK("http://…"&A1)` num campo de texto para a planilha executar
     * aquilo na máquina de quem a abrisse. É injeção de fórmula, não teoria.
     *
     * Só o caso perigoso é desviado: valor textual que começa com `=`, `+`, `-` ou
     * `@` vai como TEXTO explícito. Número continua número (`-5` e `+3` caem no
     * ramo numérico antes daqui), para não quebrar soma e ordenação.
     */
    private function escreverCelula(Worksheet $sheet, string $celula, mixed $valor): void
    {
        if (is_string($valor) && $valor !== '' && ! is_numeric($valor) && str_contains('=+-@', $valor[0])) {
            $sheet->setCellValueExplicit($celula, $valor, DataType::TYPE_STRING);

            return;
        }

        $sheet->setCellValue($celula, $valor);
    }

    /**
     * Uma linha de total. `celulas` posiciona cada valor SOB a coluna a que ele se
     * refere; sem elas, a linha vira uma faixa destacada de largura inteira.
     *
     * @param  array<int, array<string, mixed>>  $colunas
     * @param  array{rotulo: string, valor: string, celulas: array<string, string>}  $total
     * @return int a próxima linha livre
     */
    private function escreverTotal(Worksheet $sheet, int $linha, array $colunas, array $total, int $maxColunas, string $ultimaCol): int
    {
        $sheet->setCellValue('A'.$linha, $total['rotulo']);
        $sheet->getStyle('A'.$linha)->getFont()->setBold(true);

        if ($total['celulas'] !== []) {
            foreach ($colunas as $i => $c) {
                if (! isset($total['celulas'][$c['chave']])) {
                    continue;
                }

                $celula = self::col($i + 1).$linha;
                $this->escreverCelula($sheet, $celula, $total['celulas'][$c['chave']]);
                $sheet->getStyle($celula)->getFont()->setBold(true);
                $sheet->getStyle($celula)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            return $linha + 1;
        }

        if ($total['valor'] !== '' && $maxColunas > 1) {
            $this->escreverCelula($sheet, 'B'.$linha, $total['valor']);
            $sheet->getStyle('B'.$linha)->getFont()->setBold(true);

            return $linha + 1;
        }

        $sheet->mergeCells('A'.$linha.":{$ultimaCol}".$linha);
        $sheet->getStyle('A'.$linha.":{$ultimaCol}".$linha)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7EFEF');

        return $linha + 1;
    }

    /**
     * Aba "Resumo" em formato de PIVÔ: uma linha por Mês/Ano do recorte, uma
     * coluna por valor da coluna categórica, com total por linha e por coluna.
     *
     * @param  array{coluna_categoria: string, categorias: list<string>, linhas: list<array{mes: string, valores: array<string, int>, total: int}>}  $pivot
     */
    private function abaPivot(Spreadsheet $ss, array $pivot): void
    {
        $aba = $ss->createSheet();
        $aba->setTitle('Resumo');

        $cabecalho = array_merge(['Mês/Ano'], $pivot['categorias'], ['Total']);
        foreach ($cabecalho as $i => $titulo) {
            $this->escreverCelula($aba, self::col($i + 1).'1', (string) $titulo);
        }

        $ultimaCol = self::col(count($cabecalho));
        $faixa = "A1:{$ultimaCol}1";
        $aba->getStyle($faixa)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $aba->getStyle($faixa)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COR_CABECALHO);

        $linha = 2;
        $totaisPorCategoria = array_fill_keys($pivot['categorias'], 0);

        foreach ($pivot['linhas'] as $registro) {
            $this->escreverCelula($aba, 'A'.$linha, (string) $registro['mes']);

            foreach ($pivot['categorias'] as $i => $categoria) {
                $valor = (int) ($registro['valores'][$categoria] ?? 0);
                $aba->setCellValue(self::col($i + 2).$linha, $valor);
                $totaisPorCategoria[$categoria] += $valor;
            }

            $aba->setCellValue(self::col(count($pivot['categorias']) + 2).$linha, (int) $registro['total']);
            $linha++;
        }

        $this->escreverCelula($aba, 'A'.$linha, 'TOTAL');
        $geral = 0;
        foreach ($pivot['categorias'] as $i => $categoria) {
            $aba->setCellValue(self::col($i + 2).$linha, $totaisPorCategoria[$categoria]);
            $geral += $totaisPorCategoria[$categoria];
        }
        $aba->setCellValue(self::col(count($pivot['categorias']) + 2).$linha, $geral);
        $aba->getStyle('A'.$linha.":{$ultimaCol}".$linha)->getFont()->setBold(true);

        foreach (range(1, count($cabecalho)) as $i) {
            $aba->getColumnDimension(self::col($i))->setAutoSize(true);
        }
    }

    /**
     * A letra da coluna a partir do índice (1 → A).
     *
     * Não é `chr(64 + $i)`: a partir da 27ª coluna aquilo devolve `[`, `\`, `]` —
     * coordenada inválida que estoura a geração no meio do arquivo. O teto de
     * colunas da exportação é 30.
     */
    private static function col(int $indice): string
    {
        return Coordinate::stringFromColumnIndex($indice);
    }
}
