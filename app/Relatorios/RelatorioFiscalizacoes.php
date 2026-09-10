<?php

namespace App\Relatorios;

use App\Relatorios\Contracts\Relatorio;
use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\FiltroDef;
use App\Relatorios\Suporte\ResultadoRelatorio;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\FiscalizacoesFicticias;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Fiscalizações concluídas em rua — por período e por área.
 *
 * O relatório que a chefia leva para a reunião: quantas vistorias a área fez no
 * período, COMO elas terminaram e quantas geraram documento. O desfecho é a
 * coluna que importa, porque é ela que mostra se a fiscalização está sendo
 * educativa (o ambulante desmonta e sai) ou punitiva (notificação e apreensão) —
 * e essa proporção é a pergunta política do serviço, não um detalhe.
 *
 * ⚠️ PROTÓTIPO: os registros são os mesmos que a tela de Fiscalizações mostra
 * ({@see FiscalizacoesFicticias}), então relatório e tela nunca discordam. Quando
 * a fiscalização virar tabela, muda a fonte aqui — o formato do documento fica.
 */
class RelatorioFiscalizacoes implements Relatorio
{
    public function chave(): string
    {
        return 'fiscalizacoes';
    }

    public function titulo(): string
    {
        return 'Fiscalizações concluídas';
    }

    public function grupo(): string
    {
        return 'Fiscalização';
    }

    public function descricao(): string
    {
        return 'O que as equipes concluíram em rua no período, por área: desfecho, documento lavrado e prazo de retorno. Responde "quanto a área produziu e como os casos terminaram".';
    }

    public function filtros(): array
    {
        $areas = [['valor' => '', 'rotulo' => 'Todas as áreas']];

        foreach (EstruturaFicticia::nomesDeArea() as $area) {
            $areas[] = ['valor' => $area, 'rotulo' => $area];
        }

        return [
            FiltroDef::data('data_inicial', 'Concluída a partir de'),
            FiltroDef::data('data_final', 'Concluída até'),
            FiltroDef::select('area', 'Área', $areas),
        ];
    }

    public function modos(): array
    {
        return [ContextoRelatorio::MODO_ANALITICO, ContextoRelatorio::MODO_SINTETICO];
    }

