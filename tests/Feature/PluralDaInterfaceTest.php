<?php

use App\Support\Texto;

/*
|--------------------------------------------------------------------------
| Plural dos textos — a lei contra o "N registro(s)"
|--------------------------------------------------------------------------
|
| A quantidade é SEMPRE conhecida no momento de montar a frase. Então a forma
| com parênteses é sempre evitável — e ela lê como rascunho: é o sistema
| empurrando para o leitor uma conta que ele mesmo acabou de fazer.
|
| Este é um teste-LEI de FONTE, e isso é exceção deliberada no projeto (a régua
| é testar comportamento, não código escrito). O motivo: o defeito é uma CLASSE
| espalhada por telas, controllers, relatórios e verificações — dezoito
| ocorrências na primeira varredura —, e não há como exercitar cada frase por
| requisição. Uma asserção por frase seria dezoito testes frágeis; a varredura é
| uma, e cobre também a frase que alguém escrever amanhã.
|
| A régua é a spec de design (linguagem de tela) — não há HU escrita neste
| projeto.
|
*/

/**
 * Os arquivos onde texto de tela pode morar.
 *
 * @return list<string>
 */
function arquivosComTextoDeTela(): array
{
    $raizes = [
        base_path('resources/js') => ['ts', 'tsx'],
        base_path('resources/views') => ['php'],
        base_path('app') => ['php'],
        base_path('config') => ['php'],
        base_path('lang') => ['php', 'json'],
    ];

    $arquivos = [];

    foreach ($raizes as $raiz => $extensoes) {
        if (! is_dir($raiz)) {
            continue;
        }

        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $arquivo) {
            /** @var SplFileInfo $arquivo */
            if ($arquivo->isFile() && in_array($arquivo->getExtension(), $extensoes, true)) {
                // O código gerado pelo Wayfinder não é texto de tela: ele é
                // reescrito a cada build, e nada nele chega aos olhos de ninguém.
                if (! str_contains(str_replace('\\', '/', $arquivo->getPathname()), '/resources/js/actions/')
                    && ! str_contains(str_replace('\\', '/', $arquivo->getPathname()), '/resources/js/routes/')) {
                    $arquivos[] = $arquivo->getPathname();
                }
            }
        }
    }

    return $arquivos;
}

test('lei: nenhuma frase da interface empurra o plural para o leitor com parenteses', function () {
    /*
     * O que se procura: um grupo entre parênteses TERMINADO EM S, curto, grudado
     * numa palavra — "registro(s)", "verificação(ões)", "ativa(s)".
     *
     * O que fica de fora, de propósito: grupo seguido de `?`, `*` ou `+`, que é
     * quantificador de expressão regular — `/\bregular(es)?\b/` é vocabulário de
     * busca, não frase de tela.
     */
    $suspeitos = [];

    foreach (arquivosComTextoDeTela() as $arquivo) {
        $conteudo = (string) file_get_contents($arquivo);

        if (preg_match_all('/\p{L}\((?:\p{L}{1,3}s|ões)\)(?![?*+])/u', $conteudo, $achados, PREG_OFFSET_CAPTURE) === 0) {
            continue;
        }

        foreach ($achados[0] as [$trecho, $posicao]) {
            $linha = substr_count(substr($conteudo, 0, (int) $posicao), "\n") + 1;
            $relativo = str_replace(base_path().DIRECTORY_SEPARATOR, '', $arquivo);
            $suspeitos[] = "{$relativo}:{$linha} → {$trecho}";
        }
    }

    expect($suspeitos)->toBe(
        [],
        "Use o helper de plural em vez de parênteses:\n"
        .'  PHP → App\Support\Texto::contar($n, \'registro\', \'registros\')'."\n"
        .'  TS  → contar(n, \'registro\', \'registros\') de @/lib/plural'."\n",
    );
});

test('o helper de plural concorda no zero, no um e no muitos', function () {
    // O zero vai no PLURAL em português ("0 registros"), e é o caso que uma
    // implementação apressada (`$n > 1`) erra.
    expect(Texto::contar(0, 'registro', 'registros'))->toBe('0 registros')
        ->and(Texto::contar(1, 'registro', 'registros'))->toBe('1 registro')
        ->and(Texto::contar(2, 'registro', 'registros'))->toBe('2 registros')
        ->and(Texto::plural(1, 'verificação', 'verificações'))->toBe('verificação');
});
