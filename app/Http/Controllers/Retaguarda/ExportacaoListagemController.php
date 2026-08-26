<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Relatorios\Exportadores\ExportadorDocx;
use App\Relatorios\Exportadores\ExportadorPdf;
use App\Relatorios\Exportadores\ExportadorXlsx;
use App\Relatorios\Suporte\PerfilDadosListagem;
use App\Relatorios\Suporte\ResultadoRelatorio;
use App\Support\Texto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Exportação de LISTAGEM — o ponto ÚNICO de PDF / XLSX / DOCX de qualquer aba
 * "Localizar" ou grade da Retaguarda. Aqui não se gera arquivo: quem gera são os
 * exportadores do motor de relatórios, alimentados por um
 * {@see ResultadoRelatorio}.
 *
 * ## Por que as linhas vêm do CLIENTE, e não de uma nova consulta
 *
 * As listagens da Retaguarda filtram, ordenam e paginam no NAVEGADOR: o
 * controller manda a coleção e a tela recorta (busca inteligente, abas, chips).
 * Se este endpoint refizesse a consulta, o arquivo divergiria do que está na
 * tela — exportaria o universo, não o recorte que a pessoa montou. Então quem
 * manda o recorte é a tela.
 *
 * Duas consequências assumidas:
 *
 *  - **POST com o recorte no CORPO.** Os filtros carregam texto livre; em query
 *    string cairiam na lei do WAF da Prefeitura (`--`, aspas) e a falha voltaria
 *    disfarçada de erro de CORS;
 *  - **não é fronteira de dados.** A pessoa devolve o que a tela já lhe entregou
 *    — a autorização aconteceu no GET que montou a listagem. Este endpoint não
 *    amplia acesso a nada, e é por isso que ele está declarado como livre em
 *    `config/permissao_acoes.php`.
 */
class ExportacaoListagemController extends Controller
{
    /** Teto de linhas por exportação: acima disso o PDF fica inútil e o servidor sofre à toa. */
    private const MAX_LINHAS = 5000;

    /** Teto de colunas: tabela mais larga que isto não cabe em papel nenhum. */
    private const MAX_COLUNAS = 30;

    /** Acima disto a folha vai deitada — muitas colunas em retrato viram tabela ilegível. */
    private const COLUNAS_PARA_DEITAR = 6;

