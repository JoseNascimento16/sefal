<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Equipe;
use App\Models\Operacao;
use App\Models\OperacaoBairro;
use App\Support\Protocolo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * As operações do protótipo (`config/prototipo_operacoes.php`) virando banco.
 *
 * As datas continuam RELATIVAS — `inicio_ha_dias` e `fim_em_dias` viram data na
 * hora de semear, pelo mesmo motivo de sempre: data fixa envelhece, e uma
 * semana depois a demonstração mostraria tudo encerrado.
 *
 * A SITUAÇÃO não é copiada do arquivo: é recalculada por
 * {@see Operacao::situacaoPeloPeriodo()}. Copiar a do arquivo seria dar dois
 * donos à mesma regra — e o primeiro dia em que o período e o rótulo
 * discordassem, ninguém saberia qual dos dois o sistema obedece.
 */
class OperacoesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('prototipo_operacoes.operacoes', []) as $dados) {
            $area = Area::where('nome', (string) ($dados['area'] ?? ''))->first();

            if ($area === null) {
                // Sem área não há operação: ela é o recorte de quem responde.
                continue;
            }

            $inicio = Date::now()->subDays((int) ($dados['inicio_ha_dias'] ?? 0))->startOfDay();
            $fim = isset($dados['fim_em_dias']) && $dados['fim_em_dias'] !== null
                ? Date::now()->addDays((int) $dados['fim_em_dias'])->startOfDay()
                : null;

            $operacao = Operacao::firstOrNew(['nome' => (string) $dados['nome']]);

            $operacao->fill([
                'codigo' => $operacao->codigo ?? Protocolo::proximo('OP'),
                'area_id' => $area->id,
                'coordenador_id' => $area->chefe_de_setor_id,
                'regiao' => $dados['regiao'] ?? null,
                'foco' => $dados['foco'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
                'inicio' => $inicio,
                'fim' => $fim,
            ]);

            $operacao->situacao = $operacao->situacaoPeloPeriodo();
            $operacao->save();

            $this->semearEquipes($operacao, (array) ($dados['equipes'] ?? []));
            $this->semearBairros($operacao, (array) ($dados['bairros'] ?? []));
        }
    }

    /** @param  list<string>  $codigos */
    private function semearEquipes(Operacao $operacao, array $codigos): void
    {
        $ids = Equipe::whereIn('codigo', $codigos)->pluck('id')->all();

        $operacao->equipes()->syncWithoutDetaching($ids);
    }

    /** @param  list<string>  $bairros */
    private function semearBairros(Operacao $operacao, array $bairros): void
    {
        foreach ($bairros as $bairro) {
            OperacaoBairro::firstOrCreate([
                'operacao_id' => $operacao->id,
                'bairro' => (string) $bairro,
            ]);
        }
    }
}
