<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Support\ListagensDaRetaguarda;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acompanhamento de Requisitos — o que está construído bate com o que foi escrito?
 *
 * A tela não pergunta "existe?" (isso se vê usando o sistema): ela pergunta se o
 * comportamento de hoje ainda condiz com o requisito que alguém escreveu um dia.
 * É a pergunta que ninguém responde de cabeça depois de algumas semanas — e é a
 * que a Qualidade responde por nós, em forma de card de retorno, quando não
 * respondemos antes.
 *
 * É SÓ LEITURA, e isso é decisão de fonte única: o mapa vive em
 * `config/acompanhamento_requisitos.php`, versionado junto com o código que ele
 * descreve. Um botão de editar aqui daria dois donos à mesma informação — a linha
 * mudaria na tela e continuaria velha no arquivo que o time lê na revisão.
 *
 * Nada é contado à mão: os totais e o resumo por módulo saem das linhas. Número
 * escrito envelhece na primeira funcionalidade nova, e painel que conta errado é
 * pior que painel nenhum, porque ninguém desconfia dele.
 *
 * Regra de negócio em `docs/regras-de-negocio/acompanhamento-de-requisitos.md`.
 */
class AcompanhamentoRequisitosController extends Controller
{
    public function index(): Response
    {
        /** @var Collection<int, array<string, mixed>> $telas */
        $telas = collect((array) config('acompanhamento_requisitos.telas', []));

        $sim = $telas->where('hu_status', 'sim')->count();
        $desatualizada = $telas->where('hu_status', 'desatualizada')->count();
        $nao = $telas->where('hu_status', 'nao')->count();
        $total = $telas->count();
        $comHu = $sim + $desatualizada;

        return Inertia::render('Retaguarda/Sistema/AcompanhamentoDeRequisitos', [
            /*
             * As colunas da grade e as do arquivo, do catálogo único
             * (`docs/padroes/listagem-clean.md`). Duas delas são condicionais, e
             * quem resolve a condição é o servidor — pela MESMA leitura que já
             * monta as linhas:
             *
             *  · `origem` só é coluna quando há mais de uma frente no mapa. Com
             *    tudo em "Retaguarda", ela repetiria a mesma palavra em 26 linhas;
             *  · `hus` só é coluna quando alguma linha aponta HU. Sem nenhuma, a
             *    coluna seria um travessão em toda linha — e o selo "Sem
             *    requisito" já diz isso uma vez, no lugar certo.
             *
             * Deixar essa conta para a tela criaria um segundo dono para uma
             * resposta que já está aqui, e um dia os dois discordariam.
             */
            'listagens' => ListagensDaRetaguarda::para('sistema.requisitos', [
                // O mesmo padrão que a linha mapeada usa ('Retaguarda' quando a
                // origem não vem declarada): com `pluck` cru, linha sem origem
                // contaria como um segundo valor e ligaria a coluna sem motivo.
                'varias-origens' => $telas
                    ->map(fn (array $t): string => (string) ($t['origem'] ?? 'Retaguarda'))
                    ->unique()->count() > 1,
                'alguma-hu' => $telas->contains(fn (array $t): bool => ((array) ($t['hus'] ?? [])) !== []),
            ]),

            'telas' => $telas->map(fn (array $t): array => [
                'modulo' => (string) ($t['modulo'] ?? ''),
                'tela' => (string) ($t['tela'] ?? ''),
                'origem' => (string) ($t['origem'] ?? 'Retaguarda'),
                'breadcrumb' => $t['breadcrumb'] ?? null,
                'hu_status' => (string) ($t['hu_status'] ?? 'nao'),
                'hus' => array_values((array) ($t['hus'] ?? [])),
                'nota' => $t['nota'] ?? null,
            ])->values(),

            'totais' => [
                'sim' => $sim,
                'desatualizada' => $desatualizada,
                'nao' => $nao,
                'comHu' => $comHu,
                'total' => $total,
                // Quanto do sistema tem requisito ESCRITO — a cobertura.
                'percentComHu' => $total > 0 ? (int) round($comHu / $total * 100) : 0,
                // Das que têm requisito escrito, quantas ainda condizem com ele —
                // a QUALIDADE do requisito. As duas contas são diferentes: dá para
                // ter cobertura alta e requisito todo desatualizado.
                'percentAlinhada' => $comHu > 0 ? (int) round($sim / $comHu * 100) : 0,
            ],

            'porModulo' => $telas->groupBy('modulo')
                ->map(fn (Collection $g, string $modulo): array => [
                    'modulo' => $modulo,
                    'sim' => $g->where('hu_status', 'sim')->count(),
                    'desatualizada' => $g->where('hu_status', 'desatualizada')->count(),
                    'nao' => $g->where('hu_status', 'nao')->count(),
                    'total' => $g->count(),
                ])
                ->values(),
        ]);
    }
}
