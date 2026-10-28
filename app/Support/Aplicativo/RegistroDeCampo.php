<?php

namespace App\Support\Aplicativo;

use App\Models\CicloDeFiscalizacao;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\DocumentoCampo;
use App\Models\Fiscalizacao;
use App\Models\FiscalizacaoFoto;
use App\Models\FiscalizacaoRecomendacao;
use App\Models\LocalizacaoAmbulante;
use App\Models\Operacao;
use App\Support\CiclosDeFiscalizacao;
use App\Support\Protocolo;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Grava o que o fiscal despachou do aplicativo — o ÚNICO caminho de produção
 * que cria vistoria.
 *
 * Faz o que a demonstração encenava: a vistoria nasce despachada, esperando a
 * leitura do LÍDER; entra na Fiscalização (ciclo) da demanda, ou abre uma,
 * quando o registro é avulso; e a demanda ganha o passo "retorno despachado",
 * ligado à vistoria.
 *
 * É idempotente pelo `client_id`: a fila do aparelho reenvia quando a resposta
 * se perde no caminho, e o segundo envio devolve a vistoria do primeiro.
 */
final class RegistroDeCampo
{
    public const DISCO = 'local';

    public function __construct(private readonly CampoDoFiscal $campo) {}

    /**
     * @param  array<string, mixed>  $dados  validado pelo controller
     * @param  list<UploadedFile>  $fotos
     */
    public function gravar(array $dados, array $fotos): Fiscalizacao
    {
        $existente = Fiscalizacao::where('client_id', $dados['client_id'])->first();

        if ($existente !== null) {
            return $existente;
        }

        $user = $this->campo->user;
        $demanda = isset($dados['demanda_id']) ? Demanda::find($dados['demanda_id']) : null;

        if (isset($dados['demanda_id']) && $demanda === null) {
            $this->recusar('demanda_id', 'A demanda deste registro não existe mais no sistema. Registre de novo como avulso.');
        }

        if ($demanda !== null && ! $this->campo->podeVistoriar($demanda)) {
            $this->recusar('demanda_id', "A demanda {$demanda->protocolo} não está mais com a sua equipe (situação: {$demanda->situacao}). Fale com o líder da equipe.");
        }

        // A Fiscalização SEM demanda que voltou à fila do aplicativo: o retorno
        // da notificação lavrada numa ronda, ou a volta que o líder determinou.
        $ciclo = null;

        if ($demanda === null && isset($dados['ciclo_id'])) {
            $ciclo = CicloDeFiscalizacao::with('vistorias')->find($dados['ciclo_id']);
            $equipes = $this->campo->equipes()->pluck('id')->all();

            if ($ciclo === null || ! $ciclo->naFilaDoAplicativo() || ! in_array($ciclo->equipe_id, $equipes, true)) {
                $this->recusar('ciclo_id', 'Esta Fiscalização não está mais na fila da sua equipe. Fale com o líder da equipe, ou registre de novo como avulso.');
            }
        }

        if (! in_array($dados['desfecho'], Fiscalizacao::desfechosDoAto($demanda, $ciclo), true)) {
            $retorno = $demanda !== null
                ? in_array($demanda->situacao, Demanda::ESPERANDO_RETORNO, true)
                : $ciclo?->notificacaoEmPrazo() !== null;

            $this->recusar('desfecho', $retorno
                ? 'Há Notificação Preliminar esperando o retorno: o registro é o retorno. Escolha “Regularizado após notificação” ou “Retorno com a situação mantida”.'
                : "“{$dados['desfecho']}” é desfecho de retorno: só cabe onde há Notificação Preliminar esperando o retorno. Escolha o desfecho da vistoria.");
        }

        // A avulsa feita na tela de uma operação fica ligada a ela — desde que a
        // operação esteja aberta para a equipe (ou para o próprio fiscal).
        $operacao = null;

        if ($demanda === null && $ciclo === null && isset($dados['operacao_id'])) {
            if (! in_array((int) $dados['operacao_id'], $this->campo->operacoes(), true)) {
                $this->recusar('operacao_id', 'A operação deste registro não está aberta para a sua equipe. Registre de novo como avulso.');
            }

            $operacao = Operacao::find($dados['operacao_id']);
        }

        $documento = $dados['documento'] ?? null;
        $exige = Fiscalizacao::DOCUMENTO_DO_DESFECHO[$dados['desfecho']] ?? null;

        if ($exige !== null && ($documento === null || $documento['tipo'] !== $exige)) {
            $this->recusar('documento', "O desfecho “{$dados['desfecho']}” exige ".($exige === DocumentoCampo::NOTIFICACAO ? 'a Notificação Preliminar' : 'o Auto de Apreensão').' lavrado no registro.');
        }

        $equipe = $demanda?->equipe ?? $ciclo?->equipe ?? $this->campo->equipePrincipal();
        $agora = Date::now();
        // O aparelho manda a hora em UTC (ISO com "Z"); o banco guarda no fuso do
        // sistema (Salvador). Sem converter, o registro das 12h aparecia às 15h.
        $aberta = isset($dados['aberta_em'])
            ? Date::parse($dados['aberta_em'])->setTimezone(config('app.timezone'))
            : $agora;

        return DB::transaction(function () use ($dados, $fotos, $user, $demanda, $ciclo, $operacao, $documento, $equipe, $agora, $aberta) {
            $vistoria = Fiscalizacao::create([
                'protocolo' => Protocolo::proximo('FIS', $agora, Fiscalizacao::class),
                'client_id' => $dados['client_id'],
                'origem' => $demanda === null
                    ? ($ciclo?->origem ?? ($operacao !== null ? Fiscalizacao::ORIGEM_OPERACAO : Fiscalizacao::ORIGEM_AVULSA))
                    : ($demanda->operacao_id !== null ? Fiscalizacao::ORIGEM_OPERACAO : Fiscalizacao::ORIGEM_DEMANDA),
                'demanda_id' => $demanda?->id,
                'ciclo_id' => $ciclo?->id,
                'operacao_id' => $demanda?->operacao_id ?? $operacao?->id,
                'equipe_id' => $equipe?->id,
                'fiscal_id' => $user->id,
                'alvo' => $dados['ambulante'] ?? null,
                'ambulante_id' => $dados['ambulante_id'] ?? null,
                'logradouro' => $dados['endereco'] ?? $demanda?->logradouro,
                'bairro' => $dados['bairro'] ?? $demanda?->bairro,
                'latitude' => $dados['latitude'] ?? null,
                'longitude' => $dados['longitude'] ?? null,
                'precisao_m' => isset($dados['precisao_m']) ? (int) round((float) $dados['precisao_m']) : null,
                'gps_em' => isset($dados['latitude']) ? $aberta : null,
                'sem_gps_motivo' => isset($dados['latitude']) ? null : ($dados['sem_gps_motivo'] ?? null),
                'aberta_em' => $aberta,
                'concluida_em' => $agora,
                'despachada_em' => $agora,
                'sincronizada_em' => $agora,
                'desfecho' => $dados['desfecho'],
                'relato' => $dados['relato'] ?? null,
                'ocorrencias' => array_values($dados['ocorrencias'] ?? []),
                'retorno_em' => $dados['retorno_em'] ?? null,
                'consideracoes' => $dados['consideracoes'] ?? null,
                // Voltou da rua e espera o LÍDER: mandar voltar ou encaminhar ao chefe.
                // A Notificação Preliminar não: o caso fica com o notificado, e é o
                // RETORNO que volta ao líder (dono, 28/10/2026).
                'situacao' => $dados['desfecho'] === Fiscalizacao::NOTIFICACAO_EMITIDA
                    ? Fiscalizacao::PRAZO_CORRENDO
                    : Fiscalizacao::AGUARDANDO_LEITURA,
            ]);

            foreach (array_unique($dados['recomendacoes'] ?? []) as $chave) {
                FiscalizacaoRecomendacao::create(['fiscalizacao_id' => $vistoria->id, 'chave' => $chave]);
            }

            if ($documento !== null) {
                $this->gravarDocumento($vistoria, $documento, $agora);
            }

            foreach ($fotos as $i => $foto) {
                $caminho = $foto->storeAs(
                    'fiscalizacoes/'.$vistoria->id,
                    Str::uuid().'.'.strtolower($foto->getClientOriginalExtension() ?: 'jpg'),
                    self::DISCO,
                );

                FiscalizacaoFoto::create([
                    'fiscalizacao_id' => $vistoria->id,
                    'caminho' => $caminho,
                    'legenda' => 'Foto '.($i + 1),
                    'latitude' => $vistoria->latitude,
                    'longitude' => $vistoria->longitude,
                    'capturada_em' => $aberta,
                ]);
            }

            $ciclo = CiclosDeFiscalizacao::vincular($vistoria);

            // Feito o retorno, a notificação deixa de esperar por ele.
            if (in_array($vistoria->desfecho, Fiscalizacao::DESFECHOS_DE_RETORNO, true)) {
                Fiscalizacao::where('ciclo_id', $ciclo->id)
                    ->where('id', '!=', $vistoria->id)
                    ->whereIn('situacao', Fiscalizacao::NOTIFICACAO_EM_PRAZO)
                    ->update(['situacao' => Fiscalizacao::RETORNO_REGISTRADO, 'updated_at' => $agora]);
            }

            // O ambulante identificado ganha a posição desta ida ao ponto: é o
            // que move o pino dele no mapa (a última posição conhecida).
            if ($vistoria->ambulante_id !== null && $vistoria->latitude !== null) {
                LocalizacaoAmbulante::create([
                    'ambulante_id' => $vistoria->ambulante_id,
                    'latitude' => $vistoria->latitude,
                    'longitude' => $vistoria->longitude,
                    'precisao_m' => $vistoria->precisao_m,
                    'fonte' => LocalizacaoAmbulante::FONTE_FISCALIZACAO,
                    'fiscalizacao_id' => $vistoria->id,
                    'bairro' => $vistoria->bairro,
                    'registrada_em' => $aberta,
                ]);
            }

            if ($demanda !== null) {
                $notificou = $vistoria->situacao === Fiscalizacao::PRAZO_CORRENDO;
                $passo = $demanda->registrar(
                    acao: match (true) {
                        $notificou => 'Notificação Preliminar lavrada — aguardando a regularização no prazo',
                        in_array($vistoria->desfecho, Fiscalizacao::DESFECHOS_DE_RETORNO, true) => 'Retorno ao ponto — despachado ao líder',
                        default => 'Vistoria em campo — retorno despachado',
                    },
                    situacao: $notificou ? Demanda::AGUARDANDO_REGULARIZACAO : Demanda::EM_CAMPO,
                    papel: DemandaTramite::PAPEL_FISCAL,
                    autor: $user,
                    detalhe: $vistoria->relato,
                    campos: array_filter([
                        'Registro de campo' => $vistoria->protocolo,
                        'Desfecho' => $vistoria->desfecho,
                        'Documento lavrado' => $vistoria->documento?->rotulo(),
                        'Prazo para regularizar' => $notificou ? $vistoria->documento?->prazo_ate?->format('d/m/Y') : null,
                    ]),
                );

                $passo->forceFill(['fiscalizacao_id' => $vistoria->id])->save();
            }

            return $vistoria->fresh(['documento', 'fotos', 'recomendacoes', 'demanda']) ?? $vistoria;
        });
    }

