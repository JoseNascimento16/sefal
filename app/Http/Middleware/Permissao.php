<?php

namespace App\Http\Middleware;

use App\Services\PermissaoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem abre qual tela — a guarda da LEITURA.
 *
 * Esconder o item no menu é conforto, nunca fronteira: quem digita o endereço não
 * passa pelo menu. Então a conferência acontece aqui, no servidor, para todo
 * `GET /retaguarda/{tela}` — e também para as sub-rotas dela, porque
 * `/retaguarda/vistorias/nova` é a MESMA tela um segmento adiante. As mutações são
 * da outra guarda ({@see PermissaoAcao}), que confere a AÇÃO.
 *
 * Ao negar, NÃO devolve uma tela de erro seca: leva a pessoa para a tela inicial
 * dizendo o que aconteceu. Um 403 sem explicação faz parecer que o sistema
 * quebrou — e a lei do projeto é que ninguém é barrado em silêncio.
 *
 * Não há risco de loop: a tela inicial não é controlável (não declara `slug` no
 * menu), justamente porque é o destino desta negativa.
 *
 * Roda no grupo `web` inteiro, e não pendurada em rota: assim vale para as telas
 * que ainda vão nascer, sem depender de alguém lembrar de pendurá-la lá.
 */
class Permissao
{
    public function __construct(private PermissaoService $servico) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $usuario = $request->user();

        // Sem ninguém autenticado não há permissão a conferir: quem manda para o
        // login é a guarda de autenticação, e é ela que tem de responder.
        if ($usuario === null) {
            return $next($request);
        }

        $segmentos = $request->segments();

        if (($segmentos[0] ?? null) !== 'retaguarda') {
            return $next($request);
        }

        $slug = $segmentos[1] ?? '';

        if ($slug === '' || ! PermissaoService::ehControlavel($slug)) {
            return $next($request);
        }

        // O modo depende da TELA: o rollout gradual não vale para a tela que
        // distribui acesso (ver `retaguarda.permissao_sempre`).
        $modo = PermissaoService::modoPara($slug);

        if ($modo === 'off') {
            return $next($request);
        }

        if ($this->alcancavelDeOutraTela($request)) {
            return $next($request);
        }

        if ($this->servico->pode($usuario, $slug)) {
            return $next($request);
        }

        if ($modo !== 'block') {
            Log::warning('Modo Gerente: leitura que seria barrada (modo log).', [
                'tela' => $slug,
                'rota' => $request->route()?->getName(),
                'caminho' => $request->path(),
                'user_id' => $usuario->getKey(),
            ]);

            return $next($request);
        }

        return redirect()->route('retaguarda.inicio')
            ->with('flash.erro', 'Você não tem acesso a essa tela.');
    }

    /**
     * Leitura que MORA sob o caminho de uma tela mas é legitimamente alcançada de
     * outra — utilitário compartilhado por várias telas, ou documento de um fluxo
     * aberto de dentro de outro. Declarada em `config/permissao_leitura.php`.
     *
     * Sem esta porta, deduzir a tela pelo caminho tiraria de quem trabalha algo
     * que ele já fazia; com ela demais, viraria desculpa para não conceder a tela
     * na matriz. Cada linha de lá existe porque um consumo foi encontrado no
     * código.
     */
    private function alcancavelDeOutraTela(Request $request): bool
    {
        $nome = $request->route()?->getName();

        if ($nome === null) {
            return false;
        }

        $declarado = ((array) config('permissao_leitura', []))[$nome] ?? null;

        if ($declarado === '*') {
            return true;
        }

        foreach ((array) $declarado as $tela) {
            if ($this->servico->pode($request->user(), (string) $tela)) {
                return true;
            }
        }

        return false;
    }
}
