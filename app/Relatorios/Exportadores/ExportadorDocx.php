<?php

namespace App\Relatorios\Exportadores;

use App\Relatorios\Suporte\ResultadoRelatorio;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta um {@see ResultadoRelatorio} para DOCX (PhpWord) — uma seção (tabela)
 * após a outra, com o parágrafo de contexto quando o documento traz um.
 *
 * O DOCX é o formato de quem vai EDITAR ou anexar o documento a um ofício: por
 * isso ele começa por texto corrido e não por planilha.
 */
class ExportadorDocx
{
    /** Petróleo da identidade do SEFAL — o mesmo do cabeçalho das tabelas do PDF. */
    private const COR_CABECALHO = '0D5C63';

    public function baixar(ResultadoRelatorio $resultado, string $nomeArquivo): StreamedResponse
    {
        $dados = $resultado->toArray();

        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);

        $sec = $word->addSection();

        $sec->addText((string) ($dados['metadados']['titulo'] ?? 'Relatório'), ['bold' => true, 'size' => 16]);

        $resumo = trim(
            (string) ($dados['metadados']['filtros_resumo'] ?? '')
            .'  ·  Gerado em '.(string) ($dados['metadados']['gerado_em'] ?? ''),
        );
        $sec->addText($resumo, ['italic' => true, 'size' => 9, 'color' => '6B8082']);

        // Parágrafo de contexto: é ele que dá ao DOCX cara de documento formal,
        // distinta do PDF analítico e da planilha de dados.
        if (! empty($dados['metadados']['intro'])) {
            $sec->addTextBreak(1);
            $sec->addText((string) $dados['metadados']['intro'], ['size' => 11], ['align' => 'both']);
        }

        foreach ($dados['secoes'] as $secao) {
            $sec->addTextBreak(1);

            if (! empty($secao['titulo'])) {
                $sec->addText((string) $secao['titulo'], ['bold' => true, 'size' => 13, 'color' => '15292B']);
            }

            $tabela = $sec->addTable([
                'borderSize' => 6,
                'borderColor' => 'CCCCCC',
                'cellMargin' => 60,
                'width' => 100 * 50,
                'unit' => 'pct',
            ]);

            $tabela->addRow();
            foreach ($secao['colunas'] as $c) {
                $tabela->addCell(null, ['bgColor' => self::COR_CABECALHO])
                    ->addText((string) $c['titulo'], ['bold' => true, 'color' => 'FFFFFF']);
            }

            foreach ($secao['linhas'] as $registro) {
                $tabela->addRow();
                foreach ($secao['colunas'] as $c) {
                    $tabela->addCell()->addText((string) ($registro[$c['chave']] ?? ''));
                }
            }

            foreach ($secao['totais'] as $t) {
                $sec->addText($t['rotulo'].($t['valor'] !== '' ? ': '.$t['valor'] : ''), ['bold' => true]);
            }
        }

        $writer = new Word2007($word);

        return response()->streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, $nomeArquivo.'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
