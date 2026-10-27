<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * O CATÁLOGO de bairros da cidade (dono, 25/09/2026: "crie também a tela Bairros
 * para cadastro de bairros, para termos um controle melhor").
 *
 * Antes dele, bairro só existia DENTRO de uma área (`area_bairros`), escrito à
 * mão a cada vez — e "Imbuí" e "Imbui" podiam virar dois. O catálogo é o nome
 * certo e a coordenada do bairro; as áreas continuam dizendo quais bairros cobrem,
 * pelo nome, e o cadastro de Áreas oferece o que está aqui.
 *
 * Bairro que está em alguma área não se exclui.
 *
 * @property int $id
 * @property string $nome
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nome', 'latitude', 'longitude', 'ativo'])]
class Bairro extends Model
{
    protected $table = 'bairros';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    /** O bairro do catálogo com este nome, sem se importar com acento e caixa. */
    public static function pelaChave(string $nome): ?self
    {
        $chave = Area::chaveDeBairro($nome);

        return self::query()->get()->first(static fn (self $b): bool => Area::chaveDeBairro($b->nome) === $chave);
    }

    /**
     * Traz para o catálogo todo bairro que as áreas já citam e que ainda não está
     * nele — com a primeira coordenada conhecida. Idempotente: roda na migration
     * que criou o catálogo e no fim da semeadura da estrutura.
     */
    public static function sincronizarDasAreas(): void
    {
        // Numa transação só: gravar bairro a bairro fora dela levava ~20 s no SQLite.
        DB::transaction(static fn () => self::sincronizar());
    }

    private static function sincronizar(): void
    {
        $conhecidos = self::query()->pluck('nome')->map(Area::chaveDeBairro(...))->flip()->all();

        foreach (AreaBairro::query()->orderBy('id')->get(['bairro', 'latitude', 'longitude']) as $vinculo) {
            $chave = Area::chaveDeBairro($vinculo->bairro);

            if ($chave === '' || isset($conhecidos[$chave])) {
                continue;
            }

            self::create([
                'nome' => $vinculo->bairro,
                'latitude' => $vinculo->latitude ?? AreaBairro::where('bairro', $vinculo->bairro)->whereNotNull('latitude')->value('latitude'),
                'longitude' => $vinculo->longitude ?? AreaBairro::where('bairro', $vinculo->bairro)->whereNotNull('longitude')->value('longitude'),
                'ativo' => true,
            ]);

            $conhecidos[$chave] = true;
        }
    }
}
