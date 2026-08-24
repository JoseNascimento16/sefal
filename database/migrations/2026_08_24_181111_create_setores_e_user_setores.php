<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('nome', 60);
            $table->timestamps();
        });

        Schema::create('user_setores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $table->timestamps();

            // Um usuário pertence a um setor uma vez só — o par é a chave de negócio.
            $table->unique(['user_id', 'setor_id'], 'user_setores_par_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_setores');
        Schema::dropIfExists('setores');
    }
};
