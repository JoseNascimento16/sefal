<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * O DADO DE DEMONSTRAÇÃO — a cidade povoada para o cliente percorrer o fluxo.
 *
 * ## Por que ele NÃO está no `DatabaseSeeder`
 *
 * `db:seed` sem argumento é o que se roda ao preparar um ambiente — inclusive o
 * de homologação, com o Oracle do cliente do outro lado. O que o `DatabaseSeeder`
 * carrega tem de ser o que o sistema PRECISA para funcionar: os setores, a matriz
 * de permissões, as listas de escolha e a estrutura de áreas e equipes (que é o
 * documento do cliente, não invenção nossa).
 *
 * Ambulante, denúncia, operação e fiscalização de demonstração são outra coisa:
 * são gente e fatos inventados. Semeá-los junto plantaria cadastro falso na base
 * que a fiscalização consulta — e ninguém saberia, depois, quais dos 79
 * ambulantes existem de verdade.
 *
 * Separar também conserta um efeito colateral que já apareceu: teste que chama
 * `$this->seed()` para ter os setores ganhava 79 ambulantes de brinde e passava a
 * contar errado.
 *
 * ## A guarda de produção
 *
 * Ele se RECUSA a rodar em produção. Não é zelo excessivo: `db:seed --force` é
 * exatamente o comando que alguém digita num pod às onze da noite, e o estrago
 * (cadastro falso misturado ao real) não tem desfazer simples.
 */
class DemonstracaoSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production')) {
            throw new RuntimeException(
                'O seeder de demonstração planta cadastros inventados e não roda em produção. '
                .'Em produção os ambulantes vêm do SGCI e do cadastro de campo, e as demandas, da integração.',
            );
        }

        /*
         * A ordem importa: o ambulante precisa das atividades (lookup) e dá o
         * alvo das fiscalizações; a operação precisa da área e da equipe; a
         * demanda precisa das três; a fiscalização precisa da equipe com fiscais
         * dentro e do ambulante para pendurar o ponto da trilha.
         */
        /*
         * As listas de escolha primeiro: o ambulante precisa de uma atividade, e
         * chamar este seeder num banco sem elas estourava com "argumento não pode
         * ser nulo" — erro que não diz o que fazer. Ele é idempotente, então
         * chamá-lo de novo não custa nada.
         */
        $this->call(ParametrizacaoFiscalizacaoSeeder::class);

        $this->call(AmbulantesSeeder::class);
        $this->call(OperacoesSeeder::class);
        $this->call(DemandasSeeder::class);
        /*
         * O cenário da PRÉ-TRIAGEM: seis pessoas relatando o mesmo bar, mais
         * uma armadilha na mesma rua. Sem ele a tela de agrupamento abre vazia,
         * e tela que não se consegue avaliar vai para produção sem ter sido
         * olhada.
         */
        $this->call(DenunciasRepetidasSeeder::class);
        $this->call(FiscalizacoesSeeder::class);
    }
}
