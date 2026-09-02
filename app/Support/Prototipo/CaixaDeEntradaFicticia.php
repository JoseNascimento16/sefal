<?php

namespace App\Support\Prototipo;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;

/**
 * PROTÓTIPO — a Caixa de Entrada do Administrativo.
 *
 * ⚠️ Nada aqui toca o banco. As demandas de partida vêm de
 * `config/prototipo_caixa_entrada.php` e o que a pessoa registra, encaminha ou
 * devolve fica na SESSÃO dela — o bastante para o dono percorrer o fluxo inteiro
 * e aprovar a forma antes de existir tabela, migration e regra de verdade.
 *
 * ── O que este protótipo faz de propósito, e que a versão real vai precisar ──
 *
 *  • **trâmite**: toda decisão acrescenta uma linha ao histórico da demanda
 *    (quem, quando, o quê). É ato administrativo: sem o rastro, "devolvida"
 *    é uma palavra sem autor;
 *  • **justificativa obrigatória** ao devolver ou arquivar — a validação está no
 *    controller, não só no formulário;
 *  • **numeração própria** (`CXE-NNNN`). No sistema real isso passa a sair de
 *    `App\Support\Protocolo::proximo()`, que é a fonte única de numeração — aqui
 *    não sai, porque o protótipo não grava nada e o contador vive no banco.
 */
class CaixaDeEntradaFicticia
{
    private const CHAVE = 'prototipo.caixa-de-entrada';

    /**
     * A caixa vigente, em ordem de recebimento (a mais recente primeiro).
     *
     * @return list<array<string, mixed>>
     */
    public static function demandas(): array
    {
        $demandas = self::atuais();

        usort($demandas, static fn (array $a, array $b): int => [$b['recebida_em'], $b['id']] <=> [$a['recebida_em'], $a['id']]);

        return array_values($demandas);
    }

    /** Uma demanda pelo id, ou null. */
    public static function demanda(int $id): ?array
    {
        foreach (self::atuais() as $demanda) {
            if ((int) $demanda['id'] === $id) {
                return $demanda;
            }
        }

        return null;
    }

    /**
     * Registra uma demanda nova e já a coloca no destino escolhido.
     *
     * `$destino` é `encaminhar`, `devolver` ou `arquivar` — as três saídas que o
     * administrativo tem ao terminar de digitar o papel.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed> A demanda como ela ficou
     */
    public static function registrar(array $dados, string $destino): array
    {
        $demandas = self::atuais();

        $id = max([0, ...array_map(static fn (array $d): int => (int) $d['id'], $demandas)]) + 1;
        $hoje = self::hoje();

        $demanda = [
            'id' => $id,
            'protocolo' => sprintf('CXE-%04d', $id),
            'origem' => (string) $dados['origem'],
            'documento_origem' => (string) ($dados['documento_origem'] ?? ''),
            'recebida_em' => (string) ($dados['recebida_em'] ?? $hoje),
            'prazo' => (string) ($dados['prazo'] ?? self::somarDias($hoje, (int) config('prototipo_caixa_entrada.prazo_padrao_em_dias', 10))),
            'anonima' => (bool) ($dados['anonima'] ?? false),
            'requerente' => $dados['requerente'] ?? null,
            'contato' => $dados['contato'] ?? null,
            'assunto' => (string) $dados['assunto'],
            'endereco' => (string) ($dados['endereco'] ?? ''),
            'bairro' => (string) ($dados['bairro'] ?? ''),
            'descricao' => (string) ($dados['descricao'] ?? ''),
            'anexo' => $dados['anexo'] ?? null,
            'situacao' => 'Aguardando triagem',
            'equipe' => null,
            'motivo' => null,
            'justificativa' => null,
            'destino' => null,
            'tramites' => [
                self::tramite('Recebida', 'Documento de origem digitado no sistema.'),
            ],
        ];

        $demandas[] = $demanda;
        self::guardar($demandas);

        if ($destino === 'encaminhar') {
            return self::encaminhar($id, (string) $dados['equipe'], $dados['observacao'] ?? null) ?? $demanda;
        }

        return self::devolver(
            $id,
            (string) $dados['motivo'],
            (string) $dados['justificativa'],
            (string) $dados['destino_retorno'],
        ) ?? $demanda;
    }

    /**
     * Encaminha a demanda à equipe: é o que a faz aparecer como trabalho dirigido
     * para os fiscais daquela equipe.
     *
     * @return array<string, mixed>|null
     */
    public static function encaminhar(int $id, string $equipe, ?string $observacao = null): ?array
    {
        return self::alterar($id, static function (array $demanda) use ($equipe, $observacao): array {
            $detalhe = "Encaminhada à Equipe {$equipe}.";

            if (is_string($observacao) && trim($observacao) !== '') {
                $detalhe .= ' '.trim($observacao);
            }

            return [
                ...$demanda,
                'situacao' => 'Encaminhada',
                'equipe' => $equipe,
                // Encaminhar cancela um retorno anterior: a demanda voltou ao
                // fluxo, e deixar o motivo antigo pendurado faria a tela mostrar
                // "encaminhada" com a justificativa de quem a devolveu.
                'motivo' => null,
                'justificativa' => null,
                'destino' => null,
                'tramites' => [...$demanda['tramites'], self::tramite('Triada e encaminhada', $detalhe)],
            ];
        });
    }