    /** @param  array<string, mixed>  $documento */
    private function gravarDocumento(Fiscalizacao $vistoria, array $documento, CarbonInterface $agora): void
    {
        $tipo = (string) $documento['tipo'];
        $numero = (string) $documento['numero'];

        $recusa = (new ReservaDeNumeros)->usar($this->campo->user, $tipo, $numero, $vistoria->id);

        if ($recusa !== null) {
            $this->recusar('documento', $recusa);
        }

        $prazo = (string) ($documento['prazo'] ?? '');
        $dias = (int) (config('prototipo_documentos_campo.prazos_np')[$prazo]['dias'] ?? 0);

        DocumentoCampo::create([
            'fiscalizacao_id' => $vistoria->id,
            'tipo' => $tipo,
            'numero' => $numero,
            'notificado' => $documento['notificado'] ?? null,
            'documento_notificado' => $documento['documento_notificado'] ?? null,
            'prazo_chave' => $prazo === '' ? null : $prazo,
            'prazo_ate' => $prazo === '' ? null : $agora->copy()->addDays($dias)->startOfDay(),
            'motivos' => array_values($documento['motivos'] ?? []),
            'sancoes' => array_values($documento['sancoes'] ?? []),
            'itens' => array_values($documento['itens'] ?? []),
            'guarda_prazo' => $documento['guarda_prazo'] ?? null,
            'guarda_destinacao' => $documento['guarda_destinacao'] ?? null,
            'dados' => $documento['dados'] ?? [],
            'emitido_em' => $agora,
        ]);
    }

    private function recusar(string $campo, string $motivo): never
    {
        throw ValidationException::withMessages([$campo => [$motivo]]);
    }
}
