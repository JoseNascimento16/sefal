<?php

namespace Database\Seeders;

use App\Console\Commands\PrepararDemonstracao;
use App\Models\Ambulante;
use App\Models\AreaBairro;
use App\Models\Equipe;
use App\Models\Levantamento;
use App\Support\Aplicativo\CampoDoFiscal;
use App\Support\Aplicativo\LevantamentoDeCampo;
use Illuminate\Database\Seeder;

/**
 * Um censo de rua na área da equipe do `lider1`, para a demonstração mostrar o
 * censo virando estatística (dono, 28/10/2026): a camada "Presença (censo)" do
 * mapa de calor e o pino do ambulante movido para onde o censo o achou.
 *
 * Feito pelo caminho de verdade ({@see LevantamentoDeCampo}), como o `fiscal1`
 * concluiria no aplicativo. Dois ambulantes da base ganham documento (a base de
 * demonstração vem sem nenhum) para casar com as linhas do censo; os demais são
 * ambulantes fora da base, que contam só na estatística. Idempotente pelo
 * `client_id`.
 */
class CensoDaRuaSeeder extends Seeder
{
    public const CLIENT_ID = 'demo-censo-da-rua';

    /** CPFs de teste (dígito verificador válido), só para a demonstração. */
    private const DOCUMENTOS_DE_DEMONSTRACAO = ['529.982.247-25', '111.444.777-35'];

    public function run(): void
    {
        if (Levantamento::where('client_id', self::CLIENT_ID)->exists()) {
            return;
        }

        $equipe = Equipe::where('codigo', PrepararDemonstracao::EQUIPE_DO_LIDER)->first();
        $fiscal = $equipe === null ? null
            : ($equipe->fiscais()->where('login', 'fiscal1')->first() ?? $equipe->fiscais()->first());
        $bairro = $equipe === null ? null
            : AreaBairro::where('area_id', $equipe->area_id)->whereNotNull('latitude')->orderBy('bairro')->first();

        if ($fiscal === null || $bairro === null) {
            return;
        }

        $linhas = [];
        $lat = (float) $bairro->latitude;
        $lng = (float) $bairro->longitude;

        // Os da base: o censo os acha a uns 150 m de onde o pino estava.
        foreach ($this->ambulantesDaBase() as $i => $ambulante) {
            $lugar = $ambulante->ultimaLocalizacao;
            $linhas[] = [
                'id' => 'base-'.$i,
                'nome' => $ambulante->nome,
                'documento' => $ambulante->documento,
                'atividade' => $ambulante->atividade?->nome,
                'latitude' => ($lugar?->latitude ?? $lat) + 0.0012,
                'longitude' => ($lugar?->longitude ?? $lng) + 0.0009,
            ];
        }

        // Os de fora da base, espalhados pela rua: contam só na estatística.
        foreach (['Vendedor de água', 'Barraca de frutas', 'Carrinho de pipoca', 'Isopor de cerveja', 'Banca de óculos', 'Tabuleiro de acarajé'] as $i => $atividade) {
            $linhas[] = [
                'id' => 'rua-'.$i,
                'nome' => 'Ambulante não identificado '.($i + 1),
                'atividade' => $atividade,
                'referencia' => 'Ponto '.($i + 1).' da rua',
                'latitude' => $lat + 0.0004 * $i,
                'longitude' => $lng - 0.0003 * $i,
            ];
        }

        (new LevantamentoDeCampo(new CampoDoFiscal($fiscal)))->gravar([
            'client_id' => self::CLIENT_ID,
            'rua' => 'Rua principal de '.$bairro->bairro,
            'bairro' => $bairro->bairro,
            'equipes' => [$equipe->codigo],
            'linhas' => $linhas,
        ], []);
    }

    /**
     * Dois ambulantes da base com posição conhecida, com documento — os que já
     * o têm, ou os primeiros, que ganham um CPF de teste.
     *
     * @return list<Ambulante>
     */
    private function ambulantesDaBase(): array
    {
        $escolhidos = [];

        foreach (self::DOCUMENTOS_DE_DEMONSTRACAO as $documento) {
            $ambulante = Ambulante::with(['ultimaLocalizacao', 'atividade'])->where('documento', Ambulante::documentoCanonico($documento))->first()
                ?? Ambulante::with(['ultimaLocalizacao', 'atividade'])->whereNull('documento')->has('ultimaLocalizacao')
                    ->whereNotIn('id', array_map(fn (Ambulante $a) => $a->id, $escolhidos))
                    ->orderBy('id')->first();

            if ($ambulante === null) {
                continue;
            }

            if ($ambulante->documento === null) {
                $ambulante->documento = $documento;
                $ambulante->save();
            }

            $escolhidos[] = $ambulante;
        }

        return $escolhidos;
    }
}
