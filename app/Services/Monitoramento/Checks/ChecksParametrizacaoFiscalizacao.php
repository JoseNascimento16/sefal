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
 * último registro de uma lista obrigatória não avisa ninguém — a gestão tira de
 * circulação a última atividade e o efeito só aparece dias depois, longe da tela
 * em que a decisão foi tomada.
 *
 * ── O critério, aplicado lista por lista ─────────────────────────────────────
 *
 * A régua é a de sempre: entra o que QUEBRA fluxo em silêncio, com a saída
 * declarada. Das seis listas de parametrização, entram duas — e as severidades
 * são as duas `aviso`, porque hoje nenhum fluxo GRAVA usando estas listas:
 *
 *  - **Atividade do ambulante** → `aviso`. ⚠️ Era `falha` até 10/09/2026, e a
 *    justificativa era "o cadastro de ambulante exige a atividade autorizada,
 *    então ninguém cadastra mais ninguém". Essa frase morreu no dia em que a tela
 *    de Ambulantes deixou de ser cadastro: a base passou a vir do **SGCI**, por
 *    integração, e não há mais formulário nenhum a travar. A lista continua sendo
 *    o ramo que a ficha do ambulante mostra e a faceta pela qual a busca filtra —
 *    e é ela que a integração vai ter de casar quando existir (PEND-001) —, então
 *    o check FICA. Mas vermelho para fluxo que não existe é a definição de
 *    severidade desonesta (RN-16): ensina a ignorar o vermelho, e aí o dia em que
 *    um deles for de verdade ninguém repara. Volta a `falha` junto com o primeiro
 *    caminho que grave ambulante — a integração do SGCI ou a fila do aplicativo
 *    do fiscal;
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
                    // Ativas, e não cadastradas: a lista em circulação é a que a
                    // integração vai poder casar e a que a busca oferece como
                    // faceta. Contar as inativas faria o check dizer que há o que
                    // escolher quando não há.
                    $ativas = AtividadeAmbulante::query()->where('ativo', true)->count();

                    if ($ativas === 0) {
                        $inativas = AtividadeAmbulante::query()->count();

                        // AVISO, e não falha: nada está parado agora — a tela de
                        // Ambulantes é consulta da base do SGCI, e nenhum caminho
                        // de hoje grava usando esta lista. Ver o cabeçalho.
                        return ResultadoCheck::aviso(
                            'Nenhuma atividade do ambulante em uso'
                            .($inativas > 0 ? ' (há '.Texto::contar($inativas, 'fora de uso', 'fora de uso').')' : '')
                            .' — nada está parado agora, porque a tela de Ambulantes é consulta da base do SGCI '
                            .'e ninguém cadastra por aqui. Mas é este o ramo que a ficha mostra e pelo qual a '
                            .'busca filtra, e é a lista que a integração vai ter de casar: vazia, o ambulante '
                            .'recebido chega sem ramo que o sistema reconheça.',
                        );
                    }

                    return ResultadoCheck::ok(
                        Texto::contar($ativas, 'atividade em uso', 'atividades em uso')
                        .' — há ramo que a consulta de ambulantes reconhece e filtra.',
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
