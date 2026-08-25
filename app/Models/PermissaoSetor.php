<?php

namespace App\Models;

use App\Services\PermissaoService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Uma linha da matriz do Modo Gerente: o que um SETOR pode fazer numa TELA.
 *
 * Lógica positiva — a linha existe e a ação está marcada, então está concedida.
 * A ausência de linha nega. O administrador não tem linha aqui: o acesso total
 * dele é desvio no código ({@see PermissaoService}), porque linha de tabela
 * alguém desmarca por engano.
 *
 * @property int $id
 * @property string $setor
 * @property string $slug
 * @property bool $visivel
 * @property bool $habilitado
 * @property bool $apenas_leitura
 * @property bool $incluir
 * @property bool $excluir
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['setor', 'slug', 'visivel', 'habilitado', 'apenas_leitura', 'incluir', 'excluir'])]
class PermissaoSetor extends Model
{
    protected $table = 'permissoes_setor';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visivel' => 'boolean',
            'habilitado' => 'boolean',
            'apenas_leitura' => 'boolean',
            'incluir' => 'boolean',
            'excluir' => 'boolean',
        ];
    }
}
