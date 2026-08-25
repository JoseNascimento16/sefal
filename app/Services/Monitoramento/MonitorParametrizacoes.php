<?php

namespace App\Services\Monitoramento;

/**
 * O registro central das verificações — a promessa da tela de Monitoramento:
 * **tudo verde aqui, os fluxos do sistema estão operacionais**.
 *
 * A tela existe porque a alteração destrutiva de uma parametrização não avisa
 * ninguém: alguém inativa o último registro de um cadastro obrigatório, e o
 * defeito aparece dias depois, na mão de quem está trabalhando, sem que ninguém
 * relacione uma coisa com a outra. Aqui o sistema acusa O QUE deixou de
 * funcionar e leva PARA ONDE se corrige.
 *
 * ── Como ela cresce ──────────────────────────────────────────────────────────
 *
 * Um catálogo por módulo em `Checks/` — arquivo = módulo = seção retrátil da
 * tela. A regra é a mesma do primeiro dia: entra o que quebra fluxo em silêncio,
 * com saída declarada. Ao criar uma funcionalidade que dependa de parametrização
 * obrigatória, o check nasce JUNTO com ela; depois ninguém lembra.
 *
 * A Fase 1 nasce com um módulo só (infraestrutura). Fiscalização, áreas e
 * documentos entram nas fases seguintes, cada um no seu arquivo — a estrutura já
 * está pronta e os testes-lei já valem para eles.
 */
class MonitorParametrizacoes
{
    /**
     * Os módulos, na ordem em que a tela os mostra.
     *
     * @return list<array{modulo: string, catalogo: class-string}>
     */
    public static function modulos(): array
    {
        return [
            [
                'modulo' => 'Infraestrutura e ambiente',
                'catalogo' => Checks\ChecksInfraestrutura::class,
            ],
        ];
    }

    /**
     * Executa as verificações BARATAS e devolve a estrutura da tela, módulo a
     * módulo.
     *
     * Sem contagem de falhas junto: quem conta é a TELA, e por um motivo — o
     * resultado de uma verificação profunda substitui o estado de um check depois
     * que a página já está aberta. Uma contagem vinda daqui envelheceria nesse
     * instante, e a faixa-resumo diria "tudo certo" com um item vermelho logo
     * abaixo. Um número, um dono.
     *
     * @return list<array{modulo: string, checks: list<array<string, mixed>>}>
     */
    public function executarTodos(): array
    {
        return array_map(function (array $entrada): array {
            // `array_values` porque a tela recebe isto como LISTA (o JSON precisa
            // sair como vetor, não como objeto com chaves numéricas): um catálogo
            // que devolvesse os checks indexados viraria `{"0":…}` no navegador.
            $checks = array_values(array_map(
                fn (CheckParametrizacao $check): array => $check->paraTela($check->executar()),
                $entrada['catalogo']::checks(),
            ));

            return [
                'modulo' => $entrada['modulo'],
                'checks' => $checks,
            ];
        }, self::modulos());
    }

    /**
     * Executa as verificações PROFUNDAS — as que escrevem no disco de verdade ou
     * falam com um serviço externo.
     *
     * Elas ficam FORA da abertura da tela de propósito: rede e disco são lentos e
     * podem estar justamente indisponíveis, e a tela de diagnóstico não pode
     * depender do que ela está diagnosticando para aparecer.
     *
     * O resultado vem indexado por id porque a tela SUBSTITUI o status do check
     * correspondente — daí a lei do id único.
     *
     * @return array<string, array<string, mixed>>
     */
    public function executarProfundos(): array
    {
        $resultados = [];

        foreach (self::todosOsChecks() as $check) {
            if ($check->temVerificacaoProfunda()) {
                $resultados[$check->id] = $check->paraTela($check->executarProfunda());
            }
        }

        return $resultados;
    }

    /**
     * Todos os checks registrados, achatados — é por aqui que os testes-lei
     * alcançam os checks de hoje e os que ainda vão nascer.
     *
     * @return list<CheckParametrizacao>
     */
    public static function todosOsChecks(): array
    {
        $todos = [];

        foreach (self::modulos() as $entrada) {
            foreach ($entrada['catalogo']::checks() as $check) {
                $todos[] = $check;
            }
        }

        return $todos;
    }
}
