<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onde as coisas ficam — a coordenada do bairro e a TRILHA do ambulante.
 *
 * ## Por que o bairro ganha coordenada
 *
 * O mapa precisa saber onde desenhar. Até aqui as coordenadas viviam num arquivo
 * de protótipo, ao lado de gente inventada — e isso escondia que elas são a única
 * coisa daquele arquivo que NÃO era invenção: Centro Histórico, Calçada, Itapuã e
 * Cajazeiras são pontos reais de Salvador. Um mapa pode errar sobre quem está no
 * ponto; não pode errar ONDE o ponto fica, senão a única coisa que ele faz deixa
 * de valer.
 *
 * A coordenada é o CENTROIDE aproximado do bairro, e serve a três coisas: centrar
 * o mapa numa área, dar posição a um registro cujo GPS falhou, e desenhar o
 * relevo do mapa de calor. É `nullable` porque bairro novo entra pelo cadastro de
 * Áreas e Equipes, onde ninguém digita latitude — e um bairro sem coordenada
 * aparece na lista e não aparece no mapa, o que é honesto.
 *
 * ## A trilha: um ponto por EVENTO, nunca uma coluna no cadastro
 *
 * O ambulante não tem "a" localização: ele tem uma HISTÓRIA de onde esteve. Uma
 * coluna `latitude` no cadastro responderia "onde ele está" sobrescrevendo "onde
 * ele estava" — e é justamente a movimentação que a fiscalização precisa ver (o
 * ponto que migrou duas quadras depois da notificação é o caso clássico).
 *
 * Com um ponto por evento:
 *
 *  - o prontuário desenha a trilha, em ordem cronológica;
 *  - "quem pertence a esta área" passa a ser **o último ponto conhecido dentro do
 *    recorte** — pergunta que uma coluna sobrescrita não sabe responder para
 *    ontem;
 *  - a fiscalização e o cadastro alimentam a MESMA lista, e `fonte` diz qual foi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('area_bairros', function (Blueprint $table) {
            // Centroide aproximado do bairro. Ver o cabeçalho.
            $table->decimal('latitude', 10, 7)->nullable()->after('bairro');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::create('localizacoes_ambulante', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ambulante_id')->constrained('ambulantes')->cascadeOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('precisao_m')->nullable();

            /*
             * De onde veio o ponto: `fiscalizacao` (a equipe esteve lá) ou
             * `cadastro` (alguém registrou a posição do ponto de trabalho).
             * Gravado porque a confiança é diferente — o ponto de uma vistoria
             * tem GPS e testemunha; o de cadastro é o que alguém informou.
             */
            $table->string('fonte', 20)->default('fiscalizacao');

            /*
             * A ida que produziu o ponto, quando houve uma. Nulo no ponto de
             * cadastro. `nullOnDelete` porque apagar uma fiscalização não pode
             * apagar o fato de que o ambulante esteve ali.
             */
            $table->foreignId('fiscalizacao_id')->nullable()->constrained('fiscalizacoes')->nullOnDelete();

            $table->string('bairro', 120)->nullable();
            $table->timestamp('registrada_em');
            $table->timestamps();

            // A consulta que a trilha e o mapa fazem: os pontos de um ambulante,
            // do mais recente para o mais antigo.
            $table->index(['ambulante_id', 'registrada_em']);
            $table->index('registrada_em');
            $table->index('bairro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localizacoes_ambulante');

        Schema::table('area_bairros', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
