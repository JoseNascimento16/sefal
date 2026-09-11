<?php

namespace App\Relatorios;

use App\Relatorios\Contracts\Relatorio;
use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\FiltroDef;
use App\Relatorios\Suporte\ResultadoRelatorio;
use App\Support\Prototipo\DenunciasFicticias;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Denúncias recebidas — por origem, período e situação.
 *
 * A pergunta que a coordenação leva para a reunião com as ouvidorias: quantas
 * denúncias cada canal mandou, quantas ainda esperam triagem e quantas
 * viraram trabalho de rua. O PRAZO é a coluna que cobra: denúncia vencida na
 * mesa é o que o cidadão sente, e é o que o e-Salvador e o Salvador Digital
 * perguntam.
 *
 * ⚠️ PROTÓTIPO: mesma fonte das telas de Denúncias ({@see DenunciasFicticias}),
 * para relatório e tela não contarem números diferentes.
 */
class RelatorioDenuncias implements Relatorio
{
    public function chave(): string
    {
        return 'denuncias';
    }

    public function titulo(): string
    {
        return 'Denúncias recebidas';
    }

    public function grupo(): string
    {
        return 'Fiscalização';
    }

    public function descricao(): string
    {
        return 'Denúncias por canal de origem, período e situação, com o que ainda espera triagem e o que já virou trabalho de rua. Responde "quanto cada ouvidoria mandou e onde as denúncias estão paradas".';
    }

    public function filtros(): array
    {
        $canais = [['valor' => '', 'rotulo' => 'Todas as origens']];

        foreach ((array) config('prototipo_denuncias.canais', []) as $chave => $canal) {
            $canais[] = [
                'valor' => (string) $chave,
                'rotulo' => (string) ($canal['nome'] ?? $chave),
            ];
        }

        $situacoes = [['valor' => '', 'rotulo' => 'Todas as situações']];

        foreach ((array) config('prototipo_denuncias.situacoes', []) as $situacao) {
            $situacoes[] = ['valor' => (string) $situacao, 'rotulo' => (string) $situacao];
        }

        return [
            FiltroDef::select('canal', 'Origem', $canais),
            FiltroDef::data('data_inicial', 'Recebida a partir de'),
            FiltroDef::data('data_final', 'Recebida até'),
            FiltroDef::select('situacao', 'Situação', $situacoes),
        ];
    }

    public function modos(): array
    {
        return [ContextoRelatorio::MODO_ANALITICO, ContextoRelatorio::MODO_SINTETICO];
    }

