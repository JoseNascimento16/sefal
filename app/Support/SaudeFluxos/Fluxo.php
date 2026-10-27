<?php

namespace App\Support\SaudeFluxos;

/**
 * O fluxo de trabalho na Saúde dos Fluxos — uma aba da tela.
 *
 * A definição é o conhecimento do fluxo em forma de dado: quadradinhos (`nos`) com posição na grade,
 * setas (`arestas`), o passo a passo (`etapas`) e as formas de conclusão (`conclusoes`). Cada
 * quadradinho diz se o trecho é ATENDIDO hoje pelo sistema ou só PREVISTO (`situacao`).
 *
 * Mesma estrutura da tela do CODECON, de onde ela veio (27/10/2026). Lá cada quadradinho também é
 * pintado pelas verificações do ambiente; aqui, por enquanto, só se desenha o fluxo.
 *
 * @phpstan-type No array{id: string, rotulo: string, tipo: string, setor: string, col: int, lin: int, descricao: string, situacao: string, tela: string}
 * @phpstan-type Aresta array{de: string, para: string, rotulo?: string}
 * @phpstan-type Etapa array{setor: string, titulo: string, nos: list<string>, itens: list<string>}
 */
interface Fluxo
{
    /** Chave do fluxo (slug, vai na URL). */
    public static function chave(): string;

    /**
     * @return array{chave: string, titulo: string, subtitulo: string, resumo: string, nos: list<array<string, mixed>>, arestas: list<array<string, mixed>>, etapas: list<array<string, mixed>>, conclusoes: list<array<string, string>>}
     */
    public static function definicao(): array;
}
