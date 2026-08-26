<?php

namespace App\Services\Monitoramento\Checks;

use App\Models\AtividadeAmbulante;
use App\Models\TipoInfracao;
use App\Services\Monitoramento\CheckParametrizacao;
use App\Services\Monitoramento\ResultadoCheck;
use App\Support\Texto;

/**
 * Parametrização da fiscalização — as listas de escolha sem as quais um fluxo
 * para, e para em silêncio.
 *
 * É exatamente o caso que a tela de Monitoramento existe para pegar: inativar o
 * último registro de uma lista obrigatória não avisa ninguém. O gestor tira de
 * circulação a última atividade, e dias depois o cadastro de permissionário
 * simplesmente não salva — com uma mensagem de campo que ninguém relaciona a uma
 * decisão tomada em outra tela.
 *
 * ── O critério, aplicado lista por lista ─────────────────────────────────────
 *
 * A régua é a de sempre: entra o que QUEBRA fluxo em silêncio, com a saída
 * declarada. Das seis listas de parametrização, entram duas — e as severidades
 * são diferentes de propósito:
 *
 *  - **Atividade do ambulante** → `falha`. O cadastro de permissionário exige a
 *    atividade autorizada e recusa a inativada: sem nenhuma ativa, ninguém
 *    cadastra mais ninguém, e é dele que a fiscalização parte;
 *  - **Tipo de infração** → `aviso`. Hoje nada consome a lista (o enquadramento
 *    em rua é de entrega futura), então nada está quebrado. Mas lista vazia é
 *    problema que se descobre em campo, com o fiscal na calçada — e o custo de
 *    avisar antes é uma linha amarela.
 *
 * **Unidade de medida, tipo de operação, origem de operação e motivo de recusa
 * ficam FORA por enquanto**, e isto está escrito aqui para a próxima revisão não
 * reexplorar: nenhuma delas tem consumidor nesta entrega. Check de fluxo que não
 * existe é verde permanente — e uma tela com muitos verdes que ninguém lê é
 * exatamente como um vermelho passa despercebido. Cada uma entra JUNTO com a
 * tela que a consome.
 */
class ChecksParametrizacaoFiscalizacao
{
    /** @return list<CheckParametrizacao> */
    public static function checks(): array
    {
        return [
            new CheckParametrizacao(
                id: 'parametrizacao-atividade-ativa',
                titulo: 'Existe atividade do ambulante em uso',
                verificacao: function (): ResultadoCheck {
                    // Ativas, e não cadastradas: o formulário só oferece as em
                    // circulação, e o servidor recusa a inativada num cadastro
                    // novo. Contar as inativas faria o check dizer que há o que
                    // escolher quando não há.
                    $ativas = AtividadeAmbulante::query()->where('ativo', true)->count();

                    if ($ativas === 0) {
                        $inativas = AtividadeAmbulante::query()->count();

                        return ResultadoCheck::falha(
                            'Nenhuma atividade do ambulante em uso'
                            .($inativas > 0 ? ' (há '.Texto::contar($inativas, 'fora de uso', 'fora de uso').')' : '')
                            .' — o cadastro de permissionário exige a atividade autorizada, então NINGUÉM consegue '
                            .'ser cadastrado enquanto a lista estiver assim. E é do cadastro que a fiscalização parte.',
                        );
                    }

                    return ResultadoCheck::ok(
                        Texto::contar($ativas, 'atividade em uso', 'atividades em uso')
                        .' — há o que escolher no cadastro de permissionário.',
                    );
                },
                rota: 'retaguarda.parametrizacao.atividades-do-ambulante.index',
                rotaRotulo: 'Abrir Atividades do Ambulante',
            ),

            new CheckParametrizacao(
                id: 'parametrizacao-tipo-infracao-ativo',
                titulo: 'Existe tipo de infração em uso',
                verificacao: function (): ResultadoCheck {
                    $ativos = TipoInfracao::query()->where('ativo', true)->count();

                    if ($ativos === 0) {
                        // Aviso, e não falha: nada está parado HOJE — o
                        // enquadramento em rua é de entrega futura. Inflar para
                        // vermelho ensinaria a ignorar o vermelho.
                        return ResultadoCheck::aviso(
                            'Nenhum tipo de infração em uso. Nada está parado agora, porque o enquadramento em rua '
                            .'ainda não foi entregue — mas, quando for, o fiscal abre o formulário na calçada e não '
                            .'tem o que escolher. Lista vazia aqui é problema que se descobre longe da mesa.',
                        );
                    }

                    return ResultadoCheck::ok(
                        Texto::contar($ativos, 'tipo de infração em uso', 'tipos de infração em uso')
                        .' — a lista está pronta para o enquadramento em rua.',
                    );
                },
                rota: 'retaguarda.parametrizacao.tipos-de-infracao.index',
                rotaRotulo: 'Abrir Tipos de Infração',
            ),
        ];
    }
}
