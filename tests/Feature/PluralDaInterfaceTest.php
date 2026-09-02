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
 * O padrão da forma preguiçosa: **uma desinência de plural entre parênteses,
 * grudada numa palavra** — "registro(s)", "verificação(ões)", "disponível(is)".
 *
 * ── Por que uma lista de desinências, e não "qualquer coisa curta em -s" ──────
 *
 * A primeira versão pedia `\p{L}{1,3}s` dentro dos parênteses, e isso exigia uma
 * letra ANTES do "s": deixava passar justamente a forma mais comum, o "s"
 * sozinho — dezesseis das dezoito ocorrências originais. O teste ficava verde
 * vigiando quase nada.
 *
 * Abrir para `{0,3}` conserta isso e cria outro problema: `args` e `opts` também
 * são "coisas curtas terminadas em s", e `apply(args)` / `reload(opts)` são código
 * legítimo — a varredura passaria a reprovar o que está certo, e o caminho fácil
 * seria afrouxá-la de novo.
 *
 * Então a lista é FECHADA, com as desinências de plural do português. Vale a
 * pena: o que se procura é uma classe de defeito de texto, não uma heurística
 * sobre a forma das palavras.
 *
 * Fica de fora o grupo seguido de `?`, `*` ou `+` — é quantificador de expressão
 * regular, e `/\bregular(es)?\b/` é vocabulário de busca, não frase de tela.
 */
function padraoDePluralPreguicoso(): string
{
    return '/\p{L}\((?:s|es|as|os|is|ns|ões|ães|ãos|ais|eis)\)(?![?*+])/u';
}

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

test('o padrao reconhece a forma preguicosa e nao confunde codigo com texto', function (string $trecho, bool $eDefeito) {
    /*
     * O teste DO próprio detector, e ele existe por experiência recente: a
     * primeira versão do padrão não casava o "(s)" sozinho — a forma mais comum
     * de todas —, e a varredura passou verde vigiando duas ocorrências de exemplo
     * em comentário. Guarda cuja força ninguém confere é guarda que enfraquece
     * em silêncio, e essa é pior que guarda nenhuma: ela dá a sensação de
     * cobertura.
     *
     * Os casos NEGATIVOS pesam tanto quanto os positivos. Uma varredura que
     * reprova código legítimo é desligada na primeira vez que atrapalha.
     */
    expect((bool) preg_match(padraoDePluralPreguicoso(), $trecho))->toBe($eDefeito, $trecho);
})->with([
    // A forma nua — a que escapava.
    ['2 registro(s) exportado(s)', true],
    ['1 conta(s) de administrador ativa(s)', true],
    ['{totais.total} verificação(ões)', true],
    ['2 relatório(s) disponível(is)', true],
    ['{vinculados} ambulante(s) a têm', true],
    ['há 3 desligada(s)', true],
    ['amplitude de 12 dia(s)', true],
    ['8 funcionalidade(s) · 0 alinhada(s)', true],
    // Código, que tem de passar.
    ['/\bregular(es)?\b/', false],
    ['/\birregular(es)?\b/', false],
    ['apply(args)', false],
    ['router.reload(opts)', false],
    ['fn (CheckParametrizacao $c)', false],
    ['(array) $item[\'setores\']', false],
    ['useScrollLock(true)', false],
    ['preg_match(\'/(\d)\1{10}$/\', $cpf)', false],
    // Plural JÁ resolvido: a frase certa não pode acusar.
    ['2 registros exportados', false],
    ['1 conta de administrador ativa', false],
]);

test('lei: nenhuma frase da interface empurra o plural para o leitor com parenteses', function () {
    $suspeitos = [];

    foreach (arquivosComTextoDeTela() as $arquivo) {
        $conteudo = (string) file_get_contents($arquivo);

        if (preg_match_all(padraoDePluralPreguicoso(), $conteudo, $achados, PREG_OFFSET_CAPTURE) === 0) {
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
