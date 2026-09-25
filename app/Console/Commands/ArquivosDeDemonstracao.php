<?php

namespace App\Console\Commands;

use App\Models\DemandaAnexo;
use App\Models\FiscalizacaoFoto;
use App\Support\Apresentacao\ArquivoParaTela;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Gera os ARQUIVOS que faltam aos dados de demonstração.
 *
 * As fotos das vistorias e os anexos das demandas semeadas existem só como
 * registro — nome e caminho —, porque o protótipo nasceu sem arquivo. Com a
 * tela de ver e baixar (25/09/2026), cada um desses registros apareceria como
 * "arquivo não encontrado". Este comando cria, no lugar de cada arquivo que
 * falta, um arquivo de DEMONSTRAÇÃO que diz o que é: uma imagem escrita
 * "Foto de demonstração" com o nome, ou um PDF de uma página.
 *
 * Só mexe no que FALTA — arquivo que existe nunca é sobrescrito. É para a
 * demonstração (roda no boot do Render, cujo disco é efêmero) e para o
 * ambiente local; não é para homologação nem produção, onde arquivo que falta é
 * falha a investigar, e não lacuna a tapar.
 */
class ArquivosDeDemonstracao extends Command
{
    protected $signature = 'sefal:arquivos-de-demonstracao';

    protected $description = 'Cria arquivos de demonstração para fotos e anexos semeados que não existem no disco.';

    public function handle(): int
    {
        $disco = Storage::disk(ArquivoParaTela::DISCO);
        $criados = 0;

        foreach (FiscalizacaoFoto::query()->cursor() as $foto) {
            if (! $disco->exists($foto->caminho)) {
                $disco->put($foto->caminho, $this->imagem('Foto de demonstração', (string) ($foto->legenda ?? basename($foto->caminho))));
                $criados++;
            }
        }

        foreach (DemandaAnexo::query()->cursor() as $anexo) {
            if ($disco->exists($anexo->caminho)) {
                continue;
            }

            $disco->put(
                $anexo->caminho,
                ArquivoParaTela::ehImagem($anexo->tipo, $anexo->nome)
                    ? $this->imagem('Anexo de demonstração', $anexo->nome)
                    : $this->pdf($anexo->nome),
            );
            $criados++;
        }

        $this->info($criados === 1 ? '1 arquivo de demonstração criado.' : "{$criados} arquivos de demonstração criados.");

        return self::SUCCESS;
    }

    /** Uma imagem JPEG que diz o que é — nunca passa por foto de verdade. */
    private function imagem(string $titulo, string $nome): string
    {
        $imagem = imagecreatetruecolor(800, 600);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 226, 232, 240));
        $escuro = imagecolorallocate($imagem, 30, 41, 59);
        $fraco = imagecolorallocate($imagem, 100, 116, 139);

        imagestring($imagem, 5, 40, 250, $this->ascii($titulo), $escuro);
        imagestring($imagem, 4, 40, 285, $this->ascii($nome), $fraco);
        imagestring($imagem, 3, 40, 540, 'SEFAL - ambiente de demonstracao, dados ficticios', $fraco);

        ob_start();
        imagejpeg($imagem, null, 80);
        imagedestroy($imagem);

        return (string) ob_get_clean();
    }

    /** Um PDF mínimo, de uma página, que diz o que é. */
    private function pdf(string $nome): string
    {
        $texto = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->ascii('Anexo de demonstracao: '.$nome));
        $conteudo = "BT /F1 16 Tf 60 760 Td ({$texto}) Tj 0 -28 Td /F1 11 Tf (SEFAL - ambiente de demonstracao, dados ficticios) Tj ET";

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($conteudo)." >>\nstream\n{$conteudo}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $posicoes = [];

        foreach ($objetos as $i => $objeto) {
            $posicoes[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$objeto}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";

        foreach ($posicoes as $posicao) {
            $pdf .= sprintf("%010d 00000 n \n", $posicao);
        }

        return $pdf.'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /** A fonte embutida do GD e do PDF mínimo só conhece ASCII: tira os acentos. */
    private function ascii(string $texto): string
    {
        return (string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto);
    }
}
