<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Relatorios\Exportadores\ExportadorDocx;
use App\Relatorios\Exportadores\ExportadorPdf;
use App\Relatorios\Exportadores\ExportadorXlsx;
use App\Relatorios\RegistroRelatorios;
use App\Relatorios\Suporte\ContextoRelatorio;
use App\Relatorios\Suporte\PeriodoRelatorio;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Relatórios — o catálogo e a emissão.
 *
 * A tela não conhece relatório nenhum por dentro: ela desenha o que o catálogo
 * ({@see RegistroRelatorios}) descreve — título, descrição, filtros e modos — e
 * devolve os valores preenchidos. Assim relatório novo aparece na tela pela
 * própria existência no catálogo, sem uma linha de front.
 *
 * ⚠️ Isto é RELATÓRIO: documento oficial, pedido de propósito, com período e
 * totais. Exportar a grade que está na tela é outra coisa, e sai pelo
 * {@see ExportacaoListagemController}.
 */
class RelatoriosController extends Controller
{
    public function __construct(private RegistroRelatorios $registro) {}

    public function index(): Response
    {
        return Inertia::render('Retaguarda/Sistema/Relatorios', [
            'relatorios' => $this->registro->catalogo(),
        ]);
    }

    public function gerar(Request $request): HttpResponse
    {
        $chaves = array_map(static fn (array $r): string => (string) $r['chave'], $this->registro->catalogo());

        $dados = $request->validate([
            'chave' => ['required', 'string', Rule::in($chaves)],
            'formato' => ['required', 'in:pdf,xlsx,docx'],
            'modo' => ['nullable', 'string'],
            'filtros' => ['nullable', 'array'],
        ], [
            'chave.in' => 'Esse relatório não está disponível.',
        ]);

        $relatorio = $this->registro->encontrar((string) $dados['chave']);

        if ($relatorio === null) {
            // Inalcançável: a chave já foi casada com o catálogo. Fica como rede
            // para o dia em que a validação mudar — e nunca como silêncio.
            throw ValidationException::withMessages(['chave' => 'Esse relatório não está disponível.']);
        }

        /** @var array<string, mixed> $filtros */
        $filtros = $dados['filtros'] ?? [];

        /*
         * Período invertido devolveria um documento VAZIO, que quem pediu leria
         * como "não houve movimento" — e não como "você trocou as datas".
         *
         * A recusa sai como erro de VALIDAÇÃO, e não como recado de sessão, para
         * ter uma resposta só nos dois transportes: quem pede pela tela (que
         * baixa por `fetch`) recebe a mensagem em JSON e a mostra ali mesmo; quem
         * pede por formulário volta para a tela com o erro no campo.
         */
        if (($erro = PeriodoRelatorio::erro($relatorio->filtros(), $filtros)) !== null) {
            throw ValidationException::withMessages(['filtros' => $erro]);
        }

        $modo = in_array($dados['modo'] ?? '', $relatorio->modos(), true)
            ? (string) $dados['modo']
            : ($relatorio->modos()[0] ?? ContextoRelatorio::MODO_ANALITICO);

        $resultado = $relatorio->gerar(new ContextoRelatorio($filtros, $modo));

        // Quem emitiu e quando: o relatório sabe o próprio conteúdo, não a sessão.
        // A rota vive no grupo autenticado, então há sempre alguém a nomear —
        // documento oficial sem autor identificado não deveria existir.
        $resultado->metadados['gerado_em'] = now()->format('d/m/Y H:i');
        $resultado->metadados['emitido_por'] = (string) $request->user()->name;
        $resultado->metadados['titulo'] ??= mb_strtoupper($relatorio->titulo());

        $nome = Str::slug($relatorio->titulo()).'-'.now()->format('Ymd-Hi');

        return match ($dados['formato']) {
            'xlsx' => app(ExportadorXlsx::class)->baixar($resultado, $nome),
            'docx' => app(ExportadorDocx::class)->baixar($resultado, $nome),
            default => app(ExportadorPdf::class)->baixar($resultado, $nome),
        };
    }
}
