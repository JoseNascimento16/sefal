<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Demanda;
use App\Models\DemandaAnexo;
use App\Models\DemandaTramite;
use App\Models\Equipe;
use App\Models\Operacao;
use App\Support\CiclosDeFiscalizacao;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * As demandas do protótipo virando banco — as duas portas de entrada.
 *
 *  - `config/prototipo_denuncias.php` → o que chega por INTEGRAÇÃO (e-Salvador,
 *    Fala Salvador), com o trâmite que cada caso já andou;
 *  - `config/prototipo_caixa_entrada.php` → o que o coordenador DIGITA no
 *    balcão (inclusive pedido de licença e ofício).
 *
 * As duas alimentam a MESMA tabela, separadas pela coluna `entrada`. A razão
 * está na migration; aqui a consequência prática é que a demonstração continua
 * mostrando duas telas e o sistema passa a contar uma coisa só.
 *
 * ## Os protocolos do protótipo são preservados
 *
 * `DEN-0029`, `CXE-0001`: são os números que o dono viu, que aparecem nos prints
 * e que o aplicativo do fiscal referencia. Renumerá-los com
 * `Protocolo::proximo()` quebraria a correspondência entre o que foi
 * demonstrado e o que o sistema mostra — e cada demanda NOVA, criada de agora em
 * diante pela tela, nasce com o protocolo do gerador, como deve ser.
 *
 * ## As datas seguem relativas
 *
 * `recebida_ha_horas` e `prazo_em_dias` viram data ao semear. Data fixa
 * envelhece: uma semana depois, a caixa inteira apareceria vencida e isso seria
 * lido como comportamento do sistema.
 */
class DemandasSeeder extends Seeder
{
    /** Origem escrita na Caixa de Entrada → canal canônico do sistema. */
    private const CANAL_DA_ORIGEM = [
        'e-Salvador' => Demanda::CANAL_E_SALVADOR,
        'Fala Salvador' => Demanda::CANAL_FALA_SALVADOR,
        'Nova licença' => Demanda::CANAL_NOVA_LICENCA,
        // O ofício virou TIPO de avulsa (dono, 25/09/2026): canal avulsa, tipo ofício.
        'Ofício' => Demanda::CANAL_AVULSA,
    ];

    /**
     * Situação da Caixa de Entrada → situação do fluxo completo.
     *
     * "Aguardando triagem" e "Recebida" eram o MESMO estado com dois nomes; fica
     * o do fluxo. "Encaminhada" era ambígua — na Caixa ela significava
     * "direcionada à equipe", que é o que o campo `equipe` daquele arquivo
     * preenche.
     */
    private const SITUACAO_DA_CAIXA = [
        'Aguardando triagem' => Demanda::RECEBIDA,
        'Encaminhada' => Demanda::DIRECIONADA_AOS_FISCAIS,
        'Devolvida' => Demanda::DEVOLVIDA,
        'Arquivada' => Demanda::ARQUIVADA,
    ];

    public function run(): void
    {
        $this->semearIntegracao();
        $this->semearBalcao();

        // As FISCALIZAÇÕES (ciclos) do que acabou de ser semeado.
        CiclosDeFiscalizacao::sincronizarLegado();
    }

    // ── O que chega sozinho ─────────────────────────────────────────────────

