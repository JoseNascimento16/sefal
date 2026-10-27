<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O slug `denuncias` é APOSENTADO: as telas de canal passaram a morar em
 * `caixa-de-entrada` (decisão do dono, 24/09/2026).
 *
 * A Caixa de Entrada ("Geral") e a pasta Denúncias faziam o mesmo trabalho em
 * dois cantos do menu. Viraram UMA pasta com as quatro caixas de canal —
 * e-Salvador, Fala Salvador, e-Protocolo e Avulsas —, todas sob
 * `/retaguarda/caixa-de-entrada/…`. O slug é a chave da permissão (a guarda o
 * deduz do primeiro trecho do caminho), então quem tinha `denuncias` concedido
 * — o líder de equipe, em especial — perderia as telas em silêncio se a
 * concessão não fosse levada junto.
 *
 * Setor que já tem `caixa-de-entrada` fica com a linha que tem (a decisão já
 * tomada naquela tela prevalece); o que só tinha `denuncias` tem a linha
 * RENOMEADA, com as flags que tinha. Idempotente: no OKD o migrate é manual.
 */
return new class extends Migration
{
    private const ANTIGO = 'denuncias';

    private const NOVO = 'caixa-de-entrada';

    public function up(): void
    {
        $this->mesclar(self::ANTIGO, self::NOVO);
    }

    /**
     * A volta devolve `denuncias` a quem só a tinha por esta migration não é
     * distinguível — então devolve a TODOS que têm `caixa-de-entrada` e não
     * eram chefe/administrador: o líder, que era quem só tinha a tela antiga.
     */
    public function down(): void
    {
        DB::table('permissoes_setor')
            ->where('slug', self::NOVO)
            ->where('setor', 'lider-de-equipe')
            ->update(['slug' => self::ANTIGO]);
    }

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

        if ($jaTem->isNotEmpty()) {
            DB::table('permissoes_setor')->where('slug', $de)->whereIn('setor', $jaTem)->delete();
        }

        DB::table('permissoes_setor')->where('slug', $de)->update(['slug' => $para]);
    }
};
