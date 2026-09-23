<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A DEMANDA — tudo que entra e pede uma decisão — e o seu TRÂMITE.
 *
 * ## Por que Caixa de Entrada e Denúncias viram UMA tabela
 *
 * No protótipo eram dois arquivos (`prototipo_caixa_entrada.php` e
 * `prototipo_denuncias.php`) porque eram duas TELAS, e cada tela crescia sozinha.
 * Olhadas de perto, guardam a mesma coisa: alguém relatou um fato, num endereço,
 * com um prazo, e a coordenação decide o que fazer. Os campos coincidem quase um
 * a um; o trâmite coincide inteiro.
 *
 * Duas tabelas para a mesma entidade é a armadilha que a lei da FONTE ÚNICA
 * descreve: a regra de prazo, o roteamento por bairro e o cálculo de "vencida"
 * teriam dois donos e divergiriam no primeiro ajuste. Pior: uma denúncia do
 * e-Salvador que chegou por PAPEL (hoje) e a mesma denúncia quando a API existir
 * (amanhã) seriam registros de tabelas diferentes, e o relatório do canal contaria
 * metade.
 *
 * O que de fato difere são duas coisas, e as duas viram COLUNA:
 *
 *   `canal`  — de onde o fato veio (e-salvador, fala-salvador — era salvador-digital —, nova-licenca, oficio, avulsa);
 *   `entrada` — COMO ele chegou aqui: `integracao` (o sistema recebeu sozinho) ou
 *               `balcao` (o coordenador digitou o papel).
 *
 * As telas continuam separadas — cada uma filtra o que lhe cabe. A separação é
 * de LEITURA, e leitura não precisa de tabela própria.
 *
 * ## As situações são uma lista só, a do fluxo completo
 *
 * A Caixa de Entrada falava "Aguardando triagem"; as Denúncias, "Recebida". É o
 * mesmo estado com dois nomes — o começo exato da divergência. Fica a lista do
 * fluxo completo (`App\Models\Demanda::SITUACOES`), porque é ela que cobre o
 * caminho inteiro até o retorno de campo.
 *
 * ## O trâmite é a memória do processo, e ele é APPEND-ONLY
 *
 * Cada decisão vira um passo: quem, quando, o quê, o detalhe escrito, a situação
 * que aquele passo produziu e os `campos` estruturados da decisão (para onde
 * encaminhou, que motivo escolheu, que orientação deixou). Passo não se edita nem
 * se apaga: ato administrativo corrigido por sobrescrita é ato administrativo
 * perdido. Erro se conserta com passo novo.
 *
 * `campos` é CLOB com JSON dentro, e não colunas: cada tipo de passo carrega um
 * conjunto diferente (devolver traz motivo+destino; direcionar traz equipe+
 * orientação; anexar à operação traz a operação e o porquê de não ser ida avulsa).
 * Uma coluna por variante deixaria a tabela com trinta colunas nulas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandas', function (Blueprint $table) {
            $table->id();

            // Nosso número. `Protocolo::proximo('DEM')`.
            $table->string('protocolo', 20)->unique();

            // De onde veio o fato, e como chegou até aqui. Ver o cabeçalho.
            $table->string('canal', 30);
            $table->string('entrada', 20)->default('balcao');

            /*
             * O número que a ORIGEM deu ao caso (`ESL-2026-114872`, `156-2026-884120`,
             * `PROC-2026-004512`). É por ele que o cidadão cobra e que a ouvidoria
             * responde — e é ele que impede a mesma denúncia de entrar duas vezes
             * quando a API repetir o envio. Único por canal: dois canais podem
             * repetir numeração entre si sem que isso signifique duplicata.
             */
            $table->string('numero_origem', 40)->nullable();

            $table->timestamp('recebida_em');
            // Quando o atendimento vence. Data, não contador: contador envelhece.
            $table->date('prazo_em')->nullable();

            // ── Quem relatou ────────────────────────────────────────────────
            $table->boolean('anonima')->default(false);
            $table->string('requerente', 150)->nullable();
            // CPF ou CNPJ normalizado (`App\Support\Documento`) — o CNPJ pode ter LETRAS.
            $table->string('documento', 14)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();

            // ── O que foi relatado ──────────────────────────────────────────
            $table->string('assunto', 200);
            $table->text('relato')->nullable();

            // ── Onde ────────────────────────────────────────────────────────
            $table->string('logradouro', 200)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('referencia', 200)->nullable();
            $table->string('bairro', 120)->nullable();
            /*
             * O endereço dá para mandar equipe? Vem marcado pelo canal (o
             * telefônico produz endereço solto) e é o que separa a demanda que
             * segue da que volta por falta de elementos. Derivar isso da ausência
             * de `numero` erraria: "em frente ao posto salva-vidas" não tem
             * número e localiza melhor que muitos que têm.
             */
            $table->boolean('endereco_impreciso')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // ── Onde ela está no fluxo ──────────────────────────────────────
            $table->string('situacao', 40)->default('Recebida');
            /*
             * O destino que a triagem deu. Os três são nulos enquanto ninguém
             * decidiu, e ganham valor na ordem do fluxo: área (coordenador) →
             * equipe OU operação (chefe de setor). Equipe e operação convivem:
             * a operação também tem equipes, e saber QUAL foi é o que o retorno
             * de campo precisa.
             */
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('equipe_id')->nullable()->constrained('equipes')->nullOnDelete();
            $table->foreignId('operacao_id')->nullable()->constrained('operacoes')->nullOnDelete();

            // Quem digitou (balcão). Nulo quando entrou por integração — e é essa
            // ausência que prova, no registro, que ninguém a cadastrou à mão.
            $table->foreignId('criada_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();

            $table->unique(['canal', 'numero_origem']);
            $table->index(['situacao', 'recebida_em']);
            $table->index(['area_id', 'situacao']);
            $table->index('bairro');
            $table->index('prazo_em');
        });

        Schema::create('demanda_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demanda_id')->constrained('demandas')->cascadeOnDelete();

            // O nome como o cidadão o conhece; o caminho é onde ele de fato está.
            $table->string('nome', 200);
            $table->string('caminho', 255);
            $table->string('tipo', 60)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestamps();

            $table->index('demanda_id');
        });

        Schema::create('demanda_tramites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demanda_id')->constrained('demandas')->cascadeOnDelete();

            // A ordem do passo dentro da demanda. Explícita porque dois passos
            // podem nascer no mesmo segundo (decisão que encaminha e conclui).
            $table->unsignedInteger('ordem');
            $table->timestamp('ocorrida_em');

            /*
             * Quem agiu, nas duas formas — e as duas são necessárias.
             * `user_id` é a pessoa (nulo quando o ato foi do sistema: recebimento
             * por integração não tem autor humano); `papel` é o que ela era NAQUELE
             * momento (`integracao`, `coordenador`, `chefe-de-setor`, `fiscal`).
             * Sem o papel gravado, um usuário que muda de setor reescreve o
             * passado: a triagem que ele fez como coordenador apareceria assinada
             * pelo cargo de hoje.
             */
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('papel', 30);
            // O nome como ficou registrado, para o histórico sobreviver à exclusão da conta.
            $table->string('autor', 150)->nullable();

            $table->string('acao', 120);
            $table->text('detalhe')->nullable();
            // A situação em que a demanda ficou DEPOIS deste passo.
            $table->string('situacao', 40);

            // JSON: os campos estruturados da decisão. Ver o cabeçalho.
            $table->text('campos')->nullable();

            $table->timestamps();

            $table->unique(['demanda_id', 'ordem']);
            $table->index(['demanda_id', 'ocorrida_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demanda_tramites');
        Schema::dropIfExists('demanda_anexos');
        Schema::dropIfExists('demandas');
    }
};
