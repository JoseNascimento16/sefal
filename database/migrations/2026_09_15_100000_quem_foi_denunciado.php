<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QUEM foi denunciado — a informação que faltava para decidir a pré-triagem.
 *
 * A demanda guardava quem RELATOU (o requerente) e ONDE (o endereço), mas não
 * guardava o ALVO. Na pré-triagem isso é exatamente o que se precisa saber:
 * dois relatos de "mesas na calçada" na mesma rua podem ser o mesmo bar ou dois
 * estabelecimentos a cinquenta metros um do outro, e o que separa os dois casos
 * é o nome na fachada — não o assunto, que é sempre o mesmo, nem o número do
 * imóvel, que o cidadão escreve de memória.
 *
 * ── Por que TRÊS colunas, e não uma ─────────────────────────────────────────
 *
 *  - `estabelecimento` é o nome de FACHADA, o que o cidadão escreve porque é o
 *    que ele leu na rua ("Bar do Zeca"). É o campo que mais se repete entre
 *    denúncias do mesmo fato, e por isso o que mais ajuda a agrupar;
 *  - `denunciado` é a pessoa ou a razão social por trás — o que aparece no
 *    cadastro e no documento, e quase nunca no relato do cidadão;
 *  - `documento_denunciado` é o CPF/CNPJ quando existe. Guardado NORMALIZADO
 *    (ver `App\Support\Documento`): o CNPJ novo tem LETRAS, e um `preg_replace`
 *    de não-dígitos apagaria metade dele.
 *
 * Os três são nulos por natureza: a denúncia anônima da ouvidoria costuma
 * chegar só com "tem um bar botando mesa na calçada". Exigi-los faria a
 * integração recusar o que ela mais recebe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandas', function (Blueprint $table) {
            $table->string('estabelecimento', 150)->nullable()->after('relato');
            $table->string('denunciado', 150)->nullable()->after('estabelecimento');
            $table->string('documento_denunciado', 14)->nullable()->after('denunciado');
        });

        /*
         * Índice no nome de fachada: a varredura de agrupamento compara esse
         * campo entre todas as denúncias abertas da janela, e a busca das telas
         * procura por ele. Sem índice, as duas viram varredura de tabela — que
         * no SQLite da demonstração não se nota e no Oracle sim.
         */
        Schema::table('demandas', function (Blueprint $table) {
            $table->index('estabelecimento');
        });
    }

    public function down(): void
    {
        Schema::table('demandas', function (Blueprint $table) {
            $table->dropIndex(['estabelecimento']);
            $table->dropColumn(['estabelecimento', 'denunciado', 'documento_denunciado']);
        });
    }
};
