<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Os dois estados do encaminhamento passam a dizer PARA QUEM a demanda foi.
 *
 *   `Encaminhada à área`    → `Encaminhada ao líder`
 *   `Direcionada à equipe`  → `Direcionada aos fiscais`
 *
 * Decisão do dono em 22/09/2026. O Chefe de Setor deixou de ser um por área e
 * passou a ser um só, que escolhe a EQUIPE — e quem recebe é o líder dela. O
 * nome antigo ("à área") passaria a mentir numa tela em que se escolhe um líder;
 * e "Direcionada à equipe", que era o ato do chefe de área, agora é o ato do
 * líder mandando os fiscais ao ponto.
 *
 * ## Por que reescrever o que já está gravado
 *
 * A situação é TEXTO em `demandas.situacao` e em cada passo de
 * `demanda_tramites.situacao`, e o model compara por igualdade
 * (`Demanda::ABERTAS`, `FECHADAS`, os scopes). Trocar a constante sem tocar o
 * banco deixaria as demandas já encaminhadas fora de TODA lista: nem abertas nem
 * fechadas, invisíveis. Reescrever o texto do trâmite aqui NÃO é adulterar
 * história — o fato registrado é o mesmo, "foi encaminhada"; o que muda é como
 * o sistema o chama, e o trâmite precisa continuar legível pelo vocabulário que
 * a tela usa.
 *
 * Só `UPDATE` de texto: roda igual no SQLite e no Oracle.
 */
return new class extends Migration
{
    private const ESTADOS = [
        'Encaminhada à área' => 'Encaminhada ao líder',
        'Direcionada à equipe' => 'Direcionada aos fiscais',
    ];

    public function up(): void
    {
        foreach (self::ESTADOS as $de => $para) {
            $this->trocar($de, $para);
        }

        /*
         * O que estava "encaminhado à área" tinha ÁREA e não tinha EQUIPE — o
         * chefe da área escolhia a equipe depois. Agora quem recebe é o líder da
         * equipe, e o recorte dele é por `equipe_id`: sem preencher, essas
         * demandas não apareceriam para líder nenhum e ficariam paradas sem
         * ninguém ver. A equipe é a da área (hoje há uma por área; quando houver
         * mais, esta escolha já terá sido revista por quem encaminha).
         */
        $equipePorArea = DB::table('equipes')
            ->whereNotNull('area_id')
            ->orderBy('id')
            ->get(['id', 'area_id'])
            ->groupBy('area_id')
            ->map(static fn ($equipes) => $equipes->first()->id);

        foreach ($equipePorArea as $areaId => $equipeId) {
            DB::table('demandas')
                ->where('situacao', 'Encaminhada ao líder')
                ->whereNull('equipe_id')
                ->where('area_id', $areaId)
                ->update(['equipe_id' => $equipeId]);
        }
    }

    public function down(): void
    {
        foreach (self::ESTADOS as $de => $para) {
            $this->trocar($para, $de);
        }
    }

    private function trocar(string $de, string $para): void
    {
        DB::table('demandas')->where('situacao', $de)->update(['situacao' => $para]);
        DB::table('demanda_tramites')->where('situacao', $de)->update(['situacao' => $para]);
    }
};
