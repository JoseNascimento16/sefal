<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\Operacao;
use App\Models\User;

/**
 * As decisões da triagem e do direcionamento, aplicadas EM LOTE.
 *
 * Decisão de triagem acontece em lote porque a denúncia chega em lote: o
 * coordenador abre a tela de manhã com trinta casos, confere a área sugerida de
 * cada um e encaminha todos de uma vez. Uma a uma seriam trinta idas ao
 * servidor e trinta chances de a tela ficar velha no meio.
 *
 * ## O relatório de efeito é parte do contrato
 *
 * Todo método devolve `alteradas`, `ignoradas` e `resumo`. É o que permite à
 * tela dizer "22 encaminhadas (Área 1: 9 · Área 5: 13); 2 não foram encontradas
 * e ficaram como estavam" — em vez de "salvo com sucesso", que esconde
 * justamente o caso em que a listagem estava velha e alguém decidiu antes.
 *
 * Demanda que não está mais disponível é IGNORADA, nunca erro: quem clicou tinha
 * a tela antiga na frente, e derrubar as outras 29 por causa dela seria punir a
 * pessoa certa pelo motivo errado.
 *
 * ## Nada muda de situação sem deixar passo
 *
 * Toda mudança passa por {@see Demanda::registrar()}. É a lei do model, e este
 * serviço não tem atalho para ela.
 */
class TriagemDeDemandas
{
    /** @var array<string, int> */
    private array $resumo = [];

    private int $alteradas = 0;

    private int $ignoradas = 0;

    public function __construct(private readonly ?User $autor) {}

