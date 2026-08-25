<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Um número da regra de negócio que o cliente pode querer mudar sem release — o
 * prazo de notificação é o primeiro deles.
 *
 * Não tem tela nesta entrega, e isso é decisão: quem consome o parâmetro é a
 * cadeia de fiscalização, que ainda não existe. Tela de editar parâmetro antes
 * do fluxo que o lê seria um botão que não muda nada — ela nasce junto com o
 * fluxo, e é lá que se sabe qual é o efeito de mexer no número.
 *
 * O valor é guardado como TEXTO porque o significado é de quem lê (dia, real,
 * metro); quem lê converte. Uma coluna por parâmetro obrigaria uma migration a
 * cada regra nova.
 *
 * @property int $id
 * @property string $chave
 * @property string $valor
 * @property string|null $descricao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['chave', 'valor', 'descricao'])]
class ParametroFiscalizacao extends Model
{
    protected $table = 'parametros_fiscalizacao';

    /**
     * O valor de um parâmetro, ou o padrão quando ele ainda não foi cadastrado.
     *
     * O padrão em mão é obrigatório: parâmetro ausente não pode derrubar um
     * fluxo de rua — a regra segue com o valor de referência, e a falta aparece
     * no Monitoramento de Parametrizações.
     */
    public static function valor(string $chave, string $padrao): string
    {
        return (string) (static::query()->where('chave', $chave)->value('valor') ?? $padrao);
    }

    /** O mesmo, para os parâmetros que são contagem (dias, quantidade). */
    public static function inteiro(string $chave, int $padrao): int
    {
        $valor = static::valor($chave, (string) $padrao);

        return is_numeric($valor) ? (int) $valor : $padrao;
    }
}