    private function semearIntegracao(): void
    {
        $canais = (array) config('prototipo_denuncias.canais', []);

        foreach ((array) config('prototipo_denuncias.denuncias', []) as $bruta) {
            $canal = (string) $bruta['canal'];
            $recebida = Date::now()->subHours((int) ($bruta['recebida_ha_horas'] ?? 0));

            $demanda = Demanda::updateOrCreate(
                ['protocolo' => sprintf('DEN-%04d', (int) $bruta['id'])],
                [
                    'canal' => $canal,
                    'entrada' => Demanda::ENTRADA_INTEGRACAO,
                    'numero_origem' => $bruta['protocolo_origem'] ?? null,
                    'recebida_em' => $recebida,
                    'prazo_em' => $recebida->copy()->addDays((int) ($bruta['prazo_em_dias'] ?? 10))->startOfDay(),
                    'anonima' => (bool) ($bruta['anonima'] ?? false),
                    'requerente' => $bruta['requerente'] ?? null,
                    'documento' => $bruta['documento'] ?? null,
                    'email' => $bruta['email'] ?? null,
                    'telefone' => $bruta['telefone'] ?? null,
                    'assunto' => (string) $bruta['assunto'],
                    'relato' => $bruta['relato'] ?? null,
                    'logradouro' => $bruta['logradouro'] ?? null,
                    'numero' => $bruta['numero'] ?? null,
                    'referencia' => $bruta['referencia'] ?? null,
                    'bairro' => $bruta['bairro'] ?? null,
                    'endereco_impreciso' => (bool) ($bruta['endereco_impreciso'] ?? false),
                    'situacao' => (string) ($bruta['situacao'] ?? Demanda::RECEBIDA),
                    'area_id' => $this->areaId($bruta['area'] ?? null, $bruta['bairro'] ?? null),
                    /*
                     * A demanda ENCAMINHADA tem equipe desde 22/09/2026: o chefe
                     * escolhe a equipe, e é por `equipe_id` que o líder a enxerga.
                     * O arquivo de protótipo só declara a equipe a partir do
                     * direcionamento, então a encaminhada herda a equipe da área.
                     */
                    'equipe_id' => $this->equipeId($bruta['equipe'] ?? null)
                        ?? $this->equipeDaArea(
                            (string) ($bruta['situacao'] ?? Demanda::RECEBIDA),
                            $this->areaId($bruta['area'] ?? null, $bruta['bairro'] ?? null),
                        ),
                    'operacao_id' => $this->operacaoId($bruta['operacao'] ?? null),
                ],
            );

            $this->semearAnexos($demanda, (array) ($bruta['anexos'] ?? []));
            $this->semearTramites($demanda, $bruta, (string) ($canais[$canal]['nome'] ?? $canal), $recebida);
        }
    }

    // ── O que o coordenador digita ──────────────────────────────────────────

    private function semearBalcao(): void
    {
        $prazoPadrao = (int) config('prototipo_caixa_entrada.prazo_padrao_em_dias', 10);

        foreach ((array) config('prototipo_caixa_entrada.demandas', []) as $bruta) {
            $origem = (string) ($bruta['origem'] ?? 'Ofício');
            $recebida = Date::now()->subDays((int) ($bruta['dias_atras'] ?? 0));
            $situacaoAntiga = (string) ($bruta['situacao'] ?? 'Aguardando triagem');

            $demanda = Demanda::updateOrCreate(
                ['protocolo' => (string) $bruta['protocolo']],
                [
                    'canal' => self::CANAL_DA_ORIGEM[$origem] ?? Demanda::CANAL_AVULSA,
                    'tipo_avulsa' => ($origem === 'Ofício' || ! isset(self::CANAL_DA_ORIGEM[$origem])) ? Demanda::AVULSA_OFICIO : null,
                    'entrada' => Demanda::ENTRADA_BALCAO,
                    'numero_origem' => $bruta['documento_origem'] ?? null,
                    'recebida_em' => $recebida,
                    'prazo_em' => $recebida->copy()->addDays((int) ($bruta['prazo_em_dias'] ?? $prazoPadrao))->startOfDay(),
                    'anonima' => (bool) ($bruta['anonima'] ?? false),
                    'requerente' => $bruta['requerente'] ?? null,
                    'telefone' => $bruta['contato'] ?? null,
                    'assunto' => (string) $bruta['assunto'],
                    'relato' => $bruta['descricao'] ?? null,
                    'logradouro' => $bruta['endereco'] ?? null,
                    'bairro' => $bruta['bairro'] ?? null,
                    'situacao' => self::SITUACAO_DA_CAIXA[$situacaoAntiga] ?? Demanda::RECEBIDA,
                    'area_id' => $this->areaId(null, $bruta['bairro'] ?? null),
                    'equipe_id' => $this->equipeId($bruta['equipe'] ?? null),
                ],
            );

            $this->semearAnexos($demanda, array_filter([$bruta['anexo'] ?? null]));
            $this->tramiteDoBalcao($demanda, $bruta, $origem, $recebida);
        }
    }

    // ── Trâmite ─────────────────────────────────────────────────────────────

