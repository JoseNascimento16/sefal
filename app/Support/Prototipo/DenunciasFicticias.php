<?php

namespace App\Support\Prototipo;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;

/**
 * PROTÓTIPO — as denúncias que as ouvidorias entregam por INTEGRAÇÃO.
 *
 * ⚠️ Nada aqui toca o banco. As denúncias de partida vêm de
 * `config/prototipo_denuncias.php` e o que a pessoa decide fica na SESSÃO dela —
 * o bastante para o dono percorrer as duas etapas do fluxo e aprovar a forma
 * antes de existir tabela, migration e contrato de API.
 *
 * ── O fluxo tem DUAS etapas, com DOIS donos ─────────────────────────────────
 *
 *   1. **Triagem** (administrativo) — lê a denúncia `Recebida`, decide se ela
 *      procede e a **encaminha à ÁREA** correspondente, derivada do bairro. A
 *      derivação SUGERE; quem confirma é gente, porque um bairro pode pertencer
 *      a duas áreas e aí as duas respostas estão certas. As saídas da triagem são
 *      `Devolvida` e `Arquivada`, sempre com motivo e justificativa: denúncia
 *      improcedente ou duplicada não deve chegar ao gestor.
 *   2. **Direcionamento** (gestor da área) — pega o que foi encaminhado à área
 *      dele e escolhe COMO o trabalho acontece: direciona à **equipe** (a da
 *      área, ou outra, e aí a justificativa é obrigatória) ou anexa a uma
 *      **operação** já planejada.
 *
 * As duas etapas operam em LOTE e uma a uma. O lote é o caso normal do
 * administrativo (a integração entrega várias de uma vez), e é por isso que os
 * métodos daqui recebem lista de identificadores, nunca um só: dois caminhos —
 * um para o lote e outro para o registro isolado — seriam a mesma regra com dois
 * donos, e um dia só um deles ganharia a validação nova.
 *
 * ── O que este protótipo faz de propósito, e que a versão real vai precisar ──
 *
 *  • **carimbo de origem**: cada denúncia carrega de onde veio, o número que o
 *    canal lhe deu e QUANDO a integração a entregou. É o que separa "chegou de
 *    fora" de "alguém digitou aqui" — e ninguém digita nada nestas telas;
 *  • **trâmite**: toda decisão acrescenta uma linha ao histórico (quem, quando,
 *    o quê, por quê). Sem o rastro, "arquivada" é uma palavra sem autor;
 *  • **justificativa obrigatória** ao devolver, arquivar ou trocar a equipe da
 *    área — e a validação mora no controller, não só no formulário;
 *  • **numeração própria** (`DEN-NNNN`). No sistema real ela passa a sair de
 *    `App\Support\Protocolo::proximo()`, que é a fonte única de numeração — aqui
 *    não sai, porque o protótipo não grava nada e o contador vive no banco.
 */
class DenunciasFicticias
{
    /*
     * ⚠️ As duas chaves são IRMÃS (`…denuncias.itens` e `…denuncias.operacoes`), e
     * nenhuma é prefixo da outra. A sessão do Laravel interpreta ponto como
     * caminho aninhado: com `prototipo.denuncias` guardando a lista e
     * `prototipo.denuncias.operacoes` guardando as operações, a segunda gravação
     * entrava DENTRO da primeira — a lista de denúncias passava a ter um item a
     * mais, que era o array de operações, e a leitura seguinte estourava ao
     * procurar o identificador dele.
     */
    private const CHAVE = 'prototipo.denuncias.itens';

    private const CHAVE_OPERACOES = 'prototipo.denuncias.operacoes';

    /** As situações em que a denúncia ainda espera a TRIAGEM do administrativo. */
    public const AGUARDANDO_TRIAGEM = ['Recebida'];

    /** As situações em que ela espera o DIRECIONAMENTO do gestor da área. */
    public const AGUARDANDO_DIRECIONAMENTO = ['Encaminhada à área'];

    /**
     * As denúncias de UM canal, da mais recente para a mais antiga.
     *
     * @return list<array<string, mixed>>
     */
    public static function doCanal(string $canal): array
    {
        $denuncias = array_values(array_filter(
            self::todas(),
            static fn (array $d): bool => (string) $d['canal'] === $canal,
        ));

        usort(
            $denuncias,
            static fn (array $a, array $b): int => [$b['recebida_em_hora'], $b['id']] <=> [$a['recebida_em_hora'], $a['id']],
        );

        return $denuncias;
    }

