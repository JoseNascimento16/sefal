<?php

namespace App\Support\Aplicativo;

use App\Models\Bairro;
use App\Models\Demanda;
use App\Models\DocumentoCampo;
use App\Models\Fiscalizacao;
use App\Models\User;
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
            ],
            'equipes' => $equipes->pluck('codigo')->all(),
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
        $ultima = $demanda->fiscalizacoes->sortByDesc('aberta_em')->first();

        return [
            'id' => 'den-'.$demanda->id,
            'servidorId' => $demanda->id,
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
            'prazoBr' => self::dataBr($demanda->prazo_em),
            'prazoDias' => $demanda->prazo_em !== null ? (int) $hoje->diffInDays($demanda->prazo_em->copy()->startOfDay(), false) : 0,
            'situacao' => $demanda->situacao,
            'area' => $demanda->area?->nome,
            'equipe' => $demanda->equipe?->codigo,
            'operacao' => $demanda->operacao?->nome,
            'desfecho' => $ultima?->desfecho,
            // Já foi despachada e espera o líder: não é mais ato do fiscal.
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
            'anonima' => (bool) $demanda->anonima,
            'requerente' => $demanda->anonima ? null : $demanda->requerente,
            'sgci' => null,
        ];
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
            'lat' => (float) ($vistoria->latitude ?? 0),
            'lng' => (float) ($vistoria->longitude ?? 0),
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
