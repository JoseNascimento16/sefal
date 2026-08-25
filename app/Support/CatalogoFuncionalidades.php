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
        foreach ((array) config('retaguarda_menu.secoes', []) as $secao) {
            foreach ((array) ($secao['itens'] ?? []) as $item) {
                if (($item['slug'] ?? null) === $slug) {
                    return array_values(array_map('strval', (array) ($item['setores'] ?? [])));
                }
            }
        }

        return [];
    }
}
