<?php

use App\Support\CatalogoFuncionalidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O slug `retorno-de-campo` é APOSENTADO: a tela virou a aba "A decidir" de
 * Fiscalizações.
 *
 * O slug não é rótulo — é a chave pela qual a guarda de acesso decide quem abre a
 * tela, e ela o deduz do PRIMEIRO trecho do caminho. No instante em que as rotas
 * mudaram para `/retaguarda/fiscalizacoes/…`, toda linha gravada como
 * `retorno-de-campo` deixou de casar com tela nenhuma: virou lixo na matriz do
 * Modo Gerente, ocupando espaço numa tela que existe para ser lida por gente.
 *
 * Pior que o lixo é o efeito no ACESSO. O Coordenador tinha concessão de
 * `retorno-de-campo` e NÃO tinha de `fiscalizacoes` (a tela unificada é que passou
 * a servi-lo). Sem esta migration, ele perderia em silêncio o acesso à fila que
 * acompanhava — e a semente não resolve: `PermissoesSetorSeeder` é `firstOrCreate`,
 * de propósito, para não desfazer o que se decidiu na tela; ela criaria a linha nova
 * com as decisões de fábrica e deixaria a antiga como resto, jogando fora qualquer
 * ajuste feito à mão.
 *
 * ## As duas situações, e por que cada uma se resolve diferente
 *
 * O par (setor, slug) é único, então há dois casos:
 *
 *   · o setor tinha SÓ `retorno-de-campo` (o Coordenador) → a linha é RENOMEADA.
 *     Preserva as duas coisas que importam: o acesso e a decisão de quem
 *     administra;
 *   · o setor tinha as DUAS (o Chefe de Setor, que já era concedido nas duas
 *     telas) → a antiga é APAGADA. Fica a de `fiscalizacoes`, que é a que a tela
 *     unificada consulta e a que já carrega o histórico da tela que continua
 *     existindo. Renomear por cima estouraria na unicidade e mataria a migration
 *     no meio.
 *
 * ## O que NÃO é reescrito, e por quê
 *
 * O log de alterações de permissão (`permissoes_log.funcionalidade_slug`) fica como
 * está. Ele registra ATOS — "em tal dia, tal pessoa mudou tal coisa" —, e naquele dia a tela
 * se chamava `retorno-de-campo`. Reescrever registro de auditoria para ficar bonito
 * é adulterar a auditoria.
 *
 * ## E uma concessão NOVA vai junto
 *
 * O Coordenador passa a CONSULTAR o Cadastro de Operação, e isso também não chega
 * pela semente (ver {@see concederOperacoesAoCoordenador}). A migration é o único
 * lugar em que uma decisão de acesso alcança um banco já semeado sem passar por
 * cima do que quem administra decidiu.
 *
 * ⚠️ Só `UPDATE`, `DELETE` e `INSERT` de texto: nenhuma DDL. Roda igual em SQLite
 * (dev) e em Oracle (produção).
 */
return new class extends Migration
{
    private const ANTIGO = 'retorno-de-campo';

    private const NOVO = 'fiscalizacoes';

    private const COORDENADOR = 'coordenador';

    private const TELA_DE_OPERACAO = 'operacoes';

    public function up(): void
    {
        $this->mesclar(self::ANTIGO, self::NOVO);
        $this->concederOperacoesAoCoordenador();
    }

    /**
     * A volta é possível, e é honesta sobre o que ela pode devolver: quem tinha
     * concessão só da tela antiga a recupera. Quem tinha as duas não recupera a
     * segunda linha — ela foi apagada por ser duplicata da que ficou, e inventá-la
     * de novo com decisões de fábrica seria fabricar acesso que ninguém concedeu.
     */
    public function down(): void
    {
        $this->mesclar(self::NOVO, self::ANTIGO);

        // A concessão nova do Cadastro de Operação sai junto: ela nasceu com esta
        // mudança, e desfazer a mudança sem tirá-la deixaria acesso concedido por
        // uma decisão que já não existe.
        DB::table('permissoes_setor')
            ->where('setor', self::COORDENADOR)
            ->where('slug', self::TELA_DE_OPERACAO)
            ->delete();
    }

    /**
     * O COORDENADOR passa a CONSULTAR o Cadastro de Operação.
     *
     * Isto é concessão NOVA, e não renomeação — e por isso ela não chega sozinha.
     * A lista `setores` do menu é a SEMENTE da matriz, aplicada uma vez pelo
     * `PermissoesSetorSeeder`; num banco que já foi semeado, acrescentar um setor
     * lá não cria linha nenhuma. Sem esta migration, o Coordenador abriria a tela
     * e seria mandado de volta — e o motivo ("você não tem acesso") seria
     * verdadeiro e incompreensível, porque a decisão de dar-lhe acesso já havia
     * sido tomada.
     *
     * ⚠️ **APENAS LEITURA**, e é o ponto: quem tria a entrada do trabalho precisa
     * saber que operação existe para onde encaminhar a demanda; MONTAR a operação
     * é de quem responde pela área. O controller recusa o ato dele de qualquer
     * forma (a régua é o papel, não só a permissão), mas conceder o pacote inteiro
     * aqui ofereceria botão que o servidor recusa.
     *
     * `insertOrIgnore`, e não `update`: se a linha já existir — porque alguém rodou
     * a semente, ou porque quem administra já concedeu à mão —, ela fica como está.
     * Migration que sobrescreve decisão de gerente é migration que apaga trabalho.
     */
    private function concederOperacoesAoCoordenador(): void
    {
        /*
         * As flags saem do MESMO lugar que a semente usaria
         * ({@see CatalogoFuncionalidades::acoesSemente}), e não escritas à mão: com
         * duas listas, um ajuste na declaração da tela deixaria esta migration
         * concedendo o pacote antigo — e o Coordenador ganharia por aqui o que o
         * menu já não lhe dá.
         */
        DB::table('permissoes_setor')->insertOrIgnore([
            'setor' => self::COORDENADOR,
            'slug' => self::TELA_DE_OPERACAO,
            ...CatalogoFuncionalidades::acoesSemente(self::TELA_DE_OPERACAO, self::COORDENADOR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Move as concessões de um slug para o outro, respeitando a unicidade do par
     * (setor, slug).
     */
    private function mesclar(string $de, string $para): void
    {
        $origem = DB::table('permissoes_setor')->where('slug', $de)->pluck('setor');

        if ($origem->isEmpty()) {
            return;
        }

        $jaTem = DB::table('permissoes_setor')
            ->where('slug', $para)
            ->whereIn('setor', $origem)
            ->pluck('setor');

        // Setor que já tem o destino: a linha antiga é duplicata: sai.
        if ($jaTem->isNotEmpty()) {
            DB::table('permissoes_setor')
                ->where('slug', $de)
                ->whereIn('setor', $jaTem)
                ->delete();
        }

        // O que sobrou é concessão que só existia na tela antiga: renomeia, e com
        // ela vem o acesso e a decisão de quem administra.
        DB::table('permissoes_setor')
            ->where('slug', $de)
            ->update(['slug' => $para, 'updated_at' => now()]);
    }
};
