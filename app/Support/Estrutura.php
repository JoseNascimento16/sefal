<?php

namespace App\Support;

use App\Models\Area;
use App\Models\AreaBairro;
use App\Models\Equipe;
use Illuminate\Support\Collection;

/**
 * A estrutura de trabalho — áreas, bairros, equipes, fiscais — lida do BANCO.
 *
 * Substitui `App\Support\Prototipo\EstruturaFicticia`, que lia de
 * `config/prototipo_estrutura.php` e guardava alteração na sessão. **A forma do
 * que sai daqui é a mesma**, de propósito: as telas continuam recebendo os
 * mesmos props, e a consolidação não vira pretexto para redesenhar o que o dono
 * já aprovou. O que mudou é de onde vem — e que agora sobrevive a um logout.
 *
 * ## Por que uma classe, e não consulta espalhada pelos controllers
 *
 * Porque três perguntas têm de dar a MESMA resposta em todo lugar: qual equipe o
 * bairro sugere, quem é o Chefe de Setor de cada área e por quais áreas esta
 * matrícula responde. Espalhadas, elas divergiriam no primeiro ajuste — e a
 * divergência seria invisível: a Caixa de Entrada encaminharia para uma área e a
 * tela de Denúncias mostraria outra.
 *
 * ## O cache é de REQUISIÇÃO, e isso não é detalhe
 *
 * As listas são lidas uma vez por requisição e reusadas — `mapaDeSugestoes()`
 * pergunta por 157 bairros, e sem memória seriam 157 consultas.
 *
 * ⚠️ A memória vive no CONTÊINER, e não numa propriedade estática. Estática
 * sobreviveria à requisição: num processo de fila, ou na suíte de testes (onde
 * a aplicação é recriada mas a classe não), a segunda requisição leria a
 * estrutura da primeira. Custou três testes vermelhos para eu ver: o Chefe de
 * Setor era recusado com "sua conta não está vinculada a nenhuma área" porque a
 * memória ainda guardava as áreas do teste anterior.
 *
 * Também não vai a cache persistente: quem acabou de criar uma área precisa
 * vê-la na mesma tela, e cache com invalidação esquecida é a forma mais cara de
 * descobrir que alguém mudou a estrutura.
 */
class Estrutura
{
    /** A chave da memória dentro do contêiner da requisição. */
    private const MEMORIA = 'sefal.estrutura.areas';

    /** Esquece o que foi lido — obrigatório depois de qualquer escrita. */
    public static function esquecer(): void
    {
        app()->forgetInstance(self::MEMORIA);
    }

    /** @return Collection<int, Area> */
    private static function carregadas(): Collection
    {
        if (! app()->bound(self::MEMORIA)) {
            app()->instance(self::MEMORIA, Area::with([
                'bairros',
                'chefeDeSetor',
                'equipes.fiscais',
            ])->orderBy('nome')->get());
        }

        /** @var Collection<int, Area> */
        return app(self::MEMORIA);
    }

    /**
     * As áreas com tudo que a tela de Áreas e Equipes mostra.
     *
     * @return list<array<string, mixed>>
     */
    public static function areas(): array
    {
        $compartilhados = self::bairrosCompartilhados();

        return self::carregadas()->map(function (Area $area) use ($compartilhados): array {
            $bairros = $area->bairros
                ->pluck('bairro')
                ->sortBy(fn (string $b): string => Area::chaveDeBairro($b), SORT_NATURAL)
                ->values()
                ->all();

            $equipe = $area->equipes->first();

            return [
                'id' => $area->id,
                'nome' => $area->nome,
                'regiao' => (string) ($area->regiao ?? ''),
                // A tela mostra UMA equipe por área (é assim que o cliente
                // trabalha hoje). O banco já aceita várias — quando isso mudar, é
                // a tela que ganha a lista, não o modelo que muda.
                'equipe' => (string) ($equipe?->codigo ?? ''),
                'encarregado' => (string) ($equipe?->encarregado ?? ''),
                'chefe_de_setor' => $area->chefeDeSetor === null ? null : [
                    'nome' => $area->chefeDeSetor->name,
                    'matricula' => $area->chefeDeSetor->login,
                ],
                'recorte' => $area->recorte,
                'turno' => (string) ($area->turno ?? ''),
                'fiscais' => $equipe === null ? [] : $equipe->fiscais->map(static fn ($f): array => [
                    'matricula' => $f->login,
                    'nome' => $f->name,
                ])->values()->all(),
                'bairros' => $bairros,
                'total_bairros' => count($bairros),
                'total_fiscais' => $equipe?->fiscais->count() ?? 0,
                /*
                 * Os bairros desta área que também pertencem a outra. É aviso
                 * informativo, nunca pendência: a divisa passa dentro deles, e as
                 * duas áreas estão certas.
                 */
                'bairros_compartilhados' => array_values(array_filter(
                    $bairros,
                    static fn (string $b): bool => in_array(Area::chaveDeBairro($b), $compartilhados, true),
                )),
            ];
        })->values()->all();
    }

    /**
     * As equipes numa lista rasa — o que os formulários oferecem como destino.
     *
     * @return list<array{equipe: string, area: string, regiao: string, encarregado: string, recorte: string, turno: string}>
     */
    public static function equipes(): array
    {
        $equipes = [];

        foreach (self::carregadas() as $area) {
            foreach ($area->equipes as $equipe) {
                $equipes[] = [
                    'equipe' => $equipe->codigo,
                    'area' => $area->nome,
                    'regiao' => (string) ($area->regiao ?? ''),
                    'encarregado' => (string) ($equipe->encarregado ?? ''),
                    'recorte' => $area->recorte,
                    'turno' => (string) ($equipe->turno ?? $area->turno ?? ''),
                ];
            }
        }

        return $equipes;
    }

