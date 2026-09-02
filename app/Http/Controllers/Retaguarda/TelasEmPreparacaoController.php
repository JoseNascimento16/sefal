<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Services\PermissaoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * As telas do caminho da fiscalização que AINDA NÃO EXISTEM — e que, mesmo assim,
 * abrem.
 *
 * O plano do sistema anda na frente das telas. Até aqui isso aparecia como item de
 * menu sem destino e cartão "Em construção" que não levava a lugar nenhum: quem
 * clicava não recebia resposta, e a espera não era explicada em parte alguma.
 *
 * Cada uma destas tem endereço, permissão e uma tela de verdade que diz, em uma
 * linha, o que ela vai ser e em que fase chega. É a mesma decisão do `vazio` das
 * seções do menu: **melhor dizer "chega na Fase 2" do que fingir que não existe** —
 * quem usa o sistema enxerga o caminho que está sendo aberto.
 *
 * ⚠️ Isto é ANDAIME, e o teste `TelasEmPreparacaoTest` existe para ele não virar
 * moradia: no dia em que a tela real nascer, ela toma o `slug` e a rota, e a
 * entrada correspondente sai deste catálogo. Nada aqui grava nada — são rotas de
 * leitura, e só.
 */
class TelasEmPreparacaoController extends Controller
{
    /**
     * O que cada tela em preparação anuncia.
     *
     * A chave é o `slug`, que é também o primeiro trecho do caminho — é dele que as
     * guardas deduzem a permissão, sem ninguém declarar nada.
     *
     * `variante` escolhe o corpo: `cartao` é o aviso sóbrio, para as telas de
     * lista; `mapa` desenha a cidade (painel navy com a malha de ruas), porque uma
     * tela de mapa se explica melhor mostrando o que ela vai mostrar.
     *
     * ⚠️ Nenhuma entrada usa a variante `mapa` HOJE — as duas telas de mapa
     * deixaram o catálogo em 02/09/2026, quando passaram a existir. A variante fica
     * porque é ela que a próxima tela em preparação com cara de mapa vai querer, e
     * porque o desenho dela está registrado na RN-09; se ficar sem uso por muito
     * tempo, o certo é apagá-la junto com o `.prep-cidade` do CSS.
     *
     * @var array<string, array{
     *     secao: string,
     *     titulo: string,
     *     subtitulo: string,
     *     frase: string,
     *     variante: string,
     *     fase: string,
     *     itens: list<string>,
     * }>
     */
    private const CATALOGO = [
        'operacoes' => [
            'secao' => 'Fiscalização',
            'titulo' => 'Cadastro de Operação',
            'subtitulo' => 'As operações de rua planejadas pela gestão: onde, quando e quem vai.',
            'frase' => 'Aqui a gestão vai montar a operação antes de ela ir para a rua.',
            'variante' => 'cartao',
            'fase' => 'Fase 2',
            'itens' => [
                'Abrir uma operação com data, área e equipe de fiscais.',
                'Acompanhar o que cada operação produziu em campo.',
                'Encerrar a operação com o resultado consolidado.',
            ],
        ],
        'fiscalizacoes' => [
            'secao' => 'Fiscalização',
            'titulo' => 'Fiscalizações',
            'subtitulo' => 'O que os fiscais registraram em campo, com foto, local e desfecho.',
            'frase' => 'Aqui vai chegar tudo o que o aplicativo do fiscal registra na calçada.',
            'variante' => 'cartao',
            'fase' => 'Fase 2',
            'itens' => [
                'Consultar a fiscalização pelo permissionário, pela área ou pelo período.',
                'Ver foto, ponto de GPS e o documento que saiu na hora.',
                'Acompanhar o prazo de retorno de quem foi notificado.',
            ],
        ],
        /*
         * O Mapa ao Vivo e o Mapa de Calor SAÍRAM daqui em 02/09/2026: as duas
         * telas passaram a existir como protótipo, e tomaram o slug e o nome de
         * rota que este catálogo emprestava a elas
         * ({@see MapaAoVivoController}, {@see MapaDeCalorController}).
         *
         * É a troca que o cabeçalho desta classe prevê — o andaime sai quando a
         * tela chega, e o menu não é tocado, porque o nome da rota já era o das
         * telas de verdade.
         */
    ];

    /**
     * Mostra a tela em preparação a que o ENDEREÇO pertence.
     *
     * O slug sai do caminho pelo mesmo serviço que as guardas usam
     * ({@see PermissaoService::telaDoCaminho}) — e não de um parâmetro de rota. Assim
     * a rota, a permissão e o conteúdo leem a MESMA coisa: se um dia divergirem, é
     * porque o caminho mudou, e aí os três mudam juntos.
     */
    public function mostrar(Request $request): Response
    {
        $slug = PermissaoService::telaDoCaminho($request->path());
        $tela = self::CATALOGO[$slug] ?? null;

        // Rota registrada para um slug fora do catálogo é erro de programação, não
        // de usuário: some como "não encontrado" em vez de renderizar tela vazia.
        if ($tela === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Retaguarda/EmPreparacao', $tela);
    }

    /**
     * Os slugs que este controller atende — é daqui que as rotas nascem, num laço.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::CATALOGO);
    }
}
