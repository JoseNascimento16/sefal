<?php

namespace App\Support;

use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\Operacao;
use App\Models\User;

/**
 * As decisões do encaminhamento e do direcionamento, aplicadas EM LOTE.
 *
 * Decisão acontece em lote porque a demanda chega em lote: o Chefe de Setor abre
 * a Caixa de manhã com trinta casos, confere a equipe sugerida de cada um e
 * encaminha todos de uma vez. Uma a uma seriam trinta idas ao servidor e trinta
 * chances de a tela ficar velha no meio.
 *
 * ## Quem faz o quê (decisão do dono, 22/09/2026)
 *
 *  - o **Chefe de Setor** ENCAMINHA a um líder de equipe ({@see encaminharAoLider}),
 *    DEVOLVE ao canal ({@see devolver}) ou ANEXA a uma operação;
 *  - o **líder de equipe** DIRECIONA aos fiscais ({@see direcionarAosFiscais}).
 *
 * Até 22/09 a primeira etapa era do coordenador e escolhia uma ÁREA; o chefe da
 * área escolhia a equipe. Os coordenadores não usam o sistema — trabalham no
 * e-Salvador — e o chefe passou a ser um só, que escolhe a equipe. A área
 * continua existindo (é dela que sai a equipe sugerida para um bairro), mas
 * deixou de ser destino.
 *
 * ## O relatório de efeito é parte do contrato
 *
 * Todo método devolve `alteradas`, `ignoradas` e `resumo`. É o que permite à
 * tela dizer "22 encaminhadas (C2: 9 · A5: 13); 2 não foram encontradas e
 * ficaram como estavam" — em vez de "salvo com sucesso", que esconde justamente
 * o caso em que a listagem estava velha e alguém decidiu antes.
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
     * ENCAMINHAMENTO — o Chefe de Setor manda cada demanda ao líder da equipe
     * que confirmou.
     *
     * A equipe é sugerida pelo bairro e confirmada pelo chefe: bairro de divisa
     * pertence a duas áreas, e a escolha é dele. O que fica registrado é a equipe
     * E o líder dela, porque "encaminhei para a C2" só diz metade — a outra
     * metade é para quem.
     *
     * @param  array<int, string>  $equipesPorId  id da demanda => código da equipe
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public function encaminharAoLider(array $equipesPorId, ?string $observacao = null): array
    {
        foreach ($equipesPorId as $id => $codigoDaEquipe) {
            $demanda = $this->disponivel((int) $id);
            $equipe = Equipe::with(['area', 'lider'])->where('codigo', $codigoDaEquipe)->first();

            if ($demanda === null || $equipe === null) {
                $this->ignoradas++;

                continue;
            }

            $demanda->registrar(
                acao: 'Encaminhada ao líder de equipe',
                situacao: Demanda::ENCAMINHADA_AO_LIDER,
                papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR,
                autor: $this->autor,
                detalhe: $this->texto($observacao)
                    ?? "Encaminhada à {$equipe->rotulo()} para o líder direcionar aos fiscais.",
                campos: array_filter([
                    'Bairro que sugeriu a equipe' => $demanda->bairro,
                    'Equipe de destino' => $equipe->rotulo().' — '.($equipe->area?->nome ?? 'sem área'),
                    'Líder da equipe' => $equipe->lider?->name
                        ?? ($equipe->encarregado !== null
                            ? $equipe->encarregado.' (ainda sem conta no sistema)'
                            : 'equipe ainda sem líder cadastrado'),
                ]),
                mudancas: [
                    'equipe_id' => $equipe->id,
                    'area_id' => $equipe->area_id,
                    /*
                     * Encaminhar CANCELA a operação de um passo anterior: a
                     * demanda voltou ao começo do fluxo, e deixá-la pendurada
                     * numa operação faria a fila do aplicativo mostrá-la em dois
                     * lugares.
                     */
                    'operacao_id' => null,
                ],
            );

            $this->contar($equipe->rotulo());
        }

        return $this->efeito();
    }

    /**
     * ENCAMINHAMENTO — o Chefe de Setor devolve ao canal de origem ou arquiva.
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
                acao: $arquivar ? 'Arquivada pelo Chefe de Setor' : 'Devolvida ao canal de origem',
                situacao: $arquivar ? Demanda::ARQUIVADA : Demanda::DEVOLVIDA,
                papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR,
                autor: $this->autor,
                detalhe: $justificativa,
                campos: ['Motivo' => $motivo, 'Destino' => $destino],
            );

            $this->contar($destino);
        }

        return $this->efeito();
    }

    /**
     * DIRECIONAMENTO — o líder manda a própria equipe vistoriar.
     *
     * A equipe já está na demanda (o chefe a escolheu ao encaminhar); o que o
     * líder faz é mandar o ponto para a fila do aplicativo dos fiscais, com a
     * orientação dele. Não há como o líder trocar a equipe aqui: se a demanda
     * veio para a equipe errada, o caminho é devolver ao chefe.
     *
     * @param  list<int>  $ids
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public function direcionarAosFiscais(array $ids, ?string $orientacao = null): array
    {
        foreach ($ids as $id) {
            $demanda = $this->disponivel((int) $id);

            if ($demanda === null) {
                $this->ignoradas++;

                continue;
            }

            $equipe = $demanda->equipe;

            // Sem equipe não há a quem direcionar: é o caso da demanda que ainda
            // não passou pelo chefe. Ignorada e contada, nunca "direcionada a
            // ninguém".
            if ($equipe === null) {
                $this->ignoradas++;

                continue;
            }

            $demanda->registrar(
                acao: 'Direcionada aos fiscais',
                situacao: Demanda::DIRECIONADA_AOS_FISCAIS,
                papel: DemandaTramite::PAPEL_LIDER,
                autor: $this->autor,
                detalhe: $this->texto($orientacao) ?? "Enviada à fila da {$equipe->rotulo()} para vistoria.",
                campos: array_filter([
                    'Equipe' => $equipe->rotulo().' — '.($equipe->area?->nome ?? 'sem área'),
                    'Orientação aos fiscais' => $this->texto($orientacao),
                ]),
                mudancas: [
                    // Direcionar aos fiscais tira a demanda da operação: são duas
                    // saídas diferentes, e manter as duas faria a fila do
                    // aplicativo mostrar o caso em dois lugares.
                    'operacao_id' => null,
                ],
            );

            $this->contar($equipe->rotulo());
        }

        return $this->efeito();
    }

    /**
     * Anexa as demandas a uma operação já planejada.
     *
     * Pode ser ato do chefe (ao encaminhar) ou do líder (ao direcionar), e o
     * trâmite guarda QUAL dos dois: por isso o papel vem de quem chama.
     *
     * @param  list<int>  $ids
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public function anexarAOperacao(
        array $ids,
        Operacao $operacao,
        ?string $porQue = null,
        string $papel = DemandaTramite::PAPEL_CHEFE_DE_SETOR,
    ): array {
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
                papel: $papel,
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
        $demanda = Demanda::with('equipe.area')->find($id);

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
