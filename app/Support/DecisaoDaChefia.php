<?php

namespace App\Support;

use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Fiscalizacao;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * O que a CHEFIA faz com o que a equipe trouxe da rua — e "chefia", desde
 * 22/09/2026, são dois papéis: o LÍDER DE EQUIPE, que lê o retorno da própria
 * equipe, e o CHEFE DE SETOR, que lê tudo e cobre a ausência do líder.
 *
 * Três saídas, e as três são atos administrativos — cada uma deixa passo:
 *
 *   **ciência**        — leu e aceitou. O caso segue o efeito do desfecho;
 *   **nova vistoria**  — o registro não resolve; a equipe volta ao ponto;
 *   **devolver**       — não é da equipe dele, ou a medida é de outra alçada: o
 *                        caso volta à mesa do Chefe de Setor com o motivo escrito.
 *
 * O PAPEL com que o passo é assinado vem de quem chama ({@see __construct}):
 * "o líder deu ciência" e "o chefe deu ciência" são fatos diferentes mesmo
 * quando a mesma pessoa acumula os dois setores.
 *
 * ## O leque de volta acontece AQUI
 *
 * Quando a fiscalização veio de uma denúncia que agrupou outras, dar ciência
 * responde **todas as agregadas de uma vez** ({@see Demanda::responderAgregadas}).
 * É a razão de o agrupamento existir: a equipe vai uma vez, e os dez cidadãos que
 * relataram o mesmo fato são respondidos.
 *
 * ## O que o desfecho decide, e o que a chefia decide
 *
 * A chefia decide se aceita o registro. O que acontece com a DEMANDA é do
 * desfecho ({@see Fiscalizacao::EFEITO_NA_DEMANDA}) — notificação emitida deixa
 * prazo correndo e o caso não encerra, por mais que o chefe concorde com tudo.
 * Misturar as duas coisas deixaria a chefia "concluindo" caso com prazo aberto.
 */
class DecisaoDaChefia
{
    /** @var array<string, int> */
    private array $resumo = [];

    private int $alterados = 0;

    private int $ignorados = 0;

    private int $agregadasRespondidas = 0;

    /**
     * @param  string  $papel  com que papel o autor assina — {@see Papel::papelDoTramite}
     */
    public function __construct(
        private readonly ?User $autor,
        private readonly string $papel = DemandaTramite::PAPEL_LIDER,
    ) {}

    /**
     * CIÊNCIA — a chefia leu, aceitou, e o caso segue o efeito do desfecho.
     *
     * @param  list<int>  $ids
     * @return array{alterados: int, ignorados: int, resumo: array<string, int>, agregadas: int}
     */
    public function darCiencia(array $ids, ?string $observacao = null): array
    {
        foreach ($ids as $id) {
            $registro = $this->pendente((int) $id);

            if ($registro === null) {
                $this->ignorados++;

                continue;
            }

            $this->carimbar($registro, Fiscalizacao::CIENTE, $observacao);

            $this->repercutirNaDemanda($registro, $observacao);
            $this->contar(Fiscalizacao::CIENTE);
        }

        return $this->efeito();
    }

    /**
     * NOVA VISTORIA — o registro não resolve; a equipe volta ao ponto.
     *
     * A demanda volta a `Direcionada aos fiscais` porque é exatamente onde ela
     * estava antes da ida: o trabalho é o mesmo, refeito. Deixá-la "em campo"
     * faria a fila do aplicativo mostrar uma vistoria que ninguém está fazendo.
     *
     * @param  list<int>  $ids
     * @return array{alterados: int, ignorados: int, resumo: array<string, int>, agregadas: int}
     */
    public function pedirNovaVistoria(array $ids, string $justificativa): array
    {
        foreach ($ids as $id) {
            $registro = $this->pendente((int) $id);

            if ($registro === null) {
                $this->ignorados++;

                continue;
            }

            $this->carimbar($registro, Fiscalizacao::NOVA_VISTORIA, $justificativa);

            $registro->demanda?->registrar(
                acao: 'Nova vistoria determinada',
                situacao: Demanda::DIRECIONADA_AOS_FISCAIS,
                papel: $this->papel,
                autor: $this->autor,
                detalhe: $justificativa,
                campos: [
                    'Registro que motivou' => $registro->protocolo,
                    'Desfecho que não resolveu' => (string) ($registro->desfecho ?? '—'),
                ],
            );

            $this->contar(Fiscalizacao::NOVA_VISTORIA);
        }

        return $this->efeito();
    }

