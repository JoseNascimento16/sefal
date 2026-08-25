<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Grava o conteúdo de um XLSX exportado num arquivo temporário — o
 * PhpSpreadsheet lê de caminho, não de string.
 */
function arquivoTemporarioXlsx(string $conteudo): string
{
    $caminho = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
    file_put_contents($caminho, $conteudo);

    return $caminho;
}

/**
 * O texto de uma aba, achatado — o que uma pessoa leria ao abrir o arquivo.
 *
 * Mora aqui porque dois arquivos de teste inspecionam planilha exportada, e duas
 * cópias do mesmo leitor divergiriam no primeiro ajuste.
 */
function textoDaAba(?Worksheet $aba): string
{
    if ($aba === null) {
        return '';
    }

    $texto = '';

    foreach ($aba->toArray(null, false, false, false) as $linha) {
        $texto .= implode(' | ', array_map(fn ($v) => (string) $v, $linha))."\n";
    }

    return $texto;
}
