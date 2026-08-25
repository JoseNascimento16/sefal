<?php

namespace App\Http\Middleware;

use App\Services\PermissaoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem GRAVA o quê — a guarda das AÇÕES. Complementa a da leitura
 * ({@see Permissao}): lá se confere se a tela abre, aqui se confere se a mutação
 * pode acontecer (`habilitado`, `incluir` ou `excluir`).
 *
 * O ponto de desenho: a proteção é DERIVADA, não declarada. Enquanto o mapa era
 * uma lista do que se protege, toda rota nova nascia desprotegida — bastava
 * esquecer de acrescentá-la. Aqui a tela sai do próprio caminho
 * (`POST /retaguarda/vistorias` pertence à tela `vistorias`) e a ação sai da
 * convenção de nomes: `.store` inclui, DELETE/`.destroy` exclui, o resto opera.
 * Rota nova, portanto, já chega protegida — e `config/permissao_acoes.php` fica
 * só para as exceções, cada uma com o motivo escrito ao lado.
 *
 * `PermissaoAcaoCoberturaTest` é o que mantém isso de pé: mutação que não é
 * derivável nem declarada reprova no gate, não em produção.
 */
class PermissaoAcao
{
    private const MUTACOES = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private PermissaoService $servico) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        $rota = $request->route();

        if ($usuario === null || ! $rota instanceof Route) {
            return $next($request);
        }

        $regra = self::regra($rota);

        // Sem regra: não é mutação, ou a tela dela não é controlável.
        // Declarada como livre: está fora do alcance do Modo Gerente por decisão
        // escrita (ver o motivo na declaração).
        if ($regra === null || ($regra['livre'] ?? false) === true) {
            return $next($request);
        }

        /** @var list<string> $telas */
        $telas = $regra['telas'];
        $acao = $regra['acao'];

        // O modo depende da TELA: o rollout gradual não vale para a tela que
        // distribui acesso (ver `retaguarda.permissao_sempre`).
        $modo = PermissaoService::modoPara(...$telas);

        if ($modo === 'off') {
            return $next($request);
        }

        foreach ($telas as $tela) {
            if ($this->servico->pode($usuario, $tela, $acao)) {
                return $next($request);
            }
        }

        if ($modo !== 'block') {
            Log::warning('Modo Gerente: ação que seria barrada (modo log).', [
                'tela' => implode('|', $telas),
                'acao' => $acao,
                'rota' => $rota->getName(),
                'metodo' => $request->method(),
                'caminho' => $request->path(),
                'user_id' => $usuario->getKey(),
            ]);

            return $next($request);
        }

        // Volta para onde a pessoa estava, com o motivo. Uma tela de erro seca
        // aqui perderia o formulário preenchido e não explicaria nada.
        return back()->with('flash.erro', 'Você não tem permissão para esta ação.');
    }

    /**
     * A regra que vale para uma rota: a declarada em `config/permissao_acoes.php`
     * se houver, senão a derivada do caminho.
     *
     * @return array{telas: list<string>, acao: string, livre?: bool}|null
     */
    public static function regra(Route $rota): ?array
    {
        /*
         * Busca por ÍNDICE, e não por `config('permissao_acoes.'.$nome)`: nome de
         * rota tem ponto dentro (`retaguarda.modo-gerente.salvar`) e o acesso por
         * ponto do `config()` leria isso como array aninhado, nunca achando nada.
         */
        $mapa = (array) config('permissao_acoes', []);
        $declarada = $mapa[$rota->getName() ?? $rota->uri()] ?? null;

        if (is_array($declarada)) {
            if (($declarada['livre'] ?? false) === true) {
                return ['telas' => [], 'acao' => '', 'livre' => true];
            }

            return [
                'telas' => array_values(array_map(
                    'strval',
                    (array) ($declarada['slugs'] ?? [$declarada['slug'] ?? '']),
                )),
                'acao' => (string) ($declarada['acao'] ?? 'habilitado'),
            ];
        }

        $derivada = self::derivar($rota);

        return $derivada === null
            ? null
            : ['telas' => [$derivada['slug']], 'acao' => $derivada['acao']];
    }

    /**
     * A tela e a ação INFERIDAS de uma rota de mutação — a convenção que faz a
     * rota nova nascer protegida sem ninguém declarar nada.
     *
     * Devolve null quando não há o que inferir: rota de leitura (é da outra
     * guarda) ou tela que não está sob o Modo Gerente. Nesse caso a rota passa —
     * e é a cobertura, no gate, que cobra a declaração de quem cai aqui.
     *
     * @return array{slug: string, acao: string}|null
     */
    public static function derivar(Route $rota): ?array
    {
        $metodos = $rota->methods();

        if (array_intersect($metodos, self::MUTACOES) === []) {
            return null;
        }

        $segmentos = explode('/', trim($rota->uri(), '/'));

        if ($segmentos[0] !== 'retaguarda') {
            return null;
        }

        $slug = $segmentos[1] ?? '';

        if ($slug === '' || ! PermissaoService::ehControlavel($slug)) {
            return null;
        }

        $nome = (string) $rota->getName();

        $acao = match (true) {
            in_array('DELETE', $metodos, true), str_ends_with($nome, '.destroy') => 'excluir',
            str_ends_with($nome, '.store') => 'incluir',
            default => 'habilitado',
        };

        return ['slug' => $slug, 'acao' => $acao];
    }
}
