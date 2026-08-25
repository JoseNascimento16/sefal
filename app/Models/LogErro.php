<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Uma exceção que aconteceu — o registro central da observabilidade.
 *
 * Quem grava é {@see self::registrar()}, chamada do hook de `report()` em
 * `bootstrap/app.php`. Nenhum lugar do sistema grava aqui por conta própria: um
 * segundo caminho um dia gravaria com outro formato, e a tela de consulta
 * mostraria dois mundos.
 *
 * @property int $id
 * @property string|null $request_id
 * @property string $classe
 * @property string $mensagem
 * @property string|null $stack
 * @property string|null $url
 * @property string|null $metodo
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['request_id', 'classe', 'mensagem', 'stack', 'url', 'metodo', 'user_id'])]
class LogErro extends Model
{
    /**
     * Os cortes de tamanho.
     *
     * Erro de banco traz o SQL inteiro na mensagem, e uma recursão profunda gera
     * um rastro de dezenas de milhares de caracteres. Sem corte, gravar o erro
     * vira o próximo erro — e some justamente o registro que interessava.
     *
     * O rastro guarda o começo, que é onde está a origem: as primeiras molduras
     * são as do código da aplicação; o fundo é o framework, que se repete em toda
     * ocorrência.
     */
    public const LIMITE_MENSAGEM = 2000;

    public const LIMITE_STACK = 5000;

    public const LIMITE_CLASSE = 200;

    public const LIMITE_URL = 500;

    protected $table = 'log_erros';

    /**
     * Trava de reentrada: se gravar o registro disparar outra exceção reportável,
     * o hook de `report()` chamaria isto de novo, em laço, até esgotar a pilha.
     */
    private static bool $registrando = false;

    /**
     * Quem estava usando o sistema quando o erro aconteceu.
     *
     * Sem chave estrangeira dura na tabela (o registro é histórico e sobrevive ao
     * desligamento da conta), então a relação pode vir vazia — e a tela mostra
     * isso como ausência de dono, nunca inventando um nome.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Registra uma exceção. É BEST-EFFORT e nunca lança: quem chama já está com
     * um problema nas mãos, e transformar a captura em um segundo erro custaria
     * ao usuário até a página amigável.
     */
    public static function registrar(Throwable $e, ?Request $request = null, ?string $requestId = null): void
    {
        if (self::$registrando) {
            return;
        }

        self::$registrando = true;

        try {
            $request ??= request();

            /*
             * Nem todo erro nasce de uma requisição: comando agendado e trabalho
             * em fila também estouram. Fora de uma requisição o framework ainda
             * entrega um objeto de requisição — mas sintético, e gravar o
             * `http://localhost` dele como endereço faria a tela mostrar um
             * caminho que ninguém acessou. A presença do verbo entre as variáveis
             * do servidor é o que distingue os dois casos.
             */
            $deRequisicao = $request->server('REQUEST_METHOD') !== null;

            self::create([
                'request_id' => $requestId,
                'classe' => mb_substr($e::class, 0, self::LIMITE_CLASSE),
                'mensagem' => mb_substr($e->getMessage(), 0, self::LIMITE_MENSAGEM),
                'stack' => mb_substr($e->getTraceAsString(), 0, self::LIMITE_STACK),
                'url' => $deRequisicao ? mb_substr($request->fullUrl(), 0, self::LIMITE_URL) : null,
                'metodo' => $deRequisicao ? $request->method() : null,
                'user_id' => self::identificarUsuario(),
            ]);
        } catch (Throwable $falha) {
            // O cenário que isto cobre é o pior de todos: o PRÓPRIO banco fora do
            // ar. Aí não há onde gravar a linha, e o rastro volta a ser o arquivo
            // de log — que continua existindo exatamente para isso.
            Log::error('[log-erros] não foi possível gravar a ocorrência; erro original: '.$e->getMessage(), [
                'request_id' => $requestId,
                'falha_ao_gravar' => $falha->getMessage(),
            ]);
        } finally {
            self::$registrando = false;
        }
    }

    /**
     * Quem estava logado, se alguém estava.
     *
     * Percorre os guards que o sistema REALMENTE tem, lidos da configuração, em
     * vez de uma lista escrita à mão. Quando o guard do aplicativo do fiscal
     * nascer (ele autentica por token, sem sessão), o erro vindo da rua já nasce
     * com dono — sem depender de alguém lembrar de acrescentá-lo aqui. Foi
     * exatamente esse esquecimento que, no sistema irmão, fez dezenas de erros do
     * aplicativo ficarem registrados como anônimos.
     *
     * Um lookup que falhe não pode custar o registro inteiro: na dúvida, sem dono.
     */
    private static function identificarUsuario(): ?int
    {
        foreach (array_keys((array) config('auth.guards', [])) as $guard) {
            try {
                $id = Auth::guard($guard)->id();
            } catch (Throwable) {
                continue;
            }

            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }
}