    /**
     * Todas as denúncias, já com o que a tela precisa e não fica guardado: o
     * protocolo interno e a ÁREA SUGERIDA pelo bairro.
     *
     * A sugestão é calculada na leitura, e não gravada: ela vem da estrutura de
     * áreas, que a tela de Áreas e Equipes deixa a pessoa mexer. Guardada junto
     * da denúncia, ela continuaria apontando para a área de antes do ajuste —
     * a mesma informação com dois donos.
     *
     * @return list<array<string, mixed>>
     */
    public static function todas(): array
    {
        return array_values(array_map(static function (array $d): array {
            $sugestao = EstruturaFicticia::sugerirPorBairro((string) ($d['bairro'] ?? ''));

            return [
                ...$d,
                'protocolo' => sprintf('DEN-%04d', (int) $d['id']),
                'area_sugerida' => $sugestao,
            ];
        }, self::atuais()));
    }

    /** Uma denúncia pelo identificador, ou null. */
    public static function denuncia(int $id): ?array
    {
        foreach (self::todas() as $denuncia) {
            if ((int) $denuncia['id'] === $id) {
                return $denuncia;
            }
        }

        return null;
    }

    /**
     * TRIAGEM — encaminha cada denúncia à área escolhida.
     *
     * `$areasPorId` casa identificador com nome de área: o lote não manda todas
     * para o mesmo lugar. É o que a triagem de verdade faz — chegam dez
     * denúncias de bairros diferentes, e cada uma tem a sua área.
     *
     * @param  array<int, string>  $areasPorId
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public static function encaminharAArea(array $areasPorId, ?string $observacao = null): array
    {
        $resumo = [];
        $alteradas = 0;
        $ignoradas = 0;

        foreach ($areasPorId as $id => $area) {
            $mudou = self::alterar((int) $id, static function (array $d) use ($area, $observacao): array {
                $detalhe = "Encaminhada à {$area} para direcionamento do gestor.";

                if (is_string($observacao) && trim($observacao) !== '') {
                    $detalhe .= ' '.trim($observacao);
                }

                return [
                    ...$d,
                    'situacao' => 'Encaminhada à área',
                    'area' => $area,
                    // Encaminhar CANCELA um retorno anterior: a denúncia voltou ao
                    // fluxo, e deixar o motivo pendurado faria a tela mostrar
                    // "encaminhada" com a justificativa de quem a arquivou.
                    'motivo' => null,
                    'justificativa' => null,
                    'destino' => null,
                    'tramites' => [...$d['tramites'], self::tramite('Triada e encaminhada à área', $detalhe)],
                ];
            });

            if ($mudou === null) {
                $ignoradas++;

                continue;
            }

            $alteradas++;
            $resumo[$area] = ($resumo[$area] ?? 0) + 1;
        }

        return ['alteradas' => $alteradas, 'ignoradas' => $ignoradas, 'resumo' => $resumo];
    }

    /**
     * TRIAGEM — devolve ao canal de origem ou arquiva, com motivo e justificativa.
     *
     * @param  list<int>  $ids
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public static function devolver(array $ids, string $motivo, string $justificativa, string $destino): array
    {
        $arquivar = str_contains(mb_strtolower($destino), 'arquiv');
        $situacao = $arquivar ? 'Arquivada' : 'Devolvida';

        $alteradas = 0;
        $ignoradas = 0;

        foreach ($ids as $id) {
            $mudou = self::alterar((int) $id, static fn (array $d): array => [
                ...$d,
                'situacao' => $situacao,
                // A denúncia recusada não chega ao gestor: sai da área e da equipe.
                'area' => null,
                'equipe' => null,
                'operacao' => null,
                'motivo' => $motivo,
                'justificativa' => $justificativa,
                'destino' => $destino,
                'tramites' => [
                    ...$d['tramites'],
                    self::tramite(
                        $arquivar ? 'Arquivada na triagem' : 'Devolvida ao canal de origem',
                        $motivo.' — '.$justificativa,
                    ),
                ],
            ]);

            $mudou === null ? $ignoradas++ : $alteradas++;
        }

        return [
            'alteradas' => $alteradas,
            'ignoradas' => $ignoradas,
            'resumo' => [$situacao => $alteradas],
        ];
    }

    /**
     * DIRECIONAMENTO — o gestor manda a denúncia para uma equipe.
     *
     * A justificativa só é exigida quando a equipe NÃO é a da área da denúncia
     * (regra do controller): tirar trabalho da equipe responsável é decisão que
     * precisa estar escrita para quem ler depois.
     *
     * @param  list<int>  $ids
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public static function direcionarAEquipe(array $ids, string $equipe, ?string $justificativa = null): array
    {
        $alteradas = 0;
        $ignoradas = 0;

        foreach ($ids as $id) {
            $mudou = self::alterar((int) $id, static function (array $d) use ($equipe, $justificativa): array {
                $detalhe = "Direcionada à Equipe {$equipe} para vistoria.";

                if (is_string($justificativa) && trim($justificativa) !== '') {
                    $detalhe .= ' '.trim($justificativa);
                }

                return [
                    ...$d,
                    'situacao' => 'Direcionada à equipe',
                    'equipe' => $equipe,
                    // Direcionar avulso desfaz o vínculo com operação: as duas
                    // saídas do gestor são alternativas, não camadas.
                    'operacao' => null,
                    'justificativa_equipe' => $justificativa,
                    'tramites' => [...$d['tramites'], self::tramite('Direcionada à equipe', $detalhe)],
                ];
            });

            $mudou === null ? $ignoradas++ : $alteradas++;
        }

        return [
            'alteradas' => $alteradas,
            'ignoradas' => $ignoradas,
            'resumo' => ["Equipe {$equipe}" => $alteradas],
        ];
    }

    /**
     * DIRECIONAMENTO — o gestor anexa a denúncia a uma operação já planejada.
     *
     * A equipe passa a ser a da operação: é ela que vai a campo, e deixar a
     * denúncia apontando para outra faria a tela mostrar dois responsáveis.
     *
     * @param  list<int>  $ids
     * @return array{alteradas: int, ignoradas: int, resumo: array<string, int>}
     */
    public static function anexarAOperacao(array $ids, string $operacao): array
    {
        $equipe = null;

        foreach (self::operacoes() as $registro) {
            if ((string) $registro['nome'] === $operacao) {
                $equipe = (string) $registro['equipe'];

                break;
            }
        }

        $alteradas = 0;
        $ignoradas = 0;

        foreach ($ids as $id) {
            $mudou = self::alterar((int) $id, static fn (array $d): array => [
                ...$d,
                'situacao' => 'Em operação',
                'operacao' => $operacao,
                'equipe' => $equipe ?? $d['equipe'] ?? null,
                'justificativa_equipe' => null,
                'tramites' => [
                    ...$d['tramites'],
                    self::tramite(
                        'Incluída em operação',
                        "Anexada à {$operacao}".($equipe === null ? '.' : ", executada pela Equipe {$equipe}."),
                    ),
                ],
            ]);

            $mudou === null ? $ignoradas++ : $alteradas++;
        }

        return [
            'alteradas' => $alteradas,
            'ignoradas' => $ignoradas,
            'resumo' => [$operacao => $alteradas],
        ];
    }

