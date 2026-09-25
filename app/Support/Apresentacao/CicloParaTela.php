<?php

namespace App\Support\Apresentacao;

use App\Models\CicloDeFiscalizacao;
use App\Models\Demanda;
use App\Models\Fiscalizacao;

/**
 * A FISCALIZAÇÃO (o ciclo) na forma que as telas leem.
 *
 * Duas formas: a COMPLETA, para a tela Fiscalizações — com cada vistoria inteira
 * (relato, fotos, documento, recomendações) e as Fiscalizações irmãs da mesma
 * demanda —; e o RESUMO, para a demanda na Caixa de Entrada, que lista as
 * Fiscalizações dela com o caminho para abrir cada uma.
 */
class CicloParaTela
{
    public const POSSES = [
        CicloDeFiscalizacao::POSSE_EQUIPE => 'Equipe',
        CicloDeFiscalizacao::POSSE_CHEFE => 'Chefe de Setor',
    ];

    /**
     * Com QUEM está, em palavras (dono, 24/09/2026): com a equipe, a posse é do
     * LÍDER quando a decisão é dele — enviar aos fiscais, ou ler o retorno de
     * campo — e da EQUIPE quando os fiscais estão com o ponto.
     */
    public static function posse(CicloDeFiscalizacao $c): string
    {
        if ($c->posse === CicloDeFiscalizacao::POSSE_EQUIPE && $c->arquivado_em === null) {
            return $c->aDecidir() ? 'Líder de Equipe' : 'Equipe';
        }

        return self::POSSES[$c->posse] ?? $c->posse;
    }

    /** @return array<string, mixed> */
    public static function completo(CicloDeFiscalizacao $c): array
    {
        $demanda = $c->demanda;

        return [
            ...self::resumo($c),
            'origem' => match ($c->origem) {
                Fiscalizacao::ORIGEM_DEMANDA => 'Demanda',
                Fiscalizacao::ORIGEM_OPERACAO => 'Operação planejada',
                default => 'Ronda da equipe',
            },
            'area' => (string) ($c->equipe?->area?->nome ?? ''),
            'lider' => $c->equipe?->nomeDoLider(),
            'a_decidir' => $c->aDecidir(),
            'aguarda_envio' => $c->aguardaEnvioAEquipe(),
            'vistoria_pendente' => $c->vistoriaPendente()?->id,
            'encaminhado_ao_chefe_em' => $c->encaminhado_ao_chefe_em?->format('Y-m-d H:i'),
            'encaminhado_por' => $c->encaminhadoPor?->name,
            'motivo' => $c->motivo_do_encaminhamento,
            'arquivado_em' => $c->arquivado_em?->format('Y-m-d H:i'),
            'demanda' => $demanda === null ? null : [
                'id' => $demanda->id,
                'protocolo' => $demanda->protocolo,
                'canal' => $demanda->canal,
                'canal_nome' => (string) (config("demandas.canais.{$demanda->canal}.nome") ?? $demanda->canal),
                'assunto' => $demanda->assunto,
                'bairro' => (string) ($demanda->bairro ?? ''),
                'situacao' => $demanda->situacao,
                'situacao_resumida' => $demanda->situacaoResumida(),
                'url' => self::urlDaDemanda($demanda),
                // Os arquivos que vieram com a demanda: quem fiscaliza vê e baixa daqui.
                'anexos' => $demanda->anexos->map(ArquivoParaTela::anexo(...))->values()->all(),
            ],
            // Cada ida ao ponto, inteira — a prova do que foi feito.
            'vistorias' => $c->vistorias
                ->filter(static fn (Fiscalizacao $v): bool => $v->despachada_em !== null)
                ->values()
                ->map(FiscalizacaoParaTela::completa(...))
                ->all(),
            // As outras Fiscalizações do mesmo processo — o vai e vem inteiro.
            'irmas' => $demanda === null ? [] : $demanda->ciclos
                ->reject(static fn (CicloDeFiscalizacao $i): bool => $i->id === $c->id)
                ->values()
                ->map(self::resumo(...))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function resumo(CicloDeFiscalizacao $c): array
    {
        $vistorias = $c->relationLoaded('vistorias') ? $c->vistorias : $c->vistorias()->get();
        $despachadas = $vistorias->filter(static fn (Fiscalizacao $v): bool => $v->despachada_em !== null);

        return [
            'id' => $c->id,
            'protocolo' => $c->protocolo,
            'aberto_em' => $c->aberto_em->format('Y-m-d H:i'),
            'equipe' => (string) ($c->equipe?->codigo ?? ''),
            'posse' => self::posse($c),
            'aba' => $c->aba(),
            'desfecho' => $c->desfechoAtual(),
            'total_vistorias' => $despachadas->count(),
            'total_fotos' => $despachadas->sum(
                static fn (Fiscalizacao $v): int => $v->relationLoaded('fotos') ? $v->fotos->count() : $v->fotos()->count(),
            ),
            'documentos' => $despachadas
                ->map(static fn (Fiscalizacao $v): ?string => ($v->relationLoaded('documento') ? $v->documento : $v->documento()->first())?->rotulo())
                ->filter()
                ->values()
                ->all(),
            'url' => route('retaguarda.fiscalizacoes.index').'?fiscalizacao='.$c->id,
        ];
    }

    /** A caixa de onde a demanda veio, já abrindo ela. */
    public static function urlDaDemanda(Demanda $demanda): string
    {
        $rota = match ($demanda->canal) {
            Demanda::CANAL_FALA_SALVADOR => 'retaguarda.denuncias.fala-salvador.index',
            Demanda::CANAL_E_PROTOCOLO => 'retaguarda.denuncias.e-protocolo.index',
            Demanda::CANAL_AVULSA => 'retaguarda.denuncias.avulsas.index',
            default => 'retaguarda.denuncias.e-salvador.index',
        };

        return route($rota).'?demanda='.$demanda->id;
    }
}
