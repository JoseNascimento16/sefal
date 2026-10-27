<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Um passo do trâmite de uma demanda. **Append-only**: passo não se edita nem se
 * apaga — ato administrativo corrigido por sobrescrita é ato perdido. Erro se
 * conserta com passo novo.
 *
 * O `papel` fica gravado ao lado do `user_id` porque ele é o cargo de QUEM AGIU
 * NAQUELE MOMENTO. Sem ele, um usuário que muda de setor reescreveria o passado.
 *
 * @property int $id
 * @property int $demanda_id
 * @property int $ordem
 * @property Carbon $ocorrida_em
 * @property int|null $user_id
 * @property string $papel
 * @property string|null $autor
 * @property string $acao
 * @property string|null $detalhe
 * @property string $situacao
 * @property array<string, scalar|null>|null $campos
 */
#[Fillable([
    'demanda_id', 'ordem', 'ocorrida_em', 'user_id', 'papel', 'autor',
    'acao', 'detalhe', 'situacao', 'campos', 'fiscalizacao_id',
])]
class DemandaTramite extends Model
{
    /** O passo que o SISTEMA deu: recebimento por integração, sem autor humano. */
    public const PAPEL_INTEGRACAO = 'integracao';

    /**
     * Quem recebe tudo, pré-tria, encaminha a um líder, devolve, fecha e responde
     * ao canal. Até 22/09/2026 parte disso era assinado como `coordenador`; os
     * passos antigos guardam esse texto e não são reescritos — trâmite é
     * história, e naquele dia o papel se chamava assim.
     */
    public const PAPEL_CHEFE_DE_SETOR = 'chefe-de-setor';

    /** Quem direciona aos fiscais e lê o que volta da própria equipe. */
    public const PAPEL_LIDER = 'lider-de-equipe';

    public const PAPEL_FISCAL = 'fiscal';

    protected $table = 'demanda_tramites';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ocorrida_em' => 'datetime',
            // CLOB com JSON dentro: cada tipo de passo carrega campos diferentes.
            'campos' => 'array',
        ];
    }

    /** @return BelongsTo<Demanda, $this> */
    public function demanda(): BelongsTo
    {
        return $this->belongsTo(Demanda::class);
    }

    /**
     * A ida a campo que produziu este passo — nula na maioria deles.
     *
     * É por ela que a leitura da denúncia mostra o que o fiscal encontrou sem
     * guardar uma segunda cópia do relato.
     *
     * @return BelongsTo<Fiscalizacao, $this>
     */
    public function fiscalizacao(): BelongsTo
    {
        return $this->belongsTo(Fiscalizacao::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Quem assina o passo, como a tela mostra. O nome gravado tem precedência
     * sobre o da conta: é o que sobrevive à exclusão do usuário.
     */
    public function quem(): string
    {
        return $this->autor ?? $this->usuario?->name ?? 'Sistema';
    }
}
