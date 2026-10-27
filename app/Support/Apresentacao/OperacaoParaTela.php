<?php

namespace App\Support\Apresentacao;

use App\Models\Operacao;

/**
 * A operacao do banco na forma que as telas leem.
 *
 * Mesma razao do {@see DemandaParaTela}: apresentacao num lugar so, para o model
 * nao conhecer a tela e a tela nao conhecer o model. `periodo` sai montado
 * daqui porque tres telas mostram a mesma etiqueta — escrita em cada uma, elas
 * discordariam no primeiro ajuste de redacao.
 */
class OperacaoParaTela
{
    /** @return array<string, mixed> */
    public static function completa(Operacao $operacao): array
    {
        $equipes = $operacao->relationLoaded('equipes')
            ? $operacao->equipes->pluck('codigo')->values()->all()
            : [];

        $bairros = $operacao->relationLoaded('bairros')
            ? $operacao->bairros->pluck('bairro')->values()->all()
            : [];

        return [
            'id' => $operacao->id,
            'codigo' => $operacao->codigo,
            'nome' => $operacao->nome,
            'area' => (string) ($operacao->area?->nome ?? ''),
            'regiao' => (string) ($operacao->regiao ?? ''),
            'foco' => (string) ($operacao->foco ?? ''),
            'observacao' => (string) ($operacao->observacao ?? ''),
            'equipes' => $equipes,
            'bairros' => $bairros,
            'inicio' => $operacao->inicio->format('Y-m-d'),
            'fim' => $operacao->fim?->format('Y-m-d'),
            'periodo' => self::etiquetaDoPeriodo($operacao),
            'situacao' => $operacao->situacao,
            'coordenador' => $operacao->coordenador?->name,
            'total_bairros' => count($bairros),
            'total_equipes' => count($equipes),
            'encerrada' => in_array($operacao->situacao, [Operacao::ENCERRADA, Operacao::CANCELADA], true),
            // Fiscais de qualquer área postos na operação (dono, 25/09/2026).
            'fiscais' => $operacao->relationLoaded('fiscais')
                ? $operacao->fiscais->sortBy('name')->map(static fn ($u): array => ['id' => $u->id, 'nome' => $u->name])->values()->all()
                : [],
            // As denúncias anexadas à operação — a fiscalização delas vai para a rua junto.
            'demandas' => $operacao->relationLoaded('demandas')
                ? $operacao->demandas->sortBy('protocolo')->map(static fn ($d): array => [
                    'id' => $d->id,
                    'protocolo' => $d->protocolo,
                    'assunto' => $d->assunto,
                    'bairro' => (string) ($d->bairro ?? ''),
                    'situacao' => $d->situacao,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * O periodo em palavras — "01/08/2026 a 30/09/2026", ou "a partir de
     * 01/02/2026" para a rotina permanente.
     *
     * Sem fim NAO vira "—": a rotina permanente e um fato do negocio (a Rotina
     * Centro varre o Comercio toda quinta, sem data para acabar), e um travessao
     * pareceria dado faltando.
     *
     * ⚠️ A redacao e a MESMA que o dono ja viu na demonstracao. Melhora-la agora
     * mudaria o texto de tres telas e do documento impresso por conta propria —
     * e a consolidacao nao e pretexto para redesenhar o que foi aprovado.
     */
    private static function etiquetaDoPeriodo(Operacao $operacao): string
    {
        $inicio = $operacao->inicio->format('d/m/Y');

        return $operacao->fim === null
            ? "a partir de {$inicio}"
            : "{$inicio} a ".$operacao->fim->format('d/m/Y');
    }
}