    /**
     * As operações a que o gestor pode anexar denúncia — as do arquivo de dados
     * mais as que a sessão criou.
     *
     * @return list<array<string, mixed>>
     */
    public static function operacoes(): array
    {
        /** @var list<array<string, mixed>>|null $daSessao */
        $daSessao = Session::get(self::CHAVE_OPERACOES);

        if (is_array($daSessao)) {
            return $daSessao;
        }

        return array_values((array) config('prototipo_denuncias.operacoes', []));
    }

    /** @return list<string> */
    public static function nomesDeOperacao(): array
    {
        return array_map(static fn (array $o): string => (string) $o['nome'], self::operacoes());
    }

    /**
     * Cria uma operação a partir do direcionamento — o caso em que não há
     * trabalho planejado ainda para aquela região e o gestor abre um.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed> A operação como ela ficou
     */
    public static function criarOperacao(array $dados): array
    {
        $operacoes = self::operacoes();

        $operacao = [
            'id' => max([0, ...array_map(static fn (array $o): int => (int) $o['id'], $operacoes)]) + 1,
            'nome' => (string) $dados['nome'],
            'area' => (string) ($dados['area'] ?? ''),
            'equipe' => (string) ($dados['equipe'] ?? ''),
            'periodo' => (string) ($dados['periodo'] ?? 'a definir'),
            'foco' => (string) ($dados['foco'] ?? ''),
        ];

        $operacoes[] = $operacao;
        Session::put(self::CHAVE_OPERACOES, array_values($operacoes));

        return $operacao;
    }

