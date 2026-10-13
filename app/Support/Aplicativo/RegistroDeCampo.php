<?php

namespace App\Support\Aplicativo;

use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\DocumentoCampo;
use App\Models\Fiscalizacao;
use App\Models\FiscalizacaoFoto;
use App\Models\FiscalizacaoRecomendacao;
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

        $documento = $dados['documento'] ?? null;
        $exige = Fiscalizacao::DOCUMENTO_DO_DESFECHO[$dados['desfecho']] ?? null;

        if ($exige !== null && ($documento === null || $documento['tipo'] !== $exige)) {
            $this->recusar('documento', "O desfecho “{$dados['desfecho']}” exige ".($exige === DocumentoCampo::NOTIFICACAO ? 'a Notificação Preliminar' : 'o Auto de Apreensão').' lavrado no registro.');
        }

        $equipe = $demanda?->equipe ?? $this->campo->equipePrincipal();
        $agora = Date::now();
        // O aparelho manda a hora em UTC (ISO com "Z"); o banco guarda no fuso do
        // sistema (Salvador). Sem converter, o registro das 12h aparecia às 15h.
        $aberta = isset($dados['aberta_em'])
            ? Date::parse($dados['aberta_em'])->setTimezone(config('app.timezone'))
            : $agora;

        return DB::transaction(function () use ($dados, $fotos, $user, $demanda, $documento, $equipe, $agora, $aberta) {
            $vistoria = Fiscalizacao::create([
                'protocolo' => Protocolo::proximo('FIS', $agora, Fiscalizacao::class),
                'client_id' => $dados['client_id'],
                'origem' => $demanda === null
                    ? Fiscalizacao::ORIGEM_AVULSA
                    : ($demanda->operacao_id !== null ? Fiscalizacao::ORIGEM_OPERACAO : Fiscalizacao::ORIGEM_DEMANDA),
                'demanda_id' => $demanda?->id,
                'operacao_id' => $demanda?->operacao_id,
                'equipe_id' => $equipe?->id,
                'fiscal_id' => $user->id,
                'alvo' => $dados['ambulante'] ?? null,
                'logradouro' => $dados['endereco'] ?? $demanda?->logradouro,
                'bairro' => $dados['bairro'] ?? $demanda?->bairro,
                'latitude' => $dados['latitude'] ?? null,
                'longitude' => $dados['longitude'] ?? null,
                'precisao_m' => isset($dados['precisao_m']) ? (int) round((float) $dados['precisao_m']) : null,
                'gps_em' => isset($dados['latitude']) ? $aberta : null,
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
                'situacao' => Fiscalizacao::AGUARDANDO_LEITURA,
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

            CiclosDeFiscalizacao::vincular($vistoria);

            if ($demanda !== null) {
                $passo = $demanda->registrar(
                    acao: 'Vistoria em campo — retorno despachado',
                    situacao: Demanda::EM_CAMPO,
                    papel: DemandaTramite::PAPEL_FISCAL,
                    autor: $user,
                    detalhe: $vistoria->relato,
                    campos: array_filter([
                        'Registro de campo' => $vistoria->protocolo,
                        'Desfecho' => $vistoria->desfecho,
                        'Documento lavrado' => $vistoria->documento?->rotulo(),
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
