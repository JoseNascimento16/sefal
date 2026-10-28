<?php

namespace Database\Seeders;

use App\Console\Commands\PrepararDemonstracao;
use App\Models\AreaBairro;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\User;
use App\Support\Aplicativo\CampoDoFiscal;
use App\Support\Aplicativo\RegistroDeCampo;
use App\Support\Aplicativo\ReservaDeNumeros;
use App\Support\DecisaoDaChefia;
use App\Support\PrazosDaNotificacao;
use App\Support\TriagemDeDemandas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * Exemplos do RETORNO DA NOTIFICAÇÃO PRELIMINAR na demonstração (dono, 28/10/2026).
 *
 * Quatro casos na equipe do `lider1` ({@see PrepararDemonstracao::EQUIPE_DO_LIDER}),
 * feitos pelos caminhos de verdade — triagem do chefe, envio do líder e registro
 * do aplicativo pelo `fiscal1`:
 *
 *  - denúncia com Notificação em prazo ("Aguardando regularização");
 *  - denúncia com o prazo vencido sem retorno ("Retorno vencido", na mesa do líder);
 *  - ronda com Notificação em prazo (volta à fila do aplicativo);
 *  - ronda que o líder mandou refazer (volta à fila do aplicativo).
 *
 * Roda no preparo da demonstração, também numa base que já existe — é por isso
 * que o Render ganha os exemplos sem trocar o banco. Idempotente: a marca é a
 * orientação do líder no trâmite.
 */
class RetornoDaNotificacaoSeeder extends Seeder
{
    /** A orientação do líder nesses envios — e a marca de que o semeador já rodou. */
    public const ORIENTACAO = 'Conferir a ocupação e, se mantida, lavrar a Notificação Preliminar.';

    public const VOLTA_DO_LIDER = 'Voltar ao ponto no fim da tarde: o ambulante costuma montar depois das 16h.';

    public function run(): void
    {
        if (DemandaTramite::where('detalhe', self::ORIENTACAO)->exists()) {
            return;
        }

        $equipe = Equipe::with('area')->where('codigo', PrepararDemonstracao::EQUIPE_DO_LIDER)->first();
        $fiscal = $equipe === null ? null
            : ($equipe->fiscais()->where('login', 'fiscal1')->first() ?? $equipe->fiscais()->first());
        $lider = $equipe?->lider ?? User::where('login', 'lider1')->first();
        $chefe = User::whereHas('setores', static fn ($q) => $q->where('slug', 'chefe-de-setor'))->first()
            ?? User::where('admin', true)->first();

        if ($equipe === null || $fiscal === null || $lider === null || $chefe === null) {
            return;
        }

        $campo = new CampoDoFiscal($fiscal);
        $registro = new RegistroDeCampo($campo);

        $livres = Demanda::where('situacao', Demanda::RECEBIDA)
            ->whereNull('agrupada_em_id')
            ->whereDoesntHave('ciclos')
            ->orderBy('recebida_em')
            ->limit(2)
            ->get();

        foreach ($livres->values() as $i => $demanda) {
            (new TriagemDeDemandas($chefe))->encaminharAoLider([$demanda->id => $equipe->codigo]);
            (new TriagemDeDemandas($lider))->direcionarAosFiscais([$demanda->id], self::ORIENTACAO);

            $vencida = $i === 1;
            $vistoria = $registro->gravar($this->envio($fiscal, $demanda->fresh(), [
                'demanda_id' => $demanda->id,
                'relato' => $vencida
                    ? 'Carrinho fixo na calçada, bloqueando a passagem. Notificado a retirar o equipamento em 48 horas.'
                    : 'Barraca ocupando a faixa livre da calçada. Notificado a recuar a montagem em 5 dias.',
            ], $vencida ? '48h' : '5d'), []);

            if ($vencida) {
                $this->recuar($vistoria, 4);
            }
        }

        // A ronda: o fiscal anda a rua e lavra a notificação sem demanda nenhuma.
        $registro->gravar($this->envio($fiscal, null, [
            'relato' => 'Ronda: banca de frutas sobre o canteiro. Notificado a retirar em 72 horas.',
            'ambulante' => 'Vendedor de frutas, identificado no local',
        ], '72h'), []);

        // E a ronda que o líder manda refazer.
        $vazia = $registro->gravar($this->envio($fiscal, null, [
            'desfecho' => Fiscalizacao::NADA_ENCONTRADO,
            'relato' => 'Ronda: ponto vazio no horário da passagem. Vizinhos dizem que o ambulante monta à tarde.',
        ]), []);
        (new DecisaoDaChefia($lider))->pedirNovaVistoria([$vazia->id], self::VOLTA_DO_LIDER);

        PrazosDaNotificacao::vencer();
    }

