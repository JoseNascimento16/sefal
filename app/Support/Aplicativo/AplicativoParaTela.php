<?php

namespace App\Support\Aplicativo;

use App\Models\Ambulante;
use App\Models\Bairro;
use App\Models\CicloDeFiscalizacao;
use App\Models\Demanda;
use App\Models\DocumentoCampo;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\Levantamento;
use App\Models\Operacao;
use App\Models\User;
use App\Support\Apresentacao\MapaDaCidade;
use App\Support\Prototipo\RecomendacoesDoFiscal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * O servidor no formato que as telas do aplicativo leem.
 *
 * As telas nasceram no protótipo, lendo dados fictícios (`resources/js/pwa/
 * dados-*.ts`). Em vez de reescrevê-las, o servidor entrega exatamente aquelas
 * formas — os tipos `Identidade`, `Demanda` e `Registro` de lá são o contrato.
 * Datas saem já em BR, como as telas esperam.
 */
final class AplicativoParaTela
{
    /** Desfecho → como o LOCAL fica: a leitura curta do mapa ("regular" / "irregular"). */
    private const LOCAL_APOS_O_DESFECHO = [
        Fiscalizacao::REGULARIZADO_NO_LOCAL => 'regular',
        Fiscalizacao::NADA_ENCONTRADO => 'regular',
        Fiscalizacao::NOTIFICACAO_EMITIDA => 'irregular',
        Fiscalizacao::REGULARIZADO_APOS_NOTIFICACAO => 'regular',
        Fiscalizacao::SITUACAO_MANTIDA => 'irregular',
        Fiscalizacao::AUTO_LAVRADO => 'irregular',
    ];

    /**
     * Quem entrou e a equipe dele.
     *
     * @return array<string, mixed>
     */
    public static function sessao(CampoDoFiscal $campo): array
    {
        $user = $campo->user;
        $equipes = $campo->equipes();
        $equipe = $equipes->first();
        $lidera = $equipe !== null && $equipe->lider_id === $user->id;
        $area = $equipe?->area;

        return [
            'fiscal' => [
                'nome' => $user->name,
                'matricula' => $user->login,
                'setor' => 'SEFAL · Fiscalização em Logradouro Público',
                'papel' => $lidera ? 'Encarregado' : 'Fiscal',
                'turno' => $equipe?->turno ?? '',
                'iniciais' => self::iniciais($user->name),
                'desde' => $user->created_at?->format('Y') ?? '',
            ],
            'equipe' => $equipe === null ? null : [
                'codigo' => $equipe->codigo,
                'nome' => 'Equipe '.$equipe->codigo,
                'area' => $area?->nome ?? '',
                'areaNome' => $area?->regiao ?? '',
                'encarregado' => $equipe->nomeDoLider(),
                'recorte' => in_array($area?->recorte, ['bairros', 'corredores', 'cidade'], true) ? $area->recorte : 'bairros',
                'turno' => $equipe->turno ?? '',
                'bairros' => $area?->bairros()->orderBy('bairro')->pluck('bairro')->all() ?? [],
                // O desenho que a Retaguarda guarda (tela Áreas): contorno ou corredores.
                ...($area?->geometria() ?? ['contorno' => null, 'corredores' => null]),
            ],
            'equipes' => $equipes->pluck('codigo')->all(),
        ];
    }

    /** Quantos dias de incidência o mapa de calor do aplicativo recebe (a maior janela da tela). */
    public const DIAS_DE_CALOR = 90;

    /** Quantas idas ao ponto o histórico do pino mostra. */
    private const HISTORICO_DO_PINO = 5;

