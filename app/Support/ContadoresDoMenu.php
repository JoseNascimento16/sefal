<?php

namespace App\Support;

use App\Models\Ambulante;
use Throwable;

/**
 * Os NÚMEROS VIVOS que o menu lateral mostra ao lado de um item.
 *
 * Um item do menu só ganha número se declarar `contador` em
 * `config/retaguarda_menu.php` — e o valor declarado é uma chave DESTE catálogo.
 * A ideia é que o número apareça onde ele muda a decisão de quem olha ("tem 7
 * retornos vencidos, começo por ali") e em nenhum outro lugar: contador em item
 * que ninguém consulta é enfeite que custa uma consulta por requisição.
 *
 * Duas regras de projeto moram aqui:
 *
 *  1. **barato.** Cada contador é UMA contagem, sem junção e sem carregar linha.
 *     O menu é montado em toda requisição da Retaguarda; qualquer coisa mais
 *     pesada que isso paga o preço em todas as telas, inclusive nas que não
 *     mostram o número.
 *  2. **melhor esforço.** Se a contagem falhar (banco fora do ar, tabela ainda
 *     não migrada), o item aparece SEM número em vez de derrubar a tela inteira.
 *     Menu é navegação: ele tem de existir justamente quando algo está errado.
 */
class ContadoresDoMenu
{
    /**
     * O tom do número diz o que ele significa:
     *
     *  · `neutro` — é tamanho ("128 cadastrados"). Informa, não cobra.
     *  · `alerta` — é FILA ("7 retornos vencidos"). Aparece em laranja e só
     *    aparece quando há o que fazer: zero não vira selo.
     *
     * O laranja é o mesmo da incidência no resto do sistema — a cor que diz
     * "isto está fora do esperado". Ver `docs/regras-de-negocio/design-retaguarda.md`.
     */
    public const TOM_NEUTRO = 'neutro';

    public const TOM_ALERTA = 'alerta';

    /**
     * Chave declarada no menu => como o número é apurado e com que tom aparece.
     *
     * @return array<string, array{tom: string, valor: callable(): int}>
     */
    private static function catalogo(): array
    {
        return [
            // O tamanho do cadastro. Neutro: é a dimensão do trabalho, não uma fila.
            'ambulantes' => [
                'tom' => self::TOM_NEUTRO,
                'valor' => fn (): int => Ambulante::query()->count(),
            ],

            // A FILA de conferência: cadastro que nasceu em rua e espera o Chefe de Setor
            // validar. É alerta porque cobra ação — e é a razão de a quarentena
            // existir. Zero não vira selo.
            'ambulantes-em-quarentena' => [
                'tom' => self::TOM_ALERTA,
                'valor' => fn (): int => Ambulante::query()->emQuarentena()->count(),
            ],
        ];
    }

    /**
     * O número de um item do menu, ou `null` quando não há o que mostrar — chave
     * desconhecida, contagem que falhou, ou fila vazia (alerta em zero não vira
     * selo: um "0" laranja chama atenção para dizer que não há nada).
     *
     * @return array{valor: int, tom: string}|null
     */
    public static function para(string $chave): ?array
    {
        $regra = self::catalogo()[$chave] ?? null;

        if ($regra === null) {
            return null;
        }

        try {
            $valor = ($regra['valor'])();
        } catch (Throwable) {
            // Melhor esforço: o item aparece sem número. Não se reporta a exceção
            // aqui de propósito — o banco fora do ar já vai ser relatado por quem
            // tentar carregar a tela, e um relato por item de menu, em toda
            // requisição, afogaria o log no próprio volume.
            return null;
        }

        if ($valor === 0 && $regra['tom'] === self::TOM_ALERTA) {
            return null;
        }

        return ['valor' => $valor, 'tom' => $regra['tom']];
    }
}
