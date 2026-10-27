<?php

namespace App\Support;

use App\Models\Ambulante;
use App\Models\CicloDeFiscalizacao;
use App\Models\Demanda;
use App\Models\Fiscalizacao;
use Illuminate\Support\Facades\Auth;
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

            /*
             * A FILA da aba "A decidir" de Fiscalizações: o que voltou da rua e
             * espera a leitura da chefia. Alerta porque cobra ação, e porque é o
             * gatilho de trabalho de quem decide — sem o número, a chefia só
             * descobre que tem sete retornos parados quando abre a tela.
             *
             * ⚠️ O número é RECORTADO pela equipe, pela mesma regra que recorta a
             * listagem ({@see Papel}): um contador que somasse o universo
             * mostraria "12" a quem abre a tela e encontra 3, e a diferença
             * pareceria registro perdido. É a mesma fonte, o mesmo recorte.
             *
             * ⚠️ E ele só conta para quem DECIDE. Para o fiscal — que consulta e
             * não decide — o número seria uma cobrança sobre trabalho que não é
             * dele.
             *
             * É PROTÓTIPO, e por isso não é consulta a banco: a fila é derivada do
             * trâmite das denúncias mais o arquivo das avulsas, e as decisões vivem
             * na sessão. Continua barato (nenhuma linha vai ao banco) e continua
             * best-effort, como os outros.
             */
            'fiscalizacoes-a-decidir' => [
                'tom' => self::TOM_ALERTA,
                'valor' => function (): int {
                    $usuario = Auth::user();

                    if (! Papel::decide($usuario)) {
                        return 0;
                    }

                    $equipes = Papel::equipes($usuario);
                    $recorta = Papel::recorta($usuario);

                    /*
                     * O trabalho de cada um (dono, 24/09/2026): o CHEFE conta as
                     * Fiscalizações que o líder encaminhou a ele e esperam a
                     * deliberação (aba "Encaminhadas"); o LÍDER, as que estão com
                     * a equipe e pedem decisão dele — enviar à equipe, ou ler o
                     * retorno de campo. UMA contagem, sem carregar linha: o menu
                     * é montado em toda requisição da Retaguarda.
                     */
                    if (Papel::ehChefe($usuario) && ! $recorta && ! $usuario->ehAdmin()) {
                        return CicloDeFiscalizacao::query()->daAba(CicloDeFiscalizacao::ABA_ENCAMINHADAS)->count();
                    }

                    $consulta = CicloDeFiscalizacao::query()
                        ->daAba(CicloDeFiscalizacao::ABA_ANDAMENTO)
                        ->where(static fn ($q) => $q
                            ->whereHas('demanda', static fn ($d) => $d->where('situacao', Demanda::ENCAMINHADA_AO_LIDER))
                            ->orWhereHas('vistorias', static fn ($v) => $v->where('situacao', Fiscalizacao::AGUARDANDO_LEITURA)));

                    if ($recorta) {
                        $consulta->whereHas('equipe', static fn ($q) => $q->whereIn('codigo', $equipes));
                    }

                    return $consulta->count();
                },
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