    public function gerar(ContextoRelatorio $contexto): ResultadoRelatorio
    {
        $de = $this->data($contexto->filtro('data_inicial'));
        $ate = $this->data($contexto->filtro('data_final'));
        $area = trim((string) $contexto->filtro('area', ''));

        $registros = array_values(array_filter(
            FiscalizacoesFicticias::registros(),
            function (array $r) use ($de, $ate, $area): bool {
                $quando = Date::parse((string) $r['concluida_em'])->startOfDay();

                return ($de === null || $quando->gte($de))
                    && ($ate === null || $quando->lte($ate))
                    && ($area === '' || (string) $r['area'] === $area);
            },
        ));

        $resultado = new ResultadoRelatorio;
        $resultado->metadados['recorte'] = $this->recorteEmPalavras($de, $ate, $area);

        // ── Como os casos terminaram ────────────────────────────────────────
        // Primeiro quadro de propósito: é a resposta que a chefia vai repetir na
        // reunião. O detalhamento vem depois, para quem quiser conferir linha a
        // linha.
        $porDesfecho = [];
        $comDocumento = 0;

        foreach ($registros as $r) {
            $desfecho = (string) ($r['desfecho'] ?? 'Sem desfecho registrado');
            $porDesfecho[$desfecho] = ($porDesfecho[$desfecho] ?? 0) + 1;

            if (is_array($r['documento'] ?? null)) {
                $comDocumento++;
            }
        }

        arsort($porDesfecho);
        $total = count($registros);

        $desfechos = $resultado->secao('Como os casos terminaram')
            ->coluna('desfecho', 'Desfecho')
            ->coluna('quantidade', 'Fiscalizações', 'numero', 'right')
            ->coluna('fatia', 'Fatia', 'texto', 'right');

        foreach ($porDesfecho as $desfecho => $quantos) {
            $desfechos->linha([
                'desfecho' => $desfecho,
                'quantidade' => $quantos,
                'fatia' => $total > 0 ? round($quantos / $total * 100).'%' : '—',
            ]);
        }

        $desfechos->total('Total de fiscalizações', $total);
        $desfechos->total('Com documento lavrado', $comDocumento);
        $desfechos->total(
            'Resolvidas sem documento',
            $total - $comDocumento,
        );

        // ── Produção por área e equipe ──────────────────────────────────────
        $porEquipe = [];

        foreach ($registros as $r) {
            $chave = (string) $r['area'].'|'.(string) $r['equipe'];
            $porEquipe[$chave] ??= ['area' => (string) $r['area'], 'equipe' => (string) $r['equipe'], 'quantas' => 0, 'documentos' => 0];
            $porEquipe[$chave]['quantas']++;

            if (is_array($r['documento'] ?? null)) {
                $porEquipe[$chave]['documentos']++;
            }
        }

        ksort($porEquipe);

        $equipes = $resultado->secao('Produção por área e equipe')
            ->coluna('area', 'Área')
            ->coluna('equipe', 'Equipe')
            ->coluna('quantas', 'Fiscalizações', 'numero', 'right')
            ->coluna('documentos', 'Com documento', 'numero', 'right');

        foreach ($porEquipe as $linha) {
            $equipes->linha($linha);
        }

        if ($contexto->querGraficos() && $porDesfecho !== []) {
            $resultado->grafico(
                'barra',
                'Fiscalizações por desfecho',
                array_keys($porDesfecho),
                [['nome' => 'Fiscalizações', 'valores' => array_values($porDesfecho)]],
            );
        }

        // No modo síntese o documento para aqui: quem pede síntese quer o número,
        // não as linhas.
        if ($contexto->ehSintetico()) {
            return $resultado;
        }

        // ── O detalhamento ──────────────────────────────────────────────────
        $detalhe = $resultado->secao('Fiscalizações do período')
            ->coluna('concluida', 'Concluída em')
            ->coluna('protocolo', 'Protocolo')
            ->coluna('area', 'Área')
            ->coluna('equipe', 'Equipe')
            ->coluna('fiscal', 'Fiscal')
            ->coluna('endereco', 'Ponto')
            ->coluna('desfecho', 'Desfecho')
            ->coluna('documento', 'Documento')
            ->coluna('prazo', 'Prazo de retorno')
            ->coluna('estado', 'Estado na fila');

        foreach ($registros as $r) {
            $documento = is_array($r['documento'] ?? null)
                ? trim(((string) ($r['documento']['tipo'] ?? '')).' '.((string) ($r['documento']['numero'] ?? '')))
                : '—';

            $prazo = is_array($r['prazo'] ?? null)
                ? (string) ($r['prazo']['texto'] ?? '—')
                : '—';

            $detalhe->linha([
                'concluida' => Date::parse((string) $r['concluida_em'])->format('d/m/Y'),
                'protocolo' => (string) $r['protocolo'],
                'area' => (string) $r['area'],
                'equipe' => (string) $r['equipe'],
                'fiscal' => (string) $r['fiscal'],
                'endereco' => (string) $r['endereco'].' — '.(string) $r['bairro'],
                'desfecho' => (string) ($r['desfecho'] ?? '—'),
                'documento' => $documento === '' ? '—' : $documento,
                'prazo' => $prazo,
                'estado' => (string) $r['estado'],
            ]);
        }

        $detalhe->total('Fiscalizações listadas', $total);

        return $resultado;
    }

    private function data(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : Date::parse($texto)->startOfDay();
    }

    /**
     * O recorte escrito no cabeçalho do documento.
     *
     * Documento sem o recorte impresso é o defeito clássico do relatório: alguém
     * arquiva a folha, encontra-a três meses depois e não sabe se aquele número
     * é do mês, do ano ou da cidade toda.
     */
    private function recorteEmPalavras(?Carbon $de, ?Carbon $ate, string $area): string
    {
        $partes = [];

        if ($de !== null && $ate !== null) {
            $partes[] = 'de '.$de->format('d/m/Y').' a '.$ate->format('d/m/Y');
        } elseif ($de !== null) {
            $partes[] = 'a partir de '.$de->format('d/m/Y');
        } elseif ($ate !== null) {
            $partes[] = 'até '.$ate->format('d/m/Y');
        } else {
            $partes[] = 'todo o período disponível';
        }

        $partes[] = $area === '' ? 'todas as áreas' : $area;

        return implode(' · ', $partes);
    }
}
