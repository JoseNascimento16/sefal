<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PermissaoService;
use App\Support\ContadoresDoMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? $this->usuario($user) : null,
                /*
                 * A marca de "acabou de entrar", que o splash de boas-vindas lê
                 * uma vez e nunca mais.
                 *
                 * ⚠️ É consumida AQUI, na ENTREGA (`pull` = lê e apaga) e dentro
                 * de uma closure — e as duas coisas importam. A closure só roda
                 * quando os props são de fato serializados, ou seja quando a
                 * requisição termina numa TELA. Um redirecionamento não serializa
                 * props, então a marca atravessa quantos saltos internos houver
                 * entre o login e a primeira tela (a guarda de permissão pode
                 * mandar a pessoa para outro lugar). Fosse flash de sessão comum,
                 * ela morreria nesse salto e o splash nunca apareceria para quem
                 * cai num redirecionamento — foi exatamente o que aconteceu em
                 * homologação no sistema irmão.
                 */
                'boas_vindas' => fn (): bool => $request->hasSession()
                    && (bool) $request->session()->pull('boas_vindas', false),
            ],
            'menu' => $user ? $this->menu($user) : [],
            'acoes' => $this->acoes($request, $user),
            'flash' => $this->recado($request),
            'painel' => $this->painel($request),
        ];
    }

    /**
     * O painel a ABRIR ao chegar nesta tela — ou `null`, que é o normal.
     *
     * Existe para um caso só: alguém digita (ou tem nos favoritos) o endereço de
     * algo que hoje é painel sobreposto, e não página. O servidor manda a pessoa
     * para a tela inicial pedindo que o painel abra lá, em vez de devolver uma
     * página que não existe mais — ninguém fica olhando para um endereço que não
     * leva a nada.
     *
     * Vai pela sessão (flash), e não na URL: assim vale uma vez, não fica no
     * histórico do navegador e não põe nada no endereço — o WAF da Prefeitura
     * inspeciona query string.
     */
    protected function painel(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $painel = $request->session()->get('abrir.painel');

        return is_string($painel) ? $painel : null;
    }

    /**
     * O que esta pessoa pode FAZER na tela que está abrindo — para a tela não oferecer o que o
     * servidor recusa.
     *
     * Sem isto, a tela desenha os botões que sabe desenhar e a pessoa só descobre a recusa depois
     * de preencher o formulário inteiro: clica em Salvar e recebe "Você não tem permissão para
     * esta ação". Tela que abre para não fazer nada é pior que tela fechada.
     *
     * Vai como prop COMPARTILHADA, e não montada em cada controller, por dois motivos. Primeiro,
     * é a mesma pergunta em toda tela da Retaguarda — repetida em cada `Inertia::render`, uma
     * delas ia esquecer, e a esquecida seria justamente a que ninguém abre. Segundo, quem responde
     * é o {@see PermissaoService}, o MESMO que as guardas consultam: esconder botão é conforto,
     * nunca fronteira, e conforto que discorda da fronteira é o defeito que ele deveria evitar.
     *
     * Tela fora do Modo Gerente (a inicial, a área da própria conta) e visitante não têm o que
     * responder: vai `null`, e a tela trata como "sem restrição declarada".
     *
     * @return array<string, bool>|null
     */
    protected function acoes(Request $request, ?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $slug = PermissaoService::telaDoCaminho($request->path());

        return $slug === null ? null : app(PermissaoService::class)->acoes($user, $slug);
    }

    /**
     * O recado de uma tela para a próxima — CAMINHO ÚNICO das mensagens.
     *
     * Duas chaves, uma para cada tom: `flash.sucesso` e `flash.erro`. Quem grava
     * usa a sessão (`->with('flash.sucesso', '…')`), o que faz o recado
     * atravessar o redirecionamento; quem mostra é o layout, sempre do mesmo
     * jeito. Uma segunda forma de "avisar o usuário" seria a mesma informação com
     * dois donos — um dia só uma apareceria.
     *
     * O `chave` existe porque a tela só reage quando ele muda: sem ele, salvar
     * duas vezes seguidas mostraria o aviso só na primeira (mesma mensagem, mesma
     * página, nada a reagir).
     *
     * @return array<string, string|null>
     */
    protected function recado(Request $request): array
    {
        if (! $request->hasSession()) {
            return ['sucesso' => null, 'erro' => null, 'chave' => null];
        }

        $sucesso = $request->session()->get('flash.sucesso');
        $erro = $request->session()->get('flash.erro');

        return [
            'sucesso' => $sucesso,
            'erro' => $erro,
            'chave' => $sucesso === null && $erro === null
                ? null
                : bin2hex(random_bytes(8)),
        ];
    }

    /**
     * O usuário como a tela precisa dele: identificação, papel e setores.
     *
     * É um recorte explícito, e não o model inteiro, para nada além disto
     * atravessar a ponte — senão qualquer coluna nova (inclusive uma sensível)
     * passa a viajar para o navegador em toda requisição, sem ninguém decidir.
     *
     * A matrícula vai como está guardada (minúscula, forma canônica); quem a
     * mostra em MAIÚSCULA é a tela.
     *
     * @return array<string, mixed>
     */
    protected function usuario(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'login' => $user->login,
            'admin' => $user->ehAdmin(),
            'setores' => $user->setores->pluck('slug')->all(),
        ];
    }

    /**
     * Monta o menu de `config/retaguarda_menu.php` para este usuário.
     *
     * Duas guardas moram aqui:
     *
     *  1. item cuja rota ainda NÃO existe é descartado. O plano do menu anda na
     *     frente das telas, e um link para rota inexistente estouraria a
     *     renderização da barra inteira — o sistema ficaria sem menu por causa
     *     de uma linha de configuração;
     *  2. item que o usuário não pode ver não aparece. Quem responde isso é o
     *     `PermissaoService` — o MESMO que as guardas de acesso consultam. Se o
     *     menu tivesse a sua própria conta, um dia ofereceria uma tela que a
     *     guarda barra, ou esconderia uma que ela deixa passar para quem digita
     *     o endereço.
     *
     * @return list<array<string, mixed>>
     */
    protected function menu(User $user): array
    {
        $permissoes = app(PermissaoService::class);

        $secoes = [];

        foreach (config('retaguarda_menu.secoes', []) as $secao) {
            $itens = [];

            foreach ($secao['itens'] ?? [] as $item) {
                if (! Route::has($item['rota'])) {
                    continue;
                }

                /*
                 * Item marcado como oculto sai do MENU e nada mais: a rota segue
                 * viva, a permissão segue no Modo Gerente e a tela segue abrindo
                 * pelo endereço. É o jeito de tirar o atalho de uma tela pronta que
                 * ainda não vai ao ar para o usuário final — sem apagar código, que
                 * é o que transforma "esconder" em "refazer depois".
                 *
                 * A conferência vem ANTES da permissão de propósito: esconder é
                 * decisão de produto, não de acesso, e ler as duas na mesma linha
                 * faria parecer que uma depende da outra.
                 */
                if (($item['oculto'] ?? false) === true) {
                    continue;
                }

                if (! $permissoes->podeVerItemDoMenu($user, $item)) {
                    continue;
                }

                $itens[] = [
                    'rotulo' => $item['rotulo'],
                    'url' => route($item['rota'], absolute: false),
                    'icone' => $item['icone'] ?? 'padrao',
                    // Item que abre PAINEL sobre a tela atual em vez de navegar
                    // (ver o cabeçalho de `config/retaguarda_menu.php`). A `url`
                    // continua indo: é dela que o painel busca os dados.
                    'modal' => $item['modal'] ?? null,
                    /*
                     * O rótulo curto do menu RETRAÍDO (a doca), onde cabem umas
                     * nove letras. Sem declaração, a primeira palavra do rótulo —
                     * que resolve "Relatórios" e não resolve as seis telas que
                     * começam em "Tipos de…", daí a chave `curto` na config.
                     */
                    'curto' => $item['curto'] ?? Str::upper(Str::before($item['rotulo'], ' ')),
                    // O número vivo, quando o item declara um (melhor esforço:
                    // contagem que falha vira `null` e o item aparece sem número).
                    'contador' => isset($item['contador'])
                        ? ContadoresDoMenu::para((string) $item['contador'])
                        : null,
                ];
            }

            // Seção sem nenhum item visível E sem recado de "em construção" não
            // vira um título solto na barra.
            if ($itens === [] && ($secao['vazio'] ?? null) === null) {
                continue;
            }

            $secoes[] = [
                'rotulo' => $secao['rotulo'],
                'vazio' => $secao['vazio'] ?? null,
                'itens' => $itens,
            ];
        }

        return $secoes;
    }
}
