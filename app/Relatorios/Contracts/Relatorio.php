<?php

namespace App\Relatorios\Contracts;

use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\FiltroDef;
use App\Relatorios\Suporte\ResultadoRelatorio;

/**
 * Contrato de um RELATÓRIO do sistema — documento oficial, com período, totais e
 * timbre, pedido de propósito por quem vai arquivá-lo ou entregá-lo.
 *
 * Cada relatório é uma classe: declara a própria identidade, os filtros que a
 * tela deve renderizar e produz um {@see ResultadoRelatorio} neutro. Os
 * exportadores (PDF/XLSX/DOCX) consomem esse resultado sem conhecer regra de
 * negócio nenhuma — é o que permite os três formatos sem triplicar a consulta.
 *
 * ⚠️ Relatório NÃO é exportação de listagem. Exportar a grade que está na tela é
 * conveniência, sai pelo endpoint único de exportação e não passa por aqui.
 */
interface Relatorio
{
    /** Identificador estável (slug) — é o que a tela manda de volta ao pedir a geração. */
    public function chave(): string;

    public function titulo(): string;

    /** Grupo para organizar o catálogo (ex.: 'Sistema', 'Fiscalização'). */
    public function grupo(): string;

    public function descricao(): string;

    /**
     * Os filtros que a tela renderiza para este relatório.
     *
     * @return array<int, FiltroDef>
     */
    public function filtros(): array;

    /**
     * Modos suportados ({@see ContextoRelatorio}::MODO_*). O usuário escolhe na tela.
     *
     * @return array<int, string>
     */
    public function modos(): array;

    public function gerar(ContextoRelatorio $contexto): ResultadoRelatorio;
}
