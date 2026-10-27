<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A FISCALIZAÇÃO — o que aconteceu na rua. É o registro que o aplicativo do
 * fiscal grava e que a Retaguarda lê para decidir a próxima medida.
 *
 * ## Ela nasce de três lugares, e por isso o vínculo é opcional
 *
 *   1. de uma DEMANDA direcionada (`demanda_id`) — o caminho normal;
 *   2. de uma OPERAÇÃO planejada (`operacao_id`, sem demanda) — a equipe varre
 *      o trecho e registra o que encontra;
 *   3. AVULSA — o fiscal passou, viu e registrou. Sem demanda e sem operação.
 *
 * As três são o mesmo fato e moram na mesma tabela: o que muda é de onde veio a
 * ordem, não o que foi feito em campo. Tabelas separadas obrigariam o mapa, o
 * relatório e o prontuário do ambulante a somar três fontes — e a esquecer uma.
 * `origem` diz qual das três foi, sem que ninguém precise deduzir da presença
 * de chave estrangeira.
 *
 * ## GPS é obrigatório por design (lei do projeto)
 *
 * `latitude`/`longitude`/`precisao_m`/`gps_em` são o que prova que a equipe
 * esteve no ponto. São anuláveis na COLUNA porque falha de hardware existe e
 * recusar o registro por isso perderia o trabalho feito — mas a ausência é
 * excepcional e fica visível na tela, nunca silenciosa.
 *
 * ## `client_id` é o que evita o registro duplicado do offline
 *
 * O aparelho gera um UUID ANTES de haver rede. Se a resposta do envio se perder
 * e o aplicativo reenviar, o servidor reconhece o mesmo registro em vez de criar
 * um segundo. É a regra do projeto: conflito se EVITA, não se resolve.
 *
 * ## O que é imutável depois do despacho
 *
 * `despachada_em` é a fronteira. Antes dela, o fiscal corrige o que escreveu;
 * depois, o registro está na mesa da chefia e virou peça do processo. A guarda
 * vive no servidor (não só na tela do aplicativo), porque tela bloqueada é
 * conveniência e servidor é garantia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalizacoes', function (Blueprint $table) {
            $table->id();

            // `Protocolo::proximo('FIS')`.
            $table->string('protocolo', 20)->unique();

            /*
             * O UUID que o aparelho gerou offline. Nulo para o que nasce na
             * Retaguarda. Único: é a trava de idempotência do reenvio.
             */
            $table->string('client_id', 40)->nullable()->unique();

            // `demanda` | `operacao` | `avulsa` — ver o cabeçalho.
            $table->string('origem', 20);
            $table->foreignId('demanda_id')->nullable()->constrained('demandas')->nullOnDelete();
            $table->foreignId('operacao_id')->nullable()->constrained('operacoes')->nullOnDelete();
            $table->foreignId('equipe_id')->nullable()->constrained('equipes')->nullOnDelete();
            $table->foreignId('fiscal_id')->constrained('users');

            /*
             * A quem a fiscalização se refere. NULO é caso frequente e legítimo:
             * ponto vazio, ocupante que se recusou a identificar, ambulante que
             * ainda não tem cadastro. Exigir o vínculo faria o fiscal inventar
             * um alvo para conseguir gravar. `alvo` conta em palavras quem foi
             * encontrado, e é o que o Chefe de Setor lê.
             */
            $table->foreignId('ambulante_id')->nullable()->constrained('ambulantes')->nullOnDelete();
            $table->string('alvo', 255)->nullable();
            $table->string('equipamento', 150)->nullable();

            // ── Onde ────────────────────────────────────────────────────────
            $table->string('logradouro', 200)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('bairro', 120)->nullable();
            $table->string('ponto_de_referencia', 200)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('precisao_m')->nullable();
            $table->timestamp('gps_em')->nullable();

            // ── Quando ──────────────────────────────────────────────────────
            $table->timestamp('aberta_em');
            $table->timestamp('concluida_em')->nullable();
            // A fronteira da imutabilidade. Ver o cabeçalho.
            $table->timestamp('despachada_em')->nullable();
            // Quando o servidor recebeu — diferente de quando aconteceu, e a
            // diferença entre as duas é o tempo que o registro passou na fila
            // offline. É ela que mostra a área sem cobertura de rede.
            $table->timestamp('sincronizada_em')->nullable();

            // ── O que deu ───────────────────────────────────────────────────
            // `App\Models\Fiscalizacao::DESFECHOS`. Nulo enquanto está em campo.
            $table->string('desfecho', 60)->nullable();
            // A leitura do fiscal, em texto livre. É o que a chefia lê primeiro.
            $table->text('consideracoes')->nullable();

            /*
             * A situação do registro no ciclo de trabalho, que NÃO é o desfecho:
             * o desfecho diz como a vistoria terminou; a situação diz onde o
             * papel está (`Em campo`, `Aguardando decisão`, `Decidida`,
             * `Devolvida à coordenação`).
             */
            $table->string('situacao', 40)->default('Em campo');

            /*
             * Quem leu o registro e o que decidiu, com a data. Mora AQUI, e não
             * só no trâmite da demanda, porque a fiscalização AVULSA não tem
             * demanda atrás — e a decisão sobre ela é ato administrativo do
             * mesmo jeito. Sem estas duas colunas, "quem deu ciência" seria uma
             * informação que existe para metade dos registros.
             */
            $table->foreignId('decidida_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidida_em')->nullable();
            // O que a chefia escreveu ao decidir. Opcional: o ato de ler já é a
            // informação, e exigir texto para dar ciência de seis registros de
            // uma vez faria a chefia escrever seis frases vazias.
            $table->text('decisao_detalhe')->nullable();

            /*
             * A assinatura do notificado, em traçado (SVG viewBox 1000x500), ou
             * a recusa com o motivo. Recusa é ato registrado, não ausência: sem
             * ela, "não assinou" e "ninguém pediu" seriam a mesma coisa no papel.
             */
            $table->text('assinatura')->nullable();
            $table->foreignId('motivo_recusa_id')->nullable()->constrained('motivos_recusa')->nullOnDelete();

            $table->timestamps();

            $table->index(['situacao', 'concluida_em']);
            $table->index(['fiscal_id', 'concluida_em']);
            $table->index('demanda_id');
            $table->index('operacao_id');
            $table->index('ambulante_id');
        });

        /*
         * As RECOMENDAÇÕES que o fiscal assinalou. Guardadas por CHAVE, nunca
         * pela frase: a frase tem duas redações (curta no aparelho, explícita na
         * Retaguarda) e o relatório soma por chave. Gravar a frase faria o
         * relatório separar em dois o que é a mesma recomendação, no dia em que
         * alguém melhorasse o texto.
         */
        Schema::create('fiscalizacao_recomendacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacao_id')->constrained('fiscalizacoes')->cascadeOnDelete();
            $table->string('chave', 40);
            $table->timestamps();

            $table->unique(['fiscalizacao_id', 'chave']);
            $table->index('chave');
        });

        Schema::create('fiscalizacao_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacao_id')->constrained('fiscalizacoes')->cascadeOnDelete();

            /*
             * Caminho no disco PRIVADO. Foto de fiscalização mostra gente e
             * mercadoria: não fica atrás de URL adivinhável — sai por rota
             * autenticada, como a foto do ambulante.
             */
            $table->string('caminho', 255);
            $table->string('legenda', 200)->nullable();
            // A coordenada DA FOTO, que pode diferir da do registro (o fiscal
            // anda enquanto fotografa) — e é ela que prova o enquadramento.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('capturada_em')->nullable();
            // Idempotência do envio da foto, mesma razão do registro.
            $table->string('client_id', 40)->nullable()->unique();
            $table->timestamps();

            $table->index('fiscalizacao_id');
        });

        /*
         * O DOCUMENTO lavrado em rua — Notificação Preliminar ou Auto de
         * Apreensão. Ele nasce no aplicativo, com número oficial, e é impresso
         * na hora: por isso mora aqui e não numa tela da Retaguarda, que não
         * emite documento de campo.
         *
         * Os catálogos (motivos, sanções, fundamentação, guarda) são gravados
         * como CHAVE em JSON, e não como texto: é a chave que o relatório soma,
         * e a redação do impresso pode mudar sem reescrever o passado.
         */
        Schema::create('documentos_campo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacao_id')->constrained('fiscalizacoes')->cascadeOnDelete();

            // `np` (Notificação Preliminar) | `aa` (Auto de Apreensão).
            $table->string('tipo', 10);
            // O número oficial impresso no papel. Único por tipo.
            $table->string('numero', 20);

            // Quem foi notificado, como ele se identificou (ou não).
            $table->string('notificado', 200)->nullable();
            $table->string('documento_notificado', 20)->nullable();

            // A CHAVE do prazo no catálogo (`48h`, `5d`…). A duração mora no
            // catálogo, num lugar só — gravada em dias aqui, mudar o catálogo
            // deixaria este documento contando o prazo antigo.
            $table->string('prazo_chave', 20)->nullable();
            // A data que o prazo produziu. Gravada porque é o que vence, e o que
            // vence não pode depender de recalcular um catálogo que mudou.
            $table->date('prazo_ate')->nullable();

            // JSON de chaves dos catálogos de `config/prototipo_documentos_campo.php`.
            $table->text('motivos')->nullable();
            $table->text('sancoes')->nullable();
            $table->text('fundamentacao')->nullable();
            // Os bens apreendidos: produto, marca, quantidade, unidade, validade.
            $table->text('itens')->nullable();
            // Guarda dos bens (SEGUB): prazo e destinação.
            $table->string('guarda_prazo', 40)->nullable();
            $table->string('guarda_destinacao', 60)->nullable();

            $table->text('observacao')->nullable();

            /*
             * Os campos do FORMULÁRIO em si — nome, endereço, inscrição,
             * atividade, local, equipamento, decretos, artigos, portaria, os
             * complementos das caixas e as assinaturas.
             *
             * JSON, e não uma coluna cada, porque o conjunto MUDA entre os dois
             * impressos: a Notificação fala de inscrição e prazo; o Auto fala de
             * decretos, portaria e guarda de bens. Uma coluna por campo deixaria
             * metade da tabela nula em todo registro, e a cada revisão do papel
             * pediria migration.
             *
             * O que é REGRA — motivos, sanções, prazo, destinação — continua em
             * coluna própria, porque é por eles que o relatório soma e o prazo
             * vence. Aqui fica o que o papel diz, e só.
             */
            $table->text('dados')->nullable();

            $table->timestamp('emitido_em');
            $table->string('client_id', 40)->nullable()->unique();
            $table->timestamps();

            $table->unique(['tipo', 'numero']);
            $table->index('fiscalizacao_id');
            $table->index('prazo_ate');
        });

        $this->ligarTramiteAFiscalizacao();
    }

    /**
     * O passo do trâmite que veio de uma ida a campo aponta para ela.
     *
     * A ligação nasce aqui, e não na migration das demandas, porque é a
     * fiscalização que precisa existir primeiro — a chave estrangeira não pode
     * apontar para uma tabela que ainda não foi criada.
     *
     * Sem esta coluna, a leitura da denúncia perderia o que o fiscal escreveu: o
     * desfecho, as considerações, as recomendações e o documento ficariam do lado
     * da fiscalização, e o trâmite mostraria "Vistoria em campo" sem dizer o que
     * a equipe encontrou. Copiar esse conteúdo para dentro do passo seria a mesma
     * informação com dois donos — e o dia em que o fiscal corrigisse o relato, o
     * trâmite continuaria mostrando o antigo.
     */
    private function ligarTramiteAFiscalizacao(): void
    {
        Schema::table('demanda_tramites', function (Blueprint $table) {
            $table->foreignId('fiscalizacao_id')->nullable()->after('situacao')
                ->constrained('fiscalizacoes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('demanda_tramites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fiscalizacao_id');
        });

        Schema::dropIfExists('documentos_campo');
        Schema::dropIfExists('fiscalizacao_fotos');
        Schema::dropIfExists('fiscalizacao_recomendacoes');
        Schema::dropIfExists('fiscalizacoes');
    }
};
