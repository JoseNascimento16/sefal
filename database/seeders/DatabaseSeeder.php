<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * O que o sistema PRECISA para funcionar — e nada de invenção.
 *
 * `db:seed` sem argumento é o que se roda ao preparar um ambiente, inclusive o de
 * homologação com o Oracle do cliente do outro lado. Por isso aqui só entra o que
 * é do sistema ou do cliente:
 *
 *  - os SETORES e a MATRIZ de permissões (sem eles ninguém acessa nada);
 *  - as LISTAS DE ESCOLHA e os parâmetros da fiscalização (sem elas o formulário
 *    de rua abre sem o que escolher);
 *  - a ESTRUTURA de áreas, bairros, equipes e fiscais — que é a transcrição do
 *    documento do cliente, não dado inventado.
 *
 * ⚠️ O dado de DEMONSTRAÇÃO (ambulantes, denúncias, operações e fiscalizações de
 * mentira) mora no {@see DemonstracaoSeeder}, e se chama à parte:
 *
 *     php artisan db:seed --class=DemonstracaoSeeder
 *
 * Misturá-lo aqui plantaria cadastro falso na base que a fiscalização consulta —
 * e, de quebra, faria todo teste que chama `$this->seed()` contar errado.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(SetoresSeeder::class);

        // Depois dos setores: a matriz de permissões concede a setores que já
        // têm de existir.
        $this->call(PermissoesSetorSeeder::class);

        // As listas de escolha e os parâmetros da fiscalização. Não é dado de
        // demonstração: sem elas o formulário de rua abre sem o que escolher.
        $this->call(ParametrizacaoFiscalizacaoSeeder::class);

        // Áreas, bairros, equipes e fiscais: é o documento do cliente. As contas
        // de fiscal nascem aqui porque é por elas que o aplicativo autentica.
        $this->call(EstruturaSeeder::class);
    }
}
