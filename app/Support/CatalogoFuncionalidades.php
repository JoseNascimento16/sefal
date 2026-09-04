<?php

namespace App\Support;

use App\Services\PermissaoService;

/**
 * O que o Modo Gerente pode controlar — derivado do MENU, em tempo de execução.
 *
 * Um catálogo guardado em tabela envelhece: a tela nasce, ninguém roda o seeder e
 * ela cai num limbo — não aparece na matriz para ser concedida e, dependendo do
 * default, fica aberta ou fechada para todos. Derivando do menu a cada consulta,
 * o catálogo nunca fica defasado, e a lista tem um dono só
 * (`config/retaguarda_menu.php`).
 *
 * Entra no catálogo o item de menu que declara `slug`. Item sem `slug` é
 * deliberadamente incontrolável — a tela inicial (barrá-la fecharia um loop de
 * redirecionamento) e a área da própria conta.
 */
class CatalogoFuncionalidades
{
    /**
     * Todo item de menu que aponta para uma TELA, na ordem do menu — descendo nas
     * pastas (`filhos`).
     *
     * É o caminhador ÚNICO do menu, e existe porque o menu deixou de ser plano: um
     * item pode ser PASTA, e a tela mora no filho. Quem percorresse só
     * `secao.itens` passaria ao largo das telas de dentro das pastas — o catálogo
     * de permissões não as veria (e elas ficariam fora do controle de acesso), e os
     * testes-lei que varrem o menu deixariam de cobri-las sem nada acusar.
     *
     * A pasta em si não entra: ela não tem tela, rota nem permissão.
     *
     * @return list<array{item: array<string, mixed>, secao: array<string, mixed>}>
     */
    public static function folhasDoMenu(): array
    {
        $folhas = [];

        foreach ((array) config('retaguarda_menu.secoes', []) as $secao) {
            foreach ((array) ($secao['itens'] ?? []) as $item) {
                $filhos = (array) ($item['filhos'] ?? []);

                if ($filhos === []) {
                    $folhas[] = ['item' => $item, 'secao' => $secao];

                    continue;
                }

                foreach ($filhos as $filho) {
                    $folhas[] = ['item' => $filho, 'secao' => $secao];
                }
            }
        }

        return $folhas;
    }

    /**
     * As telas controláveis, na ordem do menu.
     *
     * @return list<array{slug: string, rotulo: string, secao: string}>
     */
    public static function itens(): array
    {
        /*
         * Quantas telas declaram cada slug — apurado ANTES de montar.
         *
         * Várias telas sob o MESMO slug é o caso da Parametrização, em que seis telas dividem o
         * caminho `/retaguarda/parametrizacao/…`: a permissão cobre o CONJUNTO, então quem a
         * concede tem de ler o nome do conjunto. Ver ali o nome de uma das seis faria parecer que
         * as outras cinco ficaram de fora da concessão.
         *
         * A contagem primeiro, e não a correção do rótulo ao encontrar a segunda tela, porque
         * corrigir depois só funciona quando existe uma segunda: uma seção que passasse a ter UMA
         * tela sob slug compartilhado voltaria a exibir o nome dela na matriz, e a leitura errada
         * reapareceria sem nada acusar.
         */
        $compartilhados = array_count_values(self::slugsDeclarados());

        $itens = [];

        foreach (self::folhasDoMenu() as $folha) {
            ['item' => $item, 'secao' => $secao] = $folha;

            $slug = $item['slug'] ?? null;

            if (! is_string($slug) || $slug === '') {
                continue;
            }

            if (isset($itens[$slug])) {
                continue;
            }

            $itens[$slug] = [
                'slug' => $slug,
                // Slug compartilhado usa o nome da SEÇÃO; slug de uma tela só, o dela.
                'rotulo' => ($compartilhados[$slug] ?? 0) > 1
                    ? (string) ($secao['rotulo'] ?? $slug)
                    : (string) ($item['rotulo'] ?? $slug),
                'secao' => (string) ($secao['rotulo'] ?? ''),
            ];
        }

        return array_values($itens);
    }