    public function exportar(Request $request): HttpResponse
    {
        $dados = $request->validate([
            'formato' => ['required', 'in:pdf,xlsx,docx'],
            'titulo' => ['required', 'string', 'max:150'],
            'subtitulo' => ['nullable', 'string', 'max:300'],
            // O RECORTE em palavras (aba + busca + filtros). Sem isto o arquivo sai
            // sem contexto, e semanas depois ninguém sabe o que aquelas linhas eram.
            'contexto' => ['nullable', 'string', 'max:500'],
            'colunas' => ['required', 'array', 'min:1', 'max:'.self::MAX_COLUNAS],
            'colunas.*.chave' => ['required', 'string', 'max:60'],
            'colunas.*.titulo' => ['required', 'string', 'max:60'],
            'colunas.*.alinhar' => ['nullable', 'in:left,center,right'],
            'linhas' => ['present', 'array', 'max:'.self::MAX_LINHAS],
            'orientacao' => ['nullable', 'in:portrait,landscape'],
        ], [
            'linhas.max' => 'A exportação suporta até '.self::MAX_LINHAS.' linhas. Refine o filtro e tente de novo.',
            'colunas.max' => 'A exportação suporta até '.self::MAX_COLUNAS.' colunas.',
        ]);

        /** @var array<int, array{chave: string, titulo: string, alinhar?: string}> $colunas */
        $colunas = $dados['colunas'];
        /** @var array<int, mixed> $linhas */
        $linhas = $dados['linhas'];
        $formato = (string) $dados['formato'];
        // A rota vive no grupo autenticado: há sempre alguém a nomear no arquivo.
        $emitidoPor = (string) $request->user()->name;

        $resultado = new ResultadoRelatorio;
        $resultado->metadados = [
            'titulo' => mb_strtoupper((string) $dados['titulo']),
            'gerado_em' => now()->format('d/m/Y H:i'),
            'emitido_por' => $emitidoPor,
            'orientacao' => $dados['orientacao'] ?? (count($colunas) > self::COLUNAS_PARA_DEITAR ? 'landscape' : 'portrait'),
        ];

        // É o resumo que dá SENTIDO ao arquivo: de onde saiu, que recorte é e
        // quantas linhas ele tem.
        $resultado->metadados['filtros_resumo'] = implode(' · ', array_filter([
            $dados['subtitulo'] ?? null,
            trim((string) ($dados['contexto'] ?? '')) !== '' ? $dados['contexto'] : null,
            Texto::contar(count($linhas), 'registro exportado', 'registros exportados'),
            // Só o PDF imprime `emitido_por` no cabeçalho; nos outros dois o dado
            // entra aqui, para não ficar de fora nem sair repetido no PDF.
            $formato !== 'pdf' ? 'Emitido por: '.$emitidoPor : null,
        ]));

        // Colunas todas CENTRALIZADAS no documento: a listagem exportada fica
        // visualmente uniforme, sem coluna encostada na borda. Vale só para a
        // exportação de listagem — os relatórios de `app/Relatorios/` declaram o
        // alinhamento que a sua leitura pede.
        $secao = $resultado->secao((string) ($dados['subtitulo'] ?? ''));
        foreach ($colunas as $c) {
            $secao->coluna($c['chave'], $c['titulo'], 'texto', 'center');
        }

        // Só as chaves DECLARADAS entram: a tela costuma passar o objeto inteiro, e
        // id ou marca interna não podem vazar para dentro de um documento que sai
        // do sistema.
        $chaves = array_column($colunas, 'chave');
        $registros = [];

        foreach ($linhas as $linha) {
            $registro = [];
            foreach ($chaves as $chave) {
                $registro[$chave] = self::texto(is_array($linha) ? ($linha[$chave] ?? null) : null);
            }
            $registros[] = $registro;
            $secao->linha($registro);
        }

        $this->diferenciarPorFormato($resultado, $formato, $colunas, $registros, $dados);

        $nome = Str::slug((string) $dados['titulo']).'-'.now()->format('Ymd-Hi');

        return match ($formato) {
            'xlsx' => app(ExportadorXlsx::class)->baixar($resultado, $nome),
            'docx' => app(ExportadorDocx::class)->baixar($resultado, $nome),
            default => app(ExportadorPdf::class)->baixar($resultado, $nome),
        };
    }

