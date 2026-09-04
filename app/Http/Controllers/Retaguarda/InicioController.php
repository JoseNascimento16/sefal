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
 * essa forma sempre produz: o cartão de Ambulantes continuou dizendo "Em
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
     * A `rota` é declarada pelo NOME, e o nome pode ainda não estar registrado: é
     * assim que um atalho entra no plano antes de a tela existir. Nesse caso o
     * cartão nasce em construção (sem endereço) e passa a levar a algum lugar no dia
     * em que a rota nascer, sem ninguém precisar voltar aqui.
     *
     * @var list<array{chave: string, titulo: string, descricao: string, rota: string, slug: string|null}>
     */
    private const ATALHOS = [
        [
            'chave' => 'perfil',
            'titulo' => 'Meu Perfil',
            'descricao' => 'Seus dados, sua senha e a aparência do sistema.',
            'rota' => 'profile.edit',
            // Fora do controle de acesso por decisão de projeto: trancar alguém
            // fora da própria conta não é decisão de chefia.
            'slug' => null,
        ],
        [
            'chave' => 'ambulantes',
            'titulo' => 'Ambulantes',
            'descricao' => 'Cadastro, validação do que veio da rua e prontuário.',
            'rota' => 'retaguarda.ambulantes.index',
            'slug' => 'ambulantes',
        ],
        /*
         * As quatro do caminho da fiscalização. As duas de MAPA já existem
         * (protótipo, 02/09/2026); Cadastro de Operação e Fiscalizações têm
         * endereço e uma tela que abre dizendo o que vão ser (ver
         * `TelasEmPreparacaoController`) — então nenhuma das quatro é cartão
         * esmaecido sem link: a espera, onde ainda há, mora dentro da tela, que é
         * onde ela pode ser explicada.
         *
         * O `slug` está declarado porque existe permissão de verdade para cada uma:
         * quem não a tem não vê o atalho, em vez de ser convidado para uma recusa.
         */
        [
            'chave' => 'operacoes',
            'titulo' => 'Cadastro de Operação',
            'descricao' => 'As operações de rua planejadas: onde, quando e quem vai.',
            'rota' => 'retaguarda.operacoes.index',
            'slug' => 'operacoes',
        ],
        /*
         * As Denúncias e a Caixa de Entrada vêm ANTES de Fiscalizações, como no
         * menu: é o começo da cadeia — a demanda de fora entra, é triada, e só
         * então vira trabalho de campo. A ordem dos atalhos conta essa sequência.
         *
         * Os dois canais de denúncia entram como DOIS atalhos, e não um, porque
         * são duas telas: um cartão só levaria a uma delas e deixaria a outra sem
         * caminho a partir daqui — atalho que esconde metade do módulo é pior que
         * atalho nenhum.
         */
        [
            'chave' => 'denuncias-e-salvador',
            'titulo' => 'Denúncias do e-Salvador',
            'descricao' => 'O que o portal da ouvidoria entrega: triar, encaminhar à área ou devolver.',
            'rota' => 'retaguarda.denuncias.e-salvador.index',
            'slug' => 'denuncias',
        ],
        [
            'chave' => 'denuncias-fala-salvador',
            'titulo' => 'Denúncias do Fala Salvador',
            'descricao' => 'O que chega do Disque 156, inclusive anônimo: triar e dirigir o trabalho.',
            'rota' => 'retaguarda.denuncias.fala-salvador.index',
            'slug' => 'denuncias',
        ],
        [
            'chave' => 'caixa',
            'titulo' => 'Caixa de Entrada',
            'descricao' => 'O que chega de fora em papel: registre, encaminhe à equipe ou devolva.',
            'rota' => 'retaguarda.caixa-de-entrada.index',
            'slug' => 'caixa-de-entrada',
        ],
        [
            'chave' => 'fiscalizacoes',
            'titulo' => 'Fiscalizações',
            'descricao' => 'O que os fiscais registraram em campo, com foto e local.',
            'rota' => 'retaguarda.fiscalizacoes.index',
            'slug' => 'fiscalizacoes',
        ],
        [
            'chave' => 'mapa',
            'titulo' => 'Mapa ao Vivo',
            'descricao' => 'A cidade agora: onde estão os fiscais e o que acabou de entrar.',
            'rota' => 'retaguarda.mapa.index',
            'slug' => 'mapa',
        ],
        [
            'chave' => 'calor',
            'titulo' => 'Mapa de Calor',
            'descricao' => 'Onde a irregularidade se concentra, para a operação ir aonde precisa.',
            'rota' => 'retaguarda.mapa-de-calor.index',
            'slug' => 'mapa-de-calor',
        ],
        [
            'chave' => 'areas',
            'titulo' => 'Áreas e Equipes',
            'descricao' => 'A divisão da cidade: área, equipe, encarregado e o bloco de bairros.',
            'rota' => 'retaguarda.areas-e-equipes.index',
            'slug' => 'areas-e-equipes',
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
            // A rota é CONFERIDA, e não só declarada, porque o plano anda na frente
            // das telas: um nome que ainda não existe estouraria a montagem da tela
            // inteira. Sem registro, o endereço fica nulo e o cartão sai em
            // construção — é a porta de entrada de um atalho planejado antes da tela.
            $endereco = Route::has($atalho['rota'])
                ? route($atalho['rota'], absolute: false)
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
