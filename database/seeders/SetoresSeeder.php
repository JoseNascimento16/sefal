<?php

namespace Database\Seeders;

use App\Models\Setor;
use Illuminate\Database\Seeder;

/**
 * Semeia o catálogo de setores a partir de `config('retaguarda.setores')`.
 * Idempotente: rodar de novo ajusta o nome sem duplicar o slug.
 */
class SetoresSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('retaguarda.setores', []) as $slug => $nome) {
            Setor::updateOrCreate(['slug' => $slug], ['nome' => $nome]);
        }
    }
}
