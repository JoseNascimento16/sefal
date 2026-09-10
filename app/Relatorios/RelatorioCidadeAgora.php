<?php

namespace App\Relatorios;

use App\Relatorios\Contracts\Relatorio;
use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\FiltroDef;
use App\Relatorios\Suporte\ResultadoRelatorio;
use App\Support\Prototipo\EstruturaFicticia;
use App\Support\Prototipo\MapasFicticios;

/**
 * A cidade agora, em papel — o Mapa ao Vivo traduzido em relatório.
 *
 * O mapa responde "para onde eu mando gente hoje?" olhando. Este documento
 * responde a mesma coisa para quem NÃO está na frente da tela: a reunião de
 * manhã, o ofício que sai para a secretaria, o print que vai para o grupo. E
 * responde uma que o mapa não responde bem: **a lista dos retornos vencidos, em
 * ordem de atraso** — no mapa eles pulsam, mas ninguém consegue ditar um pino
 * por telefone.
 *
 * ⚠️ PROTÓTIPO: mesma fonte do mapa ({@see MapasFicticios::aoVivo()}), então o
 * documento e a tela mostram o mesmo instante. Não há tempo real — o relatório
 * diz de que momento está falando.
 */
class RelatorioCidadeAgora implements Relatorio
{
    public function chave(): string
    {
        return 'cidade-agora';
    }

    public function titulo(): string
    {
        return 'A cidade agora (Mapa ao Vivo)';
    }

    public function grupo(): string
    {
        return 'Fiscalização';
    }

    public function descricao(): string
    {
        return 'O que o Mapa ao Vivo mostra, em papel: pontos por situação, fiscais em campo, registros do dia e a lista dos retornos vencidos em ordem de atraso. Responde "para onde mandar gente hoje" para quem não está na frente da tela.';
    }

    public function filtros(): array
    {
        $areas = [['valor' => '', 'rotulo' => 'Toda a cidade']];

        foreach (EstruturaFicticia::nomesDeArea() as $area) {
            $areas[] = ['valor' => $area, 'rotulo' => $area];
        }

        $equipes = [['valor' => '', 'rotulo' => 'Todas as equipes']];

        foreach (EstruturaFicticia::codigosDeEquipe() as $codigo) {
            $equipes[] = ['valor' => $codigo, 'rotulo' => 'Equipe '.$codigo];
        }

        return [
            FiltroDef::select('area', 'Área', $areas),
            FiltroDef::select('equipe', 'Equipe', $equipes),
        ];
    }

    public function modos(): array
    {
        return [ContextoRelatorio::MODO_ANALITICO, ContextoRelatorio::MODO_SINTETICO];
    }