    /**
     * PERFIL DE DADOS POR FORMATO — cada formato recebe um CONJUNTO DE DADOS
     * próprio, derivado pelo motor genérico {@see PerfilDadosListagem}, e não o
     * mesmo dado reembalado três vezes:
     *
     *  - PDF  → análise de distribuição: gráfico + tabela com participação e
     *           percentual acumulado por categoria;
     *  - XLSX → planilha analítica: colunas derivadas por linha (Nº; Dias desde
     *           <data>) + aba "Resumo" com o pivô temporal, cabeçalho congelado e
     *           autofiltro;
     *  - DOCX → documento gerencial: parágrafo de contexto + quadro "Síntese
     *           executiva".
     *
     * A derivação é INCONDICIONAL (sem coluna de data os campos temporais saem
     * "—", mas o perfil sempre sai) e opt-in por metadado — os relatórios de
     * `app/Relatorios/` que reusam os mesmos exportadores não mudam por causa
     * disto.
     *
     * @param  array<int, array{chave: string, titulo: string, alinhar?: string}>  $colunas
     * @param  list<array<string, string>>  $registros
     * @param  array<string, mixed>  $dados
     */
    private function diferenciarPorFormato(ResultadoRelatorio $resultado, string $formato, array $colunas, array $registros, array $dados): void
    {
        $perfil = PerfilDadosListagem::analisar($colunas, $registros);

        if ($formato === 'pdf') {
            $pdf = $perfil->paraPdf();

            $resultado->grafico(
                'bar',
                'Distribuição por '.$pdf['coluna'],
                array_map(static fn (array $i): string => $i['rotulo'], $pdf['itens']),
                [['nome' => 'Registros', 'valores' => array_map(static fn (array $i): int => $i['total'], $pdf['itens'])]],
            );

            $distribuicao = $resultado->secao('Distribuição por '.$pdf['coluna']);
            $distribuicao->coluna('categoria', $pdf['coluna']);
            $distribuicao->coluna('registros', 'Registros', 'numero', 'right');
            $distribuicao->coluna('participacao', 'Participação', 'texto', 'right');
            $distribuicao->coluna('acumulado', 'Acumulado', 'texto', 'right');

            foreach ($pdf['itens'] as $i) {
                $distribuicao->linha([
                    'categoria' => $i['rotulo'],
                    'registros' => (string) $i['total'],
                    'participacao' => $i['participacao'],
                    'acumulado' => $i['acumulado'],
                ]);
            }

            $distribuicao->total('TOTAL', count($registros), [
                'registros' => count($registros),
                'participacao' => '100,0%',
            ]);

            return;
        }

        if ($formato === 'xlsx') {
            $xlsx = $perfil->paraXlsx();
            $secaoDados = $resultado->secoes[0];

            // Colunas DERIVADAS na própria aba de dados: Nº (posição no recorte) na
            // frente e, quando há coluna de data, a idade em dias de cada linha.
            $novas = [['chave' => '__num', 'titulo' => 'Nº', 'tipo' => 'numero', 'alinhar' => 'center']];
            foreach ($secaoDados->colunas as $c) {
                $novas[] = $c;
            }
            if ($xlsx['coluna_dias_titulo'] !== null) {
                $novas[] = ['chave' => '__dias', 'titulo' => $xlsx['coluna_dias_titulo'], 'tipo' => 'numero', 'alinhar' => 'center'];
            }
            $secaoDados->colunas = $novas;

            foreach ($secaoDados->linhas as $n => $linha) {
                $secaoDados->linhas[$n]['__num'] = (string) $xlsx['linhas_derivadas'][$n]['numero'];

                if ($xlsx['coluna_dias_titulo'] !== null) {
                    $dias = $xlsx['linhas_derivadas'][$n]['dias_desde'];
                    $secaoDados->linhas[$n]['__dias'] = $dias !== null ? (string) $dias : '—';
                }
            }

            $resultado->metadados['listagem_grade'] = true; // congela cabeçalho + autofiltro
            $resultado->metadados['pivot_aba'] = $xlsx['pivot']; // aba "Resumo" = pivô temporal

            return;
        }

        $contexto = trim((string) ($dados['contexto'] ?? ''));
        $resultado->metadados['intro'] = 'Relação de '.Texto::contar(count($registros), 'registro', 'registros')
            .($contexto !== '' ? ' — recorte: '.$contexto : '')
            .'. Documento gerado para conferência e arquivo.';

        $docx = $perfil->paraDocx();
        $rotuloData = $docx['coluna_data'] !== null ? ' ('.$docx['coluna_data'].')' : '';

        $sintese = $resultado->secao('Síntese executiva');
        $sintese->coluna('indicador', 'Indicador');
        $sintese->coluna('valor', 'Valor');
        $sintese->linha(['indicador' => 'Mais antigo'.$rotuloData, 'valor' => $docx['mais_antigo']]);
        $sintese->linha(['indicador' => 'Mais recente'.$rotuloData, 'valor' => $docx['mais_recente']]);
        $sintese->linha(['indicador' => 'Amplitude do período', 'valor' => $docx['amplitude_dias'] !== null ? Texto::contar($docx['amplitude_dias'], 'dia', 'dias') : '—']);
        $sintese->linha(['indicador' => 'Média de registros por dia', 'valor' => $docx['media_por_dia']]);
        $sintese->linha(['indicador' => $docx['coluna_categoria'].' menos frequente', 'valor' => $docx['menos_frequente']]);
        $sintese->linha(['indicador' => 'Valores distintos de '.$docx['coluna_categoria'], 'valor' => (string) $docx['categorias_distintas']]);
    }

    /**
     * O valor de uma célula sempre como TEXTO já pronto.
     *
     * A formatação (data em BR, moeda, rótulo de situação) é responsabilidade da
     * TELA — é ela que sabe o que a pessoa está vendo, e o documento tem de sair
     * igual ao que estava na tela. Campo ausente vira travessão: célula em branco
     * no meio da tabela parece erro de geração.
     */
    private static function texto(mixed $valor): string
    {
        return match (true) {
            $valor === null, $valor === '' => '—',
            is_bool($valor) => $valor ? 'Sim' : 'Não',
            is_array($valor) => implode(', ', array_map(static fn ($v): string => (string) $v, $valor)),
            default => (string) $valor,
        };
    }
}
