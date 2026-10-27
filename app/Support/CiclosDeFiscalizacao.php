<?php

namespace App\Support;

use App\Models\CicloDeFiscalizacao;
use App\Models\Demanda;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * O ciclo de vida da FISCALIZAÇÃO ({@see CicloDeFiscalizacao}) — um lugar só.
 *
 *  - ABRE quando o Chefe de Setor encaminha a demanda ao líder (e quando o líder
 *    registra o Fala Salvador): a posse é da EQUIPE;
 *  - as VISTORIAS entram nela — inclusive as de "mandar a equipe voltar";
 *  - o líder a ENCAMINHA ao chefe com o resultado: a posse passa ao CHEFE, a
 *    demanda volta à mesa dele e aparece na aba "Encaminhadas";
 *  - o chefe devolve o processo à ORIGEM: todas as Fiscalizações da demanda vão
 *    para o ARQUIVO. A Fiscalização sem demanda (ronda, operação) o chefe arquiva.
 *
 * Um novo encaminhamento do chefe abre uma Fiscalização IRMÃ; as anteriores
 * continuam consultáveis pela demanda, com vistorias, fotos e documentos.
 */
class CiclosDeFiscalizacao
{
    private int $alterados = 0;

    private int $ignorados = 0;

    public function __construct(private readonly ?User $autor = null) {}

    // ── Abrir e vincular ────────────────────────────────────────────────────

    /** Abre a Fiscalização de uma demanda que acabou de chegar ao líder. */
    public static function abrirParaDemanda(Demanda $demanda, ?User $autor = null): CicloDeFiscalizacao
    {
        return CicloDeFiscalizacao::create([
            'protocolo' => Protocolo::proximo('FSC', modelClass: CicloDeFiscalizacao::class),
            'origem' => Fiscalizacao::ORIGEM_DEMANDA,
            'demanda_id' => $demanda->id,
            // Sem equipe gravada (dado antigo), a da área: senão nenhum líder a veria.
            'equipe_id' => $demanda->equipe_id
                ?? Equipe::where('area_id', $demanda->area_id)->orderBy('id')->value('id'),
            'posse' => CicloDeFiscalizacao::POSSE_EQUIPE,
            'aberto_em' => Date::now(),
            'aberto_por_id' => $autor?->id,
        ]);
    }

    /** A Fiscalização da demanda que ainda está com a equipe, se houver. */
    public static function abertoDa(Demanda $demanda): ?CicloDeFiscalizacao
    {
        return CicloDeFiscalizacao::where('demanda_id', $demanda->id)
            ->whereNull('arquivado_em')
            ->where('posse', CicloDeFiscalizacao::POSSE_EQUIPE)
            ->latest('aberto_em')->latest('id')
            ->first();
    }

    /**
     * Põe a vistoria na Fiscalização a que ela pertence — a da demanda que está
     * com a equipe; ou uma nova, quando a vistoria não tem demanda (ronda,
     * operação) ou a demanda não tem Fiscalização aberta.
     */
    public static function vincular(Fiscalizacao $vistoria): CicloDeFiscalizacao
    {
        if ($vistoria->ciclo_id !== null) {
            return CicloDeFiscalizacao::findOrFail($vistoria->ciclo_id);
        }

        $ciclo = $vistoria->demanda !== null ? self::abertoDa($vistoria->demanda) : null;

        $ciclo ??= CicloDeFiscalizacao::create([
            'protocolo' => Protocolo::proximo('FSC', $vistoria->aberta_em, CicloDeFiscalizacao::class),
            'origem' => $vistoria->origem,
            'demanda_id' => $vistoria->demanda_id,
            'equipe_id' => $vistoria->equipe_id,
            'posse' => CicloDeFiscalizacao::POSSE_EQUIPE,
            'aberto_em' => $vistoria->aberta_em ?? Date::now(),
        ]);

        $vistoria->ciclo_id = $ciclo->id;
        $vistoria->save();

        return $ciclo;
    }

