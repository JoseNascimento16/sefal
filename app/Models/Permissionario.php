<?php

namespace App\Models;

use App\Support\Documento;
use Database\Factories\PermissionarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * O permissionário — a pessoa que é fiscalizada.
 *
 * A identidade dele é **flexível por desenho** (ver a migration): documento é
 * opcional, e quem o identifica em rua é foto + apelido. Este model é o dono de
 * duas coisas que não podem ter uma segunda versão em lugar nenhum:
 *
 *  1. o catálogo de situações ({@see SITUACOES}) — inclusive a quarentena;
 *  2. a forma canônica do documento ({@see documentoCanonico()}), que grava
 *     sempre normalizado por {@see Documento}. Com máscara de um lado e sem
 *     máscara do outro, a mesma pessoa viraria dois cadastros — e a busca
 *     acharia um só.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nome
 * @property string|null $apelido
 * @property string|null $documento
 * @property string|null $rg
 * @property string|null $foto
 * @property string|null $telefone
 * @property string|null $numero_permissao
 * @property Carbon|null $validade_permissao
 * @property int $atividade_id
 * @property string $situacao
 * @property string|null $client_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AtividadeAmbulante $atividade
 */
#[Fillable([
    'codigo',
    'nome',
    'apelido',
    'documento',
    'rg',
    'foto',
    'telefone',
    'numero_permissao',
    'validade_permissao',
    'atividade_id',
    'situacao',
    'client_id',
])]
class Permissionario extends Model
{
    /** @use HasFactory<PermissionarioFactory> */
    use HasFactory;

    /** Cadastro em regra com a SEMOP. */
    public const SITUACAO_REGULAR = 'Regular';

    /** Trabalha sem permissão válida, ou fora do que ela autoriza. */
    public const SITUACAO_IRREGULAR = 'Irregular';

    /**
     * QUARENTENA. O cadastro nasceu em rua, pelo aplicativo, e espera o Gestor
     * validar (aprovar, mesclar um duplicado ou corrigir). Nunca entra direto
     * como regular: quem preencheu estava de pé na calçada, com o que a pessoa
     * disse — não com documento conferido.
     */
    public const SITUACAO_CAMPO = 'Cadastrado em campo';

    /**
     * O catálogo fechado de situações, na ordem em que a tela as oferece.
     *
     * @var list<string>
     */
    public const SITUACOES = [
        self::SITUACAO_REGULAR,
        self::SITUACAO_IRREGULAR,
        self::SITUACAO_CAMPO,
    ];

    protected $table = 'permissionarios';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['validade_permissao' => 'date'];
    }

    /**
     * Guarda o documento sempre NORMALIZADO — e vazio vira NULO.
     *
     * Mora no model, e não no controller, porque a tabela tem um índice único
     * nesta coluna: se um caminho de gravação (o formulário de hoje, a fila do
     * PWA amanhã) esquecesse de normalizar, o mesmo CPF entraria duas vezes com
     * formatos diferentes e o índice não teria como saber.
     *
     * ⚠️ `preg_replace('/\D/', '')` está PROIBIDO aqui: o CNPJ novo tem letras
     * nas 12 primeiras posições, e o strip as apagaria em silêncio.
     */
    protected function setDocumentoAttribute(?string $valor): void
    {
        $this->attributes['documento'] = self::documentoCanonico($valor);
    }

    /** A forma canônica de um documento — use ao gravar E ao procurar. */
    public static function documentoCanonico(?string $valor): ?string
    {
        $normalizado = Documento::normalizar($valor);

        return $normalizado === '' ? null : $normalizado;
    }

    /**
     * O ramo autorizado. A coluna é obrigatória e tem chave estrangeira, então a
     * relação sempre existe — não há caso de permissionário sem atividade.
     *
     * @return BelongsTo<AtividadeAmbulante, $this>
     */
    public function atividade(): BelongsTo
    {
        return $this->belongsTo(AtividadeAmbulante::class, 'atividade_id');
    }

    /** O documento como uma pessoa o lê (`000.000.000-00`), ou vazio. */
    public function documentoFormatado(): string
    {
        return $this->documento === null ? '' : Documento::formatar($this->documento);
    }

    /**
     * Os que ainda esperam a validação do Gestor.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEmQuarentena(Builder $query): void
    {
        $query->where('situacao', self::SITUACAO_CAMPO);
    }
}
