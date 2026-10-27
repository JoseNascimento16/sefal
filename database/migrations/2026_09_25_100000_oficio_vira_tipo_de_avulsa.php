<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O OFÍCIO deixa de ser canal e vira um TIPO de avulsa (dono, 25/09/2026: "pode
 * considerar um ofício como um subtipo de avulsa").
 *
 * Os dois chegam ao Chefe de Setor por fora dos canais e têm o mesmo destino:
 * depois da fiscalização, o chefe decide se abre processo no e-Salvador ou
 * encerra só com a fiscalização. Desde 24/09 o ofício estava sem caixa — os
 * registros existiam no banco e não apareciam em tela nenhuma.
 *
 *  - `demandas.tipo_avulsa` diz QUAL avulsa é: pedido de superior (ligação ou
 *    e-mail) ou ofício. Nulo para as demandas de outros canais;
 *  - as avulsas que já existiam são pedidos de superior (era o único tipo);
 *  - os ofícios viram avulsas do tipo ofício, com o mesmo protocolo, trâmite,
 *    anexos e Fiscalizações. O número de origem é único POR CANAL: se um ofício
 *    tiver o mesmo número de uma avulsa, ganha o sufixo " (ofício)".
 *
 * Idempotente: roda de novo sem efeito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('demandas', 'tipo_avulsa')) {
            Schema::table('demandas', function (Blueprint $table) {
                $table->string('tipo_avulsa', 20)->nullable();
            });
        }

        DB::table('demandas')->where('canal', 'avulsa')->whereNull('tipo_avulsa')
            ->update(['tipo_avulsa' => 'pedido-de-superior']);

        $ocupados = DB::table('demandas')->where('canal', 'avulsa')->pluck('numero_origem')->filter()->all();

        DB::table('demandas')->where('canal', 'oficio')->orderBy('id')->get(['id', 'numero_origem'])
            ->each(function (object $oficio) use (&$ocupados) {
                $numero = $oficio->numero_origem;

                if ($numero !== null && in_array($numero, $ocupados, true)) {
                    $numero = mb_substr($numero, 0, 30).' (ofício)';
                }

                DB::table('demandas')->where('id', $oficio->id)->update([
                    'canal' => 'avulsa',
                    'tipo_avulsa' => 'oficio',
                    'numero_origem' => $numero,
                ]);

                $ocupados[] = $numero;
            });
    }

    public function down(): void
    {
        DB::table('demandas')->where('canal', 'avulsa')->where('tipo_avulsa', 'oficio')
            ->update(['canal' => 'oficio']);

        if (Schema::hasColumn('demandas', 'tipo_avulsa')) {
            Schema::table('demandas', function (Blueprint $table) {
                $table->dropColumn('tipo_avulsa');
            });
        }
    }
};
