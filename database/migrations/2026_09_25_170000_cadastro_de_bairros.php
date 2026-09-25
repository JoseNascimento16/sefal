<?php

use App\Models\Bairro;
use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O CATÁLOGO de bairros (dono, 25/09/2026) e a concessão da tela Sistema ›
 * Bairros a quem o menu declara (administrador e Chefe de Setor).
 *
 * Nasce preenchido com os bairros que as áreas já citam, cada um com a primeira
 * coordenada conhecida. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bairros')) {
            Schema::create('bairros', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120)->unique();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        Bairro::sincronizarDasAreas();

        foreach (CatalogoFuncionalidades::setoresSemente('bairros') as $setor) {
            if ($setor === 'administrador') {
                continue;
            }

            DB::table('permissoes_setor')->insertOrIgnore([
                'setor' => $setor,
                'slug' => 'bairros',
                ...CatalogoFuncionalidades::acoesSemente('bairros', $setor),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissoes_setor')->where('slug', 'bairros')->delete();
        Schema::dropIfExists('bairros');
    }
};
