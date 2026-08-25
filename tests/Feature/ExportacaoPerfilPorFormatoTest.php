<?php

use App\Models\User;
use App\Relatorios\Suporte\PerfilDadosListagem;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
|--------------------------------------------------------------------------
| Perfil de dados por formato — os três conjuntos são DISJUNTOS
|--------------------------------------------------------------------------
|
| Cada formato de exportação entrega um conjunto de dados próprio, derivado por
| processamento próprio, e os conjuntos exclusivos não se cruzam:
|
|   • PDF  → análise de distribuição: participação percentual + acumulado;
|   • XLSX → planilha analítica: Nº e "Dias desde <data>" por linha + pivô
|            temporal (Mês/Ano × categoria) na aba Resumo;
|   • DOCX → síntese executiva: mais antigo/recente, amplitude, média por dia,
|            categoria menos frequente, categorias distintas.
|
| É o que sustenta a contagem dos três como saídas externas DISTINTAS na análise
| de ponto de função: não é o mesmo dado reembalado. E a derivação é
| INCONDICIONAL — recorte sem coluna de data não pode calar perfil nenhum, senão
| "às vezes a saída é igual" volta a ser verdade.
|
*/

/** @return array<string, mixed> */
function payloadPerfil(array $extra = []): array
{
    return array_merge([
        'formato' => 'pdf',
        'titulo' => 'Fiscalizações',
        'subtitulo' => 'Fiscalização › Consulta',
        'contexto' => 'Aba: Concluídas',
        'colunas' => [
            ['chave' => 'numero', 'titulo' => 'Número'],
            ['chave' => 'situacao', 'titulo' => 'Situação'],
            ['chave' => 'registrada', 'titulo' => 'Data do registro'],
        ],
        // 5 registros, 2 categorias, 3 meses distintos — matéria para os três perfis.
        'linhas' => [
            ['numero' => 'FS001', 'situacao' => 'Regular', 'registrada' => '02/06/2026'],
            ['numero' => 'FS002', 'situacao' => 'Regular', 'registrada' => '15/06/2026'],
            ['numero' => 'FS003', 'situacao' => 'Irregular', 'registrada' => '01/07/2026'],
            ['numero' => 'FS004', 'situacao' => 'Regular', 'registrada' => '20/07/2026'],
            ['numero' => 'FS005', 'situacao' => 'Irregular', 'registrada' => '03/08/2026'],
        ],
    ], $extra);
}

function perfilDoRecorte(): PerfilDadosListagem
{
    return PerfilDadosListagem::analisar(payloadPerfil()['colunas'], payloadPerfil()['linhas']);
}

// ── O motor: os três perfis derivam dados DIFERENTES do mesmo recorte ───────────────────────

test('o perfil do pdf deriva participacao percentual e acumulado', function () {
    $pdf = perfilDoRecorte()->paraPdf();

    // 3 Regulares de 5 = 60,0%; o acumulado da 2ª categoria fecha em 100,0%.
    expect($pdf['itens'][0]['participacao'])->toBe('60,0%');
    expect($pdf['itens'][1]['acumulado'])->toBe('100,0%');
});

test('o perfil do xlsx deriva posicao, dias desde e pivo temporal', function () {
    $xlsx = perfilDoRecorte()->paraXlsx();

    expect($xlsx['coluna_dias_titulo'])->toBe('Dias desde Data do registro');
    expect($xlsx['linhas_derivadas'][0]['numero'])->toBe(1);
    expect($xlsx['linhas_derivadas'][0]['dias_desde'])->toBeInt();

    // Pivô temporal: uma linha por Mês/Ano, com a contagem POR CATEGORIA.
    expect(array_column($xlsx['pivot']['linhas'], 'mes'))->toBe(['06/2026', '07/2026', '08/2026']);
    expect($xlsx['pivot']['linhas'][0]['valores']['Regular'])->toBe(2);   // jun: FS001 + FS002
    expect($xlsx['pivot']['linhas'][1]['valores']['Irregular'])->toBe(1); // jul: FS003
});

test('o perfil do docx deriva a sintese executiva', function () {
    $docx = perfilDoRecorte()->paraDocx();

    expect($docx['mais_antigo'])->toBe('02/06/2026');
    expect($docx['mais_recente'])->toBe('03/08/2026');
    expect($docx['amplitude_dias'])->toBe(62);            // 02/06 → 03/08
    expect($docx['menos_frequente'])->toBe('Irregular');  // 2 < 3
    expect($docx['categorias_distintas'])->toBe(2);
    // 5 registros / 63 dias corridos (inclusive), em pt-BR com duas casas.
    expect($docx['media_por_dia'])->toBe('0,08');
});

