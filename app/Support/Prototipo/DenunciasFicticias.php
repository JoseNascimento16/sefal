<?php

namespace App\Support\Prototipo;

use App\Support\Texto;
use Carbon\CarbonInterface;
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
 *   1. **Triagem** (coordenador) — lê a denúncia `Recebida`, decide se ela
 *      procede e a **encaminha à ÁREA** correspondente, derivada do bairro. A
 *      derivação SUGERE; quem confirma é gente, porque um bairro pode pertencer
 *      a duas áreas e aí as duas respostas estão certas. As saídas da triagem são
 *      `Devolvida` e `Arquivada`, sempre com motivo e justificativa: denúncia
 *      improcedente ou duplicada não deve chegar ao Chefe de Setor.
 *   2. **Direcionamento** (Chefe de Setor da área) — pega o que foi encaminhado à área
 *      dele e escolhe COMO o trabalho acontece: direciona à **equipe** (a da
 *      área, ou outra, e aí a justificativa é obrigatória) ou anexa a uma
 *      **operação** já planejada.
 *
 * As duas etapas operam em LOTE e uma a uma. O lote é o caso normal do
 * coordenador (a integração entrega várias de uma vez), e é por isso que os
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

    /** As situações em que a denúncia ainda espera a TRIAGEM do coordenador. */
    public const AGUARDANDO_TRIAGEM = ['Recebida'];

    /** As situações em que ela espera o DIRECIONAMENTO do Chefe de Setor da área. */
    public const AGUARDANDO_DIRECIONAMENTO = ['Encaminhada à área'];

    /**
     * Os papéis que um passo de trâmite pode declarar em `quem`.
     *
     * O passo declara o PAPEL, e o nome da pessoa é resolvido contra
     * `config/prototipo_estrutura.php` na hora de servir. É a lista que o teste
     * do catálogo confere: papel escrito errado no arquivo de dados apareceria
     * na tela como um passo sem autor, e ninguém olha um trâmite de dez linhas
     * procurando o que falta.
     */
    public const PAPEIS_DO_TRAMITE = [
        'integracao',
        'coordenador',
        'chefe-de-setor',
        'encarregado',
        'equipe',
        'fiscal',
        'fiscal2',
        'fiscal3',
    ];

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
                $detalhe = "Encaminhada à {$area} para direcionamento do Chefe de Setor.";

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
                    'tramites' => [...$d['tramites'], self::tramite('Triada e encaminhada à área', $detalhe, 'Encaminhada à área')],
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
                // A denúncia recusada não chega ao Chefe de Setor: sai da área e da equipe.
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
                        $situacao,
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
     * DIRECIONAMENTO — o Chefe de Setor manda a denúncia para uma equipe.
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
                    // saídas do Chefe de Setor são alternativas, não camadas.
                    'operacao' => null,
                    'justificativa_equipe' => $justificativa,
                    'tramites' => [...$d['tramites'], self::tramite('Direcionada à equipe', $detalhe, 'Direcionada à equipe')],
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
     * DIRECIONAMENTO — o Chefe de Setor anexa a denúncia a uma operação já planejada.
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
                        'Em operação',
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
     * As operações a que o Chefe de Setor pode anexar denúncia — as do arquivo de dados
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
     * trabalho planejado ainda para aquela região e o Chefe de Setor abre um.
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

            $tramites = self::tramitesDePartida($bruta, $recebidaEm->format('Y-m-d H:i'), $nomeDoCanal);

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
                'tramites' => $tramites,
                // COMO a vistoria terminou. Sai do trâmite — do último passo que
                // declarou desfecho —, e não de um campo escrito ao lado da
                // situação: a mesma informação com dois donos divergiria, e o
                // resumo continuaria dizendo "notificado" depois de o trâmite
                // registrar "regularizado no local".
                'desfecho' => self::desfechoDoTramite($tramites),
            ];

            unset($denuncia['recebida_ha_horas'], $denuncia['prazo_em_dias']);

            return $denuncia;
        }, (array) config('prototipo_denuncias.denuncias', [])));
    }

    /**
     * O histórico que a denúncia já traria ao abrir a tela.
     *
     * Duas formas, e cada denúncia usa UMA (ver o cabeçalho de
     * `config/prototipo_denuncias.php`):
     *
     *  • **declarada** — a denúncia que já foi a campo traz `tramites` escritos
     *    passo a passo, porque cada passo tem conteúdo próprio (o que o fiscal
     *    encontrou, as fotos, o documento lavrado). Isso não se deriva de uma
     *    palavra de situação;
     *  • **derivada** — a que ainda não foi a campo declara só a `situacao`, e o
     *    histórico dela é montado aqui. Cada passo produziu uma DECISÃO e nada
     *    mais, e derivar evita repetir a mesma sequência em vinte registros.
     *
     * A primeira linha é sempre o RECEBIMENTO POR INTEGRAÇÃO, e ela é assinada
     * pelo canal, não por pessoa: é o que deixa visível que ninguém digitou
     * aquilo aqui dentro.
     *
     * @param  array<string, mixed>  $bruta
     * @return list<array<string, mixed>>
     */
    private static function tramitesDePartida(array $bruta, string $recebidaEm, string $nomeDoCanal): array
    {
        $declarados = $bruta['tramites'] ?? null;

        if (is_array($declarados) && $declarados !== []) {
            return self::tramitesDeclarados($declarados, $bruta, $recebidaEm, $nomeDoCanal);
        }

        $situacao = (string) ($bruta['situacao'] ?? 'Recebida');
        $area = (string) ($bruta['area'] ?? '');
        $equipe = (string) ($bruta['equipe'] ?? '');

        $tramites = [self::passo([
            'em' => $recebidaEm,
            'quem' => "Integração · {$nomeDoCanal}",
            'o_que' => 'Recebida por integração',
            'detalhe' => self::detalheDaIntegracao($bruta, $nomeDoCanal),
            'situacao' => 'Recebida',
        ])];

        $passoDepois = static fn (int $horas): string => Date::parse($recebidaEm)->addHours($horas)->format('Y-m-d H:i');

        if (in_array($situacao, ['Devolvida', 'Arquivada'], true)) {
            $tramites[] = self::passo([
                'em' => $passoDepois(6),
                'quem' => 'Coordenação',
                'o_que' => $situacao === 'Arquivada' ? 'Arquivada na triagem' : 'Devolvida ao canal de origem',
                'detalhe' => ((string) ($bruta['motivo'] ?? '')).' — '.((string) ($bruta['justificativa'] ?? '')),
                'situacao' => $situacao,
            ]);

            return $tramites;
        }

        if ($area === '') {
            return $tramites;
        }

        $tramites[] = self::passo([
            'em' => $passoDepois(5),
            'quem' => 'Coordenação',
            'o_que' => 'Triada e encaminhada à área',
            'detalhe' => "Encaminhada à {$area} para direcionamento do Chefe de Setor.",
            'situacao' => 'Encaminhada à área',
        ]);

        if ($situacao === 'Encaminhada à área') {
            return $tramites;
        }

        $chefe = self::pessoaDoPasso('chefe-de-setor', $bruta, $nomeDoCanal)['texto'];

        if ($situacao === 'Em operação') {
            $tramites[] = self::passo([
                'em' => $passoDepois(9),
                'quem' => $chefe,
                'o_que' => 'Incluída em operação',
                'detalhe' => 'Anexada à '.((string) ($bruta['operacao'] ?? ''))
                    .($equipe === '' ? '.' : ", executada pela Equipe {$equipe}."),
                'situacao' => 'Em operação',
            ]);
        } else {
            $tramites[] = self::passo([
                'em' => $passoDepois(9),
                'quem' => $chefe,
                'o_que' => 'Direcionada à equipe',
                'detalhe' => "Direcionada à Equipe {$equipe} para vistoria."
                    .(($bruta['justificativa_equipe'] ?? null) !== null
                        ? ' '.((string) $bruta['justificativa_equipe'])
                        : ''),
                'situacao' => 'Direcionada à equipe',
            ]);
        }

        if ($situacao === 'Em campo') {
            $tramites[] = self::passo([
                'em' => $passoDepois(24),
                'quem' => "Equipe {$equipe}",
                'o_que' => 'Em campo',
                'detalhe' => 'A equipe recebeu a denúncia no aplicativo e está em rota para o local.',
                'situacao' => 'Em campo',
            ]);
        }

        return $tramites;
    }

    /**
     * Os passos DECLARADOS de uma denúncia que já andou até a vistoria.
     *
     * Duas coisas são resolvidas aqui, e não escritas no arquivo de dados:
     *
     *  • a **hora** de cada passo, somada ao recebimento (`ha_horas`). Data fixa
     *    envelhece — uma semana depois da demonstração o trâmite inteiro estaria
     *    no passado remoto, e o prazo de uma notificação apareceria vencido
     *    quando o caso é justamente o de prazo correndo;
     *  • **quem** agiu: o passo declara o PAPEL e o nome sai da estrutura de
     *    áreas e equipes. Nome escrito aqui daria dois donos ao mesmo cadastro, e
     *    um fiscal removido da equipe continuaria assinando vistoria.
     *
     * @param  list<array<string, mixed>>  $passos
     * @param  array<string, mixed>  $bruta
     * @return list<array<string, mixed>>
     */
    private static function tramitesDeclarados(array $passos, array $bruta, string $recebidaEm, string $nomeDoCanal): array
    {
        $resolvidos = [];

        foreach ($passos as $passo) {
            $papel = (string) ($passo['quem'] ?? 'coordenador');
            $integracao = $papel === 'integracao';
            $em = Date::parse($recebidaEm)->addHours((int) ($passo['ha_horas'] ?? 0));
            $pessoa = self::pessoaDoPasso($papel, $bruta, $nomeDoCanal);

            $resolvidos[] = self::passo([
                'em' => $em->format('Y-m-d H:i'),
                'quem' => $pessoa['texto'],
                // O passo de integração não repete o texto em cada denúncia: ele
                // é o MESMO para todas, e escrito 30 vezes divergiria na primeira
                // correção de redação.
                'o_que' => (string) ($passo['o_que'] ?? ($integracao ? 'Recebida por integração' : '')),
                'detalhe' => (string) ($passo['detalhe'] ?? ($integracao ? self::detalheDaIntegracao($bruta, $nomeDoCanal) : '')),
                'situacao' => (string) ($passo['situacao'] ?? ($integracao ? 'Recebida' : '')),
                'desfecho' => isset($passo['desfecho']) ? (string) $passo['desfecho'] : null,
                // O que o fiscal escreveu e o que ele recomendou ao fechar a
                // vistoria. Os dois nomes são o CONTRATO com o aplicativo dele
                // (`consideracoes` texto livre, `recomendacoes` lista de
                // atalhos): a mesma informação com dois nomes é o começo da
                // divergência.
                'consideracoes' => isset($passo['consideracoes']) && trim((string) $passo['consideracoes']) !== ''
                    ? trim((string) $passo['consideracoes'])
                    : null,
                'recomendacoes' => array_values(array_map('strval', (array) ($passo['recomendacoes'] ?? []))),
                'campos' => array_values((array) ($passo['campos'] ?? [])),
                'campo' => isset($passo['campo']) ? self::campoResolvido((array) $passo['campo']) : null,
                'documento' => isset($passo['documento'])
                    ? self::documentoResolvido((array) $passo['documento'], $bruta, $em, $pessoa['assinante'])
                    : null,
            ]);
        }

        return $resolvidos;
    }

    /**
     * O passo do trâmite com TODAS as chaves declaradas, mesmo as vazias.
     *
     * A tela lê `t.documento` e `t.campo` de qualquer passo, e chave ausente em
     * metade deles viraria leitura defensiva espalhada pelo front — o mesmo
     * motivo dos valores neutros da própria denúncia.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private static function passo(array $dados): array
    {
        return [
            'situacao' => '',
            'desfecho' => null,
            'consideracoes' => null,
            'recomendacoes' => [],
            'campos' => [],
            'campo' => null,
            'documento' => null,
            ...$dados,
        ];
    }

    /** O texto do passo de recebimento — um só, para todas as denúncias. */
    private static function detalheDaIntegracao(array $bruta, string $nomeDoCanal): string
    {
        return "Entregue automaticamente pelo {$nomeDoCanal} sob o número "
            .((string) $bruta['protocolo_origem']).'. Nenhum dado foi digitado no SEFAL.';
    }

    /**
     * QUEM agiu num passo, resolvido a partir do papel.
     *
     * `texto` é o que a tela mostra na linha do trâmite; `assinante` é o que vai
     * na linha "Agente fiscal" do documento — lá o impresso pede nome E
     * matrícula, e é a matrícula que identifica o agente numa defesa.
     *
     * ⚠️ As palavras de papel são NEUTRAS de propósito ("gestão da Área 5",
     * "chefia da Equipe C1"). A estrutura tem homens e mulheres nos mesmos
     * cargos — Andréa Rocha é encarregada da B2, Lourdes Figueiredo Sales
     * responde pela Área 5 —, e "Chefe da Área 5" fixo no molde erra o gênero de
     * metade da estrutura. Concordância é do dado, não do texto.
     *
     * @param  array<string, mixed>  $bruta
     * @return array{texto: string, assinante: string}
     */
    private static function pessoaDoPasso(string $papel, array $bruta, string $nomeDoCanal): array
    {
        $area = (string) ($bruta['area'] ?? '');
        $codigo = (string) ($bruta['equipe'] ?? '');
        $equipe = EstruturaFicticia::equipeDoCodigo($codigo);

        if ($papel === 'integracao') {
            return ['texto' => "Integração · {$nomeDoCanal}", 'assinante' => $nomeDoCanal];
        }

        if ($papel === 'chefe-de-setor') {
            $nome = trim((string) (EstruturaFicticia::chefiasPorArea()[$area]['nome'] ?? ''));

            return $nome === ''
                // Área sem Chefe de Setor registrado: o passo continua tendo autor
                // institucional. Devolver texto vazio deixaria a linha do trâmite
                // sem assinatura, e é justamente o que o módulo existe para não
                // fazer.
                ? ['texto' => 'Chefia da '.($area === '' ? 'área' : $area), 'assinante' => 'Chefia da área']
                : ['texto' => "{$nome} · chefia da ".($area === '' ? 'área' : $area), 'assinante' => $nome];
        }

        if ($papel === 'encarregado') {
            $nome = trim((string) ($equipe['encarregado'] ?? ''));

            return $nome === ''
                ? ['texto' => "Equipe {$codigo}", 'assinante' => "Equipe {$codigo}"]
                : ['texto' => "{$nome} · chefia da Equipe {$codigo}", 'assinante' => $nome];
        }

        if (str_starts_with($papel, 'fiscal')) {
            // `fiscal` é o primeiro da equipe, `fiscal2` o segundo, e assim por
            // diante: é o que permite ao retorno de fiscalização ser feito por
            // outra pessoa da mesma equipe, que é o que acontece em rua.
            $indice = max(0, (int) (mb_substr($papel, 6) ?: '1') - 1);
            $fiscais = array_values((array) ($equipe['fiscais'] ?? []));
            $fiscal = (array) ($fiscais[$indice] ?? $fiscais[0] ?? []);
            $nome = trim((string) ($fiscal['nome'] ?? ''));
            $matricula = trim((string) ($fiscal['matricula'] ?? ''));

            if ($nome === '') {
                return ['texto' => "Equipe {$codigo}", 'assinante' => "Equipe {$codigo}"];
            }

            return [
                'texto' => "{$nome} · Equipe {$codigo}",
                'assinante' => $matricula === '' ? $nome : "{$nome} — matrícula {$matricula}",
            ];
        }

        if ($papel === 'equipe') {
            return ['texto' => "Equipe {$codigo}", 'assinante' => "Equipe {$codigo}"];
        }

        return ['texto' => 'Coordenação', 'assinante' => 'Coordenação'];
    }

    /**
     * O que o fiscal registrou em campo, com todas as chaves declaradas.
     *
     * `gps` e `precisao_m` vêm juntos porque um ponto ruim é pior que um ponto
     * ausente disfarçado de bom — a lei do domínio que vale no aplicativo vale na
     * leitura aqui.
     *
     * @param  array<string, mixed>  $campo
     * @return array<string, mixed>
     */
    private static function campoResolvido(array $campo): array
    {
        $campo['fotos'] = array_values((array) ($campo['fotos'] ?? []));

        return [
            'encontrado' => null,
            'relato' => '',
            'gps' => null,
            'precisao_m' => null,
            'ambulante' => null,
            'equipamento' => null,
            ...$campo,
        ];
    }

    /**
     * O documento lavrado em campo, montado na ORDEM DO PAPEL.
     *
     * A montagem mora aqui, e não no arquivo de dados, pelo mesmo motivo que ela
     * mora numa função só no aplicativo do fiscal: a ordem dos campos e a
     * redação das caixas são do FORMULÁRIO LEGAL. O dado semeado declara o que
     * varia (quem, onde, quais motivos, qual prazo) e a redação sai de
     * `config/prototipo_documentos_campo.php`, que é o único dono dela.
     *
     * ⚠️ Nenhuma DATA entra em `campos`. A hora da lavratura e o vencimento saem
     * em chave própria (`emitido_em`, `vence_em`) porque quem escreve data em
     * dd/mm/aaaa é a TELA — data ISO no meio de um texto livre chegaria à tela
     * sem ninguém poder distingui-la de um nome de rua.
     *
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>  $bruta
     * @return array<string, mixed>
     */
    private static function documentoResolvido(array $doc, array $bruta, CarbonInterface $em, string $agente): array
    {
        $tipo = (string) ($doc['tipo'] ?? 'np');
        $ou = static fn (mixed $valor): string => trim((string) ($valor ?? '')) === '' ? '—' : trim((string) $valor);

        $comum = [
            'tipo' => $tipo,
            'numero' => (string) ($doc['numero'] ?? ''),
            'titulo' => (string) config("prototipo_documentos_campo.titulos.{$tipo}", ''),
            'emitido_em' => $em->format('Y-m-d H:i'),
            'agente' => $agente,
            'assinaturas' => array_values(array_map(static fn (array $a): array => [
                'nome' => null,
                ...$a,
            ], (array) ($doc['assinaturas'] ?? []))),
        ];

        if ($tipo === 'aa') {
            $fundamentacao = (array) config('prototipo_documentos_campo.fundamentacao', []);
            $segub = (array) config('prototipo_documentos_campo.segub', []);
            $prazo = (array) config('prototipo_documentos_campo.prazos_guarda.'.((string) ($doc['prazo_guarda'] ?? '')), []);
            $itens = array_values((array) ($doc['itens'] ?? []));
            $decretos = array_values((array) ($doc['decretos'] ?? []));
            $volumes = array_sum(array_map(static fn (array $i): int => (int) ($i['quantidade'] ?? 0), $itens));

            return [
                ...$comum,
                // O Auto de Apreensão não tem prazo de regularização: o prazo dele
                // é o de GUARDA dos bens, que não é conta a fazer sobre a
                // denúncia. Por isso ele não devolve vencimento.
                'vence_em' => null,
                'prazo_rotulo' => null,
                'campos' => [
                    ['rotulo' => 'Referência', 'valor' => self::referenciaDoDocumento($bruta)],
                    ['rotulo' => 'Sr.(a)', 'valor' => $ou($doc['notificado'] ?? null)],
                    ['rotulo' => 'CPF nº', 'valor' => $ou($doc['cpf'] ?? null)],
                    ['rotulo' => 'Equipamento tipo', 'valor' => $ou($doc['equipamento'] ?? null)],
                    ['rotulo' => 'Como atividade', 'valor' => $ou($doc['atividade'] ?? null)],
                    ['rotulo' => 'Localizado na', 'valor' => $ou($doc['local'] ?? null)],
                    ['rotulo' => 'Fundamento', 'valor' => $ou($fundamentacao['lei'] ?? null)],
                    // O impresso põe a desinência de plural entre parênteses
                    // nestes dois rótulos — a folha é impressa em branco e não
                    // sabe quantos decretos ou artigos serão citados. Aqui
                    // sabemos: a quantidade está na mão, e empurrar a
                    // concordância para quem lê é a forma que o projeto proíbe
                    // (ver `PluralDaInterfaceTest`, que varre até o comentário —
                    // por isso a forma errada não aparece escrita aqui).
                    [
                        'rotulo' => Texto::plural(count($decretos), 'Decreto', 'Decretos'),
                        'valor' => $ou(implode('; ', $decretos)),
                    ],
                    ['rotulo' => 'Artigos', 'valor' => $ou($doc['artigos'] ?? ($fundamentacao['artigos_padrao'] ?? null))],
                    ['rotulo' => 'Portaria nº', 'valor' => $ou($doc['portaria'] ?? ($fundamentacao['portaria_padrao'] ?? null))],
                    ['rotulo' => 'Guarda', 'valor' => $ou(($segub['nome'] ?? '').' — '.($segub['endereco'] ?? ''))],
                    ['rotulo' => 'Prazo máximo de guarda', 'valor' => $ou(
                        isset($prazo['rotulo']) ? $prazo['rotulo'].' ('.($prazo['extenso'] ?? '').')' : null,
                    )],
                    ['rotulo' => 'Após o prazo, os bens serão', 'valor' => $ou(
                        config('prototipo_documentos_campo.destinacoes_guarda.'.((string) ($doc['destinacao'] ?? ''))),
                    )],
                ],
                'listas' => [[
                    'titulo' => 'Discriminação do material apreendido',
                    'itens' => array_map(
                        static fn (array $i): string => ((int) ($i['quantidade'] ?? 0)).' '
                            .((string) ($i['unidade'] ?? 'un')).' — '.((string) ($i['descricao'] ?? '')),
                        $itens,
                    ),
                ]],
                'rodape' => Texto::contar($volumes, 'volume', 'volumes').' '
                    .Texto::plural($volumes, 'recolhido e encaminhado', 'recolhidos e encaminhados')
                    .' ao '.((string) ($segub['nome'] ?? 'SEGUB')).' — '.((string) ($segub['endereco'] ?? '')),
            ];
        }

        $prazo = (array) config('prototipo_documentos_campo.prazos_np.'.((string) ($doc['prazo'] ?? '')), []);
        $dias = (int) ($prazo['dias'] ?? 0);

        $motivos = [];

        foreach ((array) ($doc['motivos'] ?? []) as $chave) {
            $motivo = (array) config('prototipo_documentos_campo.motivos_np.'.((string) $chave), []);

            if ($motivo === []) {
                continue;
            }

            $complemento = trim((string) ($doc['complementos'][(string) $chave] ?? ''));
            $motivos[] = (string) $motivo['texto'].($complemento === '' ? '' : ": {$complemento}");
        }

        // A 20ª caixa do impresso é "Outros", campo livre — e ela vai no fim da
        // lista, como está no papel.
        if (trim((string) ($doc['outros'] ?? '')) !== '') {
            $motivos[] = 'Outros: '.trim((string) $doc['outros']);
        }

        return [
            ...$comum,
            'vence_em' => $dias > 0 ? $em->addDays($dias)->format('Y-m-d') : null,
            'prazo_rotulo' => isset($prazo['rotulo']) ? (string) $prazo['rotulo'] : null,
            'campos' => [
                ['rotulo' => 'Referência', 'valor' => self::referenciaDoDocumento($bruta)],
                ['rotulo' => 'Nome', 'valor' => $ou($doc['notificado'] ?? null)],
                ['rotulo' => 'Endereço', 'valor' => $ou($doc['endereco'] ?? null)],
                ['rotulo' => 'Inscrição / Processo nº', 'valor' => $ou($doc['inscricao'] ?? null)],
                ['rotulo' => 'Atividade', 'valor' => $ou($doc['atividade'] ?? null)],
                ['rotulo' => 'Local da atividade', 'valor' => $ou($doc['local'] ?? null)],
                ['rotulo' => 'Barraca / Box / Lote / Qda', 'valor' => $ou($doc['equipamento'] ?? null)],
            ],
            'listas' => [
                [
                    'titulo' => Texto::plural(count($motivos), 'Motivo assinalado', 'Motivos assinalados'),
                    'itens' => $motivos,
                ],
                [
                    'titulo' => 'Penalidades previstas',
                    'itens' => array_values(array_filter(array_map(
                        static fn (string $chave): ?string => config('prototipo_documentos_campo.sancoes_np.'.$chave),
                        array_map('strval', (array) ($doc['sancoes'] ?? [])),
                    ))),
                ],
            ],
            'rodape' => (string) config('prototipo_documentos_campo.rodape', ''),
        ];
    }

    /**
     * O campo REFERÊNCIA do impresso: o processo que gerou a ida a campo.
     *
     * No bloco de papel ele fica em branco quando a fiscalização é avulsa. Aqui
     * ele nunca está em branco — todo documento desta tela nasceu de uma
     * denúncia —, e é ele que amarra o papel na mão do notificado ao registro
     * que a Retaguarda está lendo.
     *
     * @param  array<string, mixed>  $bruta
     */
    private static function referenciaDoDocumento(array $bruta): string
    {
        $canal = (string) ($bruta['canal'] ?? '');
        $nome = (string) config("prototipo_denuncias.canais.{$canal}.nome", $canal);

        return sprintf('DEN-%04d', (int) ($bruta['id'] ?? 0))
            .' · denúncia do '.$nome.' '.((string) ($bruta['protocolo_origem'] ?? ''));
    }

    /**
     * O desfecho da denúncia: o do ÚLTIMO passo que declarou um.
     *
     * "Último" e não "primeiro" porque a vistoria pode ter mais de um desfecho ao
     * longo da vida do registro — notificado, depois regularizado —, e o que vale
     * é onde a coisa parou.
     *
     * @param  list<array<string, mixed>>  $tramites
     */
    private static function desfechoDoTramite(array $tramites): ?string
    {
        $desfecho = null;

        foreach ($tramites as $passo) {
            if (isset($passo['desfecho']) && trim((string) $passo['desfecho']) !== '') {
                $desfecho = (string) $passo['desfecho'];
            }
        }

        return $desfecho;
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
     * ⚠️ Passa por {@see passo()}, e isso não é estilo: a linha vai para a SESSÃO
     * e volta à tela sem passar mais por nenhuma normalização. Montada à mão, ela
     * chegava ao front sem `campos`, `campo` nem `documento` — e a leitura do
     * trâmite testa `t.documento !== null`, que é VERDADEIRO para chave ausente.
     * O passo recém-criado derrubava a tela ao ser aberto, logo depois de a pessoa
     * decidir algo: o pior momento possível, e sem erro nenhum no servidor para
     * investigar.
     *
     * @return array<string, mixed>
     */
    private static function tramite(string $oQue, string $detalhe, string $situacao): array
    {
        return self::passo([
            'em' => now()->format('Y-m-d H:i'),
            // Nullsafe: a tela é autenticada, mas um trâmite montado fora da
            // requisição (comando, teste) não tem quem assinar — e um erro de
            // acesso a propriedade de nulo aqui derrubaria a gravação inteira.
            'quem' => (string) (Auth::user()?->name ?? 'Coordenação'),
            'o_que' => $oQue,
            'detalhe' => $detalhe,
            // A situação em que a denúncia ENTROU com este passo. Ela é a mesma
            // que a decisão acabou de gravar no registro, e vem junto porque é
            // o selo que a leitura do trâmite mostra ao lado do passo — sem
            // ela, o passo recém-criado é o único do percurso sem selo.
            'situacao' => $situacao,
        ]);
    }
}
