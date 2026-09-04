<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O fiscal passa a apenas CONSULTAR o cadastro de permissionário.
 *
 * A concessão nasceu desligando só `incluir` e `excluir`, e isso deixava `habilitado` ligado —
 * quer dizer, o fiscal ALTERAVA o cadastro. Como a situação é campo do mesmo formulário, ele
 * conseguia tirar da quarentena o registro que ele próprio acabara de criar em rua, e a
 * conferência do Chefe de Setor — a razão de a fila existir — deixava de acontecer.
 *
 * A declaração no menu já foi corrigida para `['apenas_leitura' => true]`, mas o seeder da matriz
 * é `firstOrCreate`: ele NÃO reescreve linha que já existe (e é assim de propósito, para não
 * desfazer o que se decidiu na tela). Então quem já rodou a semente antiga continuaria com
 * a linha errada para sempre. Esta migration é o que fecha essa porta.
 *
 * ## Por que a correção é condicional
 *
 * Ela só toca a linha que AINDA ESTÁ como a semente antiga a deixou — a impressão digital exata
 * `visivel=1, habilitado=1, apenas_leitura=0, incluir=0, excluir=0`. Se alguém mexeu em
 * qualquer uma das cinco colunas, a linha não casa e fica intacta: decisão tomada na tela é
 * decisão de gente, e migration não desfaz decisão de gente. O preço é que uma linha ajustada à
 * mão continua com a alteração liberada — e isso é o certo, porque aí foi alguém que quis.
 */
return new class extends Migration
{
    private const TABELA = 'permissoes_setor';

    public function up(): void
    {
        DB::table(self::TABELA)
            ->where('setor', 'fiscal')
            ->where('slug', 'permissionarios')
            // A impressão digital da semente antiga, coluna a coluna.
            ->where('visivel', true)
            ->where('habilitado', true)
            ->where('apenas_leitura', false)
            ->where('incluir', false)
            ->where('excluir', false)
            ->update([
                'habilitado' => false,
                'apenas_leitura' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * A volta devolve a linha ao estado anterior, e pelo mesmo critério: só onde ela está
     * exatamente como esta migration a deixou. Rollback que reabre gravação onde
     * alguém depois restringiu de propósito seria pior do que não voltar nada.
     */
    public function down(): void
    {
        DB::table(self::TABELA)
            ->where('setor', 'fiscal')
            ->where('slug', 'permissionarios')
            ->where('visivel', true)
            ->where('habilitado', false)
            ->where('apenas_leitura', true)
            ->where('incluir', false)
            ->where('excluir', false)
            ->update([
                'habilitado' => true,
                'apenas_leitura' => false,
                'updated_at' => now(),
            ]);
    }
};
