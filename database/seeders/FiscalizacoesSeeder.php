<?php

namespace Database\Seeders;

use App\Models\Ambulante;
use App\Models\Demanda;
use App\Models\DocumentoCampo;
use App\Models\Equipe;
use App\Models\Fiscalizacao;
use App\Models\FiscalizacaoFoto;
use App\Models\FiscalizacaoRecomendacao;
use App\Models\LocalizacaoAmbulante;
use App\Models\Operacao;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * Os registros de campo do protótipo (`config/prototipo_registros_de_campo.php`)
 * virando fiscalizações de verdade.
 *
 * São os casos que chegam à mesa do Chefe de Setor sem denúncia atrás — a
 * FISCALIZAÇÃO AVULSA e a varredura de operação. O protótipo já os separava por
 * `origem` em palavras ("Operação planejada", "Ronda da equipe", "Pedido de
 * outro órgão"); aqui a origem passa a responder outra pergunta, que é a que o
 * sistema precisa: **de onde veio a ordem** (`operacao` quando há operação,
 * `avulsa` quando não há). O texto antigo não se perde — vira o `alvo`/relato
 * que a chefia lê.
 *
 * ## O fiscal do arquivo é um ÍNDICE
 *
 * `'fiscal' => 2` significava "o segundo fiscal daquela equipe". Aqui vira
 * `fiscal_id` de verdade, resolvido contra a equipe semeada — porque a
 * fiscalização precisa de autor que sobreviva a uma troca de escala.
 */
class FiscalizacoesSeeder extends Seeder
{
    public function run(): void
    {
        $this->semearAvulsas();
        $this->semearDasDenuncias();
    }

    /**
     * As fiscalizações que nasceram de uma DENÚNCIA direcionada.
     *
     * Elas vinham escondidas dentro do trâmite de cada denúncia
     * (`config/prototipo_denuncias.php`): o passo do fiscal declarava o que foi
     * encontrado, e o passo seguinte, o desfecho e o documento. Aqui cada passo
     * que declara desfecho vira UMA fiscalização — porque foi uma ida ao ponto.
     *
     * Uma denúncia pode ter duas: a vistoria e o retorno. As duas existem de
     * verdade, e contá-las como uma só faria o relatório dizer que a equipe foi
     * à rua metade das vezes que foi.
     */
    private function semearDasDenuncias(): void
    {
        foreach ((array) config('prototipo_denuncias.denuncias', []) as $bruta) {
            $passos = (array) ($bruta['tramites'] ?? []);

            if ($passos === []) {
                continue;
            }

            $demanda = Demanda::where('protocolo', sprintf('DEN-%04d', (int) $bruta['id']))->first();

            if ($demanda === null || $demanda->equipe_id === null) {
                continue;
            }

            $equipe = Equipe::with('fiscais')->find($demanda->equipe_id);

            if ($equipe === null || $equipe->fiscais->isEmpty()) {
                continue;
            }

            // O último `campo` declarado antes de cada desfecho é o que aquela
            // ida encontrou. Guardado enquanto os passos correm.
            $campo = [];
            $sequencia = 0;

            foreach ($passos as $passo) {
                if (isset($passo['campo'])) {
                    $campo = (array) $passo['campo'];
                }

                if (! isset($passo['desfecho'])) {
                    continue;
                }

                $sequencia++;
                $this->daDenuncia($demanda, $equipe, $campo, (array) $passo, $sequencia);
                $campo = [];
            }
        }
    }

    /**
     * Uma ida ao ponto, saída do passo de trâmite que a encerrou.
     *
     * @param  array<string, mixed>  $campo
     * @param  array<string, mixed>  $passo
     */
    private function daDenuncia(Demanda $demanda, Equipe $equipe, array $campo, array $passo, int $sequencia): void
    {
        $concluida = $demanda->recebida_em->copy()->addHours((int) ($passo['ha_horas'] ?? 0));
        [$latitude, $longitude] = $this->coordenada($campo['gps'] ?? null);

        $fiscalizacao = Fiscalizacao::updateOrCreate(
            ['protocolo' => $demanda->protocolo.'-V'.$sequencia],
            [
                'origem' => $demanda->operacao_id !== null
                    ? Fiscalizacao::ORIGEM_OPERACAO
                    : Fiscalizacao::ORIGEM_DEMANDA,
                'demanda_id' => $demanda->id,
                'operacao_id' => $demanda->operacao_id,
                'equipe_id' => $equipe->id,
                'fiscal_id' => $equipe->fiscais->first()->id,
                'ambulante_id' => $this->ambulanteDoPonto($campo['encontrado'] ?? null, $demanda->bairro)?->id,
                'alvo' => $campo['encontrado'] ?? null,
                'equipamento' => $campo['equipamento'] ?? null,
                'logradouro' => $demanda->logradouro,
                'numero' => $demanda->numero,
                'bairro' => $demanda->bairro,
                'ponto_de_referencia' => $demanda->referencia,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'precisao_m' => $campo['precisao_m'] ?? null,
                'gps_em' => $concluida,
                'aberta_em' => $concluida->copy()->subHour(),
                'concluida_em' => $concluida,
                'despachada_em' => $concluida,
                'sincronizada_em' => $concluida,
                'desfecho' => (string) $passo['desfecho'],
                'consideracoes' => $passo['consideracoes'] ?? ($campo['relato'] ?? null),
                /*
                 * A denúncia cuja situação já passou do campo teve o retorno LIDO
                 * pela chefia — o passo seguinte no trâmite prova isso. A que
                 * parou no desfecho ainda espera leitura, e é ela que aparece na
                 * fila do Chefe de Setor.
                 */
                'situacao' => in_array($demanda->situacao, [Demanda::CONCLUIDA, Demanda::DEVOLVIDA, Demanda::ARQUIVADA], true)
                    ? Fiscalizacao::CIENTE
                    : Fiscalizacao::AGUARDANDO_LEITURA,
            ],
        );

        $this->semearRecomendacoes($fiscalizacao, (array) ($passo['recomendacoes'] ?? []));
        $this->semearFotos($fiscalizacao, (array) ($campo['fotos'] ?? []), $concluida);
        $this->semearDocumento($fiscalizacao, $passo['documento'] ?? null, $concluida);
        $this->semearLocalizacao($fiscalizacao);

        /*
         * Liga o PASSO do trâmite a esta ida. O casamento é pela hora, que é
         * exatamente a que o `DemandasSeeder` gravou a partir do mesmo
         * `ha_horas` — os dois seeders leem o mesmo arquivo, então a hora é a
         * chave natural entre eles.
         */
        $demanda->tramites()
            ->whereNull('fiscalizacao_id')
            ->where('ocorrida_em', $concluida)
            ->update(['fiscalizacao_id' => $fiscalizacao->id]);
    }

    /** Os registros de campo sem denúncia atrás: ronda e varredura de operação. */
    private function semearAvulsas(): void
    {
        foreach ((array) config('prototipo_registros_de_campo.registros', []) as $bruto) {
            $equipe = Equipe::with('fiscais')->where('codigo', (string) ($bruto['equipe'] ?? ''))->first();

            if ($equipe === null || $equipe->fiscais->isEmpty()) {
                // Sem equipe semeada não há quem assine: melhor não inventar autor.
                continue;
            }

            $fiscal = $equipe->fiscais[max(0, (int) ($bruto['fiscal'] ?? 1) - 1)] ?? $equipe->fiscais->first();
            $operacao = Operacao::where('nome', (string) ($bruto['referencia'] ?? ''))->first();
            $concluida = Date::now()->subHours((int) ($bruto['concluida_ha_horas'] ?? 0));
            [$latitude, $longitude] = $this->coordenada($bruto['gps'] ?? null);

            $fiscalizacao = Fiscalizacao::updateOrCreate(
                ['protocolo' => sprintf('FIS-%04d', (int) $bruto['id'])],
                [
                    'origem' => $operacao !== null ? Fiscalizacao::ORIGEM_OPERACAO : Fiscalizacao::ORIGEM_AVULSA,
                    'operacao_id' => $operacao?->id,
                    'equipe_id' => $equipe->id,
                    'fiscal_id' => $fiscal->id,
                    'ambulante_id' => $this->ambulanteDoPonto($bruto['alvo'] ?? null, $bruto['bairro'] ?? null)?->id,
                    'alvo' => $bruto['alvo'] ?? null,
                    'equipamento' => $bruto['equipamento'] ?? null,
                    'logradouro' => $bruto['logradouro'] ?? null,
                    'numero' => $bruto['numero'] ?? null,
                    'bairro' => $bruto['bairro'] ?? null,
                    'ponto_de_referencia' => $bruto['ponto_de_referencia'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'precisao_m' => $bruto['precisao_m'] ?? null,
                    'gps_em' => $concluida,
                    // Uma ida a campo dura cerca de uma hora: é o que separa a
                    // abertura da conclusão, e o que faz a duração na tela ser
                    // plausível em vez de instantânea.
                    'aberta_em' => $concluida->copy()->subHour(),
                    'concluida_em' => $concluida,
                    'despachada_em' => $concluida,
                    'sincronizada_em' => $concluida,
                    'desfecho' => $bruto['desfecho'] ?? null,
                    'consideracoes' => $bruto['consideracoes'] ?? null,
                    'situacao' => Fiscalizacao::AGUARDANDO_LEITURA,
                ],
            );

            $this->semearRecomendacoes($fiscalizacao, (array) ($bruto['recomendacoes'] ?? []));
            $this->semearFotos($fiscalizacao, (array) ($bruto['fotos'] ?? []), $concluida);
            $this->semearDocumento($fiscalizacao, $bruto['documento'] ?? null, $concluida);
            $this->semearLocalizacao($fiscalizacao);
        }
    }

    /** "-12.9977, -38.4356" → dois decimais. O formato de texto morre aqui. */
    /** @return array{0: float|null, 1: float|null} */
    private function coordenada(?string $gps): array
    {
        if ($gps === null || ! str_contains($gps, ',')) {
            return [null, null];
        }

        [$lat, $lng] = array_map(trim(...), explode(',', $gps, 2));

        return [(float) $lat, (float) $lng];
    }

    /**
     * A quem esta vistoria se refere, quando dá para saber.
     *
     * A amostra do protótipo descrevia o alvo em PALAVRAS ("permissionário
     * presente, com permissão regular"), sem apontar cadastro — porque cadastro
     * não existia. Aqui a ligação é feita pelo BAIRRO: escolhe-se um ambulante
     * cadastrado no mesmo bairro da vistoria.
     *
     * ⚠️ É ligação de DEMONSTRAÇÃO, e só faz sentido porque tanto o alvo quanto o
     * ambulante são semeados. Em produção quem aponta o cadastro é o fiscal, na
     * rua, com a busca por documento/apelido — e a vistoria sem alvo
     * identificado continua sendo caso legítimo, não dado faltando.
     *
     * Devolve nulo quando a vistoria não identificou ninguém (ponto vazio,
     * ocupante que se recusou): inventar vínculo ali sujaria a trilha de quem
     * não estava lá.
     */
    private function ambulanteDoPonto(?string $alvo, ?string $bairro): ?Ambulante
    {
        if ($alvo === null || trim($alvo) === '' || $bairro === null) {
            return null;
        }

        return Ambulante::whereHas(
            'localizacoes',
            static fn ($q) => $q->where('bairro', $bairro),
        )->orderBy('id')->first();
    }

    /**
     * O ponto da vistoria entra na TRILHA do ambulante.
     *
     * Só quando há os dois: alvo identificado e GPS. Fiscalização de ponto vazio
     * ou de ocupante que se recusou a identificar não tem a quem pendurar o
     * ponto — e inventar um vínculo ali sujaria a trilha de quem não estava lá.
     */
    private function semearLocalizacao(Fiscalizacao $fiscalizacao): void
    {
        if ($fiscalizacao->ambulante_id === null || $fiscalizacao->latitude === null) {
            return;
        }

        LocalizacaoAmbulante::updateOrCreate(
            ['fiscalizacao_id' => $fiscalizacao->id],
            [
                'ambulante_id' => $fiscalizacao->ambulante_id,
                'latitude' => $fiscalizacao->latitude,
                'longitude' => $fiscalizacao->longitude,
                'precisao_m' => $fiscalizacao->precisao_m,
                'fonte' => LocalizacaoAmbulante::FONTE_FISCALIZACAO,
                'bairro' => $fiscalizacao->bairro,
                'registrada_em' => $fiscalizacao->concluida_em ?? $fiscalizacao->aberta_em,
            ],
        );
    }

    /** @param  list<string>  $chaves */
    private function semearRecomendacoes(Fiscalizacao $fiscalizacao, array $chaves): void
    {
        foreach ($chaves as $chave) {
            FiscalizacaoRecomendacao::firstOrCreate([
                'fiscalizacao_id' => $fiscalizacao->id,
                'chave' => (string) $chave,
            ]);
        }
    }

    /** @param  list<string>  $arquivos */
    private function semearFotos(Fiscalizacao $fiscalizacao, array $arquivos, CarbonInterface $quando): void
    {
        foreach ($arquivos as $arquivo) {
            FiscalizacaoFoto::firstOrCreate(
                ['fiscalizacao_id' => $fiscalizacao->id, 'caminho' => 'fiscalizacoes/'.$fiscalizacao->id.'/'.$arquivo],
                [
                    'legenda' => (string) $arquivo,
                    'latitude' => $fiscalizacao->latitude,
                    'longitude' => $fiscalizacao->longitude,
                    'capturada_em' => $quando,
                ],
            );
        }
    }

    /** @param  array<string, mixed>|null  $documento */
    private function semearDocumento(Fiscalizacao $fiscalizacao, ?array $documento, CarbonInterface $quando): void
    {
        if ($documento === null) {
            return;
        }

        $prazos = (array) config('prototipo_documentos_campo.prazos_np', []);
        $chave = (string) ($documento['prazo'] ?? '');
        $dias = (int) ($prazos[$chave]['dias'] ?? 0);

        $guarda = (string) ($documento['prazo_guarda'] ?? '');

        DocumentoCampo::updateOrCreate(
            ['tipo' => (string) $documento['tipo'], 'numero' => (string) $documento['numero']],
            [
                'fiscalizacao_id' => $fiscalizacao->id,
                'notificado' => $documento['notificado'] ?? null,
                'documento_notificado' => $documento['cpf'] ?? null,
                'prazo_chave' => $chave === '' ? null : $chave,
                // A DATA que o prazo produziu, gravada — é ela que vence, e o que
                // vence não pode depender de recalcular um catálogo que mudou.
                'prazo_ate' => $dias > 0 ? $quando->copy()->addDays($dias)->startOfDay() : null,
                // As CHAVES dos catálogos: é por elas que o relatório soma.
                'motivos' => array_values((array) ($documento['motivos'] ?? [])),
                'sancoes' => array_values((array) ($documento['sancoes'] ?? [])),
                'itens' => array_values((array) ($documento['itens'] ?? [])),
                'guarda_prazo' => $guarda === '' ? null : $guarda,
                'guarda_destinacao' => $documento['destinacao'] ?? null,
                /*
                 * O resto do IMPRESSO, como o papel o traz. Guardado inteiro
                 * porque o conjunto muda entre a Notificação e o Auto — ver a
                 * migration.
                 */
                'dados' => array_diff_key((array) $documento, array_flip([
                    'tipo', 'numero', 'notificado', 'cpf', 'prazo', 'motivos',
                    'sancoes', 'itens', 'prazo_guarda', 'destinacao',
                ])),
                'emitido_em' => $quando,
            ],
        );
    }
}
