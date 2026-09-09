<?php

namespace App\Support\Prototipo;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;

/**
 * PROTÓTIPO — o CATÁLOGO ÚNICO de operações de fiscalização.
 *
 * ⚠️ Nada aqui toca o banco. A lista de partida é `config/prototipo_operacoes.php`
 * e o que a pessoa cria ou altera fica na SESSÃO dela.
 *
 * ── Um catálogo, dois interessados ──────────────────────────────────────────
 *
 * Duas telas consomem esta lista, e por motivos diferentes:
 *
 *   · o **Cadastro de Operação** — que cria, altera e encerra;
 *   · o **direcionamento das Denúncias** — em que o Chefe de Setor anexa uma
 *     denúncia a uma operação já planejada, em vez de mandar uma ida isolada.
 *
 * Elas leem a MESMA fonte, e isso não é elegância: é a lei do projeto. Com duas
 * listas, o direcionamento ofereceria amanhã uma operação que o cadastro não
 * conhece — e recusaria a que ele acabou de criar. `DenunciasFicticias::operacoes()`
 * delega para cá; não tem lista própria.
 *
 * ── A sessão guarda a LISTA INTEIRA, e não as diferenças ────────────────────
 *
 * A lista é curta (sete operações) e comparar diferença exigiria uma segunda
 * regra de mesclagem — que é justamente o tipo de coisa que um protótipo não deve
 * inventar para depois jogar fora. É a mesma escolha de `EstruturaFicticia`.
 *
 * ── Encerrada não recebe trabalho novo ─────────────────────────────────────
 *
 * {@see disponiveis} é o recorte que o direcionamento oferece: operação
 * ENCERRADA fica fora. Ela continua existindo no cadastro (o histórico é a régua
 * da operação do ano que vem) e não aceita denúncia nova — a recusa, com o
 * motivo, mora no controller que grava.
 */
class OperacoesFicticias
{
    private const CHAVE = 'prototipo.operacoes';

    public const PLANEJADA = 'Planejada';

    public const EM_ANDAMENTO = 'Em andamento';

    /** A que não recebe mais trabalho novo. */
    public const ENCERRADA = 'Encerrada';

    /**
     * As situações, na ordem em que a operação anda — o catálogo que a tela
     * oferece e que a validação aceita.
     *
     * @return list<string>
     */
    public static function situacoes(): array
    {
        return array_values((array) config('prototipo_operacoes.situacoes', []));
    }

    /**
     * Todas as operações, já com os campos DERIVADOS que as telas leem.
     *
     * @return list<array<string, mixed>>
     */
    public static function todas(): array
    {
        return array_values(array_map(
            static fn (array $bruta): array => self::completar($bruta),
            self::cruas(),
        ));
    }

    /**
     * As operações que ainda RECEBEM trabalho novo — o que o direcionamento das
     * denúncias oferece.
     *
     * @return list<array<string, mixed>>
     */
    public static function disponiveis(): array
    {
        return array_values(array_filter(
            self::todas(),
            static fn (array $o): bool => (string) $o['situacao'] !== self::ENCERRADA,
        ));
    }

    /**
     * Os nomes das operações que aceitam denúncia — a lista que a validação do
     * direcionamento aceita.
     *
     * @return list<string>
     */
    public static function nomesDisponiveis(): array
    {
        return array_map(static fn (array $o): string => (string) $o['nome'], self::disponiveis());
    }

    /** Uma operação pelo nome, ou null — inclusive a encerrada. */
    public static function porNome(?string $nome): ?array
    {
        if ($nome === null || trim($nome) === '') {
            return null;
        }

        foreach (self::todas() as $operacao) {
            if ((string) $operacao['nome'] === $nome) {
                return $operacao;
            }
        }

        return null;
    }

    public static function porId(int $id): ?array
    {
        foreach (self::todas() as $operacao) {
            if ((int) $operacao['id'] === $id) {
                return $operacao;
            }
        }

        return null;
    }

