<?php

use App\Support\CiclosDeFiscalizacao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A FISCALIZAÇÃO passa a ser o ciclo, e não a ida ao ponto (decisão do dono,
 * 24/09/2026).
 *
 * Até aqui cada linha de `fiscalizacoes` era uma VISTORIA — uma ida da equipe ao
 * ponto, com relato, fotos e documento. O dono descreveu outra coisa: a
 * Fiscalização é o conjunto de atos que pôs a demanda em prática, do
 * encaminhamento do Chefe de Setor ao líder até o líder devolver o resultado ao
 * chefe. Dentro dela cabem várias vistorias (o "mandar a equipe voltar" é mais
 * uma, do MESMO ciclo); e cada novo encaminhamento do chefe abre uma Fiscalização
 * IRMÃ. Quando o chefe devolve o processo à origem, as Fiscalizações da demanda
 * vão para o Arquivo.
 *
 * Por isso uma tabela nova — `fiscalizacao_ciclos` — e as vistorias ganham o
 * ponteiro para ela. Nada se perde: as vistorias continuam inteiras, com o que o
 * fiscal registrou; o ciclo só as agrupa e diz com quem está a posse.
 *
 * A situação "Devolvida à coordenação" da vistoria (do tempo do coordenador)
 * passa a se chamar "Encaminhada ao Chefe de Setor".
 *
 * Os ciclos das vistorias e demandas que já existem são RECONSTRUÍDOS aqui
 * ({@see CiclosDeFiscalizacao::sincronizarLegado()}). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscalizacao_ciclos')) {
            Schema::create('fiscalizacao_ciclos', function (Blueprint $table) {
                $table->id();
                $table->string('protocolo', 40)->unique();
                $table->string('origem', 20);
                $table->foreignId('demanda_id')->nullable()->constrained('demandas')->nullOnDelete();
                $table->foreignId('equipe_id')->nullable()->constrained('equipes')->nullOnDelete();
                // Com quem está: `equipe` (líder e fiscais) ou `chefe` (Chefe de Setor).
                $table->string('posse', 20)->default('equipe');
                $table->dateTime('aberto_em');
                $table->foreignId('aberto_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('encaminhado_ao_chefe_em')->nullable();
                $table->foreignId('encaminhado_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('motivo_do_encaminhamento')->nullable();
                $table->dateTime('arquivado_em')->nullable();
                $table->timestamps();

                $table->index(['demanda_id', 'aberto_em']);
                $table->index('posse');
            });
        }

        if (! Schema::hasColumn('fiscalizacoes', 'ciclo_id')) {
            Schema::table('fiscalizacoes', function (Blueprint $table) {
                $table->foreignId('ciclo_id')->nullable()->after('id')
                    ->constrained('fiscalizacao_ciclos')->nullOnDelete();
            });
        }

        DB::table('fiscalizacoes')->where('situacao', 'Devolvida à coordenação')
            ->update(['situacao' => 'Encaminhada ao Chefe de Setor']);

        CiclosDeFiscalizacao::sincronizarLegado();
    }

    public function down(): void
    {
        DB::table('fiscalizacoes')->where('situacao', 'Encaminhada ao Chefe de Setor')
            ->update(['situacao' => 'Devolvida à coordenação']);

        if (Schema::hasColumn('fiscalizacoes', 'ciclo_id')) {
            Schema::table('fiscalizacoes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ciclo_id');
            });
        }

        Schema::dropIfExists('fiscalizacao_ciclos');
    }
};