    /** @return array<string, mixed>|null */
    public static function equipeDoCodigo(?string $codigo): ?array
    {
        if ($codigo === null || trim($codigo) === '') {
            return null;
        }

        foreach (self::equipes() as $equipe) {
            if ($equipe['equipe'] === $codigo) {
                return $equipe;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function codigosDeEquipe(): array
    {
        return array_column(self::equipes(), 'equipe');
    }

    /** @return list<string> */
    public static function nomesDeArea(): array
    {
        return self::carregadas()->pluck('nome')->unique()->values()->all();
    }

    /**
     * `área => Chefe de Setor`.
     *
     * Não confundir com o `encarregado`, que chefia a equipe em campo. O Chefe de
     * Setor é quem recebe a demanda encaminhada, decide equipe ou operação e lê o
     * que a equipe concluiu. "Encaminhei para a Área 5" só diz metade — a outra
     * metade é para QUEM.
     *
     * @return array<string, array{nome: string, matricula: string|null}>
     */
    public static function chefiasPorArea(): array
    {
        $mapa = [];

        foreach (self::carregadas() as $area) {
            $mapa[$area->nome] = [
                'nome' => (string) ($area->chefeDeSetor?->name ?? ''),
                // Área sem conta de chefe tem nome vazio e matrícula nula: é
                // estado legítimo, não dado faltando.
                'matricula' => $area->chefeDeSetor?->login,
            ];
        }

        return $mapa;
    }

    /**
     * As áreas de que esta matrícula é a chefia — vazio quando não responde por
     * nenhuma.
     *
     * Devolve LISTA porque na vida real uma pessoa responde por mais de uma área
     * (férias, acumulação, área recém-criada).
     *
     * @return list<string>
     */
    public static function areasDoChefe(?string $matricula): array
    {
        if ($matricula === null || trim($matricula) === '') {
            return [];
        }

        $procurada = mb_strtolower(trim($matricula));
        $areas = [];

        foreach (self::carregadas() as $area) {
            if ($area->chefeDeSetor !== null && mb_strtolower($area->chefeDeSetor->login) === $procurada) {
                $areas[] = $area->nome;
            }
        }

        return $areas;
    }

    /**
     * A equipe SUGERIDA para um bairro — e as alternativas, quando o bairro
     * pertence a mais de uma área.
     *
     * A sugestão nunca decide sozinha: quem confirma é o coordenador. Um bairro
     * de divisa tem duas respostas igualmente certas, e escolher uma em silêncio
     * esconderia a decisão de quem tem de tomá-la.
     *
     * @return array{equipe: string, area: string, regiao: string, encarregado: string, alternativas: list<array<string, string>>}|null
     */
    public static function sugerirPorBairro(?string $bairro): ?array
    {
        $chave = Area::chaveDeBairro($bairro);

        if ($chave === '') {
            return null;
        }

        $casadas = [];

        foreach (self::carregadas() as $area) {
            foreach ($area->bairros as $vinculo) {
                if (Area::chaveDeBairro($vinculo->bairro) !== $chave) {
                    continue;
                }

                $equipe = $area->equipes->first();

                $casadas[] = [
                    'equipe' => (string) ($equipe?->codigo ?? ''),
                    'area' => $area->nome,
                    'regiao' => (string) ($area->regiao ?? ''),
                    'encarregado' => (string) ($equipe?->encarregado ?? ''),
                ];

                break;
            }
        }

        if ($casadas === []) {
            return null;
        }

        return [
            ...$casadas[0],
            'alternativas' => array_values(array_slice($casadas, 1)),
        ];
    }

    /**
     * Todos os bairros cadastrados, sem repetição e em ordem.
     *
     * @return list<string>
     */
    public static function bairros(): array
    {
        $vistos = [];
        $bairros = [];

        foreach (self::carregadas() as $area) {
            foreach ($area->bairros as $vinculo) {
                $chave = Area::chaveDeBairro($vinculo->bairro);

                if (! isset($vistos[$chave])) {
                    $vistos[$chave] = true;
                    $bairros[] = $vinculo->bairro;
                }
            }
        }

        usort($bairros, static fn (string $a, string $b): int => Area::chaveDeBairro($a) <=> Area::chaveDeBairro($b));

        return $bairros;
    }

    /**
     * `bairro => sugestão`, pronto para a tela responder sem ida ao servidor a
     * cada tecla digitada.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function mapaDeSugestoes(): array
    {
        $mapa = [];

        foreach (self::bairros() as $bairro) {
            $sugestao = self::sugerirPorBairro($bairro);

            if ($sugestao !== null) {
                $mapa[$bairro] = $sugestao;
            }
        }

        return $mapa;
    }

    /**
     * As chaves dos bairros que pertencem a mais de uma área.
     *
     * @return list<string>
     */
    private static function bairrosCompartilhados(): array
    {
        $contagem = [];

        foreach (AreaBairro::all() as $vinculo) {
            $chave = Area::chaveDeBairro($vinculo->bairro);
            $contagem[$chave] = ($contagem[$chave] ?? 0) + 1;
        }

        return array_keys(array_filter($contagem, static fn (int $n): bool => $n > 1));
    }

    /** A equipe do código, como model — para quem precisa do vínculo, não do rótulo. */
    public static function equipeModel(?string $codigo): ?Equipe
    {
        return $codigo === null || trim($codigo) === ''
            ? null
            : Equipe::where('codigo', $codigo)->first();
    }
}
