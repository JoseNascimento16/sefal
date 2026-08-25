<?php

use App\Rules\ArquivoSeguro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * O que pode ser anexado no sistema. Allowlist + checagem do CONTEÚDO + higiene do NOME.
 *
 * Três coisas que a Rule tem de barrar e que já custaram incidente no sistema irmão: executável
 * disfarçado de extensão dupla (`foto.jpg.exe`, que o Windows abre como programa), executável
 * renomeado (o conteúdo é `MZ`/ELF, o nome diz `.pdf`) e nome com assinatura de SQLi (`doc--final`),
 * que grava bem e depois some no download — o WAF da Prefeitura barra o `--` na URL e a falha chega
 * ao dev fantasiada de erro de CORS.
 *
 * Usa o container (facade Validator), por isso pede TestCase; não toca banco.
 */
uses(TestCase::class);

function validaArquivo(UploadedFile $arquivo, ?array $permitidas = ['pdf', 'jpg', 'jpeg', 'png']): Illuminate\Validation\Validator
{
    return Validator::make(['a' => $arquivo], ['a' => [new ArquivoSeguro($permitidas)]]);
}

test('barra dupla extensao perigosa e nome com assinatura de sqli', function () {
    expect(validaArquivo(UploadedFile::fake()->create('foto.jpg.exe', 10))->fails())->toBeTrue()
        ->and(validaArquivo(UploadedFile::fake()->create('doc--final.pdf', 10))->fails())->toBeTrue()
        ->and(validaArquivo(UploadedFile::fake()->image('foto.jpg'))->fails())->toBeFalse();
});

test('pdf legitimo passa', function () {
    expect(validaArquivo(UploadedFile::fake()->createWithContent('laudo.pdf', '%PDF-1.4 conteudo'))->fails())->toBeFalse();
});

test('a mensagem da extensao dupla aponta a extensao culpada', function () {
    $v = validaArquivo(UploadedFile::fake()->createWithContent('foto.jpg.exe', 'MZ'));

    expect($v->fails())->toBeTrue()
        ->and((string) $v->errors()->first('a'))->toContain('(.exe)');
});

test('executavel e recusado seja qual for o nome', function (string $nome) {
    $v = validaArquivo(UploadedFile::fake()->createWithContent($nome, 'MZ binario'));

    expect($v->fails())->toBeTrue("{$nome} não podia ser aceito")
        ->and((string) $v->errors()->first('a'))->toContain('não permitido');
})->with(['virus.exe', 'script.bat', 'run.cmd', 'a.ps1', 'x.sh', 'app.jar', 'shell.php', 'macro.xlsm', 'lib.dll', 's.vbs']);

test('extensao fora da allowlist e recusada', function () {
    // Não é "perigosa", mas também não é do negócio.
    expect(validaArquivo(UploadedFile::fake()->createWithContent('pacote.zip', 'PK'))->fails())->toBeTrue();
});

test('executavel renomeado para pdf e recusado pelo conteudo', function () {
    // `UploadedFile::fake()` deduz o MIME da EXTENSÃO; para exercitar o finfo é preciso um arquivo
    // REAL em disco, como acontece em produção. Bytes `MZ` = cabeçalho de executável do Windows.
    $caminho = tempnam(sys_get_temp_dir(), 'seg').'.pdf';
    file_put_contents($caminho, "MZ\x90\x00\x03".str_repeat("\x00", 128));
    $falso = new UploadedFile($caminho, 'laudo.pdf', null, null, true);

    $v = validaArquivo($falso);

    expect($v->fails())->toBeTrue()
        ->and((string) $v->errors()->first('a'))->toContain('executável');

    @unlink($caminho);
});

test('conteudo que nao casa com a extensao e recusado', function () {
    // PNG 1x1 real, guardado com nome .pdf — a extensão engana, o conteúdo não.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');
    $caminho = tempnam(sys_get_temp_dir(), 'seg').'.pdf';
    file_put_contents($caminho, $png);
    $falso = new UploadedFile($caminho, 'laudo.pdf', null, null, true);

    $v = validaArquivo($falso);

    expect($v->fails())->toBeTrue()
        ->and((string) $v->errors()->first('a'))->toContain('não corresponde à extensão');

    @unlink($caminho);
});

test('nome de arquivo suspeito e recusado', function (string $nome) {
    expect(validaArquivo(UploadedFile::fake()->createWithContent($nome, '%PDF-1.4'))->fails())
        ->toBeTrue("o nome \"{$nome}\" não podia ser aceito");
})->with([
    "nota'; DROP TABLE x.pdf",   // aspas + ;
    'doc--comentario.pdf',       // hífen duplo — o WAF barra na URL de download
    '<script>.pdf',              // < >
    'relatorio"final".pdf',      // aspas duplas
]);

test('allowlist propria restringe ainda mais', function () {
    // Ponto que só aceita planilha: PDF legítimo tem de ser recusado ali.
    $v = validaArquivo(UploadedFile::fake()->createWithContent('laudo.pdf', '%PDF-1.4'), ['xlsx', 'xls']);

    expect($v->fails())->toBeTrue()
        ->and((string) $v->errors()->first('a'))->toContain('XLSX');
});

test('sem allowlist aceita documentos, imagens e planilhas', function () {
    expect(validaArquivo(UploadedFile::fake()->createWithContent('planilha.csv', 'a;b'), null)->fails())->toBeFalse()
        ->and(validaArquivo(UploadedFile::fake()->image('foto.png'), null)->fails())->toBeFalse();
});

test('valor que nao e arquivo fica para as regras file e required', function () {
    // A Rule não opina sobre string vazia/null — quem barra isso é `required`/`file`.
    expect(Validator::make(['a' => null], ['a' => [new ArquivoSeguro]])->fails())->toBeFalse();
});
