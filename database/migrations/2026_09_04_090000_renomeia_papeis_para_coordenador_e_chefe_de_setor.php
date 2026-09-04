<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Os dois papéis de retaguarda passam a se chamar como o cliente os chama:
 * `administrativo` → **Coordenador** e `gestor` → **Chefe de Setor**.
 *
 * Decisão do dono em 04/09/2026. O motivo não é estético: "gestor" e
 * "administrativo" são palavras genéricas que o próprio sistema usa para falar
 * de gestão e de ato administrativo, então o papel ficava sem nome próprio — e
 * na SEMOP quem responde por uma área é o **Chefe de Setor**, e quem faz a
 * triagem do que chega dos canais é o **Coordenador**.
 *
 * ## Por que a migration existe (o slug não é rótulo)
 *
 * O slug é a CHAVE por onde três coisas se encontram: o catálogo (`setores`), o
 * vínculo da pessoa (`user_setores`, que aponta para a linha do catálogo) e a
 * matriz de permissões (`permissoes_setor.setor`, que guarda o slug como TEXTO).
 * Trocar a lista em `config/retaguarda.setores` sem tocar no banco produziria o
 * pior resultado possível, e em silêncio:
 *
 *  - o `SetoresSeeder` é `updateOrCreate` por slug, então ele CRIARIA dois
 *    setores novos e deixaria os antigos como lixo — com os usuários ainda
 *    vinculados ao lixo, sem acesso a nada;
 *  - o `PermissoesSetorSeeder` é `firstOrCreate` de propósito (para não desfazer
 *    o que se decidiu na tela do Modo Gerente), então ele criaria linhas NOVAS
 *    com as concessões de fábrica e abandonaria as linhas ajustadas à mão.
 *
 * Renomear a linha existente preserva as três coisas de uma vez: o catálogo, o
 * vínculo de cada conta (que é por `setor_id`, e portanto acompanha o rename sem
 * ser tocado) e as concessões como o gerente as deixou.
 *
 * ## O que NÃO é reescrito, e por quê
 *
 * O log de alterações de permissão (`permissoes_log`) fica como está: ele
 * registra ATOS — "em tal dia, tal pessoa mudou tal coisa" —, e naquele dia o
 * papel se chamava `gestor`. Reescrever registro de auditoria para ficar
 * coerente com o vocabulário de hoje é adulterar a auditoria.
 *
 * As MATRÍCULAS de demonstração (`gestor1`, `gestor2`, `gestor3`,
 * `administrativo1`) também ficam: matrícula identifica gente, não cargo. A
 * pessoa que responde pela Área 5 continua sendo a mesma depois de o cargo
 * mudar de nome, e trocar a matrícula quebraria o vínculo com a área em
 * `config/prototipo_estrutura.php`, que casa pelo `login`.
 *
 * ## Portabilidade
 *
 * Só `UPDATE` de texto, sem DDL: roda igual no SQLite do desenvolvimento e no
 * Oracle. Nenhum `DROP`, nenhuma tabela recriada — e portanto nada a perder no
 * caminho. Os dois slugs novos cabem no `varchar(30)` das duas colunas
 * (`chefe-de-setor` tem 14 caracteres).
 */
return new class extends Migration
{
    /**
     * `antigo => [novo, nome de exibição]`.
     *
     * O nome vem junto porque a coluna `setores.nome` é o que a tela do Modo
     * Gerente mostra: renomear só o slug deixaria a matriz apresentando
     * "Gestor" para um papel cujo slug já é `chefe-de-setor`.
     */
    private const PAPEIS = [
        'administrativo' => ['coordenador', 'Coordenador'],
        'gestor' => ['chefe-de-setor', 'Chefe de Setor'],
    ];

    public function up(): void
    {
        foreach (self::PAPEIS as $antigo => [$novo, $nome]) {
            $this->trocar($antigo, $novo, $nome);
        }
    }

    public function down(): void
    {
        $nomes = ['coordenador' => 'Administrativo', 'chefe-de-setor' => 'Gestor'];

        foreach (self::PAPEIS as $antigo => [$novo]) {
            $this->trocar($novo, $antigo, $nomes[$novo]);
        }
    }

    /**
     * Renomeia um papel no catálogo e na matriz, preservando os pares únicos.
     *
     * A linha de DESTINO é apagada antes em cada tabela: se as duas existirem —
     * o cenário de um banco em que a semente rodou depois do rename —, o
     * `update` estouraria na unicidade (`setores.slug`, e o par
     * `permissoes_setor.(setor, slug)`) e a migration morreria no meio, com
     * metade dos papéis renomeados. Fica a linha que já tem histórico (a
     * renomeada), e não a que a semente acabou de inventar.
     */
    private function trocar(string $de, string $para, string $nome): void
    {
        $existia = DB::table('setores')->where('slug', $de)->exists();

        if ($existia) {
            DB::table('setores')->where('slug', $para)->delete();
            DB::table('setores')->where('slug', $de)->update([
                'slug' => $para,
                'nome' => $nome,
                'updated_at' => now(),
            ]);
        }

        $slugsDeTela = DB::table('permissoes_setor')->where('setor', $de)->pluck('slug');

        if ($slugsDeTela->isEmpty()) {
            return;
        }

        DB::table('permissoes_setor')
            ->where('setor', $para)
            ->whereIn('slug', $slugsDeTela)
            ->delete();

        DB::table('permissoes_setor')
            ->where('setor', $de)
            ->update(['setor' => $para, 'updated_at' => now()]);
    }
};