    /**
     * O registro como o aplicativo o despacharia. Com `$prazo`, leva a
     * Notificação Preliminar, com número reservado ao fiscal.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function envio(User $fiscal, ?Demanda $demanda, array $extra, ?string $prazo = null): array
    {
        $lugar = $this->lugar($demanda);
        $dados = [
            'client_id' => 'demo-retorno-'.bin2hex(random_bytes(6)),
            'desfecho' => $prazo === null ? Fiscalizacao::REGULARIZADO_NO_LOCAL : Fiscalizacao::NOTIFICACAO_EMITIDA,
            'endereco' => $lugar['endereco'],
            'bairro' => $lugar['bairro'],
            ...($lugar['latitude'] !== null
                ? ['latitude' => $lugar['latitude'], 'longitude' => $lugar['longitude'], 'precisao_m' => 9]
                : ['sem_gps_motivo' => array_key_first((array) config('prototipo_denuncias.motivos_sem_gps', []))]),
            ...$extra,
        ];

        if ($prazo !== null) {
            $dados['documento'] = [
                'tipo' => 'np',
                'numero' => (new ReservaDeNumeros)->repor($fiscal)['np'][0],
                'notificado' => (string) ($extra['ambulante'] ?? 'Ocupante do ponto'),
                'prazo' => $prazo,
                'motivos' => [],
                'dados' => ['assinaturas' => []],
            ];
        }

        return $dados;
    }

    /** @return array{endereco: string, bairro: string, latitude: float|null, longitude: float|null} */
    private function lugar(?Demanda $demanda): array
    {
        if ($demanda !== null) {
            return [
                'endereco' => trim(implode(', ', array_filter([$demanda->logradouro, $demanda->numero]))) ?: 'Endereço da denúncia',
                'bairro' => (string) $demanda->bairro,
                'latitude' => $demanda->latitude !== null ? (float) $demanda->latitude : null,
                'longitude' => $demanda->longitude !== null ? (float) $demanda->longitude : null,
            ];
        }

        $bairro = AreaBairro::where('area_id', Equipe::where('codigo', PrepararDemonstracao::EQUIPE_DO_LIDER)->value('area_id'))
            ->whereNotNull('latitude')->first();

        return [
            'endereco' => 'Avenida principal do bairro, em frente ao ponto de ônibus',
            'bairro' => (string) ($bairro?->bairro ?? ''),
            'latitude' => $bairro?->latitude !== null ? (float) $bairro->latitude : null,
            'longitude' => $bairro?->longitude !== null ? (float) $bairro->longitude : null,
        ];
    }

    /** Leva a notificação para `$dias` atrás — é o que faz o prazo dela vencer. */
    private function recuar(Fiscalizacao $vistoria, int $dias): void
    {
        $passado = Date::now()->subDays($dias);

        $vistoria->forceFill([
            'aberta_em' => $passado->copy()->subHour(),
            'concluida_em' => $passado,
            'despachada_em' => $passado,
            'sincronizada_em' => $passado,
            'gps_em' => $vistoria->gps_em !== null ? $passado->copy()->subHour() : null,
        ])->save();

        $documento = $vistoria->documento;
        $documento?->forceFill([
            'emitido_em' => $passado,
            'prazo_ate' => $documento->prazo_ate?->copy()->subDays($dias),
        ])->save();

        DemandaTramite::where('fiscalizacao_id', $vistoria->id)->update(['ocorrida_em' => $passado]);
    }
}
