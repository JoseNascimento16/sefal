<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A entidade central deixa de ser o permissionário e passa a ser o AMBULANTE —
 * que pode ter permissão da SEMOP, ou não.
 *
 * Decisão da área de negócio em 02/09/2026 (fecha a PEND-010). O motivo não é
 * estético: a fiscalização de rua encontra quem tem permissão e quem não tem, e
 * quem não tem é a maior parte do trabalho. Chamar a tabela de
 * `permissionarios` obrigava a mentir sobre metade dos registros — o cadastro
 * local é de quem a fiscalização acha na calçada, com permissão ou sem.
 *
 * ## Por que RENOMEAR, e não criar tabela nova
 *
 * A tabela original já foi aplicada em banco real (homologação), então há dado
 * gravado. `Schema::rename` é a única operação que troca o nome **sem tocar nas
 * linhas**: em Oracle é `alter table … rename to …`, em SQLite o
 * `ALTER TABLE … RENAME TO …` — nos dois casos os índices, o índice único e a
 * chave estrangeira acompanham a tabela. Nada é copiado, nada é recriado, e
 * portanto nada se perde no caminho. A migration que criou a tabela fica como
 * está: reescrevê-la faria o histórico contar uma coisa e o banco outra.
 *
 * ⚠️ **Os índices continuam com o NOME antigo** (`permissionarios_documento_unique`
 * e companhia). Isso é o que os dois bancos fazem ao renomear a tabela, e não há
 * prejuízo: nome de índice é identificação interna, não regra. Mas quem for
 * mexer num deles depois tem de usar o nome ANTIGO — renomeá-los aqui exigiria
 * tratar constraint (Oracle) e índice recriado (SQLite) de formas diferentes,
 * trocando um detalhe cosmético por risco em cima de dado real.
 *
 * ## O atributo novo
 *
 * `permissionario` responde "tem permissão da SEMOP?". É o que dá sentido a
 * `numero_permissao` e `validade_permissao`, que antes ficavam pendurados em
 * todo mundo — inclusive em quem nunca teve permissão nenhuma. É também o campo
 * que o vínculo futuro com o SGCI (a base de quem TEM permissão) vai preencher.
 *
 * Nasce `false` porque é a resposta honesta para um cadastro que não diz nada:
 * afirmar permissão que ninguém conferiu é pior do que não afirmar nada.
 *
 * **A situação (`Regular` / `Irregular` / `Cadastrado em campo`) continua
 * independente disto** — um ambulante sem permissão pode estar regular num ponto
 * autorizado por outra via, e um permissionário pode estar irregular. São duas
 * perguntas diferentes, e juntá-las numa coluna só apagaria as duas.
 *
 * ## O que o `up()` deduz do que já está gravado
 *
 * Quem tem `numero_permissao` preenchido recebe `permissionario = true`. Não é
 * chute: a única razão de um cadastro ter número de permissão é ter permissão.
 * Deixar todo mundo em `false` marcaria como sem-permissão quem o gestor
 * cadastrou justamente lendo o documento da permissão na tela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('permissionarios', 'ambulantes');

        Schema::table('ambulantes', function (Blueprint $table) {
            $table->boolean('permissionario')->default(false);
        });

        DB::table('ambulantes')
            ->whereNotNull('numero_permissao')
            ->update(['permissionario' => true]);
    }

    /**
     * A volta desfaz na ordem inversa: primeiro a coluna, depois o nome.
     *
     * Renomear antes de tirar a coluna deixaria uma tabela `permissionarios` com
     * um campo `permissionario` dentro — coerente com nada.
     */
    public function down(): void
    {
        Schema::table('ambulantes', function (Blueprint $table) {
            $table->dropColumn('permissionario');
        });

        Schema::rename('ambulantes', 'permissionarios');
    }
};