    public function gerar(ContextoRelatorio $contexto): ResultadoRelatorio
    {
        $area = trim((string) $contexto->filtro('area', ''));
        $equipe = trim((string) $contexto->filtro('equipe', ''));

        $vivo = MapasFicticios::aoVivo();

        $noRecorte = static fn (array $item): bool => ($area === '' || (string) ($item['area'] ?? '') === $area)
            && ($equipe === '' || (string) ($item['equipe'] ?? '') === $equipe);

        $pontos = array_values(array_filter($vivo['pontos'], $noRecorte));
        $registros = array_values(array_filter($vivo['registros'], $noRecorte));
        $fiscais = array_values(array_filter($vivo['fiscais'], $noRecorte));

        $resultado = new ResultadoRelatorio;
        $resultado->metadados['recorte'] = implode(' · ', [
            $area === '' ? 'toda a cidade' : $area,
            $equipe === '' ? 'todas as equipes' : 'Equipe '.$equipe,
            'situação em '.((string) $vivo['momento']),
        ]);

        // ── O quadro do dia ─────────────────────────────────────────────────
        $vencidos = array_values(array_filter(
            $pontos,
            static fn (array $p): bool => $p['retorno_ha_dias'] !== null,
        ));

        usort(
            $vencidos,
            static fn (array $a, array $b): int => (int) $b['retorno_ha_dias'] <=> (int) $a['retorno_ha_dias'],
        );

        $porSituacao = [];

        foreach ($pontos as $p) {
            $sit = (string) $p['situacao'];
            $porSituacao[$sit] = ($porSituacao[$sit] ?? 0) + 1;
        }

        arsort($porSituacao);

        $quadro = $resultado->secao('A cidade agora')
            ->coluna('indicador', 'Indicador')
            ->coluna('valor', 'Valor', 'numero', 'right');

        $quadro->linha(['indicador' => 'Pontos conhecidos no recorte', 'valor' => count($pontos)]);
        $quadro->linha(['indicador' => 'Retornos vencidos', 'valor' => count($vencidos)]);
        $quadro->linha(['indicador' => 'Fiscais em campo', 'valor' => count($fiscais)]);
        $quadro->linha(['indicador' => 'Registros no período mostrado', 'valor' => count($registros)]);

        foreach ($porSituacao as $sit => $quantos) {
            $quadro->linha(['indicador' => 'Pontos — '.$sit, 'valor' => $quantos]);
        }

        if ($contexto->querGraficos() && $porSituacao !== []) {
            $resultado->grafico(
                'barra',
                'Pontos por situação',
                array_keys($porSituacao),
                [['nome' => 'Pontos', 'valores' => array_values($porSituacao)]],
            );
        }

        // ── Os retornos vencidos, em ordem de atraso ────────────────────────
        // A razão de existir deste relatório: no mapa o retorno vencido pulsa,
        // mas ninguém dita um pino por telefone nem anexa um pulso a um ofício.
        $fila = $resultado->secao('Retornos vencidos — do mais antigo para o mais recente')
            ->coluna('dias', 'Dias', 'numero', 'right')
            ->coluna('ambulante', 'Ambulante')
            ->coluna('atividade', 'Atividade')
            ->coluna('bairro', 'Bairro')
            ->coluna('area', 'Área')
            ->coluna('equipe', 'Equipe')
            ->coluna('encarregado', 'Encarregado')
            ->coluna('ultima', 'Última fiscalização');

        foreach ($vencidos as $p) {
            $fila->linha([
                'dias' => (int) $p['retorno_ha_dias'],
                'ambulante' => (string) $p['nome'].' — '.(string) $p['apelido'],
                'atividade' => (string) $p['atividade'],
                'bairro' => (string) $p['bairro'],
                'area' => (string) $p['area'],
                'equipe' => (string) $p['equipe'],
                'encarregado' => (string) $p['encarregado'],
                'ultima' => (string) $p['ultima_em'],
            ]);
        }

        $fila->total('Retornos vencidos', count($vencidos));

        if ($contexto->ehSintetico()) {
            return $resultado;
        }

        // ── Quem está na rua ────────────────────────────────────────────────
        $rua = $resultado->secao('Fiscais em campo')
            ->coluna('fiscal', 'Fiscal')
            ->coluna('matricula', 'Matrícula')
            ->coluna('equipe', 'Equipe')
            ->coluna('area', 'Área')
            ->coluna('bairro', 'Último ponto')
            ->coluna('registros', 'Registrou hoje', 'numero', 'right');

        foreach ($fiscais as $f) {
            $rua->linha([
                'fiscal' => (string) $f['nome'],
                'matricula' => (string) $f['matricula'],
                'equipe' => (string) $f['equipe'],
                'area' => (string) $f['area'],
                'bairro' => (string) $f['bairro'],
                'registros' => (int) $f['registros_hoje'],
            ]);
        }

        // ── Os últimos registros ────────────────────────────────────────────
        $ultimos = $registros;
        usort($ultimos, static fn (array $a, array $b): int => (int) $a['ha_minutos'] <=> (int) $b['ha_minutos']);

        $lista = $resultado->secao('Registros do período mostrado')
            ->coluna('protocolo', 'Protocolo')
            ->coluna('apelido', 'Ambulante')
            ->coluna('situacao', 'Situação')
            ->coluna('ocorrencia', 'Ocorrência')
            ->coluna('bairro', 'Bairro')
            ->coluna('equipe', 'Equipe')
            ->coluna('fiscal', 'Fiscal');

        foreach ($ultimos as $r) {
            $lista->linha([
                'protocolo' => (string) $r['protocolo'],
                'apelido' => (string) $r['apelido'],
                'situacao' => (string) $r['situacao'],
                'ocorrencia' => (string) ($r['ocorrencia'] ?? '—'),
                'bairro' => (string) $r['bairro'],
                'equipe' => (string) $r['equipe'],
                'fiscal' => (string) $r['fiscal'],
            ]);
        }

        $lista->total('Registros listados', count($ultimos));

        return $resultado;
    }
}
