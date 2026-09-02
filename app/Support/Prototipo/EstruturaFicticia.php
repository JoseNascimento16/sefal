<?php

namespace App\Support\Prototipo;

use App\Support\Texto;
use Illuminate\Support\Facades\Session;

/**
 * PROTÓTIPO — a estrutura de fiscalização (Área > Equipe > bloco de bairros).
 *
 * ⚠️ Nada aqui toca o banco. A estrutura REAL vem de
 * `config/prototipo_estrutura.php` (transcrição do documento do cliente) e o que
 * a pessoa mexe na tela fica na SESSÃO dela, para a navegação parecer viva
 * enquanto o dono confere a forma. Ao virar produção, esta classe morre e o lugar
 * da regra passa a ser o model + a action.
 *
 * A sessão guarda a LISTA INTEIRA de áreas, e não um conjunto de diferenças: a
 * lista é pequena (oito áreas) e comparar diferença exigiria uma segunda regra
 * de mesclagem — que é justamente o tipo de coisa que um protótipo não deve
 * inventar para depois jogar fora.
 */
class EstruturaFicticia
{
    private const CHAVE = 'prototipo.estrutura.areas';

    /**
     * As áreas como a tela precisa delas: já com as contagens e com a marca de
     * bairro compartilhado.
     *
     * @return list<array<string, mixed>>
     */
    public static function areas(): array
    {
        $areas = self::cruas();
        $compartilhados = self::bairrosCompartilhados($areas);

        return array_values(array_map(
            static function (array $area) use ($compartilhados): array {
                $bairros = array_values((array) ($area['bairros'] ?? []));

                // A ORDEM é decidida aqui, e não confiada ao arquivo de dados: os
                // três bairros compartilhados chegam no fim do bloco da Área 6
                // (foram acrescentados depois), e o bloco apareceria fora de ordem
                // exatamente onde ele é mais longo. Chave sem acento, a mesma régua
                // da busca — por byte, "Águas Claras" cai depois de "Vila Canária".
                usort(
                    $bairros,
                    static fn (string $a, string $b): int => Texto::chave($a) <=> Texto::chave($b),
                );

                return [
                    // `gestor` declarado ANTES do espalhamento: área criada pela
                    // tela (na sessão) não tem a chave, e a tela lê
                    // `area.gestor.nome` de qualquer cartão. Chave ausente em
                    // metade dos registros vira leitura defensiva espalhada.
                    'gestor' => null,
                    ...$area,
                    'bairros' => $bairros,
                    'total_bairros' => count($bairros),
                    'total_fiscais' => count((array) ($area['fiscais'] ?? [])),
                    // Os bairros DESTA área que também pertencem a outra. É aviso
                    // informativo na tela, nunca pendência: o vínculo
                    // bairro↔equipe não é 1:1 (a sugestão é confirmada por gente).
                    'bairros_compartilhados' => array_values(array_filter(
                        $bairros,
                        static fn (string $b): bool => in_array(Texto::chave($b), $compartilhados, true),
                    )),
                ];
            },
            $areas,
        ));
    }

    /**
     * As equipes numa lista rasa — o que o formulário da Caixa de Entrada
     * oferece para escolher o destino do encaminhamento.
     *
     * @return list<array{equipe: string, area: string, regiao: string, encarregado: string, recorte: string, turno: string}>
     */
    public static function equipes(): array
    {
        return array_values(array_map(static fn (array $a): array => [
            'equipe' => (string) $a['equipe'],
            'area' => (string) $a['nome'],
            'regiao' => (string) $a['regiao'],
            'encarregado' => (string) $a['encarregado'],
            'recorte' => (string) $a['recorte'],
            'turno' => (string) $a['turno'],
        ], self::cruas()));
    }

    /**
     * A equipe de um código (`C1`, `A2`…) como ela está na estrutura — com o
     * encarregado e os fiscais, que a lista rasa de `equipes()` não carrega.
     *
     * Existe para quem precisa NOMEAR quem agiu: o trâmite avançado de uma
     * denúncia diz "vistoria feita pelo fiscal da equipe", e o nome tem de sair
     * daqui. Escrito no dado da denúncia, ele daria dois donos ao mesmo cadastro
     * — e um fiscal removido da equipe continuaria assinando vistoria.
     *
     * @return array<string, mixed>|null
     */
    public static function equipeDoCodigo(?string $codigo): ?array
    {
        if ($codigo === null || trim($codigo) === '') {
            return null;
        }

        $procurado = mb_strtoupper(trim($codigo));

        foreach (self::cruas() as $area) {
            if (mb_strtoupper((string) $area['equipe']) === $procurado) {
                return $area;
            }
        }

        return null;
    }

