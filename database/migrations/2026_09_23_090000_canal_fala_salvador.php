<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O canal `salvador-digital` passa a se chamar `fala-salvador`.
 *
 * Decisão do dono em 22/09/2026: o canal telefônico da Prefeitura é o **Fala
 * Salvador** (156). Ele não tem API por enquanto e **só os líderes de equipe** o
 * acessam — o SEFAL é intermediário de registro: o líder digita aqui o que
 * recebeu lá, para o caso andar pelo mesmo fluxo das outras demandas, e continua
 * respondendo ao cidadão no próprio Fala Salvador.
 *
 * A chave viaja no banco (`demandas.canal`), na tela e no relatório. Trocar a
 * constante sem tocar o banco deixaria as demandas já gravadas fora da tela do
 * canal — nem de um nem do outro. É só `UPDATE` de texto: roda igual no SQLite
 * e no Oracle, e é idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('demandas')->where('canal', 'salvador-digital')->update(['canal' => 'fala-salvador']);
    }

    public function down(): void
    {
        DB::table('demandas')->where('canal', 'fala-salvador')->update(['canal' => 'salvador-digital']);
    }
};
