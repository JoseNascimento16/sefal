<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
            ],
            'menu' => $user ? $this->menu($user) : [],
            'flash' => $this->recado($request),
        ];
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
     *  2. item com `setores` só aparece para quem pertence a um deles. O
     *     administrador enxerga tudo.
     *
     * @return list<array<string, mixed>>
     */
    protected function menu(User $user): array
    {
        $ehAdmin = $user->ehAdmin();
        $meusSetores = $user->setores->pluck('slug')->all();

        $secoes = [];

        foreach (config('retaguarda_menu.secoes', []) as $secao) {
            $itens = [];

            foreach ($secao['itens'] ?? [] as $item) {
                if (! Route::has($item['rota'])) {
                    continue;
                }

                $setores = $item['setores'] ?? [];

                if ($setores !== [] && ! $ehAdmin && array_intersect($setores, $meusSetores) === []) {
                    continue;
                }

                $itens[] = [
                    'rotulo' => $item['rotulo'],
                    'url' => route($item['rota'], absolute: false),
                    'icone' => $item['icone'] ?? 'padrao',
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
