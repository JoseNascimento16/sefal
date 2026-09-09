<?php

namespace App\Support;

use App\Support\Prototipo\PapelNaArea;
use InvalidArgumentException;

/**
 * As colunas de cada listagem da Retaguarda — o leitor do catálogo
 * `config/listagens_da_retaguarda.php`.
 *
 * A régua que ele aplica está em `docs/padroes/listagem-clean.md`: grade
 * enxuta, uma linha por registro, detalhe no clique, arquivo completo.
 *
 * ── Por que quem resolve é o SERVIDOR ───────────────────────────────────────
 *
 * A coluna condicional (`quando`) depende de QUEM entrou: a área só é coluna
 * para quem responde por mais de uma. Resolver isso na tela obrigaria cada tela
 * a repetir a regra "conta as áreas do usuário", que é justamente a coisa que
 * {@see PapelNaArea} existe para ter um dono só. Então o
 * controller passa o contexto, este resolvedor devolve a grade pronta, e a tela
 * apenas desenha o que recebeu.
 *
 * @phpstan-type Coluna array{chave: string, titulo: string, alinhar?: string, largura?: int}
 */
final class ListagensDaRetaguarda
{
    /**
     * A listagem pronta para a tela: grade resolvida pelo contexto + colunas do
     * arquivo.
     *
     * Aceita vários identificadores porque uma tela com abas tem uma listagem
     * por aba — a aba troca a FONTE dos dados, e com ela o recorte de colunas.
     *
     * @param  list<string>|string  $ids
     * @param  array<string, bool>  $contexto  ex.: `['varias-areas' => true]`
     * @return array<string, array{grade: list<array<string, mixed>>, exportacao: list<array<string, mixed>>}>
     */
    public static function para(array|string $ids, array $contexto = []): array
    {
        $pronto = [];

        foreach ((array) $ids as $id) {
            $pronto[$id] = [
                'grade' => self::grade($id, $contexto),
                'exportacao' => self::exportacao($id),
            ];
        }

        return $pronto;
    }

    /**
     * As colunas VISÍVEIS, na ordem, já sem as condicionais que o contexto não
     * pediu.
     *
     * @param  array<string, bool>  $contexto
     * @return list<array<string, mixed>>
     */
    public static function grade(string $id, array $contexto = []): array
    {
        $colunas = [];

        foreach (self::listagem($id)['grade'] as $coluna) {
            $quando = $coluna['quando'] ?? null;

            if ($quando !== null && ($contexto[$quando] ?? false) !== true) {
                continue;
            }

            // `quando` é instrução para este resolvedor, não dado de tela: se
            // viajasse, a tela teria como decidir de novo o que já foi decidido
            // aqui — e um dia decidiria diferente.
            unset($coluna['quando']);

            $colunas[] = $coluna;
        }

        return $colunas;
    }

    /**
     * As colunas do ARQUIVO — sempre o conjunto completo, independente do que a
     * grade mostra. É o ponto da régua: enxugar é da tela.
     *
     * @return list<array<string, mixed>>
     */
    public static function exportacao(string $id): array
    {
        return array_values(self::listagem($id)['exportacao']);
    }

    /**
     * O que DESCEU da grade para o detalhe do registro — a lista que o
     * teste-lei cobra na exportação.
     *
     * @return list<string>
     */
    public static function detalhe(string $id): array
    {
        return array_values(self::listagem($id)['detalhe'] ?? []);
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys((array) config('listagens_da_retaguarda.listagens', []));
    }

    /**
     * Os campos de texto livre do sistema — o que não pode virar coluna.
     *
     * @return list<string>
     */
    public static function textoLivre(): array
    {
        return array_values((array) config('listagens_da_retaguarda.texto_livre', []));
    }

    /** @return array<string, mixed> */
    public static function listagem(string $id): array
    {
        // O array inteiro, e o índice na mão. `config('...listagens.'.$id)` NÃO
        // serve: o identificador tem ponto ("denuncias.triagem"), e o ponto é o
        // separador de níveis do `config()` — ele iria procurar uma chave
        // "triagem" dentro de "denuncias" e não acharia nada.
        $todas = (array) config('listagens_da_retaguarda.listagens', []);
        $listagem = $todas[$id] ?? null;

        if (! is_array($listagem)) {
            // Explode em vez de devolver vazio: grade sem colunas renderizaria
            // uma tabela em branco, e o defeito apareceria como "a tela sumiu"
            // em vez de "o identificador está errado".
            throw new InvalidArgumentException(
                "Listagem \"{$id}\" não está declarada em config/listagens_da_retaguarda.php."
            );
        }

        return $listagem;
    }
}