    /** Volta as denúncias e as operações ao estado de partida. */
    public static function reiniciar(): void
    {
        Session::forget(self::CHAVE);
        Session::forget(self::CHAVE_OPERACOES);
    }

    public static function alterada(): bool
    {
        return Session::has(self::CHAVE) || Session::has(self::CHAVE_OPERACOES);
    }

    /**
     * O estado vigente — o da sessão, se a pessoa decidiu algo; senão as
     * denúncias de partida com as datas resolvidas.
     *
     * @return list<array<string, mixed>>
     */
    private static function atuais(): array
    {
        /** @var list<array<string, mixed>>|null $daSessao */
        $daSessao = Session::get(self::CHAVE);

        if (is_array($daSessao)) {
            return $daSessao;
        }

        return self::departida();
    }

    /**
     * As denúncias do arquivo de dados, com `recebida_ha_horas`/`prazo_em_dias`
     * virando data e com o trâmite montado a partir da situação.
     *
     * @return list<array<string, mixed>>
     */
    private static function departida(): array
    {
        return array_values(array_map(static function (array $bruta): array {
            $horas = (int) ($bruta['recebida_ha_horas'] ?? 0);
            $recebidaEm = now()->subHours($horas);
            $canal = (string) $bruta['canal'];
            $nomeDoCanal = (string) config("prototipo_denuncias.canais.{$canal}.nome", $canal);

            $denuncia = [
                // Os campos que TODA denúncia tem, com o valor neutro declarado:
                // o front lê `d.operacao` de qualquer linha, e uma chave ausente
                // em metade delas viraria leitura defensiva espalhada por lá.
                'area' => null,
                'equipe' => null,
                'operacao' => null,
                'motivo' => null,
                'justificativa' => null,
                'justificativa_equipe' => null,
                'destino' => null,
                'atendente' => null,
                'categoria' => null,
                'anexos' => [],
                ...$bruta,
                'recebida_em' => $recebidaEm->format('Y-m-d'),
                'recebida_em_hora' => $recebidaEm->format('Y-m-d H:i'),
                'prazo' => now()->addDays((int) ($bruta['prazo_em_dias'] ?? 0))->format('Y-m-d'),
                'tramites' => self::tramitesDePartida($bruta, $recebidaEm->format('Y-m-d H:i'), $nomeDoCanal),
            ];

            unset($denuncia['recebida_ha_horas'], $denuncia['prazo_em_dias']);

            return $denuncia;
        }, (array) config('prototipo_denuncias.denuncias', [])));
    }

