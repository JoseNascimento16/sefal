<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O AGRUPAMENTO — dez denúncias que são UM fato.
 *
 * ## O problema, na forma como ele acontece
 *
 * O e-Salvador não entrega casos organizados: entrega o que cada cidadão
 * escreveu. Dez pessoas reclamam de "mesas e cadeiras atrapalhando a via" em dez
 * protocolos diferentes e, quando a equipe chega ao local, é o MESMO ambiente, o
 * MESMO ambulante, o MESMO fato. Mandar dez equipes — ou dez vezes a mesma
 * equipe — é o desperdício óbvio; menos óbvio, e pior, é o sistema passar a
 * contar dez problemas onde há um, e a chefia decidir prioridade sobre um número
 * inflado.
 *
 * ## A forma escolhida: uma demanda vira o REGISTRO DE TRABALHO, as outras se agregam
 *
 * Nenhuma denúncia é apagada nem fundida: cada uma continua existindo inteira,
 * com o protocolo dela, o número que a ouvidoria deu e o requerente que a abriu.
 * O que a triagem faz é dizer **qual delas é o registro que vai a campo** — e as
 * demais apontam para ela por `agrupada_em_id`.
 *
 * Isso preserva as duas coisas que não podem se perder:
 *
 *  1. **a resposta individual** — o cidadão que abriu o protocolo ESL-114872
 *     precisa ser respondido nesse protocolo, e a ouvidoria cobra por ele;
 *  2. **o trabalho único** — a equipe vai uma vez, e o resultado da fiscalização
 *     responde por todas as agregadas de uma vez (o leque de volta).
 *
 * Uma alternativa seria criar uma entidade nova ("ocorrência") acima das
 * denúncias. Foi descartada: ela obrigaria TODO caso — inclusive os 9 em cada 10
 * que são denúncia única — a carregar dois registros, e duplicaria a fila, o
 * prazo e o trâmite. O agrupamento é exceção frequente, não a regra.
 *
 * ## Desagrupar tem de ser barato
 *
 * A associação pode estar errada: "mesas na calçada" na mesma rua pode ser dois
 * estabelecimentos a cinquenta metros um do outro. Por isso desagrupar é só
 * zerar a coluna — e é por isso que nada é fundido. Um desenho que copiasse os
 * dados da agregada para a principal tornaria o erro irreversível, e o
 * coordenador deixaria de corrigir por medo.
 *
 * ## A sugestão da IA é PROPOSTA, nunca ato
 *
 * A tabela de sugestões existe para que a máquina possa opinar sem decidir. Ela
 * guarda o par proposto, o quanto confia e **por quê** — e o estado da decisão
 * humana. Três consequências deliberadas:
 *
 *  - agrupamento só acontece por ato de gente: a sugestão `aceita` é registrada
 *    com quem aceitou e quando;
 *  - a sugestão `recusada` NÃO desaparece: é ela que impede o sistema de propor
 *    de novo o mesmo par a cada varredura, cansando o coordenador até ele parar
 *    de ler as sugestões;
 *  - o `motivo` é exigido na escrita porque sugestão sem justificativa é oráculo:
 *    o coordenador precisa poder discordar do raciocínio, e não só do resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandas', function (Blueprint $table) {
            /*
             * A demanda que leva este caso a campo. Nulo = esta é a principal (ou
             * não está agrupada — as duas coisas são o mesmo estado de propósito:
             * uma demanda sozinha é a principal de um grupo de um).
             *
             * `nullOnDelete` para que apagar a principal nunca leve junto o que
             * um cidadão relatou.
             */
            $table->foreignId('agrupada_em_id')->nullable()->after('operacao_id')
                ->constrained('demandas')->nullOnDelete();

            $table->timestamp('agrupada_em')->nullable()->after('agrupada_em_id');

            $table->index('agrupada_em_id');
        });

        Schema::create('sugestoes_agrupamento', function (Blueprint $table) {
            $table->id();

            // A denúncia que a máquina propõe agregar...
            $table->foreignId('demanda_id')->constrained('demandas')->cascadeOnDelete();
            // ...e a demanda que seria o registro de trabalho.
            $table->foreignId('principal_id')->constrained('demandas')->cascadeOnDelete();

            // Quem propôs: `ia` (modelo de linguagem) ou `regra` (proximidade de
            // endereço e assunto). Gravado porque a confiança que o coordenador
            // dá a cada uma é diferente, e a tela precisa dizer qual foi.
            $table->string('origem', 20)->default('regra');

            // 0 a 1. Quanto a proposta confia em si mesma — é o que ordena a fila
            // de sugestões, para o coordenador olhar as fortes primeiro.
            $table->decimal('confianca', 4, 3)->nullable();

            // POR QUÊ. Obrigatório: sugestão sem justificativa é oráculo.
            $table->text('motivo');

            // `sugerida` | `aceita` | `recusada`.
            $table->string('estado', 20)->default('sugerida');
            $table->foreignId('decidida_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidida_em')->nullable();
            // O que o humano respondeu ao recusar — é isto que ensina a próxima varredura.
            $table->text('observacao')->nullable();

            $table->timestamps();

            // O mesmo par proposto uma vez só. Sem isto, cada varredura empilharia
            // uma cópia da sugestão que o coordenador já recusou.
            $table->unique(['demanda_id', 'principal_id']);
            $table->index(['estado', 'confianca']);
            $table->index('principal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugestoes_agrupamento');

        Schema::table('demandas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agrupada_em_id');
            $table->dropColumn('agrupada_em');
        });
    }
};
