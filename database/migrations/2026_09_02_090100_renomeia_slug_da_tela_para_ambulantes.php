<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A matriz de permissões passa a chamar a tela de `ambulantes`.
 *
 * O slug não é rótulo: é a chave pela qual a guarda de acesso decide quem abre a
 * tela. Ele sai do PRIMEIRO trecho do caminho (`/retaguarda/ambulantes/…`),
 * então no instante em que a rota mudou de nome, toda linha gravada como
 * `permissionarios` deixou de casar com tela nenhuma — e o efeito prático é o
 * pior possível: quem tinha a tela concedida perderia o acesso em silêncio, e o
 * administrador teria de reconceder à mão setor por setor.
 *
 * A semente (`PermissoesSetorSeeder`) não resolve: ela é `firstOrCreate`, e é
 * assim de propósito — para não desfazer o que se decidiu na tela do Modo
 * Gerente. Ela criaria linhas NOVAS com o slug novo e as decisões de fábrica,
 * deixando as antigas como lixo e jogando fora qualquer ajuste feito à mão.
 * Renomear a linha existente preserva as duas coisas: o acesso e a decisão.
 *
 * ## O que NÃO é reescrito, e por quê
 *
 * O log de alterações de permissão (`permissoes_log.funcionalidade_slug`) fica
 * como está. Ele registra ATOS — "em tal dia, tal pessoa mudou tal coisa" —, e
 * naquele dia a tela se chamava `permissionarios`. Reescrever registro de
 * auditoria para ficar bonito é adulterar a auditoria.
 */
return new class extends Migration
{
    private const ANTIGO = 'permissionarios';

    private const NOVO = 'ambulantes';

    public function up(): void
    {
        $this->trocar(self::ANTIGO, self::NOVO);
    }

    public function down(): void
    {
        $this->trocar(self::NOVO, self::ANTIGO);
    }

    /**
     * Troca o slug preservando o par (setor, slug), que é único.
     *
     * A linha de destino é apagada antes: se as duas existirem — cenário de banco
     * onde a semente rodou depois do rename —, o `update` estouraria na unicidade
     * e a migration morreria no meio. Fica a linha que já tem histórico (a
     * renomeada), e não a que a semente acabou de inventar.
     */
    private function trocar(string $de, string $para): void
    {
        $setores = DB::table('permissoes_setor')->where('slug', $de)->pluck('setor');

        if ($setores->isEmpty()) {
            return;
        }

        DB::table('permissoes_setor')
            ->where('slug', $para)
            ->whereIn('setor', $setores)
            ->delete();

        DB::table('permissoes_setor')
            ->where('slug', $de)
            ->update(['slug' => $para, 'updated_at' => now()]);
    }
};