    /**
     * Devolve ao remetente ou arquiva — sempre com o motivo e a justificativa.
     *
     * @return array<string, mixed>|null
     */
    public static function devolver(int $id, string $motivo, string $justificativa, string $destinoRetorno): ?array
    {
        $arquivar = str_contains(mb_strtolower($destinoRetorno), 'arquiv');

        return self::alterar($id, static fn (array $demanda): array => [
            ...$demanda,
            'situacao' => $arquivar ? 'Arquivada' : 'Devolvida',
            'equipe' => null,
            'motivo' => $motivo,
            'justificativa' => $justificativa,
            'destino' => $destinoRetorno,
            'tramites' => [
                ...$demanda['tramites'],
                self::tramite(
                    $arquivar ? 'Arquivada' : 'Devolvida ao remetente',
                    $motivo.' — '.$justificativa,
                ),
            ],
        ]);
    }

    /** Volta a caixa ao estado de partida, desfazendo o que a sessão mudou. */
    public static function reiniciar(): void
    {
        Session::forget(self::CHAVE);
    }

    public static function alterada(): bool
    {
        return Session::has(self::CHAVE);
    }

    /**
     * A caixa vigente — a da sessão, se a pessoa mexeu; senão as demandas de
     * partida, com as datas resolvidas.
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
     * As demandas do arquivo de dados, com `dias_atras`/`prazo_em_dias` virando
     * data e com o histórico de trâmite montado a partir da situação.
     *
     * @return list<array<string, mixed>>
     */
    private static function departida(): array
    {
        $hoje = self::hoje();

        return array_values(array_map(static function (array $bruta) use ($hoje): array {
            $recebida = self::somarDias($hoje, -(int) ($bruta['dias_atras'] ?? 0));
            $prazo = self::somarDias($hoje, (int) ($bruta['prazo_em_dias'] ?? 0));

            $tramites = [[
                'em' => $recebida,
                'quem' => 'Setor Administrativo',
                'o_que' => 'Recebida',
                'detalhe' => 'Documento de origem ('.$bruta['origem'].') digitado no sistema.',
            ]];

            if (($bruta['situacao'] ?? '') === 'Encaminhada') {
                $tramites[] = [
                    'em' => self::somarDias($recebida, 1),
                    'quem' => 'Setor Administrativo',
                    'o_que' => 'Triada e encaminhada',
                    'detalhe' => 'Encaminhada à Equipe '.$bruta['equipe'].'.',
                ];
            }

            if (in_array($bruta['situacao'] ?? '', ['Devolvida', 'Arquivada'], true)) {
                $tramites[] = [
                    'em' => self::somarDias($recebida, 1),
                    'quem' => 'Setor Administrativo',
                    'o_que' => $bruta['situacao'] === 'Arquivada' ? 'Arquivada' : 'Devolvida ao remetente',
                    'detalhe' => $bruta['motivo'].' — '.$bruta['justificativa'],
                ];
            }

            $demanda = [...$bruta, 'recebida_em' => $recebida, 'prazo' => $prazo, 'tramites' => $tramites];

            unset($demanda['dias_atras'], $demanda['prazo_em_dias']);

            return $demanda;
        }, (array) config('prototipo_caixa_entrada.demandas', [])));
    }

    /**
     * Aplica uma mudança a UMA demanda e grava a caixa. Devolve a demanda já
     * alterada, ou null se o id não existe.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $mudar
     * @return array<string, mixed>|null
     */
    private static function alterar(int $id, callable $mudar): ?array
    {
        $demandas = self::atuais();
        $alterada = null;

        foreach ($demandas as $i => $demanda) {
            if ((int) $demanda['id'] !== $id) {
                continue;
            }

            $alterada = $mudar($demanda);
            $demandas[$i] = $alterada;

            break;
        }

        if ($alterada === null) {
            return null;
        }

        self::guardar($demandas);

        return $alterada;
    }

    /** @param  list<array<string, mixed>>  $demandas */
    private static function guardar(array $demandas): void
    {
        Session::put(self::CHAVE, array_values($demandas));
    }

    /**
     * Uma linha de trâmite feita AGORA, assinada por quem está usando o sistema.
     *
     * @return array<string, string>
     */
    private static function tramite(string $oQue, string $detalhe): array
    {
        return [
            'em' => self::hoje(),
            // Nullsafe: a tela é autenticada, mas um trâmite montado fora da
            // requisição (comando, teste) não tem quem assinar — e um erro de
            // acesso a propriedade de nulo aqui derrubaria a gravação inteira.
            'quem' => (string) (Auth::user()?->name ?? 'Setor Administrativo'),
            'o_que' => $oQue,
            'detalhe' => $detalhe,
        ];
    }

    /** Hoje em ISO — formato interno; quem escreve dd/mm/aaaa é a tela. */
    private static function hoje(): string
    {
        return now()->format('Y-m-d');
    }

    /**
     * Data ISO deslocada em dias.
     *
     * As datas do projeto são imutáveis (`Date::use(CarbonImmutable)`), então o
     * resultado de `addDays` tem de ser usado — não há mutação no lugar.
     */
    private static function somarDias(string $iso, int $dias): string
    {
        return Date::parse($iso)->addDays($dias)->format('Y-m-d');
    }
}
