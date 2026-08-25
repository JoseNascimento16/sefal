<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SetoresSeeder::class);

        // Depois dos setores: a matriz de permissões concede a setores que já
        // têm de existir.
        $this->call(PermissoesSetorSeeder::class);

        // As listas de escolha e os parâmetros da fiscalização. Não é dado de
        // demonstração: sem elas o formulário de rua abre sem o que escolher.
        $this->call(ParametrizacaoFiscalizacaoSeeder::class);

        User::factory()->create([
            'name' => 'Test User',
            'login' => 'F0001',
            'email' => 'test@example.com',
        ]);
    }
}
