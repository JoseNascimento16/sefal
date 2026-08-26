<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LRV_PROTOCOLO_CONTADORES — contador atômico do gerador de protocolo (App\Support\Protocolo).
     * Uma linha por (prefixo, data YYYYMMDD) guarda o PRÓXIMO sequencial a ser entregue. O
     * `Protocolo::proximo` trava a linha (lockForUpdate) e incrementa — sem isso, dois pedidos
     * simultâneos leem o mesmo `count()` e nascem com o mesmo protocolo.
     *
     * A tabela nasce SEM prefixo aqui: o `LRV_` vem da conexão (`DB_PREFIX`), não do nome escrito
     * na migration. A unicidade (prefixo, data) é o que impede duas linhas de contador para o
     * mesmo dia — é ela que segura a corrida de criação junto com o `insertOrIgnore`.
     */
    public function up(): void
    {
        if (Schema::hasTable('protocolo_contadores')) {
            return;
        }

        Schema::create('protocolo_contadores', function (Blueprint $table) {
            $table->id();
            $table->string('prefixo', 8);
            $table->string('data', 8); // YYYYMMDD
            $table->unsignedInteger('proximo')->default(1);
            $table->timestamps();

            $table->unique(['prefixo', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocolo_contadores');
    }
};
