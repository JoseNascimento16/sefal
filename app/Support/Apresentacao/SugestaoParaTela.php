<?php

namespace App\Support\Apresentacao;

use App\Models\Demanda;
use App\Models\SugestaoAgrupamento;

/**
 * Uma proposta de agrupamento na forma que o coordenador le antes de decidir.
 *
 * Ela leva os DOIS lados inteiros — assunto, endereco, requerente e data —, e
 * nao so os protocolos. O motivo: aceitar junta dois casos de cidadaos
 * diferentes, e ninguem deve decidir isso lendo "DEN-0031 parece DEN-0029" e
 * confiando num numero de confianca. O que convence e ver os dois relatos lado
 * a lado.
 *
 * O `motivo` e o `origem` vao junto pelo mesmo motivo: o coordenador precisa
 * poder discordar do RACIOCINIO, e saber se quem propos foi a regra ou o modelo
 * de linguagem muda o peso que ele da a proposta.
 */
class SugestaoParaTela
{
    /** @return array<string, mixed> */
    public static function completa(SugestaoAgrupamento $sugestao): array
    {
        return [
            'id' => $sugestao->id,
            'origem' => $sugestao->origem,
            'confianca' => $sugestao->confianca,
            // Em porcentagem inteira: e assim que a tela mostra, e a conta e do
            // servidor para os dois nao discordarem por arredondamento.
            'confianca_pct' => $sugestao->confianca === null ? null : (int) round($sugestao->confianca * 100),
            'motivo' => $sugestao->motivo,
            'agregada' => self::lado($sugestao->demanda),
            'principal' => self::lado($sugestao->principal),
        ];
    }

    /**
     * Um dos lados da proposta, com o que basta para reconhecer o caso.
     *
     * @return array<string, mixed>|null
     */
    private static function lado(?Demanda $demanda): ?array
    {
        if ($demanda === null) {
            return null;
        }

        return [
            'id' => $demanda->id,
            'protocolo' => $demanda->protocolo,
            'numero_origem' => (string) ($demanda->numero_origem ?? ''),
            'canal' => DemandaParaTela::rotuloDoCanal($demanda->canal),
            'assunto' => $demanda->assunto,
            // QUEM foi denunciado: é o que separa "o mesmo bar" de "dois
            // estabelecimentos na mesma rua", e é a pergunta que a pré-triagem
            // responde. Sem isso o coordenador decide pelo endereço, que o
            // cidadão escreve de memória.
            'estabelecimento' => (string) ($demanda->estabelecimento ?? ''),
            'denunciado' => (string) ($demanda->denunciado ?? ''),
            'relato' => (string) ($demanda->relato ?? ''),
            'endereco' => trim(((string) $demanda->logradouro).' '.((string) $demanda->numero)),
            'referencia' => (string) ($demanda->referencia ?? ''),
            'bairro' => (string) ($demanda->bairro ?? ''),
            'requerente' => $demanda->anonima ? null : $demanda->requerente,
            'anonima' => $demanda->anonima,
            'recebida_em' => $demanda->recebida_em->format('Y-m-d'),
            'situacao' => $demanda->situacao,
        ];
    }
}
