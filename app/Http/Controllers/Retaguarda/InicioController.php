<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PermissaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela inicial da Retaguarda — para onde o login leva, e para onde volta (com o
 * motivo) quem foi barrado em outra tela.
 *
 * Ela existia como `Route::inertia`, sem servidor nenhum por trás, e os atalhos
 * eram quatro cartões escritos na própria tela. O resultado foi o defeito que
 * essa forma sempre produz: o cartão de Permissionários continuou dizendo "Em
 * construção" depois de a tela ficar pronta, e a primeira coisa que o usuário
 * enxergava desmentia a entrega principal — enquanto o menu, ao lado, abria a
 * tela normalmente.
 *
 * Agora o atalho é decidido AQUI, pelas mesmas duas perguntas que o menu faz:
 *
 *  1. **a rota existe?** Se não, a tela ainda não foi construída — o cartão fica
 *     à vista, esmaecido, dizendo "Em construção". É informação útil: quem usa o
 *     sistema enxerga o caminho que está sendo aberto;
 *  2. **esta pessoa pode entrar?** Quem responde é o {@see PermissaoService}, o
 *     MESMO que o menu e as guardas consultam. Sem permissão, o cartão não
 *     aparece — oferecer atalho para uma tela que a guarda barra é convite para
 *     uma recusa que ninguém entende.
 *
 * O que fica na tela é só o desenho: o ícone de cada atalho, escolhido pela
 * `chave`.
 */
class InicioController extends Controller
{
    /**
     * Os atalhos da tela inicial, na ordem em que aparecem.
     *
     * `rota` nula quer dizer "tela das próximas entregas" — o cartão nasce em
     * construção e passa a levar a algum lugar no dia em que a rota existir, sem
     * ninguém precisar lembrar de voltar aqui.
     *
     * @var list<array{chave: string, titulo: string, descricao: string, rota: string|null, slug: string|null}>
     */
    private const ATALHOS = [
        [
            'chave' => 'perfil',
            'titulo' => 'Meu Perfil',
            'descricao' => 'Seus dados, sua senha e a aparência do sistema.',
            'rota' => 'profile.edit',
            // Fora do controle de acesso por decisão de projeto: trancar alguém
            // fora da própria conta não é decisão de gestor.
            'slug' => null,
        ],
        [
            'chave' => 'permissionarios',
            'titulo' => 'Permissionários',
            'descricao' => 'Cadastro, validação do que veio da rua e prontuário.',
            'rota' => 'retaguarda.permissionarios.index',
            'slug' => 'permissionarios',
        ],
        [
            'chave' => 'fiscalizacoes',
            'titulo' => 'Fiscalizações',
            'descricao' => 'O que os fiscais registraram em campo, com foto e local.',
            'rota' => null,
            'slug' => null,
        ],
        [
            'chave' => 'areas',
            'titulo' => 'Áreas de atuação',
            'descricao' => 'Os polígonos que dizem quem pertence a cada área.',
            'rota' => null,
            'slug' => null,
        ],
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Retaguarda/Inicio', [
            'atalhos' => $this->atalhos($request->user()),
        ]);
    }

    /**
     * Os atalhos que esta pessoa pode ver, já com o endereço resolvido.
     *
     * @return list<array{chave: string, titulo: string, descricao: string, href: string|null}>
     */
    private function atalhos(?User $usuario): array
    {
        $permissoes = app(PermissaoService::class);
        $atalhos = [];

        foreach (self::ATALHOS as $atalho) {
            $rota = $atalho['rota'];

            // Endereço nulo = tela das próximas entregas. A rota é conferida (e
            // não só declarada) porque o plano anda na frente das telas: um nome
            // de rota que ainda não existe estouraria a montagem da tela inteira.
            $endereco = $rota !== null && Route::has($rota)
                ? route($rota, absolute: false)
                : null;

            // A tela existe mas esta pessoa não entra: o atalho SOME, em vez de
            // convidar para uma recusa. Quem responde é a mesma regra do menu.
            if ($endereco !== null && $atalho['slug'] !== null
                && ! $permissoes->podeVerItemDoMenu($usuario, ['slug' => $atalho['slug']])) {
                continue;
            }

            $atalhos[] = [
                'chave' => $atalho['chave'],
                'titulo' => $atalho['titulo'],
                'descricao' => $atalho['descricao'],
                'href' => $endereco,
            ];
        }

        return $atalhos;
    }
}