    /**
     * O nome já está em uso por OUTRA operação?
     *
     * O nome é como a equipe reconhece a operação em rua e é o que o
     * direcionamento das denúncias grava na linha — duas com o mesmo nome fariam
     * a anexação apontar para qualquer uma das duas, e ninguém saberia qual.
     */
    public static function nomeEmUso(string $nome, ?int $exceto = null): bool
    {
        foreach (self::todas() as $operacao) {
            if ($exceto !== null && (int) $operacao['id'] === $exceto) {
                continue;
            }

            if (mb_strtolower(trim((string) $operacao['nome'])) === mb_strtolower(trim($nome))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grava uma operação — nova (sem `id`) ou existente.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed> A operação como ela ficou
     */
    public static function salvar(array $dados): array
    {
        $operacoes = self::cruas();
        $id = isset($dados['id']) ? (int) $dados['id'] : 0;

        if ($id > 0) {
            foreach ($operacoes as $i => $operacao) {
                if ((int) $operacao['id'] === $id) {
                    // Mescla em cima da existente: o formulário manda o que edita,
                    // e o que ele não mostra continua sendo o que estava lá.
                    $operacoes[$i] = [...$operacao, ...$dados];
                    self::guardar($operacoes);

                    return self::completar($operacoes[$i]);
                }
            }
        }

        $nova = [
            'id' => max([0, ...array_map(static fn (array $o): int => (int) $o['id'], $operacoes)]) + 1,
            'equipes' => [],
            'bairros' => [],
            'regiao' => '',
            'foco' => '',
            'observacao' => '',
            ...$dados,
        ];

        unset($nova['id_novo']);

        $operacoes[] = $nova;
        self::guardar($operacoes);

        return self::completar($nova);
    }

    public static function excluir(int $id): void
    {
        self::guardar(array_values(array_filter(
            self::cruas(),
            static fn (array $o): bool => (int) $o['id'] !== $id,
        )));
    }

    /**
     * Cria a operação a partir do DIRECIONAMENTO de denúncia — o caso em que não
     * há trabalho planejado ainda para aquela região e o Chefe de Setor abre um
     * dali mesmo.
     *
     * Ela nasce EM ANDAMENTO e começando hoje, e isto é escolha consciente: quem
     * abre operação no meio de um direcionamento está mandando a equipe agora, e
     * nascer "Planejada" a deixaria fora do trabalho que motivou a criação.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public static function criarDoDirecionamento(array $dados): array
    {
        $equipe = trim((string) ($dados['equipe'] ?? ''));

        return self::salvar([
            'nome' => (string) $dados['nome'],
            'area' => (string) ($dados['area'] ?? ''),
            'equipes' => $equipe === '' ? [] : [$equipe],
            'bairros' => [],
            'regiao' => '',
            'inicio' => Date::now()->format('Y-m-d'),
            'fim' => null,
            'situacao' => self::EM_ANDAMENTO,
            'foco' => trim((string) ($dados['foco'] ?? '')),
            // O direcionamento tem um campo de período em TEXTO LIVRE ("até o fim
            // de março"), que não cabe em data. Ele é preservado como anotação em
            // vez de ser jogado fora: foi o que a chefia escreveu, e no Cadastro de
            // Operação alguém vai transformá-lo em data com o calendário à mão.
            'observacao' => trim((string) ($dados['periodo'] ?? '')) === ''
                ? 'Aberta durante o direcionamento de denúncia.'
                : 'Aberta durante o direcionamento de denúncia. Período informado pela chefia: '
                    .trim((string) $dados['periodo']).'.',
        ]);
    }

    /** Devolve o catálogo ao estado de partida — existe porque é protótipo. */
    public static function reiniciar(): void
    {
        Session::forget(self::CHAVE);
    }

    public static function alterada(): bool
    {
        return Session::has(self::CHAVE);
    }

    /**
     * Acrescenta a uma operação os campos que ela não guarda e as telas leem.
     *
     * Tudo o que é CONTA fica aqui, e não no navegador: `periodo` é data, e data
     * calculada no cliente depende do relógio e do fuso da máquina de quem abre a
     * tela. A ETIQUETA sai em português do Brasil porque é lei do projeto — o
     * ISO existe só no valor do campo de formulário.
     *
     * @param  array<string, mixed>  $bruta
     * @return array<string, mixed>
     */
    private static function completar(array $bruta): array
    {
        $inicio = self::dataDe($bruta, 'inicio', 'inicio_ha_dias');
        $fim = self::dataDe($bruta, 'fim', 'fim_em_dias');

        return [
            // Declarados ANTES do espalhamento: operação criada pela tela (ou pelo
            // direcionamento) pode não trazer a chave, e a tela lê `o.regiao` de
            // qualquer cartão. Chave ausente em metade dos registros vira leitura
            // defensiva espalhada pelo front.
            'regiao' => '',
            'foco' => '',
            'observacao' => '',
            'equipes' => [],
            'bairros' => [],
            ...$bruta,
            'inicio' => $inicio,
            'fim' => $fim,
            // A etiqueta que as telas mostram — inclusive o direcionamento das
            // denúncias, que a exibe ao lado do nome da operação.
            'periodo' => self::etiquetaDoPeriodo($inicio, $fim),
            'total_bairros' => count((array) ($bruta['bairros'] ?? [])),
            'total_equipes' => count((array) ($bruta['equipes'] ?? [])),
            'encerrada' => (string) ($bruta['situacao'] ?? '') === self::ENCERRADA,
        ];
    }

    /**
     * A data de um marco: a gravada, se houver; senão a derivada dos dias
     * relativos do arquivo de dados.
     *
     * @param  array<string, mixed>  $bruta
     */
    private static function dataDe(array $bruta, string $campo, string $relativo): ?string
    {
        if (array_key_exists($campo, $bruta)) {
            $valor = trim((string) ($bruta[$campo] ?? ''));

            return $valor === '' ? null : $valor;
        }

        $dias = $bruta[$relativo] ?? null;

        if ($dias === null) {
            return null;
        }

        // Positivo é FUTURO no arquivo de dados quando o campo é `fim_em_dias`, e
        // PASSADO quando é `inicio_ha_dias` — os dois nomes dizem o sentido, e o
        // sinal do valor permite o contrário (início no futuro = operação
        // planejada). Daí a conta ser a mesma nos dois, invertida pelo nome.
        $deslocamento = str_contains($relativo, 'ha_dias') ? -((int) $dias) : (int) $dias;

        return Date::now()->addDays($deslocamento)->format('Y-m-d');
    }

    /** "01/03/2026 a 31/03/2026", "a partir de 01/03/2026" ou "a definir". */
    private static function etiquetaDoPeriodo(?string $inicio, ?string $fim): string
    {
        $br = static fn (?string $iso): ?string => $iso === null
            ? null
            : Date::parse($iso)->format('d/m/Y');

        if ($inicio === null && $fim === null) {
            return 'a definir';
        }

        if ($fim === null) {
            return 'a partir de '.$br($inicio);
        }

        if ($inicio === null) {
            return 'até '.$br($fim);
        }

        return $br($inicio).' a '.$br($fim);
    }

    /**
     * O catálogo vigente — o da sessão, se a pessoa mexeu; senão o do arquivo.
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

        return array_values((array) config('prototipo_operacoes.operacoes', []));
    }

    /** @param  list<array<string, mixed>>  $operacoes */
    private static function guardar(array $operacoes): void
    {
        Session::put(self::CHAVE, array_values($operacoes));
    }
}
