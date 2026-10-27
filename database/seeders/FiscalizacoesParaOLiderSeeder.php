<?php

namespace Database\Seeders;

use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\FiscalizacaoFoto;
use App\Models\FiscalizacaoRecomendacao;
use App\Models\User;
use App\Support\CiclosDeFiscalizacao;
use App\Support\Estrutura;
use App\Support\TriagemDeDemandas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * Fiscalizações para o LÍDER trabalhar na demonstração (pedido do dono, 24/09/2026).
 *
 * Duas levas, tiradas das demandas RECEBIDAS que ainda estão na mesa do chefe:
 *
 *  - VINDAS DO CHEFE: o chefe encaminhou à equipe; a Fiscalização espera o líder
 *    enviá-la aos fiscais (posse: Líder de Equipe, "Aguardando envio à equipe");
 *  - CONCLUÍDAS PELA EQUIPE: o líder já tinha enviado, os fiscais foram ao ponto
 *    e despacharam o retorno — com relato, fotos e recomendação —, que espera o
 *    líder decidir: mandar voltar ou encaminhar ao chefe.
 *
 * NÃO roda no boot nem no `DatabaseSeeder`: é para montar o snapshot da
 * demonstração (`php artisan db:seed --class=FiscalizacoesParaOLiderSeeder`).
 * Idempotente: se a observação dele já está no trâmite, não faz nada.
 */
class FiscalizacoesParaOLiderSeeder extends Seeder
{
    /** Quantas de cada leva. */
    public const VINDAS_DO_CHEFE = 4;

    public const CONCLUIDAS = 5;

    /** A observação do chefe nesses encaminhamentos — e a marca de que o semeador já rodou. */
    public const OBSERVACAO = 'Reincidência no ponto; priorizar na próxima saída da equipe.';

    /**
     * O que a equipe encontrou, por vistoria — desfechos que não exigem papel
     * lavrado, para a demonstração não depender de documento de campo.
     *
     * @var list<array{desfecho: string, relato: string, alvo: string|null, recomendacao: string, fotos: list<string>}>
     */
    private const RETORNOS = [
        [
            'desfecho' => Fiscalizacao::REGULARIZADO_NO_LOCAL,
            'relato' => 'Mesas e cadeiras recolhidas na presença da equipe; o responsável foi orientado sobre a faixa livre da calçada.',
            'alvo' => 'Responsável pelo estabelecimento, presente',
            'recomendacao' => 'passagem',
            'fotos' => ['antes-calcada-ocupada.jpg', 'depois-calcada-livre.jpg'],
        ],
        [
            'desfecho' => Fiscalizacao::NADA_ENCONTRADO,
            'relato' => 'Equipe esteve no ponto no horário indicado e não encontrou ocupação irregular. Vizinhos confirmam que o ponto não monta há dias.',
            'alvo' => null,
            'recomendacao' => 'nada',
            'fotos' => ['ponto-vazio.jpg'],
        ],
        [
            'desfecho' => Fiscalizacao::SITUACAO_MANTIDA,
            'relato' => 'A barraca continua no mesmo lugar e o ocupante se recusou a remover. Sugerida nova ida com apoio da operação da área.',
            'alvo' => 'Ocupante não identificado, recusou-se a informar o nome',
            'recomendacao' => 'retorno',
            'fotos' => ['barraca-fixa.jpg', 'ocupante-recusa.jpg'],
        ],
        [
            'desfecho' => Fiscalizacao::REGULARIZADO_NO_LOCAL,
            'relato' => 'Carrinho deslocado para o ponto autorizado; o permissionário apresentou a licença e foi orientado a não ocupar o ponto de ônibus.',
            'alvo' => 'Permissionário com licença, presente',
            'recomendacao' => 'sgci',
            'fotos' => ['carrinho-realocado.jpg'],
        ],
        [
            'desfecho' => Fiscalizacao::NADA_ENCONTRADO,
            'relato' => 'Local vistoriado duas vezes no mesmo turno; nenhum comércio no ponto relatado.',
            'alvo' => null,
            'recomendacao' => 'nada',
            'fotos' => ['vistoria-noturna.jpg'],
        ],
    ];

