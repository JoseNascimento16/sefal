<?php

namespace App\Services;

use App\Models\PermissaoSetor;
use App\Models\User;
use App\Support\CatalogoFuncionalidades;

/**
 * Quem pode o quê — a ÚNICA resposta do sistema para essa pergunta.
 *
 * Leem daqui o menu lateral (o que aparece), a guarda de leitura (quem abre a
 * tela) e a guarda de ação (quem grava). Se cada um tivesse a sua conta, um dia o
 * menu ofereceria uma tela que a guarda barra — ou, pior, esconderia uma tela que
 * a guarda deixa passar para quem digita o endereço.
 *
 * A permissão é `setor × tela × ação`, em lógica POSITIVA: linha presente e
 * marcada CONCEDE; a ausência nega. Duas regras de resolução:
 *
 *  1. administrador pode tudo, por desvio no código. Não é linha de matriz de
 *     propósito — linha se desmarca por engano, e aí ninguém mais administra;
 *  2. quem tem vários setores fica com a UNIÃO (OR) do que cada um concede.
 *     Fosse interseção, acumular setores TIRARIA acesso — o contrário do que
 *     acumular papéis significa para quem trabalha.
 *
 * Tela fora do catálogo ({@see CatalogoFuncionalidades}) não é controlável: não
 * entra na matriz e as guardas a deixam passar. É o caso da tela inicial e da
 * área da própria conta, e é deliberado — ver o cabeçalho do menu.
 */
class PermissaoService
{
    /** As cinco ações da matriz, na ordem em que a tela as mostra. */
    public const ACOES = ['visivel', 'habilitado', 'apenas_leitura', 'incluir', 'excluir'];

    public const SETOR_ADMIN = 'administrador';

    /**
     * Memória do mapa por usuário, dentro da requisição. Uma tela desenha o menu,
     * passa por duas guardas e ainda checa botões: sem isto, a mesma consulta
     * repetiria meia dúzia de vezes por página.
     *
     * @var array<string, array<string, array<string, bool>>>
     */
    private array $memoria = [];

    /** Pode o usuário executar esta ação nesta tela? */
    public function pode(?User $usuario, string $slug, string $acao = 'visivel'): bool
    {
        return (bool) ($this->mapa($usuario)[$slug][$acao] ?? false);
    }

    /**
     * O mapa completo do usuário: tela => ação => pode.
     *
     * @return array<string, array<string, bool>>
     */
    public function mapa(?User $usuario): array
    {
        $chave = $usuario === null ? 'visitante' : (string) $usuario->getKey();

        return $this->memoria[$chave] ??= $this->calcular($usuario);
    }

    /**
     * As telas que o usuário pode abrir — é com isto que o menu é montado.
     *
     * @return list<string>
     */
    public function slugsVisiveis(?User $usuario): array
    {
        return array_keys(array_filter(
            $this->mapa($usuario),
            static fn (array $acoes): bool => $acoes['visivel'],
        ));
    }

    /**
     * Este item de menu aparece para este usuário?
     *
     * Mora aqui, e não na montagem do menu, porque é a MESMA pergunta que as
     * guardas fazem: item que aparece é item que abre.
     *
     * Há UMA regra, não duas: item que declara `slug` é decidido pela matriz;
     * item que não declara está fora do controle de acesso e aparece para quem
     * está autenticado. A lista `setores` do menu NÃO é lida aqui — ela é semente
     * da matriz, e só o seeder a lê. Se ela também filtrasse o menu, a mesma
     * decisão teria dois donos: mexer na config pareceria mudar o acesso, sem
     * mudar nada para quem já tem linha na matriz.
     *
     * Item restrito a setor SEM `slug` escaparia do controle — `ModoGerenteTest`
     * reprova essa configuração no gate.
     *
     * @param  array<string, mixed>  $item
     */
    public function podeVerItemDoMenu(?User $usuario, array $item): bool
    {
        if ($usuario === null) {
            return false;
        }

        $slug = $item['slug'] ?? null;

        return is_string($slug) && $slug !== ''
            ? $this->pode($usuario, $slug)
            : true;
    }

    /**
     * A tela está sob o Modo Gerente? Estático porque a resposta depende só do
     * catálogo — e a derivação de rota da guarda de ação precisa dela sem ter um
     * serviço em mão.
     */
    public static function ehControlavel(string $slug): bool
    {
        return CatalogoFuncionalidades::contem($slug);
    }

    /**
     * O quanto a guarda pode barrar NESTAS telas — `off`, `log` ou `block`.
     *
     * É o modo configurado, com uma exceção: tela listada em
     * `retaguarda.permissao_sempre` é barrada de verdade sempre. Mora aqui, e não
     * em cada guarda, para as duas (leitura e ação) não discordarem sobre quando
     * o bloqueio vale — leitura barrada e escrita liberada seria o pior dos dois
     * mundos.
     */
    public static function modoPara(string ...$telas): string
    {
        $sempre = (array) config('retaguarda.permissao_sempre', []);

        foreach ($telas as $tela) {
            if (in_array($tela, $sempre, true)) {
                return 'block';
            }
        }

        return (string) config('retaguarda.permissao_enforce', 'log');
    }

    /**
     * Normaliza uma linha da matriz antes de gravar. Duas regras de negócio:
     * "visível" é pré-requisito de todo o resto (sem ele, nada vale), e "apenas
     * leitura" derruba incluir e excluir — para não existir linha que diz, ao
     * mesmo tempo, que o setor só olha e que ele pode apagar.
     *
     * @param  array<string, mixed>  $linha
     * @return array<string, bool>
     */
    public static function normalizar(array $linha): array
    {
        if (! (bool) ($linha['visivel'] ?? false)) {
            return array_fill_keys(self::ACOES, false);
        }

        $apenasLeitura = (bool) ($linha['apenas_leitura'] ?? false);

        return [
            'visivel' => true,
            'habilitado' => (bool) ($linha['habilitado'] ?? false),
            'apenas_leitura' => $apenasLeitura,
            'incluir' => ! $apenasLeitura && (bool) ($linha['incluir'] ?? false),
            'excluir' => ! $apenasLeitura && (bool) ($linha['excluir'] ?? false),
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function calcular(?User $usuario): array
    {
        $telas = CatalogoFuncionalidades::slugs();

        if ($usuario !== null && $usuario->ehAdmin()) {
            return array_fill_keys($telas, [
                'visivel' => true,
                'habilitado' => true,
                'apenas_leitura' => false,
                'incluir' => true,
                'excluir' => true,
            ]);
        }

        $mapa = array_fill_keys($telas, array_fill_keys(self::ACOES, false));

        $setores = $usuario === null ? [] : $usuario->setores->pluck('slug')->all();

        if ($setores === []) {
            return $mapa;
        }

        foreach (PermissaoSetor::query()->whereIn('setor', $setores)->get() as $linha) {
            if (! isset($mapa[$linha->slug])) {
                continue; // linha de tela que saiu do menu — ignorada, não apagada
            }

            foreach (self::ACOES as $acao) {
                $mapa[$linha->slug][$acao] = $mapa[$linha->slug][$acao] || (bool) $linha->{$acao};
            }
        }

        // "Apenas leitura" só vale se NENHUM dos setores tiver concedido escrita:
        // a união deu poder de gravar, então a tela não é de leitura para ele.
        foreach ($mapa as $slug => $acoes) {
            if ($acoes['incluir'] || $acoes['excluir']) {
                $mapa[$slug]['apenas_leitura'] = false;
            }
        }

        return $mapa;
    }
}
