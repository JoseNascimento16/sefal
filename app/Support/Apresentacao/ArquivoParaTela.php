<?php

namespace App\Support\Apresentacao;

use App\Models\DemandaAnexo;
use App\Models\FiscalizacaoFoto;
use Illuminate\Support\Facades\Storage;

/**
 * Um ARQUIVO do processo na forma que a tela lê — a foto tirada em campo ou o
 * anexo que veio com a demanda (pedido do dono, 25/09/2026: "deve ser possível
 * visualizar e baixar os arquivos da fiscalização/processo").
 *
 * Os dois viram a mesma coisa na tela: nome, se é imagem (para a miniatura), os
 * dois endereços — ver e baixar — e se o arquivo existe mesmo no disco. Arquivo
 * que sumiu do disco aparece DITO, e não como link que dá erro.
 *
 * O endereço leva só o NÚMERO do registro: o nome do arquivo nunca vai para a
 * URL (é por onde se atravessa diretório, e o WAF barra assinatura de SQLi).
 */
class ArquivoParaTela
{
    /** O disco privado: o arquivo só sai pela rota, que confere quem pede. */
    public const DISCO = 'local';

    /** @return array{id: int, nome: string, imagem: bool, disponivel: bool, url: string, baixar: string} */
    public static function foto(FiscalizacaoFoto $foto): array
    {
        $url = route('retaguarda.fiscalizacoes.foto', $foto->id);

        return [
            'id' => $foto->id,
            'nome' => (string) ($foto->legenda ?? basename($foto->caminho)),
            'imagem' => true,
            'disponivel' => Storage::disk(self::DISCO)->exists($foto->caminho),
            'url' => $url,
            'baixar' => $url.'?baixar=1',
        ];
    }

    /** @return array{id: int, nome: string, imagem: bool, disponivel: bool, url: string, baixar: string} */
    public static function anexo(DemandaAnexo $anexo): array
    {
        $url = route('retaguarda.denuncias.anexo', $anexo->id);

        return [
            'id' => $anexo->id,
            'nome' => $anexo->nome,
            'imagem' => self::ehImagem($anexo->tipo, $anexo->nome),
            'disponivel' => Storage::disk(self::DISCO)->exists($anexo->caminho),
            'url' => $url,
            'baixar' => $url.'?baixar=1',
        ];
    }

    public static function ehImagem(?string $tipo, string $nome): bool
    {
        if ($tipo !== null && str_starts_with($tipo, 'image/')) {
            return true;
        }

        return in_array(strtolower(pathinfo($nome, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }
}