    /**
     * Todo slug declarado no menu, COM repetição — a matéria-prima da contagem acima.
     *
     * @return list<string>
     */
    private static function slugsDeclarados(): array
    {
        $slugs = [];

        foreach (self::folhasDoMenu() as $folha) {
            $slug = $folha['item']['slug'] ?? null;

            if (is_string($slug) && $slug !== '') {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_map(static fn (array $i): string => $i['slug'], self::itens());
    }

    /** O nome de tela de um slug catalogado, ou null se ele não é controlável. */
    public static function rotulo(string $slug): ?string
    {
        foreach (self::itens() as $item) {
            if ($item['slug'] === $slug) {
                return $item['rotulo'];
            }
        }

        return null;
    }

    public static function contem(string $slug): bool
    {
        return self::rotulo($slug) !== null;
    }

    /**
     * Os setores que a configuração do menu declara para uma tela — a SEMENTE da
     * matriz, lida só pelo seeder. Em tempo de execução quem manda é a matriz.
     *
     * @return list<string>
     */
    public static function setoresSemente(string $slug): array
    {
        return array_keys(self::semente($slug));
    }

    /**
     * As ações com que um setor NASCE numa tela.
     *
     * O normal é o pacote inteiro — "este setor usa esta tela" quer dizer ver,
     * operar, incluir e excluir. Mas há caso em que o pacote inteiro não é o que
     * se quer dizer, e aí a semente precisa saber disso: o **fiscal** apenas
     * CONSULTA o cadastro de ambulante (chegar na calçada sem saber quem
     * está cadastrado é trabalhar às cegas) e não grava nada por lá — ele
     * cadastra em rua, pelo aplicativo, e o que nasce em rua entra em quarentena
     * para o Chefe de Setor conferir. Deixá-lo alterar de mesa permitiria que ele mesmo
     * tirasse da quarentena o cadastro que acabou de criar, e a conferência que
     * dá sentido à fila deixaria de acontecer.
     *
     * A exceção mora na config do menu, junto do resto da declaração da tela, e
     * não aqui: quem lê "quem entra onde" tem de achar tudo no mesmo lugar.
     *
     * ⚠️ O resultado passa pela MESMA normalização que a tela do Modo Gerente
     * aplica ao gravar ({@see PermissaoService::normalizar}), e isso não é
     * capricho: sem ela, declarar `['apenas_leitura' => true]` semearia uma linha
     * que se contradiz — "só consulta" marcado ao lado de "opera", "inclui" e
     * "exclui" ainda ligados. A resolução em tempo de execução lê as colunas
     * cruas, então a linha incoerente daria poder de gravar a quem a config diz
     * que só olha.
     *
     * @return array<string, bool>
     */
    public static function acoesSemente(string $slug, string $setor): array
    {
        $pacoteCompleto = [
            'visivel' => true,
            'habilitado' => true,
            'apenas_leitura' => false,
            'incluir' => true,
            'excluir' => true,
        ];

        return PermissaoService::normalizar([
            ...$pacoteCompleto,
            ...(self::semente($slug)[$setor] ?? []),
        ]);
    }

    /**
     * A declaração `setores` de uma tela, normalizada em `setor => ajustes`.
     *
     * A config aceita as duas formas — `'fiscal'` (pacote completo) e
     * `'fiscal' => ['excluir' => false]` (pacote com ajuste) —, e o resto do
     * código não precisa saber qual delas foi usada.
     *
     * @return array<string, array<string, bool>>
     */
    private static function semente(string $slug): array
    {
        foreach (self::folhasDoMenu() as $folha) {
            $item = $folha['item'];

            if (($item['slug'] ?? null) !== $slug) {
                continue;
            }

            $semente = [];

            foreach ((array) ($item['setores'] ?? []) as $chave => $valor) {
                // Chave numérica = a forma curta, em que o VALOR é o setor.
                if (is_int($chave)) {
                    $semente[(string) $valor] = [];

                    continue;
                }

                $semente[(string) $chave] = array_map(
                    static fn (mixed $ligada): bool => (bool) $ligada,
                    (array) $valor,
                );
            }

            return $semente;
        }

        return [];
    }
}
