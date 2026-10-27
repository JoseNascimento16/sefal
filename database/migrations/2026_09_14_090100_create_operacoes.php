<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A OPERAÇÃO — o trabalho de rua planejado, com período, área e equipes.
 *
 * Ela existe para que a demanda isolada não vire sempre uma ida avulsa: quando o
 * ponto denunciado cai no trecho de uma operação em andamento, o Chefe de Setor
 * a ANEXA à operação em vez de despachar uma equipe só para ele. É a terceira
 * saída da triagem, ao lado de "equipe da área" e "devolver".
 *
 * ## A situação é DERIVADA do período — não é escolha de formulário
 *
 * `Planejada` (ainda não começou) · `Em andamento` (hoje está dentro do período)
 * · `Encerrada` (passou do fim). Quem decide é a data, e o recálculo é de quem
 * lê (model/command), não de quem edita: situação digitada envelhece sozinha e
 * a tela passa a mostrar "em andamento" para operação que acabou mês passado.
 * A ÚNICA situação que é ato humano é `Cancelada` — por isso ela tem coluna
 * própria (`cancelada_em` + motivo) em vez de ser um valor da mesma lista: o
 * cancelamento precisa dizer quem e por quê, e um valor de lista não diz.
 *
 * ## Sem data de fim é estado legítimo
 *
 * "Rotina Centro" é permanente. `fim` nulo significa isso, e inventar uma data
 * faria a tela mostrar prazo onde não há.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacoes', function (Blueprint $table) {
            $table->id();

            // `Protocolo::proximo('OP')`.
            $table->string('codigo', 20)->unique();
            $table->string('nome', 150);

            $table->foreignId('area_id')->constrained('areas');

            // Lookups de parametrização — o cliente muda sem release.
            $table->foreignId('tipo_operacao_id')->nullable()->constrained('tipos_operacao');
            $table->foreignId('origem_operacao_id')->nullable()->constrained('origens_operacao');

            // Quem responde pela operação na Retaguarda.
            $table->foreignId('coordenador_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('regiao', 150)->nullable();
            $table->text('foco')->nullable();
            $table->text('observacao')->nullable();

            $table->date('inicio');
            // Nulo = rotina permanente. Ver o cabeçalho.
            $table->date('fim')->nullable();

            /*
             * Derivada do período (ver cabeçalho). Gravada mesmo assim porque a
             * listagem filtra e ordena por ela, e recalcular em SQL para milhares
             * de linhas custaria a cada consulta. Quem a mantém honesta é o
             * command agendado — não o formulário.
             */
            $table->string('situacao', 20)->default('Planejada');

            /*
             * O ENCERRAMENTO ANTECIPADO. A operação acabou antes da data
             * planejada (o efetivo foi realocado, o objetivo foi cumprido), e
             * isso é ATO de gestão — não uma consequência do calendário.
             *
             * Tem coluna própria pelo mesmo motivo do cancelamento: a situação é
             * derivada, e aceitar um rótulo digitado daria dois donos à mesma
             * verdade. Com a data aqui, a derivação continua tendo um dono só e
             * ganha um segundo FATO para ler — em vez de uma opinião.
             *
             * Encerrada ≠ cancelada: a encerrada aconteceu e produziu trabalho;
             * a cancelada não chegou a acontecer. Somá-las no relatório diria que
             * a SEFAL executou operações que nunca saíram do papel.
             */
            $table->timestamp('encerrada_em')->nullable();

            $table->timestamp('cancelada_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->foreignId('cancelada_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('criada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['situacao', 'inicio']);
            $table->index('area_id');
        });

        Schema::create('operacao_equipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->cascadeOnDelete();
            $table->foreignId('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['operacao_id', 'equipe_id']);
        });

        /*
         * Os bairros que a operação varre. NÃO são os bairros da área: a operação
         * costuma pegar um TRECHO dela ("a orla de Itapuã à Boca do Rio", não a
         * Área 5 inteira). É por esta lista que o sistema sabe dizer ao Chefe de
         * Setor "o ponto desta denúncia está dentro da Operação Verão" — a
         * pergunta que decide entre anexar e despachar equipe.
         */
        Schema::create('operacao_bairros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->cascadeOnDelete();
            $table->string('bairro', 120);
            $table->timestamps();

            $table->unique(['operacao_id', 'bairro']);
            $table->index('bairro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacao_bairros');
        Schema::dropIfExists('operacao_equipes');
        Schema::dropIfExists('operacoes');
    }
};