    public function gerar(ContextoRelatorio $contexto): ResultadoRelatorio
    {
        $canal = trim((string) $contexto->filtro('canal', ''));
        $situacao = trim((string) $contexto->filtro('situacao', ''));
        $de = $this->data($contexto->filtro('data_inicial'));
        $ate = $this->data($contexto->filtro('data_final'));

        $denuncias = array_values(array_filter(
            DenunciasFicticias::todas(),
            function (array $d) use ($canal, $situacao, $de, $ate): bool {
                $quando = Date::parse((string) $d['recebida_em'])->startOfDay();

                return ($canal === '' || (string) $d['canal'] === $canal)
                    && ($situacao === '' || (string) $d['situacao'] === $situacao)
                    && ($de === null || $quando->gte($de))
                    && ($ate === null || $quando->lte($ate));
            },
        ));

        $nomesDeCanal = [];

        foreach ((array) config('prototipo_denuncias.canais', []) as $chave => $dados) {
            $nomesDeCanal[(string) $chave] = (string) ($dados['nome'] ?? $chave);
        }

        $resultado = new ResultadoRelatorio;
        $resultado->metadados['recorte'] = $this->recorteEmPalavras($canal, $nomesDeCanal, $de, $ate, $situacao);

        $total = count($denuncias);
        $vencidas = 0;
        $anonimas = 0;
        $porCanal = [];
        $porSituacao = [];

        foreach ($denuncias as $d) {
            $nome = $nomesDeCanal[(string) $d['canal']] ?? (string) $d['canal'];
            $porCanal[$nome] = ($porCanal[$nome] ?? 0) + 1;

            $sit = (string) $d['situacao'];
            $porSituacao[$sit] = ($porSituacao[$sit] ?? 0) + 1;

            if ((int) ($d['prazo']['dias'] ?? 0) < 0) {
                $vencidas++;
            }

            if ((bool) ($d['anonima'] ?? false)) {
                $anonimas++;
            }
        }

        arsort($porSituacao);

        // ── Por origem ──────────────────────────────────────────────────────
        $origens = $resultado->secao('Por origem')
            ->coluna('canal', 'Canal')
            ->coluna('quantidade', 'Denúncias', 'numero', 'right')
            ->coluna('fatia', 'Fatia', 'texto', 'right');

        foreach ($porCanal as $nome => $quantos) {
            $origens->linha([
                'canal' => $nome,
                'quantidade' => $quantos,
                'fatia' => $total > 0 ? round($quantos / $total * 100).'%' : '—',
            ]);
        }

        $origens->total('Total de denúncias', $total);
        $origens->total('Com prazo vencido', $vencidas);
        $origens->total('Anônimas', $anonimas);

        // ── Onde elas estão ─────────────────────────────────────────────────
        $estados = $resultado->secao('Em que pé estão')
            ->coluna('situacao', 'Situação')
            ->coluna('quantidade', 'Denúncias', 'numero', 'right')
            ->coluna('vencidas', 'Com prazo vencido', 'numero', 'right');

        foreach ($porSituacao as $sit => $quantos) {
            $vencidasNaSituacao = count(array_filter(
                $denuncias,
                static fn (array $d): bool => (string) $d['situacao'] === $sit && (int) ($d['prazo']['dias'] ?? 0) < 0,
            ));

            $estados->linha([
                'situacao' => $sit,
                'quantidade' => $quantos,
                'vencidas' => $vencidasNaSituacao,
            ]);
        }

        if ($contexto->querGraficos() && $porSituacao !== []) {
            $resultado->grafico(
                'barra',
                'Denúncias por situação',
                array_keys($porSituacao),
                [['nome' => 'Denúncias', 'valores' => array_values($porSituacao)]],
            );
        }

        if ($contexto->ehSintetico()) {
            return $resultado;
        }

        // ── O detalhamento ──────────────────────────────────────────────────
        $detalhe = $resultado->secao('Denúncias do recorte')
            ->coluna('protocolo', 'Protocolo')
            ->coluna('origem', 'Origem')
            ->coluna('numero_origem', 'Nº na origem')
            ->coluna('recebida', 'Recebida em')
            ->coluna('assunto', 'Assunto')
            ->coluna('bairro', 'Bairro')
            ->coluna('area', 'Área')
            ->coluna('situacao', 'Situação')
            ->coluna('prazo', 'Prazo');

        foreach ($denuncias as $d) {
            $detalhe->linha([
                'protocolo' => (string) $d['protocolo'],
                'origem' => $nomesDeCanal[(string) $d['canal']] ?? (string) $d['canal'],
                'numero_origem' => (string) $d['protocolo_origem'],
                'recebida' => Date::parse((string) $d['recebida_em'])->format('d/m/Y'),
                'assunto' => (string) $d['assunto'],
                'bairro' => (string) $d['bairro'],
                'area' => (string) ($d['area'] ?? '—'),
                'situacao' => (string) $d['situacao'],
                'prazo' => (string) ($d['prazo']['texto'] ?? '—'),
            ]);
        }

        $detalhe->total('Denúncias listadas', $total);

        return $resultado;
    }

    private function data(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : Date::parse($texto)->startOfDay();
    }

    /** @param  array<string, string>  $nomesDeCanal */
    private function recorteEmPalavras(string $canal, array $nomesDeCanal, ?Carbon $de, ?Carbon $ate, string $situacao): string
    {
        $partes = [$canal === '' ? 'todas as origens' : ($nomesDeCanal[$canal] ?? $canal)];

        if ($de !== null && $ate !== null) {
            $partes[] = 'de '.$de->format('d/m/Y').' a '.$ate->format('d/m/Y');
        } elseif ($de !== null) {
            $partes[] = 'a partir de '.$de->format('d/m/Y');
        } elseif ($ate !== null) {
            $partes[] = 'até '.$ate->format('d/m/Y');
        } else {
            $partes[] = 'todo o período disponível';
        }

        $partes[] = $situacao === '' ? 'todas as situações' : $situacao;

        return implode(' · ', $partes);
    }
}