test('os conjuntos exclusivos de cada formato sao disjuntos', function () {
    $perfil = perfilDoRecorte();

    $pdf = array_keys($perfil->paraPdf()['itens'][0]);
    $xlsx = array_merge(array_keys($perfil->paraXlsx()['linhas_derivadas'][0]), ['pivot']);
    $docx = array_keys($perfil->paraDocx());

    $soPdf = ['participacao', 'acumulado'];
    $soXlsx = ['numero', 'dias_desde', 'pivot'];
    $soDocx = ['mais_antigo', 'mais_recente', 'amplitude_dias', 'media_por_dia', 'menos_frequente'];

    // Nenhum campo exclusivo de um formato aparece em outro — a disjunção é o
    // contrato, e sem teste ela se perde na primeira "pequena melhoria".
    expect(array_values(array_intersect($soPdf, array_merge($xlsx, $docx))))->toBe([]);
    expect(array_values(array_intersect($soXlsx, array_merge($pdf, $docx))))->toBe([]);
    expect(array_values(array_intersect($soDocx, array_merge($pdf, $xlsx))))->toBe([]);
});

test('sem coluna de data os perfis continuam saindo', function () {
    // Incondicionalidade: recorte sem data não cala nenhum perfil — muda o valor
    // dos campos temporais, não a existência da saída.
    $perfil = PerfilDadosListagem::analisar(
        [['chave' => 'nome', 'titulo' => 'Nome'], ['chave' => 'situacao', 'titulo' => 'Situação']],
        [['nome' => 'A', 'situacao' => 'Regular'], ['nome' => 'B', 'situacao' => 'Irregular']],
    );

    expect($perfil->paraPdf()['itens'][0]['participacao'])->toBe('50,0%');
    expect($perfil->paraXlsx()['linhas_derivadas'][0]['numero'])->toBe(1);
    expect($perfil->paraXlsx()['coluna_dias_titulo'])->toBeNull();
    expect($perfil->paraDocx()['mais_antigo'])->toBe('—');
    expect($perfil->paraDocx()['categorias_distintas'])->toBe(2);
});

test('categoria chamada mes ou total nao atropela o pivo', function () {
    // As contagens vão num sub-array: categoria com nome de chave reservada
    // sobrescreveria o mês ou o total da linha do pivô.
    $perfil = PerfilDadosListagem::analisar(
        [['chave' => 'tipo', 'titulo' => 'Tipo'], ['chave' => 'quando', 'titulo' => 'Quando']],
        [
            ['tipo' => 'total', 'quando' => '02/06/2026'],
            ['tipo' => 'mes', 'quando' => '03/06/2026'],
        ],
    );

    $linha = $perfil->paraXlsx()['pivot']['linhas'][0];

    expect($linha['mes'])->toBe('06/2026');
    expect($linha['total'])->toBe(2);
    expect($linha['valores']['total'])->toBe(1);
    expect($linha['valores']['mes'])->toBe(1);
});

// ── O arquivo: o que cada documento REALMENTE carrega ──────────────────────────────────────

test('o xlsx carrega as derivadas e o pivo, mas nao os percentuais', function () {
    $r = $this->actingAs(User::factory()->create(['admin' => true]))
        ->post(route('retaguarda.exportar-listagem'), payloadPerfil(['formato' => 'xlsx']));

    $r->assertOk();
    $planilha = IOFactory::createReader('Xlsx')->load(arquivoTemporarioXlsx($r->streamedContent()));

    $dados = textoDaAba($planilha->getActiveSheet());
    expect($dados)->toContain('Nº')->toContain('Dias desde Data do registro');

    $resumo = textoDaAba($planilha->getSheetByName('Resumo'));
    expect($resumo)->toContain('Mês/Ano')->toContain('06/2026')->toContain('Regular')->toContain('TOTAL');

    // E não carrega o que é exclusivo dos outros formatos.
    expect($dados.$resumo)->not->toContain('%')->not->toContain('Mais antigo');
});

test('o docx carrega a sintese executiva, mas nao as derivadas nem os percentuais', function () {
    $r = $this->actingAs(User::factory()->create(['admin' => true]))
        ->post(route('retaguarda.exportar-listagem'), payloadPerfil(['formato' => 'docx']));

    $r->assertOk();

    $caminho = tempnam(sys_get_temp_dir(), 'exp').'.docx';
    file_put_contents($caminho, $r->streamedContent());
    $zip = new ZipArchive;
    $zip->open($caminho);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    expect($xml)
        ->toContain('Síntese executiva')
        ->toContain('Mais antigo')
        ->toContain('02/06/2026')
        ->toContain('Amplitude do período')
        ->toContain('62 dia')
        ->not->toContain('Dias desde')
        ->not->toContain('Participação');
});

test('o pdf gera com o perfil de distribuicao aplicado', function () {
    // O binário do PDF não é inspecionável de forma estável (streams comprimidos):
    // a prova dos dados exclusivos está nos testes do motor. Aqui, o essencial —
    // o formato gera com o perfil aplicado, sem estourar.
    $r = $this->actingAs(User::factory()->create(['admin' => true]))
        ->post(route('retaguarda.exportar-listagem'), payloadPerfil(['formato' => 'pdf']));

    $r->assertOk();
    expect((string) $r->headers->get('content-type'))->toContain('pdf');
});
