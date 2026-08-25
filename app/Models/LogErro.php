<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
 * @property string|null $caminho
 * @property string|null $metodo
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['request_id', 'classe', 'mensagem', 'stack', 'caminho', 'metodo', 'user_id'])]
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

    public const LIMITE_CAMINHO = 500;

    /**
     * Caminhos cujo ÚLTIMO trecho é segredo, e a máscara que entra no lugar.
     *
     * O endereço de uma requisição pode ser a credencial em si: quem tem o token
     * de `reset-password/{token}` troca a senha da conta alheia sem saber a
     * antiga. Guardado aqui, ele ficaria à vista de qualquer administrador que
     * abrisse a tela de Logs — e sairia junto na exportação em PDF.
     *
     * A lista é curta de propósito. A defesa principal não é ela: é a consulta
     * NUNCA ser gravada (ver {@see self::caminhoSeguro()}), o que já cobre todo
     * segredo que viaje como parâmetro. Aqui ficam só os que viajam no caminho.
     *
     * ⚠️ Lista escrita à mão envelhece calada — e envelhecer aqui significa
     * gravar credencial. Por isso ela NÃO é conferida contra si mesma:
     * `ObservabilidadeTest` percorre as rotas REAIS do sistema, pega toda aquela
     * cujo endereço tem `{token}`, `{hash}` ou `{signature}` e exige que o
     * caminho gravado não carregue o valor. Rota nova de segredo, portanto,
     * reprova a suíte até entrar aqui.
     *
     * @var array<string, string> padrão de caminho => o que gravar no lugar
     */
    private const CAMINHOS_SENSIVEIS = [
        // Fortify: o formulário de redefinição recebe o token no caminho e o
        // e-mail na consulta.
        'reset-password/*' => 'reset-password/[token]',
        'password/reset/*' => 'password/reset/[token]',
        /*
         * Link assinado de confirmação de e-mail: os dois últimos trechos são o
         * identificador e a assinatura. O caminho é o do Fortify de verdade
         * (`email/verify/{id}/{hash}`) — a verificação de e-mail está desligada
         * neste sistema, e o padrão fica pronto para o dia em que ligar.
         */
        'email/verify/*/*' => 'email/verify/[id]/[assinatura]',
    ];

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
                'stack' => mb_substr(self::rastroDe($e), 0, self::LIMITE_STACK),
                'caminho' => $deRequisicao ? mb_substr(self::caminhoSeguro($request), 0, self::LIMITE_CAMINHO) : null,
                'metodo' => $deRequisicao ? $request->method() : null,
                'user_id' => self::identificarUsuario(),
            ]);
        } catch (Throwable $falha) {
            try {
                // O cenário que isto cobre é o pior de todos: o PRÓPRIO banco fora
                // do ar. Aí não há onde gravar a linha, e o rastro volta a ser o
                // arquivo de log — que continua existindo exatamente para isso.
                Log::error('[log-erros] não foi possível gravar a ocorrência; erro original: '.$e->getMessage(), [
                    'request_id' => $requestId,
                    'falha_ao_gravar' => $falha->getMessage(),
                ]);
            } catch (Throwable) {
                /*
                 * Nem a reserva deu certo — disco cheio, diretório de log sem
                 * permissão, driver de log mal configurado. Não há terceira
                 * opção, e deixar a exceção escapar daqui derrubaria o `report()`
                 * e, com ele, a própria página amigável que o usuário veria. O
                 * silêncio aqui é a decisão certa: este método promete não lançar,
                 * e é essa promessa que segura o pedido de pé.
                 */
            }
        } finally {
            self::$registrando = false;
        }
    }

    /**
     * O endereço da requisição, sem nada que possa ser segredo.
     *
     * Duas decisões, e as duas custam pouco e resolvem muito:
     *
     *  1. **a consulta nunca é gravada.** O que viaja depois do `?` é escolha de
     *     quem escreveu a tela, e amanhã pode ser um documento, um termo de busca
     *     ou um e-mail. Guardar só o caminho tira essa decisão do caminho do erro:
     *     ninguém consegue, sem perceber, mandar segredo para esta tabela;
     *  2. **o último trecho de caminho sensível vira máscara.** Alguns segredos
     *     viajam no próprio caminho — o token de redefinição de senha é o
     *     arquétipo, e quem o tem troca a senha da conta alheia.
     *
     * O que se perde é pouco (dois erros na mesma tela ficam com o mesmo
     * endereço); o que se evita é uma tabela de credenciais que qualquer
     * administrador lê e exporta em PDF.
     */
    private static function caminhoSeguro(Request $request): string
    {
        $caminho = $request->path();

        foreach (self::CAMINHOS_SENSIVEIS as $padrao => $mascara) {
            if (Str::is($padrao, $caminho)) {
                return $mascara;
            }
        }

        return $caminho;
    }

    /**
     * O rastro da exceção, montado quadro a quadro e SEM os argumentos.
     *
     * `getTraceAsString()` imprime os argumentos escalares de cada chamada quando
     * `zend.exception_ignore_args` está desligado — e desligado é o default do
     * PHP fora do `php.ini-production`. Numa falha durante o login, a senha
     * digitada entraria no rastro em texto claro e apareceria no detalhe da tela
     * para quem abrisse a ocorrência.
     *
     * Montar o rastro aqui, em vez de confiar na configuração do servidor, tira a
     * segurança do dado das mãos do ambiente: seja qual for o `php.ini` da máquina
     * onde o sistema rodar, argumento nenhum passa por este método. (A ini
     * continua ligada na imagem publicada, como segunda camada — ver o
     * `dockerfile_redhat`.)
     *
     * A exceção ANTERIOR entra junto: quase sempre é ela que diz a causa real, e
     * sem ela o rastro mostra só o embrulho.
     */
    private static function rastroDe(Throwable $e, int $profundidade = 0): string
    {
        // O ponto onde a exceção nasceu NÃO está no rastro (nem no do PHP): ele
        // vive em `getFile()`/`getLine()`. Sem esta linha, a primeira moldura é
        // quem CHAMOU o código que quebrou, e a origem some.
        $linhas = ['em '.$e->getFile().'('.$e->getLine().')'];
        $n = 0;

        foreach ($e->getTrace() as $quadro) {
            $origem = isset($quadro['file'])
                ? $quadro['file'].'('.($quadro['line'] ?? '?').')'
                : '[função interna]';

            $chamada = ($quadro['class'] ?? '').($quadro['type'] ?? '').$quadro['function'];

            // Os parênteses ficam VAZIOS: é exatamente onde os argumentos
            // apareceriam, e é o que este método existe para não gravar.
            $linhas[] = '#'.$n++.' '.$origem.': '.$chamada.'()';
        }

        $linhas[] = '#'.$n.' {main}';

        // O teto de profundidade não é zelo excessivo: um embrulho sobre embrulho
        // sobre embrulho encheria o corte de texto com camadas e empurraria para
        // fora justamente a origem, que está no topo.
        if (($anterior = $e->getPrevious()) !== null && $profundidade < 3) {
            $linhas[] = '';
            $linhas[] = 'Causada por '.$anterior::class.': '.$anterior->getMessage();
            $linhas[] = self::rastroDe($anterior, $profundidade + 1);
        }

        return implode("\n", $linhas);
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