    /**
     * Os ambulantes com posição conhecida — os pinos do mapa, na ÚLTIMA posição
     * de cada um, com as últimas idas da fiscalização ao ponto.
     *
     * @return list<array<string, mixed>>
     */
    public static function ambulantes(): array
    {
        $hoje = Date::today();

        return Ambulante::query()
            ->with(['atividade', 'ultimaLocalizacao.fiscalizacao'])
            ->has('ultimaLocalizacao')
            ->get()
            ->map(function (Ambulante $a) use ($hoje): array {
                $lugar = $a->ultimaLocalizacao;
                $idas = $a->fiscalizacoes()->whereNotNull('despachada_em')->latest('aberta_em')->limit(self::HISTORICO_DO_PINO)->get();

                return [
                    'id' => 'amb-'.$a->id,
                    'servidorId' => $a->id,
                    'nome' => $a->nome,
                    'apelido' => (string) ($a->apelido ?? $a->nome),
                    'atividade' => (string) ($a->atividade?->nome ?? 'Atividade não informada'),
                    // O emoji é escolha do aplicativo, pelo nome da atividade.
                    'emoji' => null,
                    'situacao' => $a->situacao === Ambulante::SITUACAO_REGULAR ? 'regular' : 'irregular',
                    'permissao' => $a->numero_permissao,
                    'regiao' => (string) ($lugar->bairro ?? ''),
                    'endereco' => (string) ($lugar->fiscalizacao?->logradouro ?? $lugar->bairro ?? 'Endereço não registrado'),
                    'lat' => (float) $lugar->latitude,
                    'lng' => (float) $lugar->longitude,
                    'ultimaEm' => self::dataBr($lugar->registrada_em),
                    'retornoHaDias' => MapaDaCidade::retornoVencidoHaDias($a, $hoje),
                    'historico' => $idas->map(fn (Fiscalizacao $f) => [
                        'data' => self::dataBr($f->aberta_em),
                        'resumo' => (string) $f->desfecho,
                        'status' => self::LOCAL_APOS_O_DESFECHO[$f->desfecho] ?? 'regular',
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * As vistorias dos últimos {@see DIAS_DE_CALOR} dias com GPS em que havia
     * IRREGULARIDADE no ponto — a matéria do mapa de calor ("onde a rua está
     * quente"). Só "Nada encontrado no local" fica de fora: "Regularizado no
     * local" teve ambulante irregular, resolvido na hora. Cada uma pesa igual.
     *
     * @return list<array{lat: float, lng: float, peso: float, haDias: int, regiao: string}>
     */
    public static function incidencias(): array
    {
        $hoje = Date::today();

        return Fiscalizacao::query()
            ->whereNotNull('latitude')
            ->whereNotNull('concluida_em')
            ->where('desfecho', '!=', Fiscalizacao::NADA_ENCONTRADO)
            ->where('concluida_em', '>=', $hoje->copy()->subDays(self::DIAS_DE_CALOR))
            ->get(['latitude', 'longitude', 'bairro', 'concluida_em'])
            ->map(fn (Fiscalizacao $f) => [
                'lat' => (float) $f->latitude,
                'lng' => (float) $f->longitude,
                'peso' => 1.0,
                // A tela recorta por "até N dias": a de hoje conta como 1.
                'haDias' => (int) $f->concluida_em->copy()->startOfDay()->diffInDays($hoje) + 1,
                'regiao' => (string) $f->bairro,
            ])
            ->values()
            ->all();
    }

    /**
     * As operações abertas que chegam a esta pessoa (as das equipes dela e as em
     * que ela foi posta à parte), na ordem em que começam.
     *
     * @return list<array<string, mixed>>
     */
    public static function operacoes(CampoDoFiscal $campo): array
    {
        $hoje = Date::today();

        return Operacao::query()
            ->with(['bairros', 'tipo', 'coordenador'])
            ->whereIn('id', $campo->operacoes())
            ->orderBy('inicio')
            ->get()
            ->map(function (Operacao $o) use ($hoje): array {
                $inicio = $o->inicio->copy()->startOfDay();
                $bairros = $o->bairros->pluck('bairro')->all();
                $emCurso = $inicio->lte($hoje) && ($o->fim === null || $o->fim->copy()->startOfDay()->gte($hoje));

                return [
                    'id' => 'op-'.$o->id,
                    'nome' => $o->nome,
                    'local' => (string) ($o->foco ?: (implode(', ', array_slice($bairros, 0, 3)) ?: ($o->regiao ?? ''))),
                    'regiao' => (string) ($bairros[0] ?? $o->regiao ?? ''),
                    'quando' => match (true) {
                        $emCurso => 'Em andamento',
                        $inicio->equalTo($hoje->copy()->addDay()) => 'Amanhã, '.self::dataBr($inicio),
                        default => self::dataBr($inicio),
                    },
                    'horario' => $o->fim !== null ? 'até '.self::dataBr($o->fim) : 'sem data de fim',
                    'fiscais' => count($o->fiscaisQueRecebem()),
                    'tom' => $emCurso ? 'agora' : ($inicio->equalTo($hoje->copy()->addDay()) ? 'proxima' : 'planejada'),
                    'observacao' => (string) ($o->observacao ?? ''),
                    // Para a tela da operação no aplicativo: o que é, quem abriu
                    // e todos os bairros que ela cobre (o cartão mostra só três).
                    'tipo' => $o->tipo?->nome,
                    'abertaPor' => $o->coordenador?->name,
                    'bairros' => $bairros,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * As equipes ativas da cidade, para o cabeçalho do levantamento (duas
     * equipes podem levantar a mesma rua) — com o encarregado de cada uma.
     *
     * @return list<array{equipe: string, regiao: string, encarregado: string}>
     */
    public static function equipesDaCidade(): array
    {
        return Equipe::query()->ativas()->with(['area', 'lider'])->orderBy('codigo')->get()
            ->map(fn (Equipe $e) => [
                'equipe' => $e->codigo,
                'regiao' => (string) ($e->area?->nome ?? ''),
                'encarregado' => $e->nomeDoLider(),
            ])
            ->values()
            ->all();
    }

    /**
     * Os levantamentos das equipes de quem entrou nos últimos 30 dias, os mais
     * novos primeiro.
     *
     * @return list<array<string, mixed>>
     */
    public static function levantamentos(CampoDoFiscal $campo): array
    {
        $codigos = $campo->equipes()->pluck('codigo')->all();

        return Levantamento::query()
            ->withCount('linhas')
            ->where('concluido_em', '>=', Date::now()->subDays(CampoDoFiscal::DIAS_DE_HISTORICO))
            ->latest('concluido_em')
            ->get()
            ->filter(fn (Levantamento $l) => $l->user_id === $campo->user->id || array_intersect($l->equipes, $codigos) !== [])
            ->map(self::levantamento(...))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function levantamento(Levantamento $levantamento): array
    {
        return [
            'id' => 'lev-'.$levantamento->id,
            'clientId' => $levantamento->client_id,
            'protocolo' => $levantamento->protocolo,
            'rua' => $levantamento->rua,
            'bairro' => $levantamento->bairro,
            'equipes' => $levantamento->equipes,
            'encarregados' => $levantamento->encarregados,
            'dataBr' => self::dataBr($levantamento->concluido_em),
            'linhas' => (int) ($levantamento->linhas_count ?? $levantamento->linhas()->count()),
            'situacao' => 'concluido',
        ];
    }

    /**
     * O catálogo de bairros com coordenada — o que o aplicativo usa para dizer
     * em que bairro o fiscal está.
     *
     * @return list<array{id: string, nome: string, lat: float, lng: float}>
     */
    public static function bairros(): array
    {
        return Bairro::query()
            ->where('ativo', true)
            ->whereNotNull('latitude')
            ->orderBy('nome')
            ->get()
            ->map(fn (Bairro $b) => ['id' => $b->nome, 'nome' => $b->nome, 'lat' => (float) $b->latitude, 'lng' => (float) $b->longitude])
            ->all();
    }

    /** @return array<string, mixed> */
    public static function demanda(Demanda $demanda): array
    {
        $hoje = Date::today();
        // Duas vistorias no mesmo segundo: a mais nova é a de id maior.
        $ultima = $demanda->fiscalizacoes->sortBy([['aberta_em', 'desc'], ['id', 'desc']])->first();
        // Esperando o retorno, o prazo que conta é o da Notificação, não o do atendimento.
        $prazo = in_array($demanda->situacao, Demanda::ESPERANDO_RETORNO, true)
            ? ($ultima?->documento?->prazo_ate ?? $demanda->prazo_em)
            : $demanda->prazo_em;

        return [
            'id' => 'den-'.$demanda->id,
            'servidorId' => $demanda->id,
            'cicloId' => null,
            'protocolo' => $demanda->protocolo,
            'documentoOrigem' => $demanda->numero_origem ?? '—',
            'origem' => $demanda->canal,
            'assunto' => $demanda->assunto,
            'detalhe' => (string) $demanda->relato,
            'endereco' => trim(implode(', ', array_filter([$demanda->logradouro, $demanda->numero]))) ?: 'Endereço não informado',
            'referencia' => $demanda->referencia,
            'bairro' => (string) $demanda->bairro,
            'regiao' => (string) $demanda->bairro,
            'lat' => $demanda->latitude !== null ? (float) $demanda->latitude : null,
            'lng' => $demanda->longitude !== null ? (float) $demanda->longitude : null,
            'recebidaBr' => self::dataBr($demanda->recebida_em),
            'prazoBr' => self::dataBr($prazo),
            'prazoDias' => $prazo !== null ? (int) $hoje->diffInDays($prazo->copy()->startOfDay(), false) : 0,
            'situacao' => $demanda->situacao,
            'area' => $demanda->area?->nome,
            'equipe' => $demanda->equipe?->codigo,
            'operacao' => $demanda->operacao?->nome,
            // A ligação pela CHAVE, e não pelo nome: duas operações podem ter o
            // mesmo nome, e é por aqui que a tela da operação acha as denúncias dela.
            'operacaoId' => $demanda->operacao_id !== null ? 'op-'.$demanda->operacao_id : null,
            'desfecho' => $ultima?->desfecho,
            // Já foi despachada e espera o líder: não é mais ato do fiscal. A
            // Notificação Preliminar não espera o líder — espera o retorno.
            'aguardaLider' => $demanda->fiscalizacoes->contains(fn (Fiscalizacao $f) => $f->situacao === Fiscalizacao::AGUARDANDO_LEITURA),
            'campo' => $ultima === null || $ultima->despachada_em === null ? null : [
                'quandoBr' => self::dataBr($ultima->despachada_em).' às '.$ultima->despachada_em->format('H:i'),
                'encontrado' => (string) $ultima->desfecho,
                'ambulante' => $ultima->alvo,
                'equipamento' => $ultima->equipamento,
                'relato' => (string) ($ultima->relato ?? $ultima->consideracoes),
                'fotos' => $ultima->fotos->count(),
                'gps' => $ultima->latitude !== null ? number_format($ultima->latitude, 5, '.', '').', '.number_format((float) $ultima->longitude, 5, '.', '') : null,
                'precisaoM' => (int) ($ultima->precisao_m ?? 0),
            ],
            'documento' => $ultima?->documento === null ? null : self::documento($ultima->documento),
            'voltaDoLider' => self::voltaDoLider($ultima),
            'anonima' => (bool) $demanda->anonima,
            'requerente' => $demanda->anonima ? null : $demanda->requerente,
            // As denúncias que relatam o mesmo fato e vão junto com esta: o que a
            // equipe registrar aqui responde por todas. Sem quem as abriu — para ir
            // a campo basta saber que existem e de onde vieram.
            'agregadas' => $demanda->agregadas->sortBy('recebida_em')->map(fn (Demanda $a) => [
                'protocolo' => $a->protocolo,
                'origem' => $a->canal,
                'documentoOrigem' => $a->numero_origem ?? '—',
                'assunto' => $a->assunto,
                'recebidaBr' => self::dataBr($a->recebida_em),
            ])->values()->all(),
            'sgci' => null,
        ];
    }

    /**
     * A Fiscalização SEM demanda na fila do aplicativo — na MESMA forma da
     * demanda, para a fila, o detalhe e o registro a lerem sem caminho à parte.
     * O id leva o prefixo `fsc-`: é por ele que o registro vai com `ciclo_id`.
     *
     * @return array<string, mixed>
     */
    public static function fiscalizacaoNaFila(CicloDeFiscalizacao $ciclo): array
    {
        $ultima = $ciclo->vistorias->last();
        $notificacao = $ciclo->notificacaoEmPrazo();
        $prazo = $notificacao?->documento?->prazo_ate;
        $hoje = Date::today();

        return [
            'id' => 'fsc-'.$ciclo->id,
            'servidorId' => null,
            'cicloId' => $ciclo->id,
            'protocolo' => $ciclo->protocolo,
            'documentoOrigem' => '—',
            'origem' => 'ronda',
            'assunto' => $notificacao !== null ? 'Retorno da Notificação Preliminar' : 'Nova vistoria determinada pelo líder',
            // Na volta determinada, o que procurar é a justificativa do líder.
            'detalhe' => (string) ($notificacao !== null ? ($ultima?->relato ?? '') : ($ultima?->decisao_detalhe ?? $ultima?->relato ?? '')),
            'endereco' => trim((string) ($ultima?->logradouro ?? '')) ?: 'Endereço não informado',
            'referencia' => null,
            'bairro' => (string) ($ultima?->bairro ?? ''),
            'regiao' => (string) ($ultima?->bairro ?? ''),
            'lat' => $ultima?->latitude,
            'lng' => $ultima?->longitude,
            'recebidaBr' => self::dataBr($ciclo->aberto_em),
            'prazoBr' => self::dataBr($prazo),
            'prazoDias' => $prazo !== null ? (int) $hoje->diffInDays($prazo->copy()->startOfDay(), false) : 0,
            'situacao' => match (true) {
                $notificacao?->situacao === Fiscalizacao::PRAZO_VENCIDO => Demanda::RETORNO_VENCIDO,
                $notificacao !== null => Demanda::AGUARDANDO_REGULARIZACAO,
                default => Demanda::DIRECIONADA_AOS_FISCAIS,
            },
            'area' => $ciclo->equipe?->area?->nome,
            'equipe' => $ciclo->equipe?->codigo,
            'operacao' => null,
            'operacaoId' => null,
            'desfecho' => $ultima?->desfecho,
            'aguardaLider' => false,
            'campo' => $ultima === null || $ultima->despachada_em === null ? null : [
                'quandoBr' => self::dataBr($ultima->despachada_em).' às '.$ultima->despachada_em->format('H:i'),
                'encontrado' => (string) $ultima->desfecho,
                'ambulante' => $ultima->alvo,
                'equipamento' => $ultima->equipamento,
                'relato' => (string) ($ultima->relato ?? $ultima->consideracoes),
                'fotos' => $ultima->fotos->count(),
                'gps' => $ultima->latitude !== null ? number_format($ultima->latitude, 5, '.', '').', '.number_format((float) $ultima->longitude, 5, '.', '') : null,
                'precisaoM' => (int) ($ultima->precisao_m ?? 0),
            ],
            'documento' => $notificacao?->documento === null ? null : self::documento($notificacao->documento),
            // Na ronda, a justificativa do líder já é o `detalhe` (acima).
            'voltaDoLider' => null,
            'anonima' => false,
            'requerente' => null,
            'agregadas' => [],
            'sgci' => null,
        ];
    }

    /**
     * O que o líder escreveu ao mandar a equipe voltar ao ponto — enquanto a
     * nova vistoria não foi feita (feita, ela vira a última e o pedido sai).
     *
     * @return array{justificativa: string, quandoBr: string}|null
     */
    private static function voltaDoLider(?Fiscalizacao $ultima): ?array
    {
        if ($ultima?->situacao !== Fiscalizacao::NOVA_VISTORIA) {
            return null;
        }

        return [
            'justificativa' => (string) $ultima->decisao_detalhe,
            'quandoBr' => self::dataBr($ultima->decidida_em),
        ];
    }

    /**
     * A redação dos impressos de campo (Notificação Preliminar e Auto de
     * Apreensão), do único dono dela: `config/prototipo_documentos_campo.php`.
     * O aplicativo não guarda cópia — o que o fiscal assinala e o que a
     * Retaguarda lê saem do mesmo texto. Listas na ordem do papel.
     *
     * @return array<string, mixed>
     */
    public static function impressos(): array
    {
        $c = (array) config('prototipo_documentos_campo', []);
        $fundamentacao = (array) ($c['fundamentacao'] ?? []);
        $lista = static fn (string $chave): array => (array) ($c[$chave] ?? []);

        return [
            'motivosNp' => collect($lista('motivos_np'))->map(fn (array $m, string $id) => [
                'id' => $id,
                'texto' => (string) $m['texto'],
                ...(isset($m['complemento']) ? ['complemento' => (string) $m['complemento']] : []),
            ])->values()->all(),
            'sancoesNp' => collect($lista('sancoes_np'))->map(fn (string $rotulo, string $id) => ['id' => $id, 'rotulo' => $rotulo])->values()->all(),
            'prazosNp' => collect($lista('prazos_np'))->map(fn (array $p, string $id) => [
                'id' => $id,
                'rotulo' => (string) $p['rotulo'],
                'dias' => (int) $p['dias'],
                ...(isset($p['nota']) ? ['nota' => (string) $p['nota']] : []),
            ])->values()->all(),
            'fundamentacao' => [
                'lei' => (string) ($fundamentacao['lei'] ?? ''),
                'decretos' => array_values((array) ($fundamentacao['decretos'] ?? [])),
                'artigosPadrao' => (string) ($fundamentacao['artigos_padrao'] ?? ''),
                'portariaPadrao' => (string) ($fundamentacao['portaria_padrao'] ?? ''),
            ],
            'segub' => (array) ($c['segub'] ?? []),
            'prazosGuarda' => collect($lista('prazos_guarda'))->map(fn (array $p, string|int $id) => [
                'id' => (string) $id,
                'rotulo' => (string) $p['rotulo'],
                'extenso' => (string) $p['extenso'],
            ])->values()->all(),
            'destinacoesGuarda' => collect($lista('destinacoes_guarda'))->map(fn (string $texto, string $id) => ['id' => $id, 'texto' => $texto])->values()->all(),
        ];
    }

    /**
     * O catálogo de recomendações do fiscal na redação CURTA (a pílula do
     * aparelho), com a marca de quais o aplicativo oferece hoje. Vai inteiro
     * porque o aparelho também lê registros antigos, gravados com atalhos que
     * deixaram de ser oferecidos.
     *
     * @return list<array{id: string, rotulo: string, oferecida: bool}>
     */
    public static function recomendacoes(): array
    {
        $oferecidas = RecomendacoesDoFiscal::oferecidas();

        return collect(RecomendacoesDoFiscal::catalogo())
            ->map(fn (array $r, string $id) => ['id' => $id, 'rotulo' => $r['curto'], 'oferecida' => in_array($id, $oferecidas, true)])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function documento(DocumentoCampo $documento): array
    {
        $dados = (array) ($documento->dados ?? []);
        $prazos = (array) config('prototipo_documentos_campo.prazos_np', []);
        $vence = $documento->prazo_ate;

        return [
            'tipo' => $documento->tipo,
            'numero' => $documento->numero,
            'rotulo' => $documento->rotulo(),
            'lavradoBr' => self::dataBr($documento->emitido_em),
            'prazoRotulo' => $documento->prazo_chave !== null ? ($prazos[$documento->prazo_chave]['rotulo'] ?? $documento->prazo_chave) : null,
            'venceBr' => $vence !== null ? self::dataBr($vence) : null,
            'venceEmDias' => $vence !== null ? (int) Date::today()->diffInDays($vence->copy()->startOfDay(), false) : null,
            'notificado' => (string) $documento->notificado,
            'inscricao' => $dados['inscricao'] ?? null,
            'atividade' => $dados['atividade'] ?? null,
            'equipamento' => $dados['equipamento'] ?? null,
            'motivos' => array_values((array) ($documento->motivos ?? [])),
            'assinaturas' => array_values((array) ($dados['assinaturas'] ?? [])),
        ];
    }

    /**
     * Uma vistoria como o aplicativo a lista em "Meus registros".
     *
     * @return array<string, mixed>
     */
    public static function registro(Fiscalizacao $vistoria): array
    {
        $documento = $vistoria->documento;
        $via = $documento !== null ? (($documento->dados ?? [])['via'] ?? null) : null;

        return [
            'id' => 'srv-'.$vistoria->id,
            'clientId' => $vistoria->client_id,
            'protocolo' => $vistoria->protocolo,
            'dataBr' => self::dataBr($vistoria->aberta_em),
            'hora' => $vistoria->aberta_em->format('H:i'),
            'regiao' => (string) $vistoria->bairro,
            'endereco' => (string) ($vistoria->logradouro ?? ''),
            'lat' => $vistoria->latitude !== null ? (float) $vistoria->latitude : null,
            'lng' => $vistoria->longitude !== null ? (float) $vistoria->longitude : null,
            'semGps' => $vistoria->sem_gps_motivo,
            'desfecho' => $vistoria->desfecho,
            'status' => self::LOCAL_APOS_O_DESFECHO[$vistoria->desfecho] ?? 'regular',
            'ocorrencias' => array_values((array) ($vistoria->ocorrencias ?? [])),
            'relato' => (string) $vistoria->relato,
            'fotos' => $vistoria->fotos->count(),
            'ambulante' => $vistoria->alvo,
            'retornoBr' => $vistoria->retorno_em !== null ? self::dataBr($vistoria->retorno_em) : null,
            'envio' => 'enviado',
            'documento' => $documento?->rotulo(),
            'documentoTipo' => $documento?->tipo,
            'via' => is_array($via) ? $via : null,
            'origem' => $vistoria->demanda_id !== null ? 'dirigida' : 'avulsa',
            'demandaId' => $vistoria->demanda_id !== null ? 'den-'.$vistoria->demanda_id : null,
            'operacaoId' => $vistoria->operacao_id,
            'operacaoNome' => $vistoria->operacao?->nome,
            'referencia' => $vistoria->demanda?->protocolo,
            'consideracoes' => (string) $vistoria->consideracoes,
            'recomendacoes' => $vistoria->recomendacoes->pluck('chave')->values()->all(),
            'despachadoBr' => $vistoria->despachada_em !== null ? self::dataBr($vistoria->despachada_em) : null,
        ];
    }

    private static function dataBr(?CarbonInterface $data): string
    {
        return $data?->format('d/m/Y') ?? '';
    }

    private static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $primeira = $partes[0] ?? '';
        $ultima = count($partes) > 1 ? $partes[count($partes) - 1] : '';

        return mb_strtoupper(mb_substr($primeira, 0, 1).mb_substr($ultima, 0, 1));
    }

    /** Para quem pergunta pela conta sem ter equipe — usado na recusa da porta. */
    public static function semEquipe(User $user): string
    {
        return "{$user->name}, sua conta não está em nenhuma equipe de fiscalização. Peça ao administrador para incluí-la numa equipe.";
    }
}
