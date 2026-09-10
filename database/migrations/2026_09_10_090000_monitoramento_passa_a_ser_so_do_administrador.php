<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O Monitoramento passa a ser SÓ do administrador.
 *
 * Ordem do dono (10/09/2026): "somente admin pode ver Monitoramento". A tela é
 * diagnóstico do AMBIENTE — conta de administrador ativa, armazenamento gravável,
 * listas obrigatórias vazias —, e o que ela mostra quando algo está vermelho conta
 * como o sistema é montado por dentro. Isso não é decisão de operação.
 *
 * A declaração no menu já foi corrigida para `['administrador']`, e isso resolve o
 * banco que ainda vai nascer. Não resolve os que já existem: a lista `setores` do
 * menu é a SEMENTE da matriz, aplicada UMA VEZ pelo `PermissoesSetorSeeder` — que é
 * `firstOrCreate` de propósito, para nunca desfazer o que se decidiu na tela do
 * Modo Gerente. Num banco já semeado (o de desenvolvimento e o da demonstração), a
 * linha do Chefe de Setor continua lá para sempre, concedendo uma tela que a decisão
 * já tirou dele. Esta migration é o que fecha essa porta.
 *
 * ## Por que a remoção é CONDICIONAL
 *
 * Ela só apaga a linha que AINDA ESTÁ como a semente a deixou — a impressão digital
 * exata do pacote completo (`visivel=1, habilitado=1, apenas_leitura=0, incluir=1,
 * excluir=1`). Se alguém mexeu em qualquer uma das cinco colunas pelo Modo Gerente,
 * a linha não casa e fica INTACTA: decisão tomada na tela é decisão de gente, e
 * migration não desfaz decisão de gente. O preço é que um Chefe de Setor a quem
 * alguém ajustou o acesso à mão continua entrando — e isso é o certo, porque aí foi
 * alguém que quis. É o mesmo critério de
 * `2026_08_26_120000_fiscal_apenas_consulta_permissionarios`.
 *
 * A impressão digital está escrita COLUNA A COLUNA aqui, e não lida do catálogo: o
 * setor já saiu da declaração do menu, então perguntar ao catálogo "com que pacote
 * este setor nasce nesta tela?" devolveria a resposta de agora ("com nenhum"), e não
 * a de ontem — que é a única que interessa a quem procura a linha antiga. Quem
 * garante que a impressão digital continua sendo a que a semente grava é o teste
 * desta migration, que a compara com `CatalogoFuncionalidades::acoesSemente`.
 *
 * ⚠️ Só `DELETE` e `INSERT` de texto: nenhuma DDL. Roda igual em SQLite (dev) e em
 * Oracle (produção), e é idempotente — no OKD o `migrate` é passo manual depois do
 * deploy, e quem executa à mão executa duas vezes um dia.
 */
return new class extends Migration
{
    private const TABELA = 'permissoes_setor';

    private const TELA = 'monitoramento';

    private const SETOR = 'chefe-de-setor';

    public function up(): void
    {
        DB::table(self::TABELA)
            ->where('setor', self::SETOR)
            ->where('slug', self::TELA)
            ->where($this->pacoteDaSemente())
            ->delete();
    }

    /**
     * A volta devolve a concessão, e só onde ela NÃO existe.
     *
     * `insertOrIgnore` porque desfazer esta mudança não pode passar por cima do que
     * quem administra fez depois: se a linha já está lá (alguém a concedeu à mão),
     * ela fica como está.
     */
    public function down(): void
    {
        DB::table(self::TABELA)->insertOrIgnore([
            'setor' => self::SETOR,
            'slug' => self::TELA,
            ...$this->pacoteDaSemente(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * O pacote com que a semente concedeu esta tela a este setor — a impressão
     * digital de "ninguém tocou nisto depois".
     *
     * É o pacote COMPLETO, que é o que "este setor usa esta tela" significa na
     * semente quando a declaração não traz ajuste nenhum (o caso do Monitoramento
     * até 10/09/2026).
     *
     * @return array<string, bool>
     */
    private function pacoteDaSemente(): array
    {
        return [
            'visivel' => true,
            'habilitado' => true,
            'apenas_leitura' => false,
            'incluir' => true,
            'excluir' => true,
        ];
    }
};
