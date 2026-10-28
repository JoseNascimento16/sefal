<?php

namespace App\Support\SaudeFluxos;

/**
 * O conhecimento do fluxo (passo a passo e formas de conclusão) fora da tela, em Markdown, para
 * guardar ou repassar. Sai do MESMO catálogo do fluxograma — a tela e o documento nunca contam
 * histórias diferentes.
 */
final class ConhecimentoDoFluxo
{
    /** @param  array<string, mixed>  $fluxo */
    public static function markdown(array $fluxo): string
    {
        $linhas = ['# '.$fluxo['titulo'].' — '.$fluxo['subtitulo'], '', $fluxo['resumo'], '', '## Passo a passo', ''];
        foreach ($fluxo['etapas'] as $i => $etapa) {
            $linhas[] = '### '.($i + 1).'. '.$etapa['setor'].' — '.$etapa['titulo'];
            foreach ($etapa['itens'] as $item) {
                $linhas[] = '- '.$item;
            }
            $linhas[] = '';
        }

        $previstos = array_filter($fluxo['nos'], fn (array $no) => $no['situacao'] === CatalogoFluxos::PREVISTO);
        if ($previstos !== []) {
            $linhas[] = '## Trechos previstos (ainda não atendidos pelo sistema)';
            $linhas[] = '';
            foreach ($previstos as $no) {
                $linhas[] = '- **'.str_replace("\n", ' — ', $no['rotulo']).':** '.$no['descricao'];
            }
            $linhas[] = '';
        }

        $linhas[] = '## Todas as formas de conclusão';
        $linhas[] = '';
        $linhas[] = '| # | Conclusão | Quem encerra | Caminho |';
        $linhas[] = '|---|---|---|---|';
        foreach ($fluxo['conclusoes'] as $i => $c) {
            $linhas[] = '| '.($i + 1).' | '.$c['conclusao'].' | '.$c['quem'].' | '.$c['caminho'].' |';
        }

        return implode("\n", $linhas)."\n";
    }
}