    public function run(): void
    {
        if (DemandaTramite::where('detalhe', self::OBSERVACAO)->exists()) {
            return;
        }

        $chefe = User::whereHas('setores', static fn ($q) => $q->where('slug', 'chefe-de-setor'))->first()
            ?? User::where('admin', true)->first();

        $livres = Demanda::where('situacao', Demanda::RECEBIDA)
            ->whereNull('agrupada_em_id')
            ->whereIn('canal', [Demanda::CANAL_E_SALVADOR, Demanda::CANAL_FALA_SALVADOR, Demanda::CANAL_E_PROTOCOLO, Demanda::CANAL_AVULSA])
            ->whereDoesntHave('ciclos')
            ->orderBy('recebida_em')
            ->limit(self::VINDAS_DO_CHEFE + self::CONCLUIDAS)
            ->get();

        foreach ($livres->values() as $i => $demanda) {
            $equipe = $this->equipePara($demanda);

            if ($equipe === null) {
                continue;
            }

            // O chefe encaminha — é isso que abre a Fiscalização.
            (new TriagemDeDemandas($chefe))->encaminharAoLider([$demanda->id => $equipe->codigo], self::OBSERVACAO);

            if ($i < self::VINDAS_DO_CHEFE) {
                continue;
            }

            $this->concluirEmCampo($demanda->fresh(), $equipe, self::RETORNOS[($i - self::VINDAS_DO_CHEFE) % count(self::RETORNOS)]);
        }
    }

    private function equipePara(Demanda $demanda): ?Equipe
    {
        $sugerida = Estrutura::sugerirPorBairro($demanda->bairro)['equipe'] ?? null;

        return Estrutura::equipeModel(is_string($sugerida) ? $sugerida : null)
            ?? Equipe::orderBy('id')->first();
    }

    /**
     * O líder envia aos fiscais, a equipe vai ao ponto e despacha o retorno.
     *
     * @param  array{desfecho: string, relato: string, alvo: string|null, recomendacao: string, fotos: list<string>}  $retorno
     */
    private function concluirEmCampo(Demanda $demanda, Equipe $equipe, array $retorno): void
    {
        $lider = $equipe->lider ?? User::where('admin', true)->first();
        $fiscal = $equipe->fiscais()->first() ?? $lider;

        (new TriagemDeDemandas($lider))->direcionarAosFiscais([$demanda->id], 'Ir no horário em que o ponto monta.');

        $quando = Date::now()->subHours(3);
        $demanda->refresh();

        $vistoria = Fiscalizacao::create([
            'protocolo' => $demanda->protocolo.'-V1',
            'origem' => Fiscalizacao::ORIGEM_DEMANDA,
            'demanda_id' => $demanda->id,
            'equipe_id' => $equipe->id,
            'fiscal_id' => $fiscal->id,
            'alvo' => $retorno['alvo'],
            'logradouro' => $demanda->logradouro,
            'bairro' => $demanda->bairro,
            'latitude' => $demanda->latitude,
            'longitude' => $demanda->longitude,
            'precisao_m' => 8,
            'aberta_em' => $quando->copy()->subHour(),
            'concluida_em' => $quando,
            'despachada_em' => $quando,
            'sincronizada_em' => $quando,
            'desfecho' => $retorno['desfecho'],
            'consideracoes' => $retorno['relato'],
            // Voltou da rua e espera o LÍDER: mandar voltar ou encaminhar ao chefe.
            'situacao' => Fiscalizacao::AGUARDANDO_LEITURA,
        ]);

        FiscalizacaoRecomendacao::firstOrCreate(['fiscalizacao_id' => $vistoria->id, 'chave' => $retorno['recomendacao']]);

        foreach ($retorno['fotos'] as $arquivo) {
            FiscalizacaoFoto::firstOrCreate(
                ['fiscalizacao_id' => $vistoria->id, 'caminho' => 'fiscalizacoes/'.$vistoria->id.'/'.$arquivo],
                ['legenda' => $arquivo, 'capturada_em' => $quando],
            );
        }

        CiclosDeFiscalizacao::vincular($vistoria);

        $demanda->registrar(
            acao: 'Vistoria em campo — retorno despachado',
            situacao: Demanda::EM_CAMPO,
            papel: DemandaTramite::PAPEL_FISCAL,
            autor: $fiscal,
            detalhe: $retorno['relato'],
            campos: ['Registro de campo' => $vistoria->protocolo, 'Desfecho' => $retorno['desfecho']],
        );
    }
}
