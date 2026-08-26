<?php

namespace App\Support;

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
     * As telas controláveis, na ordem do menu.
     *
     * @return list<array{slug: string, rotulo: string, secao: string}>
     */
    public static function itens(): array
    {
        $itens = [];

        foreach ((array) config('retaguarda_menu.secoes', []) as $secao) {
            foreach ((array) ($secao['itens'] ?? []) as $item) {
                $slug = $item['slug'] ?? null;

                if (! is_string($slug) || $slug === '') {
                    continue;
                }

                /*
                 * Várias telas sob o MESMO slug (é o caso da Parametrização, em
                 * que seis telas dividem o caminho `/retaguarda/parametrizacao/…`):
                 * a permissão cobre o CONJUNTO, então quem a concede tem de ler
                 * o nome do conjunto. Ver ali o nome de uma das seis faria
                 * parecer que as outras cinco ficaram de fora da concessão.
                 */
                if (isset($itens[$slug])) {
                    $itens[$slug]['rotulo'] = (string) ($secao['rotulo'] ?? $itens[$slug]['rotulo']);

                    continue;
                }

                $itens[$slug] = [
                    'slug' => $slug,
                    'rotulo' => (string) ($item['rotulo'] ?? $slug),
                    'secao' => (string) ($secao['rotulo'] ?? ''),
                ];
            }
        }

        return array_values($itens);
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
     * se quer dizer, e aí a semente precisa saber disso: o **fiscal** enxerga o
     * cadastro de permissionário (chegar na calçada sem saber quem está
     * cadastrado é trabalhar às cegas) e NÃO cria nem apaga por lá — ele cadastra
     * em rua, pelo aplicativo, e o que nasce em rua entra em quarentena para o
     * gestor conferir. Criar direto pela Retaguarda passaria ao largo dessa
     * conferência, e apagar cadastro é ato de gestão.
     *
     * A exceção mora na config do menu, junto do resto da declaração da tela, e
     * não aqui: quem lê "quem entra onde" tem de achar tudo no mesmo lugar.
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

        return [...$pacoteCompleto, ...(self::semente($slug)[$setor] ?? [])];
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
        foreach ((array) config('retaguarda_menu.secoes', []) as $secao) {
            foreach ((array) ($secao['itens'] ?? []) as $item) {
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
        }

        return [];
    }
}
