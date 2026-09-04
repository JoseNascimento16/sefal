<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A base das listas de escolha da Parametrização — tipo de infração, atividade
 * do ambulante, unidade de medida, tipo de operação, origem da operação e motivo
 * de recusa.
 *
 * Todas respondem às MESMAS duas perguntas ("quais existem?" e "quais podem ser
 * escolhidas hoje?"), e todas têm nome e situação. Isso mora aqui, uma vez só:
 * seis cópias de `where('ativo', true)` divergiriam no primeiro ajuste, e a
 * lista esquecida continuaria oferecendo em rua um valor que a gestão tirou de
 * circulação.
 *
 * É classe-base, e não trait, porque o tipo é usado como CONTRATO: a
 * parametrização trabalha com `class-string<ListaDeEscolha>`, e é isso que
 * garante — na análise estática, antes de rodar — que a lista declarada numa
 * tela tem mesmo nome e situação.
 *
 * @property int $id
 * @property string $nome
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
abstract class ListaDeEscolha extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    /**
     * As que podem ser escolhidas hoje.
     *
     * É o que separa "existe no cadastro" de "está em circulação": valor
     * inativado some do formulário e continua legível no que já foi gravado —
     * por isso inativar, e não excluir, é o caminho normal de aposentar um
     * valor.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAtivos(Builder $query): void
    {
        $query->where('ativo', true);
    }
}