    /** Todas as Fiscalizações da demanda vão para o Arquivo — o processo voltou à origem. */
    public static function arquivarDa(Demanda $demanda): int
    {
        return CicloDeFiscalizacao::where('demanda_id', $demanda->id)
            ->whereNull('arquivado_em')
            ->update([
                'arquivado_em' => Date::now(),
                'posse' => CicloDeFiscalizacao::POSSE_CHEFE,
                'updated_at' => Date::now(),
            ]);
    }

    // ── Os atos ─────────────────────────────────────────────────────────────

    /**
     * ENCAMINHAR AO CHEFE DE SETOR — o líder devolve a Fiscalização com o
     * resultado (ou com o motivo, se o caso não é da equipe).
     *
     * A vistoria que esperava a leitura dele fica carimbada; a demanda volta à
     * mesa do chefe (`Recebida`, sem equipe) com o resultado no trâmite — é ele
     * quem delibera: responder à origem ou pedir nova fiscalização. As denúncias
     * agregadas recebem o mesmo desfecho (a equipe foi UMA vez).
     *
     * @param  list<int>  $ids  ids de Fiscalização (ciclo)
     * @return array{alterados: int, ignorados: int}
     */
    public function encaminharAoChefe(array $ids, string $motivo): array
    {
        foreach ($ids as $id) {
            $ciclo = CicloDeFiscalizacao::with(['vistorias.documento', 'demanda'])->find((int) $id);

            if ($ciclo === null || ! $ciclo->comAEquipe()) {
                $this->ignorados++;

                continue;
            }

            DB::transaction(function () use ($ciclo, $motivo) {
                $pendente = $ciclo->vistoriaPendente();

                if ($pendente !== null) {
                    $pendente->situacao = Fiscalizacao::DEVOLVIDA;
                    $pendente->decisao_detalhe = $motivo;
                    $pendente->decidida_por_id = $this->autor?->id;
                    $pendente->decidida_em = Date::now();
                    $pendente->save();
                }

                $resultado = $ciclo->vistorias
                    ->filter(static fn (Fiscalizacao $v): bool => $v->despachada_em !== null && $v->desfecho !== null)
                    ->last();

                $demanda = $ciclo->demanda;

                if ($demanda !== null && ! in_array($demanda->situacao, Demanda::FECHADAS, true)) {
                    $demanda->registrar(
                        acao: $resultado !== null ? 'Resultado encaminhado ao Chefe de Setor' : 'Encaminhada ao Chefe de Setor',
                        situacao: Demanda::RECEBIDA,
                        papel: Papel::papelDoTramite($this->autor, 'lider'),
                        autor: $this->autor,
                        detalhe: $motivo,
                        campos: array_filter([
                            'Fiscalização' => $ciclo->protocolo,
                            'Registro de campo' => $resultado?->protocolo,
                            'Desfecho' => $resultado?->desfecho,
                            'Documento lavrado' => $resultado?->documento?->rotulo(),
                        ]),
                        mudancas: ['equipe_id' => null, 'operacao_id' => null],
                    );

                    if ($resultado?->desfecho !== null && $this->autor !== null) {
                        $demanda->responderAgregadas(
                            (string) $resultado->desfecho,
                            $this->autor,
                            Fiscalizacao::EFEITO_NA_DEMANDA[$resultado->desfecho] ?? Demanda::CONCLUIDA,
                        );
                    }
                }

                $ciclo->forceFill([
                    'posse' => CicloDeFiscalizacao::POSSE_CHEFE,
                    'encaminhado_ao_chefe_em' => Date::now(),
                    'encaminhado_por_id' => $this->autor?->id,
                    'motivo_do_encaminhamento' => $motivo,
                ])->save();
            });

            $this->alterados++;
        }

        return $this->efeito();
    }