    /**
     * DEVOLVER — o caso volta à mesa do Chefe de Setor, com o motivo escrito.
     *
     * A demanda volta a `Recebida`, que é a mesa do Chefe de Setor, e perde
     * equipe e operação: o trabalho foi desfeito, e deixar o time pendurado faria
     * a fila do aplicativo continuar mostrando o caso a quem já não responde por
     * ele. A ÁREA fica — ela é o histórico de para onde o caso foi, e apagá-la
     * tiraria do chefe a informação que ele precisa para re-encaminhar.
     *
     * @param  list<int>  $ids
     * @return array{alterados: int, ignorados: int, resumo: array<string, int>, agregadas: int}
     */
    public function devolverAoChefe(array $ids, string $motivo): array
    {
        foreach ($ids as $id) {
            $registro = $this->pendente((int) $id);

            if ($registro === null) {
                $this->ignorados++;

                continue;
            }

            $this->carimbar($registro, Fiscalizacao::DEVOLVIDA, $motivo);

            $registro->demanda?->registrar(
                acao: 'Devolvida ao Chefe de Setor',
                situacao: Demanda::RECEBIDA,
                papel: $this->papel,
                autor: $this->autor,
                detalhe: $motivo,
                campos: [
                    'Registro de campo' => $registro->protocolo,
                    'Equipe que devolveu' => (string) ($registro->equipe?->rotulo() ?? '—'),
                ],
                mudancas: ['equipe_id' => null, 'operacao_id' => null],
            );

            $this->contar(Fiscalizacao::DEVOLVIDA);
        }

        return $this->efeito();
    }

    // ── O trabalho ──────────────────────────────────────────────────────────

    /**
     * Grava a decisão no registro: o estado, QUEM decidiu e QUANDO.
     *
     * As três coisas juntas, num lugar só, porque decisão sem autor é decisão
     * sem dono — e na fiscalização avulsa não há demanda atrás para guardar essa
     * informação por ela.
     */
    private function carimbar(Fiscalizacao $registro, string $situacao, ?string $detalhe = null): void
    {
        $registro->situacao = $situacao;
        $registro->decisao_detalhe = $detalhe;
        $registro->decidida_por_id = $this->autor?->id;
        $registro->decidida_em = Date::now();
        $registro->save();
    }

    /**
     * O efeito da ciência sobre a demanda — e sobre as denúncias agregadas a ela.
     */
    private function repercutirNaDemanda(Fiscalizacao $registro, ?string $observacao): void
    {
        $demanda = $registro->demanda;

        if ($demanda === null) {
            // Fiscalização avulsa não tem processo atrás: ela se encerra na
            // leitura da chefia, e isso já está gravado na situação dela.
            return;
        }

        $desfecho = (string) ($registro->desfecho ?? '');
        $situacao = Fiscalizacao::EFEITO_NA_DEMANDA[$desfecho] ?? Demanda::CONCLUIDA;

        $demanda->registrar(
            acao: 'Retorno de campo lido pela chefia',
            situacao: $situacao,
            papel: $this->papel,
            autor: $this->autor,
            detalhe: $observacao ?? $registro->consideracoes,
            campos: array_filter([
                'Registro de campo' => $registro->protocolo,
                'Desfecho' => $desfecho === '' ? null : $desfecho,
                'Documento lavrado' => $registro->documento?->rotulo(),
                'Prazo do documento' => $registro->documento?->prazo_ate?->format('d/m/Y'),
            ]),
            quando: Date::now(),
        );

        /*
         * O leque de volta: a mesma resposta vai para todas as denúncias que
         * relatavam o mesmo fato. É por isso que a equipe foi UMA vez.
         */
        if ($desfecho !== '' && $this->autor !== null) {
            $this->agregadasRespondidas += $demanda->responderAgregadas($desfecho, $this->autor, $situacao);
        }
    }

    /**
     * O registro, se ele ainda espera a leitura da chefia.
     *
     * Recusa o que já foi decidido: quem clicou tinha a listagem antiga na
     * frente, e decidir duas vezes escreveria dois atos sobre o mesmo fato.
     */
    private function pendente(int $id): ?Fiscalizacao
    {
        $registro = Fiscalizacao::with(['demanda', 'documento', 'equipe.area'])->find($id);

        return $registro !== null && $registro->situacao === Fiscalizacao::AGUARDANDO_LEITURA
            ? $registro
            : null;
    }

    private function contar(string $destino): void
    {
        $this->alterados++;
        $this->resumo[$destino] = ($this->resumo[$destino] ?? 0) + 1;
    }

    /** @return array{alterados: int, ignorados: int, resumo: array<string, int>, agregadas: int} */
    private function efeito(): array
    {
        return [
            // Masculino: o que se conta aqui são REGISTROS de campo, e o recado
            // da tela concorda com eles. (Na triagem são denúncias, e lá é
            // feminino — os dois serviços contam coisas diferentes.)
            'alterados' => $this->alterados,
            'ignorados' => $this->ignorados,
            'resumo' => $this->resumo,
            'agregadas' => $this->agregadasRespondidas,
        ];
    }
}
