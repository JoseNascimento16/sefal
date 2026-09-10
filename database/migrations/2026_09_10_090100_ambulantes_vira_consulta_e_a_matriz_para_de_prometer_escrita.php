<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A tela de Ambulantes deixou de gravar, e a MATRIZ para de prometer que ela grava.
 *
 * Decisão do dono (10/09/2026): "a tela de Ambulantes não será CRUD, só irá receber
 * os registros do SGCI via integração". As rotas de inclusão, alteração e exclusão
 * saíram do servidor — não existe mais caminho de escrita nesta tela.
 *
 * Isso deixaria uma mentira na matriz do Modo Gerente. O Chefe de Setor nasceu com o
 * pacote completo (`Vê`, `Opera`, `Inclui`, `Exclui`) porque validar e corrigir
 * cadastro de campo era trabalho dele; quem abrisse o Modo Gerente hoje veria as
 * quatro marcas ligadas e concluiria que ele inclui e exclui ambulante — e
 * DESMARCÁ-LAS não mudaria nada, porque não há rota a barrar. Marca que não decide
 * nada é pior que marca ausente: ela ensina errado quem distribui acesso.
 *
 * A declaração no menu já foi corrigida para `['apenas_leitura' => true]` em todos
 * os setores, e isso resolve o banco que ainda vai nascer. Não resolve os que já
 * existem: a semente é aplicada UMA VEZ (`firstOrCreate`, de propósito, para não
 * desfazer o que se decidiu na tela).
 *
 * ## Por que a correção é CONDICIONAL
 *
 * Ela só toca a linha que AINDA ESTÁ como a semente a deixou — a impressão digital
 * exata do pacote completo. Se alguém mexeu em qualquer uma das cinco colunas pelo
 * Modo Gerente, a linha fica INTACTA: migration não desfaz decisão de gente. Mesmo
 * critério de `2026_08_26_120000_fiscal_apenas_consulta_permissionarios`, que é o
 * irmão desta — ele fez pelo FISCAL, em 26/08, o que esta faz pelo resto.
 *
 * O FISCAL não é tocado aqui: ele já está em `apenas_leitura` desde aquela
 * migration, que é justamente a linha que esta procura como resultado.
 *
 * ⚠️ Só `UPDATE` de texto: nenhuma DDL. Roda igual em SQLite (dev) e em Oracle
 * (produção), e é idempotente — no OKD o `migrate` é passo manual depois do deploy.
 *
 * ⚠️ O `slug` NÃO muda. `ambulantes` é a identidade da tela na guarda de acesso
 * (deduzida do primeiro trecho do caminho): renomeá-lo porque o rótulo mudou tiraria
 * a tela da matriz e mataria a permissão de quem já a tem.
 */
return new class extends Migration
{
    private const TABELA = 'permissoes_setor';

    private const TELA = 'ambulantes';

    public function up(): void
    {
        DB::table(self::TABELA)
            ->where('slug', self::TELA)
            // A impressão digital da semente antiga, coluna a coluna.
            ->where('visivel', true)
            ->where('habilitado', true)
            ->where('apenas_leitura', false)
            ->where('incluir', true)
            ->where('excluir', true)
            ->update([
                'habilitado' => false,
                'apenas_leitura' => true,
                'incluir' => false,
                'excluir' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * A volta devolve o pacote completo, e pelo mesmo critério: só onde a linha está
     * exatamente como esta migration a deixou.
     *
     * ⚠️ Ela é honesta sobre o que NÃO consegue distinguir: uma linha que já era
     * `apenas_leitura` por decisão de alguém — o fiscal, por exemplo — tem a mesma
     * aparência da que esta migration converteu. Por isso o rollback exclui o fiscal
     * nominalmente: reabrir gravação para quem foi restringido de propósito em
     * 26/08 seria desfazer uma decisão que nada aqui tomou.
     */
    public function down(): void
    {
        DB::table(self::TABELA)
            ->where('slug', self::TELA)
            ->where('setor', '<>', 'fiscal')
            ->where('visivel', true)
            ->where('habilitado', false)
            ->where('apenas_leitura', true)
            ->where('incluir', false)
            ->where('excluir', false)
            ->update([
                'habilitado' => true,
                'apenas_leitura' => false,
                'incluir' => true,
                'excluir' => true,
                'updated_at' => now(),
            ]);
    }
};