    /**
     * Replica o trâmite da denúncia: o declarado passo a passo quando existe, ou
     * o que a situação implica quando não existe.
     *
     * ⚠️ Semeado com `ocorrida_em` explícito e `ordem` crescente, e não com
     * `Demanda::registrar()`: o registrar carimba AGORA, e todos os passos
     * nasceriam com a mesma hora — o histórico apareceria achatado num instante
     * só, e a leitura do caso perderia a única coisa que ela tem de contar, que
     * é a passagem do tempo entre as decisões.
     *
     * @param  array<string, mixed>  $bruta
     */
    private function semearTramites(Demanda $demanda, array $bruta, string $nomeDoCanal, CarbonInterface $recebida): void
    {
        // Idempotência: semear de novo não empilha histórico.
        $demanda->tramites()->delete();

        $ordem = 0;
        $passo = function (int $horas, string $papel, string $acao, ?string $detalhe, string $situacao, array $campos = []) use ($demanda, $recebida, &$ordem): void {
            $demanda->tramites()->create([
                'ordem' => ++$ordem,
                'ocorrida_em' => $recebida->copy()->addHours($horas),
                'papel' => $papel,
                'autor' => null,
                'acao' => $acao,
                'detalhe' => $detalhe,
                'situacao' => $situacao,
                'campos' => $campos === [] ? null : $campos,
            ]);
        };

        $declarados = (array) ($bruta['tramites'] ?? []);

        if ($declarados !== []) {
            foreach ($declarados as $d) {
                $passo(
                    (int) ($d['ha_horas'] ?? 0),
                    (string) ($d['quem'] ?? DemandaTramite::PAPEL_INTEGRACAO),
                    (string) ($d['o_que'] ?? "Recebida por integração — {$nomeDoCanal}"),
                    $d['detalhe'] ?? null,
                    (string) ($d['situacao'] ?? Demanda::RECEBIDA),
                    $this->camposDoPasso((array) ($d['campos'] ?? [])),
                );
            }

            return;
        }

        $situacao = (string) ($bruta['situacao'] ?? Demanda::RECEBIDA);
        $area = (string) ($bruta['area'] ?? '');
        $equipe = (string) ($bruta['equipe'] ?? '');

        $passo(0, DemandaTramite::PAPEL_INTEGRACAO, "Recebida por integração — {$nomeDoCanal}",
            'Entrou pelo canal '.$nomeDoCanal.', com o número '.((string) ($bruta['protocolo_origem'] ?? '—'))
            .'. Nenhum dado foi digitado no SEFAL.', Demanda::RECEBIDA);

        if (in_array($situacao, [Demanda::DEVOLVIDA, Demanda::ARQUIVADA], true)) {
            $passo(6, DemandaTramite::PAPEL_CHEFE_DE_SETOR,
                $situacao === Demanda::ARQUIVADA ? 'Arquivada na triagem' : 'Devolvida ao canal de origem',
                trim(((string) ($bruta['motivo'] ?? '')).' — '.((string) ($bruta['justificativa'] ?? ''))),
                $situacao);

            return;
        }

        if ($area === '') {
            return;
        }

        $passo(5, DemandaTramite::PAPEL_CHEFE_DE_SETOR, 'Encaminhada ao líder de equipe',
            "Encaminhada à equipe da {$area} para o líder direcionar aos fiscais.",
            Demanda::ENCAMINHADA_AO_LIDER, ['Área de destino' => $area]);

        if ($situacao === Demanda::ENCAMINHADA_AO_LIDER) {
            return;
        }

        if ($situacao === Demanda::EM_OPERACAO) {
            $passo(9, DemandaTramite::PAPEL_CHEFE_DE_SETOR, 'Incluída em operação',
                'Anexada à '.((string) ($bruta['operacao'] ?? '')).($equipe === '' ? '.' : ", executada pela Equipe {$equipe}."),
                Demanda::EM_OPERACAO, ['Operação' => (string) ($bruta['operacao'] ?? '—')]);
        } else {
            $passo(9, DemandaTramite::PAPEL_LIDER, 'Direcionada aos fiscais',
                "Enviada à fila da Equipe {$equipe} para vistoria.",
                Demanda::DIRECIONADA_AOS_FISCAIS, ['Equipe' => "Equipe {$equipe}"]);
        }

        if ($situacao === Demanda::EM_CAMPO) {
            $passo(24, DemandaTramite::PAPEL_FISCAL, 'Em campo',
                'A equipe recebeu a denúncia no aplicativo e está em rota para o local.',
                Demanda::EM_CAMPO);
        }
    }