    /** Os códigos de equipe existentes — a lista que a validação aceita. */
    public static function codigosDeEquipe(): array
    {
        return array_column(self::cruas(), 'equipe');
    }

    /**
     * Os nomes das áreas, sem repetição e na ordem da estrutura — a lista que a
     * validação aceita e que as telas oferecem.
     *
     * @return list<string>
     */
    public static function nomesDeArea(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $a): string => (string) $a['nome'],
            self::cruas(),
        )));
    }

    /**
     * `área => gestor` — quem responde por cada área DENTRO do sistema.
     *
     * Não confundir com o `encarregado`, que chefia a equipe em campo. O gestor é
     * quem recebe a denúncia encaminhada e decide equipe ou operação, e é o nome
     * que o triador precisa ver antes de encaminhar: "encaminhei para a Área 5" só
     * diz metade — a outra metade é para QUEM.
     *
     * @return array<string, array{nome: string, matricula: string|null}>
     */
    public static function gestoresPorArea(): array
    {
        $mapa = [];

        foreach (self::cruas() as $area) {
            $gestor = (array) ($area['gestor'] ?? []);

            $mapa[(string) $area['nome']] = [
                'nome' => (string) ($gestor['nome'] ?? ''),
                // Área sem conta de demonstração tem nome de gestor e não tem
                // matrícula: é o caso normal aqui, não dado faltando.
                'matricula' => isset($gestor['matricula']) && $gestor['matricula'] !== null
                    ? (string) $gestor['matricula']
                    : null,
            ];
        }

        return $mapa;
    }

    /**
     * As áreas de que esta matrícula é gestora — vazio quando ela não é gestora de
     * nenhuma.
     *
     * Devolve LISTA, e não uma área só, porque na vida real uma pessoa responde por
     * mais de uma área (férias, acumulação, área recém-criada). Quem consome já
     * trata o plural, então a modelagem definitiva não obriga a mexer em quem lê.
     *
     * @return list<string>
     */
    public static function areasDoGestor(?string $matricula): array
    {
        if ($matricula === null || trim($matricula) === '') {
            return [];
        }

        $procurada = mb_strtolower(trim($matricula));
        $areas = [];

        foreach (self::gestoresPorArea() as $area => $gestor) {
            if ($gestor['matricula'] !== null && mb_strtolower($gestor['matricula']) === $procurada) {
                $areas[] = $area;
            }
        }

        return $areas;
    }

    /**
     * A equipe SUGERIDA para um bairro — e as alternativas, quando o bairro
     * pertence a mais de uma área.
     *
     * A sugestão nunca decide sozinha: quem confirma é o administrativo. Um
     * bairro compartilhado (Mussurunga, Patamares, Jardim das Margaridas) tem
     * duas respostas igualmente certas, e escolher uma em silêncio esconderia a
     * decisão de quem tem de tomá-la.
     *
     * @return array{equipe: string, area: string, regiao: string, encarregado: string, alternativas: list<array<string, string>>}|null
     */
    public static function sugerirPorBairro(?string $bairro): ?array
    {
        if ($bairro === null || trim($bairro) === '') {
            return null;
        }

        $chave = Texto::chave($bairro);
        $casadas = [];

        foreach (self::cruas() as $area) {
            foreach ((array) ($area['bairros'] ?? []) as $nome) {
                if (Texto::chave((string) $nome) === $chave) {
                    $casadas[] = [
                        'equipe' => (string) $area['equipe'],
                        'area' => (string) $area['nome'],
                        'regiao' => (string) $area['regiao'],
                        'encarregado' => (string) $area['encarregado'],
                    ];

                    break;
                }
            }
        }

        if ($casadas === []) {
            return null;
        }

        return [
            ...$casadas[0],
            // As demais áreas que também cobrem o bairro. Vazio no caso normal.
            'alternativas' => array_values(array_slice($casadas, 1)),
        ];
    }

    /**
     * Todo bairro conhecido, sem repetição e em ordem — a lista de escolha do
     * formulário da Caixa de Entrada (é o bairro que sugere a equipe).
     *
     * @return list<string>
     */
    public static function bairros(): array
    {
        $porChave = [];

        foreach (self::cruas() as $area) {
            foreach ((array) ($area['bairros'] ?? []) as $nome) {
                $porChave[Texto::chave((string) $nome)] = (string) $nome;
            }
        }

        $bairros = array_values($porChave);

        // A ordem é pela CHAVE sem acento, e não pelo texto cru: ordenado por
        // byte, "Águas Claras" cai depois de "Vitória" e "São Caetano" depois de
        // "Sussuarana" — a lista fica impossível de varrer com o olho justamente
        // onde ela é mais longa.
        usort(
            $bairros,
            static fn (string $a, string $b): int => Texto::chave($a) <=> Texto::chave($b),
        );

        return $bairros;
    }

    /**
     * Grava uma área — nova (sem `id`) ou existente.
     *
     * @param  array<string, mixed>  $dados
     */
    public static function salvarArea(array $dados): void
    {
        $areas = self::cruas();
        $id = isset($dados['id']) ? (int) $dados['id'] : 0;

        if ($id > 0) {
            foreach ($areas as $i => $area) {
                if ((int) $area['id'] === $id) {
                    // Mescla em cima da área existente: o formulário manda o que
                    // ele edita, e os blocos que ele não mostra (fiscais, bairros)
                    // continuam sendo os que estavam lá.
                    $areas[$i] = [...$area, ...$dados];

                    break;
                }
            }
        } else {
            $areas[] = [
                'id' => max([0, ...array_map(static fn (array $a): int => (int) $a['id'], $areas)]) + 1,
                'fiscais' => [],
                'bairros' => [],
                ...$dados,
            ];
        }

        self::guardar($areas);
    }

    public static function excluirArea(int $id): void
    {
        self::guardar(array_values(array_filter(
            self::cruas(),
            static fn (array $a): bool => (int) $a['id'] !== $id,
        )));
    }

    /** Acrescenta um bairro ao bloco de uma área, se ele ainda não estiver lá. */
    public static function adicionarBairro(int $id, string $bairro): void
    {
        $areas = self::cruas();

        foreach ($areas as $i => $area) {
            if ((int) $area['id'] !== $id) {
                continue;
            }

            $existentes = array_map(
                static fn (string $b): string => Texto::chave($b),
                (array) ($area['bairros'] ?? []),
            );

            if (! in_array(Texto::chave($bairro), $existentes, true)) {
                $bairros = [...(array) ($area['bairros'] ?? []), trim($bairro)];

                // Mesma régua da lista geral: ordem pela chave sem acento.
                usort(
                    $bairros,
                    static fn (string $a, string $b): int => Texto::chave($a) <=> Texto::chave($b),
                );

                $areas[$i]['bairros'] = array_values($bairros);
            }

            break;
        }

        self::guardar($areas);
    }

    public static function removerBairro(int $id, string $bairro): void
    {
        $areas = self::cruas();

        foreach ($areas as $i => $area) {
            if ((int) $area['id'] !== $id) {
                continue;
            }

            $areas[$i]['bairros'] = array_values(array_filter(
                (array) ($area['bairros'] ?? []),
                static fn (string $b): bool => Texto::chave($b) !== Texto::chave($bairro),
            ));

            break;
        }

        self::guardar($areas);
    }

    /** Volta a estrutura ao documento do cliente, desfazendo o que a sessão mudou. */
    public static function reiniciar(): void
    {
        Session::forget(self::CHAVE);
    }

    public static function alterada(): bool
    {
        return Session::has(self::CHAVE);
    }

    /**
     * A estrutura vigente — a da sessão, se a pessoa mexeu; senão a do documento.
     *
     * @return list<array<string, mixed>>
     */
    private static function cruas(): array
    {
        /** @var list<array<string, mixed>>|null $daSessao */
        $daSessao = Session::get(self::CHAVE);

        if (is_array($daSessao)) {
            return $daSessao;
        }

        return array_values((array) config('prototipo_estrutura.areas', []));
    }

    /** @param  list<array<string, mixed>>  $areas */
    private static function guardar(array $areas): void
    {
        Session::put(self::CHAVE, array_values($areas));
    }

    /**
     * As chaves dos bairros que aparecem em mais de uma área.
     *
     * @param  list<array<string, mixed>>  $areas
     * @return list<string>
     */
    private static function bairrosCompartilhados(array $areas): array
    {
        $contagem = [];

        foreach ($areas as $area) {
            // Únicos DENTRO da área: o mesmo bairro repetido por engano no bloco
            // de uma área só não é bairro compartilhado — é digitação.
            $naArea = [];

            foreach ((array) ($area['bairros'] ?? []) as $nome) {
                $naArea[Texto::chave((string) $nome)] = true;
            }

            foreach (array_keys($naArea) as $chave) {
                $contagem[$chave] = ($contagem[$chave] ?? 0) + 1;
            }
        }

        return array_keys(array_filter($contagem, static fn (int $n): bool => $n > 1));
    }
}
