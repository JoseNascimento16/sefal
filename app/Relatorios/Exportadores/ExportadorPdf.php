<?php

namespace App\Relatorios\Exportadores;

use App\Relatorios\Suporte\GraficoSvg;
use App\Relatorios\Suporte\ResultadoRelatorio;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporta um {@see ResultadoRelatorio} para PDF (dompdf sobre uma view Blade).
 *
 * Os gráficos são gerados como SVG no servidor ({@see GraficoSvg}) e embutidos
 * como imagem data-URI: o dompdf os desenha pelo php-svg-lib, sem depender da
 * extensão GD — então o documento sai igual no Windows de desenvolvimento e no
 * contêiner. `isRemoteEnabled` está ligado porque o dompdf trata data-URI no
 * mesmo caminho das imagens "remotas".
 */
class ExportadorPdf
{
    public function baixar(ResultadoRelatorio $resultado, string $nomeArquivo): Response
    {
        return $this->montar($resultado)->download($nomeArquivo.'.pdf');
    }

    /** Os bytes do PDF, para quem precisa guardá-lo em vez de entregá-lo. */
    public function gerar(ResultadoRelatorio $resultado): string
    {
        return (string) $this->montar($resultado)->output();
    }

    private function montar(ResultadoRelatorio $resultado): DocumentoPdf
    {
        $graficos = array_values(array_filter(array_map(
            static fn (array $g): string => GraficoSvg::html($g),
            $resultado->graficos,
        )));

        $orientacao = ($resultado->metadados['orientacao'] ?? 'portrait') === 'landscape'
            ? 'landscape'
            : 'portrait';

        return Pdf::loadView('relatorios.pdf', [
            'r' => $resultado->toArray(),
            'graficos' => $graficos,
        ])
            ->setPaper('a4', $orientacao)
            ->setOption(['isRemoteEnabled' => true]);
    }
}
