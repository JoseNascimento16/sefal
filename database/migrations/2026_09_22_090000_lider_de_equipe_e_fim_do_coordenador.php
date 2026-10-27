<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O LÍDER DE EQUIPE entra, o COORDENADOR sai — e o Chefe de Setor vira um só.
 *
 * Decisão do dono em 22/09/2026, depois de ouvir coordenadores, chefe de setor e
 * líderes. O que se descobriu muda a fundação do controle de acesso:
 *
 *  - os **coordenadores não usam o SEFAL**. Eles trabalham no e-Salvador e, de
 *    lá, direcionam as demandas para a caixa do setor. Manter o setor
 *    `coordenador` aqui seria manter uma porta para gente que nunca vai entrar
 *    por ela — e a Caixa de Entrada, construída como "a mesa do coordenador",
 *    passa a ser a mesa do Chefe de Setor;
 *  - o **Chefe de Setor é UMA pessoa**, e vê tudo. Até aqui ele era um por área
 *    (`areas.chefe_de_setor_id`), e todo o recorte do Modo Gerente pendia desse
 *    vínculo. O recorte que existe de verdade é o do **líder de equipe**: cada
 *    equipe tem o seu, e ele só responde pela própria equipe;
 *  - o **líder de equipe** é o "encarregado" do documento das áreas — que até
 *    aqui era um NOME em texto (`equipes.encarregado`). Ele vira usuário, com
 *    setor próprio, porque é ele que direciona aos fiscais e recebe o retorno.
 *
 * ## O que esta migration faz, e o que deixa como está
 *
 *  - cria `equipes.lider_id` (FK para `users`, nula). O texto `encarregado`
 *    FICA: é o nome do documento do cliente, e continua sendo o que a tela
 *    mostra quando a equipe ainda não tem líder com conta;
 *  - remove o setor `coordenador` do catálogo, dos vínculos e da matriz de
 *    permissões — o mesmo padrão da migration de 04/09 (só DML, roda igual em
 *    SQLite e Oracle). As CONTAS que tinham esse setor ficam: matrícula
 *    identifica gente. Elas perdem o setor e, com isso, o acesso às telas — que é
 *    exatamente o estado real de quem não usa o sistema;
 *  - `areas.chefe_de_setor_id` FICA, sem uso. Apagar coluna é DDL no Oracle e não
 *    há o que ganhar; o vínculo simplesmente deixa de ser lido.
 *
 * O log de permissões (`permissoes_log`) não é tocado: registra atos, e naquele
 * dia o papel existia.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: no OKD o `migrate` é passo manual depois do deploy, e quem
        // executa à mão executa duas vezes um dia.
        if (! Schema::hasColumn('equipes', 'lider_id')) {
            Schema::table('equipes', function (Blueprint $table) {
                $table->foreignId('lider_id')->nullable()->after('encarregado')
                    ->constrained('users')->nullOnDelete();
            });
        }

        $setor = DB::table('setores')->where('slug', 'coordenador')->first();

        if ($setor !== null) {
            DB::table('user_setores')->where('setor_id', $setor->id)->delete();
            DB::table('setores')->where('id', $setor->id)->delete();
        }

        DB::table('permissoes_setor')->where('setor', 'coordenador')->delete();
    }

    public function down(): void
    {
        Schema::table('equipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lider_id');
        });

        // O catálogo volta; os vínculos e a matriz não têm como voltar — o que
        // foi apagado não deixou cópia. Quem reverter semeia a matriz de novo.
        DB::table('setores')->updateOrInsert(
            ['slug' => 'coordenador'],
            ['nome' => 'Coordenador', 'created_at' => now(), 'updated_at' => now()],
        );
    }
};
