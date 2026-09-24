<?php

namespace App\Support\Agrupamento;

use App\Models\Demanda;

/**
 * Uma PROPOSTA de agrupamento, antes de virar linha no banco.
 *
 * Os dois lados vem NOMEADOS — `agregada` e `principal` —, e nao como "as duas
 * denuncias" para quem le decidir depois. Quem analisa e quem sabe qual delas
 * espera ha mais tempo; deixar essa escolha para o chamador foi exatamente o
 * defeito que este comentario existe para nao repetir (a varredura tentava
 * deduzir a agregada comparando objetos, e errava quando a candidata era a
 * propria principal).
 *
 * O `motivo` e obrigatorio aqui, no tipo, e nao numa validacao la na frente:
 * sugestao sem justificativa e oraculo — o Chefe de Setor precisa poder discordar
 * do RACIOCINIO, e nao so do resultado.
 */
final class Proposta
{
    public function __construct(
        /** A denuncia que seria agregada — ela sai da fila de trabalho. */
        public readonly Demanda $agregada,
        /** A denuncia que leva o caso a campo e responde por todas. */
        public readonly Demanda $principal,
        /** 0 a 1. Quanto a proposta confia em si mesma; ordena a fila. */
        public readonly float $confianca,
        /** POR QUE. E o que o Chefe de Setor le antes de aceitar. */
        public readonly string $motivo,
    ) {}
}
