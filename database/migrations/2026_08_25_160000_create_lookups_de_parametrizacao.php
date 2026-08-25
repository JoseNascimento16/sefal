<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As listas que o resto do sistema escolhe, e os parâmetros que ele lê.
 *
 * Todas têm o mesmo feitio de propósito — nome + situação —, porque é isso que
 * uma lista de escolha é. O que muda de uma para outra é o campo que só ela tem:
 * a descrição de apoio do tipo de infração e a sigla da unidade de medida.
 *
 * ## Por que `ativo` e não exclusão
 *
 * Valor de lista aparece em registro histórico (uma fiscalização de dois anos
 * atrás aponta para um tipo de infração). Apagar a linha deixaria o histórico
 * apontando para o nada, então o caminho normal de "não usar mais" é INATIVAR —
 * some do formulário, continua legível no que já foi gravado. A exclusão existe
 * para o valor cadastrado errado, que ainda não foi usado por ninguém.
 */
return new class extends Migration
{
    /** As listas de nome simples: mesma estrutura, tabelas separadas. */
    private const LISTAS_SIMPLES = [
        'atividades_ambulante',
        'tipos_operacao',
        'origens_operacao',
        'motivos_recusa',
    ];

    public function up(): void
    {
        Schema::create('tipos_infracao', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique();
            // Apoio ao fiscal na hora de escolher em rua: o nome curto cabe na
            // lista, a descrição explica o que aquilo abrange.
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique();
            // É a sigla que sai no documento impresso em rua, onde não cabe o
            // nome por extenso.
            $table->string('sigla', 10);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        foreach (self::LISTAS_SIMPLES as $tabela) {
            Schema::create($tabela, function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120)->unique();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        /*
         * Os números que a regra de negócio usa e que o cliente pode querer
         * mudar sem release — o prazo de notificação é o primeiro deles.
         *
         * Chave e valor em texto: o dono do significado é quem lê o parâmetro
         * (é lá que se sabe se aquilo é dia, real ou metro), e uma coluna por
         * parâmetro obrigaria uma migration a cada regra nova.
         */
        Schema::create('parametros_fiscalizacao', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 60)->unique();
            $table->string('valor', 255);
            // O que o parâmetro significa, para quem o encontrar sem contexto.
            $table->string('descricao', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_fiscalizacao');

        foreach (self::LISTAS_SIMPLES as $tabela) {
            Schema::dropIfExists($tabela);
        }

        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('tipos_infracao');
    }
};