    /**
     * TRIAGEM — encaminha cada demanda à área que o coordenador confirmou.
     *
     * @param  array<int, string>  $areasPorId  id da demanda => nome da área
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public function encaminharAArea(array $areasPorId, ?string $observacao = null): array
    {
        foreach ($areasPorId as $id => $nomeDaArea) {
            $demanda = $this->disponivel((int) $id);
            $area = Area::where('nome', $nomeDaArea)->first();

            if ($demanda === null || $area === null) {
                $this->ignoradas++;

                continue;
            }

            $chefe = $area->chefeDeSetor;

            $demanda->registrar(
                acao: 'Triada e encaminhada à área',
                situacao: Demanda::ENCAMINHADA_A_AREA,
                papel: DemandaTramite::PAPEL_COORDENADOR,
                autor: $this->autor,
                detalhe: $this->texto($observacao) ?? "Encaminhada à {$area->nome} para direcionamento do Chefe de Setor.",
                campos: array_filter([
                    'Bairro que sugeriu a área' => $demanda->bairro,
                    'Área de destino' => $area->nome,
                    'Chefe de Setor' => $chefe?->name ?? 'área ainda sem chefe cadastrado',
                ]),
                mudancas: [
                    'area_id' => $area->id,
                    /*
                     * Encaminhar CANCELA a equipe de um direcionamento anterior: a
                     * denúncia voltou ao começo do fluxo, e deixar a equipe
                     * pendurada faria a tela mostrar "na mesa do chefe" com um
                     * time já escalado.
                     */
                    'equipe_id' => null,
                    'operacao_id' => null,
                ],
            );

            $this->contar($area->nome);
        }

        return $this->efeito();
    }

    /**
     * TRIAGEM — devolve ao canal de origem ou arquiva.
     *
     * @param  list<int>  $ids
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public function devolver(array $ids, string $motivo, string $justificativa, string $destino): array
    {
        $arquivar = $destino === 'Arquivada';

        foreach ($ids as $id) {
            $demanda = $this->disponivel((int) $id);

            if ($demanda === null) {
                $this->ignoradas++;

                continue;
            }

            $demanda->registrar(
                acao: $arquivar ? 'Arquivada na triagem' : 'Devolvida ao canal de origem',
                situacao: $arquivar ? Demanda::ARQUIVADA : Demanda::DEVOLVIDA,
                papel: DemandaTramite::PAPEL_COORDENADOR,
                autor: $this->autor,
                detalhe: $justificativa,
                campos: ['Motivo' => $motivo, 'Destino' => $destino],
            );

            $this->contar($destino);
        }

        return $this->efeito();
    }

    /**
     * DIRECIONAMENTO — o Chefe de Setor manda a equipe vistoriar.
     *
     * @param  list<int>  $ids
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public function direcionarAEquipe(array $ids, string $codigoDaEquipe, ?string $justificativa = null): array
    {
        $equipe = Equipe::with('area')->where('codigo', $codigoDaEquipe)->first();

        if ($equipe === null) {
            $this->ignoradas += count($ids);

            return $this->efeito();
        }

        foreach ($ids as $id) {
            $demanda = $this->disponivel((int) $id);

            if ($demanda === null) {
                $this->ignoradas++;

                continue;
            }

            // A equipe é de outra área? A tela já exigiu o porquê; aqui ele fica
            // registrado, porque é a explicação que sobra para quem ler depois.
            $deOutraArea = $demanda->area_id !== null && $demanda->area_id !== $equipe->area_id;

            $demanda->registrar(
                acao: 'Direcionada à equipe',
                situacao: Demanda::DIRECIONADA_A_EQUIPE,
                papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR,
                autor: $this->autor,
                detalhe: $this->texto($justificativa) ?? "Direcionada à {$equipe->rotulo()} para vistoria.",
                campos: array_filter([
                    'Saída escolhida' => 'Vistoria dirigida à equipe',
                    'Equipe escolhida' => $equipe->rotulo().' — '.($equipe->area?->nome ?? 'sem área'),
                    'Por que sai da equipe da área' => $deOutraArea ? $this->texto($justificativa) : null,
                ]),
                mudancas: [
                    'equipe_id' => $equipe->id,
                    // Direcionar a uma equipe tira a denúncia da operação: são
                    // duas saídas diferentes, e manter as duas faria a fila do
                    // aplicativo mostrar o caso em dois lugares.
                    'operacao_id' => null,
                    'area_id' => $demanda->area_id ?? $equipe->area_id,
                ],
            );

            $this->contar($equipe->rotulo());
        }

        return $this->efeito();
    }

    /**
     * DIRECIONAMENTO — o Chefe de Setor anexa as denúncias a uma operação.
     *
     * @param  list<int>  $ids
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public function anexarAOperacao(array $ids, Operacao $operacao, ?string $porQue = null): array
    {
        $equipe = $operacao->equipes->first();

        foreach ($ids as $id) {
            $demanda = $this->disponivel((int) $id);

            if ($demanda === null) {
                $this->ignoradas++;

                continue;
            }

            $demanda->registrar(
                acao: 'Incluída em operação',
                situacao: Demanda::EM_OPERACAO,
                papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR,
                autor: $this->autor,
                detalhe: $this->texto($porQue) ?? 'Anexada à '.$operacao->nome
                    .($equipe === null ? '.' : ", executada pela {$equipe->rotulo()}."),
                campos: array_filter([
                    'Saída escolhida' => 'Anexada a operação já planejada',
                    'Operação' => $operacao->nome,
                    'Período' => $operacao->inicio->format('d/m/Y')
                        .($operacao->fim === null ? ' — sem data de encerramento' : ' a '.$operacao->fim->format('d/m/Y')),
                    'Equipe da operação' => $equipe?->rotulo(),
                ]),
                mudancas: [
                    'operacao_id' => $operacao->id,
                    'equipe_id' => $equipe?->id,
                    'area_id' => $operacao->area_id,
                ],
            );

            $this->contar($operacao->nome);
        }

        return $this->efeito();
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /**
     * A demanda, se ela ainda aceita decisão.
     *
     * Recusa a que já fechou e a que está AGREGADA a outra: quem leva o caso a
     * campo é a principal, e decidir sobre a agregada duplicaria o trabalho que
     * o agrupamento existe para evitar.
     */
    private function disponivel(int $id): ?Demanda
    {
        $demanda = Demanda::find($id);

        if ($demanda === null || $demanda->agregada() || in_array($demanda->situacao, Demanda::FECHADAS, true)) {
            return null;
        }

        return $demanda;
    }

    private function contar(string $destino): void
    {
        $this->alteradas++;
        $this->resumo[$destino] = ($this->resumo[$destino] ?? 0) + 1;
    }

    /** Texto útil, ou nulo — string em branco é ausência, não conteúdo. */
    private function texto(?string $valor): ?string
    {
        $limpo = trim((string) $valor);

        return $limpo === '' ? null : $limpo;
    }

    /** @return array{alteradas: int, ignoradas: int, resumo: array<string, int>} */
    private function efeito(): array
    {
        return ['alteradas' => $this->alteradas, 'ignoradas' => $this->ignoradas, 'resumo' => $this->resumo];
    }
}
