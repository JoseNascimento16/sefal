<?php

namespace App\Support\Apresentacao;

use App\Models\Ambulante;
use App\Models\AreaBairro;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\User;
use App\Support\Estrutura;
use App\Support\Papel;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * A cidade que as duas telas de mapa desenham — lida do BANCO.
 *
 * Substitui `App\Support\Prototipo\MapasFicticios`, que INVENTAVA os pontos. A
 * diferença não é de origem de dado, é de natureza: lá o ponto era um desenho —
 * clicar nele não levava a lugar nenhum, e nenhuma fiscalização podia apontar
 * para ele. Aqui cada pino é um `ambulante` com trilha, que se pode abrir,
 * fiscalizar e corrigir.
 *
 * ## O mapa desenha o que existe, e o vazio é resposta
 *
 * Se nenhuma equipe registrou nada hoje, "registros de hoje" é ZERO — e a tela
 * diz isso. A tentação de preencher o painel com número plausível é exatamente o
 * que faz uma tela de operação perder a serventia: quem olha para decidir para
 * onde mandar gente precisa distinguir "não houve trabalho" de "houve e eu não
 * sei".
 *
 * Pela mesma razão, `fiscais` traz quem está com vistoria ABERTA neste momento —
 * e não uma amostra da escala. Enquanto o aplicativo do fiscal não estiver em
 * uso, essa lista é vazia, e vazia é a verdade.
 *
 * ## O que é conta da TELA, e não daqui
 *
 * "42% no Centro Histórico", o foco do dia e o ranking de regiões são agregações
 * sobre os MESMOS pontos que o mapa desenha, e a tela as faz. Número de cabeçalho
 * que sai de uma segunda consulta um dia discorda do que está desenhado ao lado
 * dele — e é sempre o número que se acredita.
 */
class MapaDaCidade
{
    /** Quantos dias o mapa de calor olha para trás. */
    private const JANELA_DE_CALOR = 180;