    /**
     * ARQUIVAR — o chefe encerra a Fiscalização que não tem processo atrás
     * (ronda, operação). A que tem demanda vai para o Arquivo quando o chefe
     * devolve o processo à origem, e só então.
     *
     * @param  list<int>  $ids
     * @return array{alterados: int, ignorados: int}
     */
    public function arquivar(array $ids): array
    {
        foreach ($ids as $id) {
            $ciclo = CicloDeFiscalizacao::find((int) $id);

            if ($ciclo === null || $ciclo->demanda_id !== null || $ciclo->aba() !== CicloDeFiscalizacao::ABA_ENCAMINHADAS) {
                $this->ignorados++;

                continue;
            }

            $ciclo->forceFill(['arquivado_em' => Date::now()])->save();
            $this->alterados++;
        }

        return $this->efeito();
    }

    /** @return array{alterados: int, ignorados: int} */
    private function efeito(): array
    {
        return ['alterados' => $this->alterados, 'ignorados' => $this->ignorados];
    }

    // ── O que já existia ────────────────────────────────────────────────────

    /**
     * Reconstrói as Fiscalizações do que foi registrado antes delas existirem
     * (24/09/2026): toda vistoria ganha a sua; toda demanda que está com a equipe
     * também. A posse sai do estado das coisas — com a equipe se há algo com ela,
     * com o chefe se não; e no Arquivo se o processo já voltou à origem (ou, sem
     * processo, se a vistoria já tinha sido lida).
     *
     * Idempotente: só mexe no que ainda não tem Fiscalização.
     */
    public static function sincronizarLegado(): void
    {
        $criados = [];

        Fiscalizacao::whereNull('ciclo_id')->with('demanda')->orderBy('aberta_em')->orderBy('id')
            ->each(function (Fiscalizacao $vistoria) use (&$criados) {
                $antes = $vistoria->demanda !== null ? self::abertoDa($vistoria->demanda)?->id : null;
                $ciclo = self::vincular($vistoria);

                if ($ciclo->id !== $antes) {
                    $criados[] = $ciclo->id;
                }
            });

        $comEquipe = [
            Demanda::ENCAMINHADA_AO_LIDER, Demanda::DIRECIONADA_AOS_FISCAIS, Demanda::EM_OPERACAO,
            Demanda::EM_CAMPO, Demanda::AGUARDANDO_REGULARIZACAO, Demanda::RETORNO_VENCIDO,
        ];

        Demanda::whereIn('situacao', $comEquipe)->whereNull('agrupada_em_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('fiscalizacao_ciclos')
                ->whereColumn('fiscalizacao_ciclos.demanda_id', 'demandas.id'))
            ->each(function (Demanda $demanda) use (&$criados) {
                $ciclo = self::abrirParaDemanda($demanda);
                $ciclo->forceFill(['aberto_em' => $demanda->updated_at ?? Date::now()])->save();
                $criados[] = $ciclo->id;
            });

        foreach (array_unique($criados) as $id) {
            $ciclo = CicloDeFiscalizacao::with(['vistorias', 'demanda'])->find($id);

            if ($ciclo === null) {
                continue;
            }

            $demanda = $ciclo->demanda;
            $comAEquipe = $demanda !== null
                ? in_array($demanda->situacao, $comEquipe, true) || $ciclo->vistoriaPendente() !== null
                : $ciclo->vistoriaPendente() !== null;

            $arquivar = $demanda !== null
                ? $demanda->respondida()
                : ! $comAEquipe && $ciclo->vistorias->every(static fn (Fiscalizacao $v): bool => $v->situacao === Fiscalizacao::CIENTE);

            $ciclo->forceFill([
                'posse' => $comAEquipe && ! $arquivar ? CicloDeFiscalizacao::POSSE_EQUIPE : CicloDeFiscalizacao::POSSE_CHEFE,
                'encaminhado_ao_chefe_em' => $comAEquipe ? null : ($ciclo->vistorias->last()?->decidida_em ?? $ciclo->aberto_em),
                'arquivado_em' => $arquivar ? Date::now() : null,
            ])->save();
        }
    }
}
