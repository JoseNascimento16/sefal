<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro sem GPS diz o motivo (dono, 14/10/2026).
 *
 * O aplicativo do fiscal, quando o aparelho não liberava a localização, mandava
 * no lugar dela o centro do bairro — uma coordenada inventada, que entrava no
 * mapa, no calor e no pino do ambulante como se fosse a posição medida. Agora o
 * registro sem GPS vai sem coordenada e com o MOTIVO, e a Retaguarda mostra a
 * marca. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscalizacoes', function (Blueprint $table) {
            if (! Schema::hasColumn('fiscalizacoes', 'sem_gps_motivo')) {
                $table->string('sem_gps_motivo', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fiscalizacoes', function (Blueprint $table) {
            if (Schema::hasColumn('fiscalizacoes', 'sem_gps_motivo')) {
                $table->dropColumn('sem_gps_motivo');
            }
        });
    }
};