    /** @param  array<string, mixed>  $bruta */
    private function tramiteDoBalcao(Demanda $demanda, array $bruta, string $origem, CarbonInterface $recebida): void
    {
        $demanda->tramites()->delete();

        $demanda->tramites()->create([
            'ordem' => 1,
            'ocorrida_em' => $recebida,
            'papel' => DemandaTramite::PAPEL_CHEFE_DE_SETOR,
            'acao' => 'Demanda cadastrada',
            'detalhe' => 'Registrada pelo Chefe de Setor, com origem '.$origem.'.',
            'situacao' => Demanda::RECEBIDA,
            'campos' => ['Origem do documento' => $origem, 'Número na origem' => (string) ($bruta['documento_origem'] ?? '—')],
        ]);

        $situacao = self::SITUACAO_DA_CAIXA[(string) ($bruta['situacao'] ?? '')] ?? Demanda::RECEBIDA;

        if ($situacao === Demanda::RECEBIDA) {
            return;
        }

        $devolvida = in_array($situacao, [Demanda::DEVOLVIDA, Demanda::ARQUIVADA], true);

        $demanda->tramites()->create([
            'ordem' => 2,
            'ocorrida_em' => $recebida->copy()->addHours(4),
            'papel' => DemandaTramite::PAPEL_CHEFE_DE_SETOR,
            'acao' => $devolvida
                ? ($situacao === Demanda::ARQUIVADA ? 'Arquivada pelo Chefe de Setor' : 'Devolvida ao remetente')
                : 'Direcionada aos fiscais',
            'detalhe' => $devolvida
                ? (string) ($bruta['justificativa'] ?? '')
                : 'Encaminhada à Equipe '.((string) ($bruta['equipe'] ?? '')).', da área do bairro.',
            'situacao' => $situacao,
            'campos' => $devolvida
                ? array_filter([
                    'Motivo' => $bruta['motivo'] ?? null,
                    'Destino' => $bruta['destino'] ?? null,
                ])
                : ['Equipe escolhida' => 'Equipe '.((string) ($bruta['equipe'] ?? ''))],
        ]);
    }

    /**
     * Os campos do passo: do formato do arquivo (lista de rótulo+valor) para o
     * mapa que o banco guarda.
     *
     * @param  list<array{rotulo: string, valor: string}>  $campos
     * @return array<string, string>
     */
    private function camposDoPasso(array $campos): array
    {
        $mapa = [];

        foreach ($campos as $campo) {
            $mapa[(string) ($campo['rotulo'] ?? '')] = (string) ($campo['valor'] ?? '');
        }

        return $mapa;
    }

    // ── Resolução de vínculos ───────────────────────────────────────────────

    /** A área declarada; na falta dela, a que o bairro implica. */
    private function areaId(?string $nome, ?string $bairro): ?int
    {
        if ($nome !== null && $nome !== '') {
            $area = Area::where('nome', $nome)->first();

            if ($area !== null) {
                return $area->id;
            }
        }

        return Area::sugeridaParaBairro($bairro)?->id;
    }

    private function equipeId(?string $codigo): ?int
    {
        return $codigo === null || $codigo === ''
            ? null
            : Equipe::where('codigo', $codigo)->value('id');
    }

    /**
     * A equipe da área — só para a demanda que JÁ passou pelo encaminhamento.
     *
     * A que ainda espera o chefe (ou que ele devolveu) não tem equipe, e é isso
     * que a mantém na Caixa dele e fora da fila de qualquer líder.
     */
    private function equipeDaArea(string $situacao, ?int $areaId): ?int
    {
        $semEquipe = [Demanda::EM_PRE_TRIAGEM, Demanda::RECEBIDA, Demanda::DEVOLVIDA, Demanda::ARQUIVADA];

        if ($areaId === null || in_array($situacao, $semEquipe, true)) {
            return null;
        }

        return Equipe::where('area_id', $areaId)->orderBy('id')->value('id');
    }

    private function operacaoId(?string $nome): ?int
    {
        return $nome === null || $nome === ''
            ? null
            : Operacao::where('nome', $nome)->value('id');
    }

    /** @param  list<string>  $arquivos */
    private function semearAnexos(Demanda $demanda, array $arquivos): void
    {
        foreach ($arquivos as $arquivo) {
            DemandaAnexo::firstOrCreate(
                ['demanda_id' => $demanda->id, 'nome' => (string) $arquivo],
                [
                    /*
                     * O arquivo em si NÃO existe: o protótipo só tinha o nome. O
                     * caminho aponta para onde ele estaria, e a tela trata a
                     * ausência dizendo isso — prometer um download que dá 404 é
                     * pior que declarar que o anexo é de demonstração.
                     */
                    'caminho' => 'demandas/'.$demanda->id.'/'.$arquivo,
                    'tipo' => str_ends_with((string) $arquivo, '.pdf') ? 'application/pdf' : 'image/jpeg',
                ],
            );
        }
    }
}
