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
     * Memória do mapa por usuário. Numa mesma requisição a pergunta é feita por
     * três consumidores — as duas guardas e a montagem do menu —, e sem isto a
     * consulta de permissões repetiria a cada um.
     *
     * @var array<string, array<string, array<string, bool>>>
     */
    private array $memoria = [];

    /**
     * Descarta a memória. Chamado quando a matriz muda (ver `AppServiceProvider`).
     *
     * Cache sem invalidação explícita é bomba de relógio: em qualquer contexto que
     * atenda duas coisas com o mesmo objeto — um teste que grava entre duas
     * visitas, um servidor persistente — o mapa antigo continuaria respondendo,
     * e a permissão recém-concedida pareceria não ter pegado.
     */
    public function esquecer(): void
    {
        $this->memoria = [];
    }

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
    private function mapa(?User $usuario): array
    {
        $chave = $usuario === null ? 'visitante' : (string) $usuario->getKey();

        return $this->memoria[$chave] ??= $this->calcular($usuario);
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
     * ── Por que o menu OBEDECE o modo de rollout ────────────────────────────
     *
     * Fora do modo `block`, o item CONTINUA aparecendo. Some do menu é
     * exatamente o tipo de barrada em silêncio que o rollout existe para
     * evitar: quem perdesse o item não veria recado nenhum, não geraria registro
     * nenhum e ainda assim abriria a tela digitando o endereço — o pior dos dois
     * mundos, e o oposto do que "observar antes de barrar" quer dizer. Em `log`,
     * quem visita a tela sem permissão passa e fica REGISTRADO; é esse registro
     * que se confere antes de virar a chave.
     *
     * @param  array<string, mixed>  $item
     */
    public function podeVerItemDoMenu(?User $usuario, array $item): bool
    {
        if ($usuario === null) {
            return false;
        }

        $slug = $item['slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            return true;
        }

        if (self::modoPara($slug) !== 'block') {
            return true;
        }

        return $this->pode($usuario, $slug);
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
     * A tela CONTROLÁVEL a que um caminho de requisição pertence, ou null.
     *
     * A tela sai do PRIMEIRO trecho depois de `retaguarda` — `retaguarda/permissionarios/7/foto`
     * é a tela `permissionarios` dois segmentos adiante. Mora aqui porque dois lugares fazem a
     * mesma pergunta (a guarda de leitura e a montagem dos props da tela): com uma cópia em cada
     * um, bastaria um deles mudar de ideia sobre o que é "a tela do caminho" para a tela oferecer
     * o que a guarda barra.
     *
     * Recebe o CAMINHO, e não os segmentos já partidos, para o contrato não depender de quem
     * chama ter partido a string do mesmo jeito.
     */
    public static function telaDoCaminho(string $caminho): ?string
    {
        $segmentos = array_values(array_filter(explode('/', $caminho), static fn (string $t): bool => $t !== ''));

        if (($segmentos[0] ?? null) !== 'retaguarda') {
            return null;
        }

        $slug = $segmentos[1] ?? '';

        return $slug !== '' && self::ehControlavel($slug) ? $slug : null;
    }

    /**
     * TODAS as ações do usuário numa tela — o que a própria tela precisa saber para não oferecer
     * o que o servidor recusa.
     *
     * Existe porque a alternativa é pior: sem isto, a tela desenha os botões que sabe desenhar e
     * a pessoa só descobre a recusa depois de preencher o formulário inteiro. As guardas seguem
     * sendo a fronteira — esconder botão é conforto, nunca autorização —, mas conforto que vem da
     * MESMA resposta que barra, e não de uma segunda conta feita no navegador.
     *
     * ── Por que o modo de rollout entra aqui também ──────────────────────────────
     *
     * Fora do modo `block` as guardas deixam passar, então a tela tem de oferecer. Esconder o
     * botão enquanto o servidor aceita seria a mesma divergência de sempre, só na direção
     * contrária — e em `log` ninguém veria o registro daquilo que "seria barrado", porque a ação
     * nem teria como ser tentada. É o mesmo raciocínio de {@see podeVerItemDoMenu}.
     *
     * @return array<string, bool>
     */
    public function acoes(?User $usuario, string $slug): array
    {
        if (self::modoPara($slug) !== 'block') {
            return array_fill_keys(self::ACOES, true);
        }

        return $this->mapa($usuario)[$slug] ?? array_fill_keys(self::ACOES, false);
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
     *
     *  1. "vê" é pré-requisito de todo o resto — sem ele, nada mais vale;
     *  2. "só consulta" derruba TUDO que grava: operar, incluir e excluir.
     *
     * A segunda vale para `habilitado` também, e isso importa: "só consulta"
     * convivendo com "opera" deixava o setor gravar por PUT/PATCH — a coluna
     * dizia "abre para olhar" e o servidor deixava alterar. Linha que se
     * contradiz é linha que alguém vai ler errado.
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
            'habilitado' => ! $apenasLeitura && (bool) ($linha['habilitado'] ?? false),
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

        // "Só consulta" só vale se NENHUM dos setores tiver concedido escrita: a
        // união deu poder de gravar, então a tela não é de leitura para ele.
        // Inclui `habilitado` pelo mesmo motivo da normalização — operar é
        // gravar.
        foreach ($mapa as $slug => $acoes) {
            if ($acoes['habilitado'] || $acoes['incluir'] || $acoes['excluir']) {
                $mapa[$slug]['apenas_leitura'] = false;
            }
        }

        return $mapa;
    }
}