    /**
     * O histórico que a denúncia já traria, montado a partir da situação em que
     * ela aparece no arquivo de dados.
     *
     * A primeira linha é sempre o RECEBIMENTO POR INTEGRAÇÃO, e ela é assinada
     * pelo canal, não por pessoa: é o que deixa visível que ninguém digitou
     * aquilo aqui dentro.
     *
     * @param  array<string, mixed>  $bruta
     * @return list<array<string, string>>
     */
    private static function tramitesDePartida(array $bruta, string $recebidaEm, string $nomeDoCanal): array
    {
        $situacao = (string) ($bruta['situacao'] ?? 'Recebida');
        $area = (string) ($bruta['area'] ?? '');
        $equipe = (string) ($bruta['equipe'] ?? '');

        $tramites = [[
            'em' => $recebidaEm,
            'quem' => "Integração · {$nomeDoCanal}",
            'o_que' => 'Recebida por integração',
            'detalhe' => "Entregue automaticamente pelo {$nomeDoCanal} sob o número "
                .((string) $bruta['protocolo_origem']).'. Nenhum dado foi digitado no SEFAL.',
        ]];

        $passoDepois = static fn (int $horas): string => Date::parse($recebidaEm)->addHours($horas)->format('Y-m-d H:i');

        if (in_array($situacao, ['Devolvida', 'Arquivada'], true)) {
            $tramites[] = [
                'em' => $passoDepois(6),
                'quem' => 'Setor Administrativo',
                'o_que' => $situacao === 'Arquivada' ? 'Arquivada na triagem' : 'Devolvida ao canal de origem',
                'detalhe' => ((string) ($bruta['motivo'] ?? '')).' — '.((string) ($bruta['justificativa'] ?? '')),
            ];

            return $tramites;
        }

        if ($area === '') {
            return $tramites;
        }

        $tramites[] = [
            'em' => $passoDepois(5),
            'quem' => 'Setor Administrativo',
            'o_que' => 'Triada e encaminhada à área',
            'detalhe' => "Encaminhada à {$area} para direcionamento do gestor.",
        ];

        if ($situacao === 'Encaminhada à área') {
            return $tramites;
        }

        if ($situacao === 'Em operação') {
            $tramites[] = [
                'em' => $passoDepois(9),
                'quem' => 'Gestor da '.$area,
                'o_que' => 'Incluída em operação',
                'detalhe' => 'Anexada à '.((string) ($bruta['operacao'] ?? ''))
                    .($equipe === '' ? '.' : ", executada pela Equipe {$equipe}."),
            ];
        } else {
            $tramites[] = [
                'em' => $passoDepois(9),
                'quem' => 'Gestor da '.$area,
                'o_que' => 'Direcionada à equipe',
                'detalhe' => "Direcionada à Equipe {$equipe} para vistoria."
                    .(($bruta['justificativa_equipe'] ?? null) !== null
                        ? ' '.((string) $bruta['justificativa_equipe'])
                        : ''),
            ];
        }

        if ($situacao === 'Em campo') {
            $tramites[] = [
                'em' => $passoDepois(24),
                'quem' => "Equipe {$equipe}",
                'o_que' => 'Em campo',
                'detalhe' => 'A equipe recebeu a denúncia no aplicativo e está em rota para o local.',
            ];
        }

        if ($situacao === 'Concluída') {
            $tramites[] = [
                'em' => $passoDepois(24),
                'quem' => "Equipe {$equipe}",
                'o_que' => 'Em campo',
                'detalhe' => 'A equipe recebeu a denúncia no aplicativo e foi ao local.',
            ];
            $tramites[] = [
                'em' => $passoDepois(30),
                'quem' => "Equipe {$equipe}",
                'o_que' => 'Concluída',
                'detalhe' => 'Vistoria registrada em campo, com desfecho lançado pelo aplicativo do fiscal.',
            ];
        }

        return $tramites;
    }

    /**
     * Aplica uma mudança a UMA denúncia e grava. Devolve a denúncia alterada, ou
     * null se o identificador não existe — o caso de quem clicou com a listagem
     * antiga aberta na frente.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $mudar
     * @return array<string, mixed>|null
     */
    private static function alterar(int $id, callable $mudar): ?array
    {
        $denuncias = self::atuais();
        $alterada = null;

        foreach ($denuncias as $i => $denuncia) {
            if ((int) $denuncia['id'] !== $id) {
                continue;
            }

            $alterada = $mudar($denuncia);
            $denuncias[$i] = $alterada;

            break;
        }

        if ($alterada === null) {
            return null;
        }

        Session::put(self::CHAVE, array_values($denuncias));

        return $alterada;
    }

    /**
     * Uma linha de trâmite feita AGORA, assinada por quem está usando o sistema.
     *
     * @return array<string, string>
     */
    private static function tramite(string $oQue, string $detalhe): array
    {
        return [
            'em' => now()->format('Y-m-d H:i'),
            // Nullsafe: a tela é autenticada, mas um trâmite montado fora da
            // requisição (comando, teste) não tem quem assinar — e um erro de
            // acesso a propriedade de nulo aqui derrubaria a gravação inteira.
            'quem' => (string) (Auth::user()?->name ?? 'Setor Administrativo'),
            'o_que' => $oQue,
            'detalhe' => $detalhe,
        ];
    }
}
