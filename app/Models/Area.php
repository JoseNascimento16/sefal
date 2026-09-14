<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A ÁREA — o recorte territorial que tem um Chefe de Setor e equipes.
 *
 * É o primeiro destino de uma demanda triada, e o sistema a SUGERE a partir do
 * bairro. Este model é o dono dessa sugestão ({@see doBairro()}) porque ela tem
 * de dar a mesma resposta na Caixa de Entrada, nas Denúncias e na API do
 * aplicativo — a mesma pergunta respondida em três lugares divergiria no
 * primeiro bairro que mudasse de área.
 *
 * @property int $id
 * @property string $nome
 * @property string|null $regiao
 * @property int|null $chefe_de_setor_id
 * @property string $recorte
 * @property string|null $poligono
 * @property string|null $turno
 * @property bool $ativa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $chefeDeSetor
 * @property-read Collection<int, Equipe> $equipes
 * @property-read Collection<int, AreaBairro> $bairros
 */
#[Fillable(['nome', 'regiao', 'chefe_de_setor_id', 'recorte', 'poligono', 'turno', 'ativa'])]
class Area extends Model
{
    public const RECORTE_BAIRROS = 'bairros';

    public const RECORTE_POLIGONO = 'poligono';

    protected $table = 'areas';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function chefeDeSetor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chefe_de_setor_id');
    }

    /** @return HasMany<Equipe, $this> */
    public function equipes(): HasMany
    {
        return $this->hasMany(Equipe::class);
    }

    /** @return HasMany<AreaBairro, $this> */
    public function bairros(): HasMany
    {
        return $this->hasMany(AreaBairro::class);
    }

    /** @return HasMany<Operacao, $this> */
    public function operacoes(): HasMany
    {
        return $this->hasMany(Operacao::class);
    }

    /**
     * As áreas que cobrem este bairro, na ordem do cadastro.
     *
     * Devolve LISTA porque o vínculo não é 1:1: Mussurunga, Patamares e Jardim
     * das Margaridas ficam na divisa e pertencem a duas áreas. Devolver uma só
     * faria o sistema escolher em silêncio entre duas respostas certas — e
     * esconder de quem tria a decisão que é dele.
     *
     * A comparação é frouxa de propósito (sem acento, sem caixa, sem espaço
     * sobrando): o bairro chega digitado por gente e por integração, e
     * "Centro Histórico", "centro historico" e "CENTRO HISTORICO" são o mesmo
     * lugar. Casar só o texto exato mandaria a demanda para lugar nenhum por
     * causa de um acento.
     *
     * @return list<self>
     */
    public static function doBairro(?string $bairro): array
    {
        $procurado = self::chaveDeBairro($bairro);

        if ($procurado === '') {
            return [];
        }

        $areas = [];

        foreach (AreaBairro::with('area')->get() as $vinculo) {
            if (self::chaveDeBairro($vinculo->bairro) === $procurado && $vinculo->area !== null) {
                $areas[$vinculo->area->id] = $vinculo->area;
            }
        }

        return array_values($areas);
    }

    /**
     * A área SUGERIDA para o bairro — a primeira que o cobre, ou nula.
     *
     * Atalho para quem só precisa de um destino padrão (o seeder, a API). Quem
     * mostra a escolha a uma pessoa deve usar {@see doBairro()} e oferecer as
     * alternativas: a sugestão nunca decide sozinha.
     */
    public static function sugeridaParaBairro(?string $bairro): ?self
    {
        return self::doBairro($bairro)[0] ?? null;
    }

    /** A forma comparável de um nome de bairro. Use ao gravar E ao procurar. */
    public static function chaveDeBairro(?string $bairro): string
    {
        $texto = mb_strtolower(trim((string) $bairro));
        $semAcento = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        return (string) preg_replace('/\s+/', ' ', $semAcento === false ? $texto : $semAcento);
    }

    /** @param  Builder<static>  $query */
    public function scopeAtivas(Builder $query): void
    {
        $query->where('ativa', true);
    }
}
