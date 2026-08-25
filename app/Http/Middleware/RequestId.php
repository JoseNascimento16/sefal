<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dá a cada requisição um código curto — o fio que liga o erro que o usuário viu
 * ao registro que o suporte lê.
 *
 * O MESMO código aparece em três lugares, e é essa coincidência que o torna útil:
 *
 *  1. na página de erro (`resources/views/errors/_base.blade.php` o mostra como
 *     "Código deste erro", para a pessoa ditar por telefone);
 *  2. no cabeçalho `X-Request-Id` da resposta;
 *  3. na coluna `request_id` da ocorrência gravada pelo hook de `report()`.
 *
 * Sem ele, "deu erro às 14h" vira caçada no arquivo de log do servidor.
 *
 * Roda no início do encadeamento GLOBAL, e não no grupo `web`: o erro que mais
 * precisa de código é justamente o que acontece antes de a aplicação ficar de pé
 * (manutenção, sessão, banco fora).
 *
 * O código é HEXADECIMAL de propósito. Ele viaja em URL e em campo de busca, e o
 * WAF da Prefeitura barra qualquer texto com cara de injeção de SQL — um `--`
 * sorteado por acaso num código em base64 faria a consulta voltar disfarçada de
 * erro de CORS.
 */
class RequestId
{
    /** Chave do container: é por ela que o hook de `report()` recupera o código. */
    public const CHAVE = 'sefal.request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = 'REQ-'.bin2hex(random_bytes(3));

        $request->attributes->set('request_id', $id);
        app()->instance(self::CHAVE, $id);

        // O nome `referencia` é o que a página de erro espera. Compartilhar (e não
        // passar por parâmetro) é o que faz o código aparecer também quando a
        // página de erro é renderizada pelo framework, longe de qualquer
        // controller nosso.
        View::share('referencia', $id);

        $resposta = $next($request);
        $resposta->headers->set('X-Request-Id', $id);

        return $resposta;
    }

    /** O código desta requisição, ou nulo fora de uma (fila, comando, teste). */
    public static function atual(): ?string
    {
        return app()->bound(self::CHAVE) ? app(self::CHAVE) : null;
    }
}