    /**
     * O MAPA AO VIVO: onde estão os pontos conhecidos e o que aconteceu hoje.
     *
     * @return array<string, mixed>
     */
    public static function aoVivo(?User $usuario = null): array
    {
        $recorte = self::recorteDoLider($usuario);

        return [
            'pontos' => self::pontos(),
            'registros' => self::registrosDeHoje($recorte),
            'fiscais' => self::fiscaisEmCampo($recorte),
            // O líder vê só as fiscalizações da área dele (dono, 25/09/2026) — e a
            // tela diz isso, para o mapa vazio não parecer cidade parada.
            'recorte' => $recorte === null ? null : ['equipes' => $recorte['codigos']],
            'equipes' => Estrutura::equipes(),
            'centro' => (array) config('geografia.centro'),
            // O instante que a tela está mostrando. Dito em palavras porque o
            // mapa não tem atualização contínua: é uma fotografia, e esconder
            // isso faria a chefia ler um dado de dez minutos atrás como "agora".
            'momento' => Date::now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * O MAPA DE CALOR: a incidência de fiscalização nos últimos 180 dias.
     *
     * Vão 180 e não 90 de propósito: o ranking mostra a VARIAÇÃO contra o período
     * anterior, e para comparar 90 dias com os 90 de antes é preciso ter os 180.
     * Sem isso a coluna de variação seria invenção.
     *
     * O ponto viaja como tupla (`[bairro, lat, lng, dias, noturno]`), e não como
     * objeto com o nome do bairro repetido a cada linha: o nome, a área e o
     * encarregado vêm uma vez só na lista de bairros.
     *
     * @return array<string, mixed>
     */
    public static function calor(?User $usuario = null): array
    {
        $recorte = self::recorteDoLider($usuario);

        $bairros = self::bairrosComEquipe();
        $indicePorBairro = [];

        foreach ($bairros as $i => $b) {
            $indicePorBairro[$b['bairro']] = $i;
        }

        $hoje = Date::now()->startOfDay();
        $pontos = [];

        $registros = self::recortar(Fiscalizacao::whereNotNull('latitude'), $recorte)
            ->whereNotNull('concluida_em')
            ->where('concluida_em', '>=', $hoje->copy()->subDays(self::JANELA_DE_CALOR))
            ->get(['latitude', 'longitude', 'bairro', 'concluida_em']);

        foreach ($registros as $r) {
            $indice = $indicePorBairro[(string) $r->bairro] ?? null;

            // Fiscalização em bairro que não está no mapa não vira ponto: ela
            // existe, mas não há onde desenhá-la, e plantá-la no centro da cidade
            // mentiria sobre a concentração.
            if ($indice === null) {
                continue;
            }

            $pontos[] = [
                $indice,
                (float) $r->latitude,
                (float) $r->longitude,
                (int) $r->concluida_em->startOfDay()->diffInDays($hoje),
                // Vistoria noturna: depois das 18h. É o recorte que a tela oferece.
                (int) ($r->concluida_em->hour >= 18 || $r->concluida_em->hour < 6),
            ];
        }

        return [
            'bairros' => $bairros,
            'pontos' => $pontos,
            'equipes' => Estrutura::equipes(),
            'centro' => (array) config('geografia.centro'),
            'momento' => Date::now()->format('d/m/Y H:i'),
            'janela_em_dias' => self::JANELA_DE_CALOR,
            'recorte' => $recorte === null ? null : ['equipes' => $recorte['codigos']],
        ];
    }

    // ── O que o mapa desenha ────────────────────────────────────────────────

    /**
     * Um pino por ambulante, na ÚLTIMA posição conhecida dele.
     *
     * A última, e não a primeira: o ambulante se move, e o mapa mostra onde ele
     * está hoje. Quem quiser ver a movimentação abre a trilha no prontuário.
     *
     * @return list<array<string, mixed>>
     */
    private static function pontos(): array
    {
        $porBairro = self::mapaDeBairros();
        $hoje = Date::now()->startOfDay();

        return Ambulante::with(['atividade', 'ultimaLocalizacao'])
            ->has('ultimaLocalizacao')
            ->get()
            ->map(function (Ambulante $a) use ($porBairro, $hoje): array {
                $lugar = $a->ultimaLocalizacao;
                $bairro = $porBairro[(string) $lugar->bairro] ?? null;

                return [
                    'id' => $a->codigo,
                    'ambulante_id' => $a->id,
                    'nome' => $a->nome,
                    'apelido' => (string) ($a->apelido ?? $a->nome),
                    'atividade' => (string) ($a->atividade?->nome ?? '—'),
                    'situacao' => $a->situacao === Ambulante::SITUACAO_REGULAR ? 'regular' : 'irregular',
                    'situacao_cadastro' => $a->situacao,
                    // Permissão só de quem a tem: é exatamente o que a
                    // fiscalização confere na calçada.
                    'permissao' => $a->numero_permissao,
                    'permissionario' => $a->permissionario,
                    'retorno_ha_dias' => self::retornoVencidoHaDias($a, $hoje),
                    'bairro' => (string) ($lugar->bairro ?? '—'),
                    'area' => $bairro['area'] ?? 'Sem área definida',
                    'regiao' => $bairro['regiao'] ?? '—',
                    'equipe' => $bairro['equipe'] ?? '—',
                    'encarregado' => $bairro['encarregado'] ?? '—',
                    'tambem_de' => $bairro['tambem_de'] ?? [],
                    'lat' => (float) $lugar->latitude,
                    'lng' => (float) $lugar->longitude,
                    'ultima_em' => $lugar->registrada_em->format('d/m/Y'),
                    'fonte' => $lugar->fonte,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Há quantos dias este ambulante tem um prazo VENCIDO sem ninguém voltar.
     *
     * É o único pino que grita na tela, e com razão: é o caso em que a própria
     * SEFAL prometeu voltar ao ponto e não voltou. Nulo quando não há promessa
     * vencida — a maioria.
     */
    private static function retornoVencidoHaDias(Ambulante $ambulante, CarbonInterface $hoje): ?int
    {
        $documento = $ambulante->fiscalizacoes()
            ->with('documento')
            ->get()
            ->map(static fn (Fiscalizacao $f) => $f->documento)
            ->filter(static fn ($d): bool => $d !== null && $d->prazo_ate !== null && $d->prazo_ate->lt($hoje))
            ->sortByDesc(static fn ($d) => $d->prazo_ate)
            ->first();

        return $documento === null ? null : (int) $documento->prazo_ate->diffInDays($hoje);
    }

    /**
     * As fiscalizações CONCLUÍDAS hoje — o painel "registros de hoje".
     *
     * Zero é resposta: quando nenhuma equipe registrou nada, a tela diz isso em
     * vez de mostrar um número plausível.
     *
     * @return list<array<string, mixed>>
     */
    private static function registrosDeHoje(?array $recorte = null): array
    {
        $porBairro = self::mapaDeBairros();
        $agora = Date::now();

        return self::recortar(Fiscalizacao::with(['fiscal', 'equipe.area', 'ambulante.atividade']), $recorte)
            ->whereNotNull('concluida_em')
            ->where('concluida_em', '>=', $agora->copy()->startOfDay())
            ->orderByDesc('concluida_em')
            ->get()
            ->map(function (Fiscalizacao $f) use ($porBairro, $agora): array {
                $bairro = $porBairro[(string) $f->bairro] ?? null;
                $irregular = $f->desfecho !== null
                    && in_array($f->desfecho, Fiscalizacao::DESFECHOS_COM_DOCUMENTO, true);

                return [
                    'id' => (string) $f->id,
                    'protocolo' => $f->protocolo,
                    'apelido' => (string) ($f->ambulante?->apelido ?? $f->alvo ?? 'Não identificado'),
                    'atividade' => (string) ($f->ambulante?->atividade?->nome ?? '—'),
                    'situacao' => $irregular ? 'irregular' : 'regular',
                    'ocorrencia' => (string) ($f->desfecho ?? 'Em campo'),
                    'fiscal' => (string) ($f->fiscal?->name ?? '—'),
                    'bairro' => (string) ($f->bairro ?? '—'),
                    'area' => $bairro['area'] ?? (string) ($f->equipe?->area?->nome ?? '—'),
                    'regiao' => $bairro['regiao'] ?? '—',
                    'equipe' => (string) ($f->equipe?->codigo ?? '—'),
                    'lat' => (float) $f->latitude,
                    'lng' => (float) $f->longitude,
                    'turno' => $f->concluida_em->hour >= 18 || $f->concluida_em->hour < 6 ? 'Noturno' : 'Diurno',
                    'ha_minutos' => (int) $f->concluida_em->diffInMinutes($agora),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Quem está com vistoria ABERTA neste momento.
     *
     * Não é a escala do dia: é presença real. Enquanto o aplicativo do fiscal não
     * estiver em uso, esta lista é vazia — e vazia é a verdade. Desenhar a escala
     * aqui faria o painel prometer uma cobertura que ninguém conferiu.
     *
     * @return list<array<string, mixed>>
     */
    private static function fiscaisEmCampo(?array $recorte = null): array
    {
        $porBairro = self::mapaDeBairros();

        return self::recortar(Fiscalizacao::with(['fiscal', 'equipe.area']), $recorte)
            ->where('situacao', Fiscalizacao::EM_CAMPO)
            ->whereNotNull('latitude')
            ->get()
            ->map(function (Fiscalizacao $f) use ($porBairro): array {
                $nome = (string) ($f->fiscal?->name ?? 'Fiscal');
                $partes = preg_split('/\s+/', trim($nome)) ?: [$nome];
                $bairro = $porBairro[(string) $f->bairro] ?? null;

                return [
                    'id' => (string) ($f->fiscal?->login ?? $f->id),
                    'nome' => $nome,
                    'curto' => 'fiscal '.$partes[0],
                    'matricula' => mb_strtoupper((string) ($f->fiscal?->login ?? '—')),
                    'iniciais' => mb_substr($partes[0], 0, 1).mb_substr((string) end($partes), 0, 1),
                    'bairro' => (string) ($f->bairro ?? '—'),
                    'area' => $bairro['area'] ?? (string) ($f->equipe?->area?->nome ?? '—'),
                    'regiao' => $bairro['regiao'] ?? '—',
                    'equipe' => (string) ($f->equipe?->codigo ?? '—'),
                    'lat' => (float) $f->latitude,
                    'lng' => (float) $f->longitude,
                    'desde' => $f->aberta_em->format('H:i'),
                ];
            })
            ->values()
            ->all();
    }

    // ── O recorte do líder ──────────────────────────────────────────────────

    /**
     * O que o LÍDER vê no mapa (dono, 25/09/2026): as fiscalizações das equipes
     * dele e as feitas nos bairros das áreas dessas equipes. Nulo para quem vê a
     * cidade inteira — Chefe de Setor, fiscal e administrador.
     *
     * O recorte é feito AQUI, no servidor: filtrar no navegador esconderia, mas a
     * coordenada e o relato de outras áreas teriam viajado até a tela do líder.
     *
     * @return array{equipes: list<int>, codigos: list<string>, bairros: list<string>}|null
     */
    private static function recorteDoLider(?User $usuario): ?array
    {
        if (! Papel::recorta($usuario)) {
            return null;
        }

        $codigos = Papel::equipes($usuario);
        $equipes = Equipe::whereIn('codigo', $codigos)->get(['id', 'area_id']);

        return [
            'equipes' => $equipes->pluck('id')->all(),
            'codigos' => $codigos,
            'bairros' => AreaBairro::whereIn('area_id', $equipes->pluck('area_id')->unique()->all())->pluck('bairro')->all(),
        ];
    }

    /**
     * Aplica o recorte do líder a uma consulta de fiscalizações.
     *
     * @template T of \Illuminate\Database\Eloquent\Builder
     *
     * @param  T  $consulta
     * @param  array{equipes: list<int>, codigos: list<string>, bairros: list<string>}|null  $recorte
     * @return T
     */
    private static function recortar($consulta, ?array $recorte)
    {
        if ($recorte === null) {
            return $consulta;
        }

        return $consulta->where(static function ($q) use ($recorte) {
            $q->whereIn('equipe_id', $recorte['equipes'] ?: [0])
                ->orWhereIn('bairro', $recorte['bairros'] ?: ['']);
        });
    }

    // ── Os bairros do mapa ──────────────────────────────────────────────────

    /**
     * Os bairros que têm COORDENADA, cada um com a área e a equipe que a
     * estrutura diz — e com as outras equipes que também os cobrem.
     *
     * Bairro sem coordenada fica de fora: ele existe na estrutura e aparece nas
     * listas, mas não há onde desenhá-lo. Plantá-lo no centro da cidade faria o
     * mapa afirmar uma concentração que não existe.
     *
     * @return list<array<string, mixed>>
     */
    private static function bairrosComEquipe(): array
    {
        $lista = [];
        $vistos = [];

        foreach (AreaBairro::with('area.equipes')->whereNotNull('latitude')->get() as $vinculo) {
            $nome = $vinculo->bairro;

            if (isset($vistos[$nome])) {
                // Bairro de divisa aparece uma vez no mapa; as outras áreas que o
                // cobrem entram em `tambem_de`.
                continue;
            }

            $vistos[$nome] = true;
            $sugestao = Estrutura::sugerirPorBairro($nome);

            $lista[] = [
                'bairro' => $nome,
                'lat' => (float) $vinculo->latitude,
                'lng' => (float) $vinculo->longitude,
                // Bairro sem equipe seria buraco na estrutura, e o mapa tem de
                // dizer isso em palavras em vez de fingir cobertura.
                'area' => $sugestao['area'] ?? 'Sem área definida',
                'regiao' => $sugestao['regiao'] ?? '—',
                'equipe' => $sugestao['equipe'] ?? '—',
                'encarregado' => $sugestao['encarregado'] ?? '—',
                'tambem_de' => array_values(array_map(
                    static fn (array $a): string => $a['equipe'].' · '.$a['area'],
                    (array) ($sugestao['alternativas'] ?? []),
                )),
            ];
        }

        usort($lista, static fn (array $a, array $b): int => $a['bairro'] <=> $b['bairro']);

        return $lista;
    }

    /**
     * `bairro => dados do mapa`, para não repetir a resolução por ponto.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function mapaDeBairros(): array
    {
        $mapa = [];

        foreach (self::bairrosComEquipe() as $bairro) {
            $mapa[$bairro['bairro']] = $bairro;
        }

        return $mapa;
    }
}
