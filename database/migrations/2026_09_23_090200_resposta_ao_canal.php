<?php

use App\Services\ESalvador\ESalvador;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O RETORNO ao canal — o ato do Chefe de Setor que fecha o ciclo de uma demanda.
 *
 * Duas frentes pedem isso (decisão do dono, 22/09/2026):
 *
 *  - **e-Salvador**: concluído o trabalho, o chefe RESPONDE no processo de
 *    origem o que a fiscalização apurou (lá, é um trâmite no processo);
 *  - **avulsa**: a ligação/e-mail de superior não tem processo; concluído o
 *    trabalho, o chefe ABRE o processo no e-Salvador com o resultado.
 *
 * A API do e-Salvador é de PRODUÇÃO e a escrita nela está PROIBIDA por
 * enquanto (só GET). Então o ato existe e fica REGISTRADO AQUI — o texto, quem,
 * quando e o número do processo que o chefe abriu/respondeu à mão no e-Salvador
 * — e a chamada à API fica atrás de `ESALVADOR_LIGADA` ({@see ESalvador}).
 * Quando a escrita for liberada, o mesmo ato passa a enviar; o que já foi
 * registrado à mão continua valendo como história.
 *
 * Colunas na própria demanda porque a resposta é UMA por demanda — o que se
 * responde a dez denúncias agregadas é o registro principal, e a integração é
 * que replica nos processos dos agregados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandas', function (Blueprint $table) {
            if (! Schema::hasColumn('demandas', 'respondida_ao_canal_em')) {
                $table->dateTime('respondida_ao_canal_em')->nullable()->after('concluida_em');
                $table->text('resposta_ao_canal')->nullable()->after('respondida_ao_canal_em');
                // O identificador no e-Salvador (`assunto.unidade.numero/ano`): o do
                // processo respondido, ou o do processo que o chefe abriu.
                $table->string('processo_esalvador', 40)->nullable()->after('resposta_ao_canal');
                $table->foreignId('respondida_por_id')->nullable()->after('processo_esalvador')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('demandas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('respondida_por_id');
            $table->dropColumn(['respondida_ao_canal_em', 'resposta_ao_canal', 'processo_esalvador']);
        });
    }
};
