<?php

namespace App\Support\Agrupamento;

use App\Models\Area;
use App\Models\Demanda;
use App\Models\SugestaoAgrupamento;
use App\Support\Documento;
use App\Support\Texto;
use Illuminate\Support\Collection;

/**
 * O olhar DETERMINISTA: mesmo bairro, endereços próximos, assunto parecido.
 *
 * É o primeiro analisador porque não depende de nada externo e porque o motivo
 * que ele escreve é auditável — "mesmo bairro, o mesmo logradouro e 7 palavras
 * em comum no assunto" é uma frase que o coordenador pode conferir em dois
 * segundos. Um modelo de linguagem responderá melhor nos casos difíceis (o
 * cidadão que descreve o mesmo ponto com palavras completamente diferentes),
 * mas responderá com uma confiança que ninguém consegue verificar sozinho — e
 * por isso ele entra DEPOIS, quando a mecânica de proposta e decisão já estiver
 * rodada.
 *
 * ## Como a confiança é montada
 *
 * Três sinais somam, e nenhum sozinho basta:
 *
 *  - **bairro igual** é pré-requisito, não pontua. Sem ele nem se compara: o
 *    comércio de rua repete assunto ("mesas na calçada") a cidade inteira, e
 *    comparar Cajazeiras com a Barra produziria ruído a um custo alto;
 *  - **logradouro** — a mesma rua vale muito; a mesma rua com número próximo
 *    vale mais. É o sinal mais forte porque é o que a equipe usa para saber se
 *    vai ao mesmo lugar;
 *  - **assunto** — palavras em comum, fora as de ligação. Sozinho é fraco
 *    (dois pontos diferentes na mesma rua podem ter o mesmo problema), mas
 *    confirma o endereço;
 *  - **distância**, quando as duas têm coordenada: abaixo de 80 metros é o
 *    mesmo ponto para efeito de fiscalização.
 *
 * ## O que ele se recusa a propor
 *
 * Denúncia de canal que não agrupa (`config/demandas.php` → `agrupa`). Pedido de
 * licença é de UM requerente para UM ponto, e ofício tem um remetente que
 * espera resposta própria: agrupá-los não economizaria ida nenhuma e perderia o
 * caso de alguém.
 */
class AnalisadorPorRegra implements Analisador
{
    /** Metros abaixo dos quais dois pontos são, para a fiscalização, o mesmo. */
    private const METROS_DO_MESMO_PONTO = 80;

    /**
     * Palavras que aparecem em tudo e não distinguem nada.
     *
     * Sem esta lista, "de" e "na" contariam como coincidência e toda denúncia
     * pareceria parecida com toda denúncia.
     *
     * @var list<string>
     */
    private const LIGACAO = [
        'a', 'o', 'as', 'os', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na',
        'nos', 'nas', 'um', 'uma', 'e', 'com', 'sem', 'por', 'para', 'que',
        'ao', 'aos', 'pela', 'pelo', 'sobre',
    ];

    public function origem(): string
    {
        return SugestaoAgrupamento::ORIGEM_REGRA;
    }

    /**
     * @param  Collection<int, Demanda>  $vizinhas
     * @return list<Proposta>
     */
    public function analisar(Demanda $candidata, Collection $vizinhas): array
    {
        if (! self::canalAgrupa($candidata)) {
            return [];
        }

        $minima = (float) config('demandas.agrupamento.confianca_minima', 0.6);
        $propostas = [];

        foreach ($vizinhas as $vizinha) {
            if ($vizinha->is($candidata) || ! self::canalAgrupa($vizinha)) {
                continue;
            }

            // Bairro é pré-requisito, não pontuação. Ver o cabeçalho.
            if (Area::chaveDeBairro($candidata->bairro) !== Area::chaveDeBairro($vizinha->bairro)) {
                continue;
            }

            [$confianca, $razoes] = $this->pesar($candidata, $vizinha);

            if ($confianca < $minima) {
                continue;
            }

            /*
             * A PRINCIPAL é a mais antiga. É a que espera há mais tempo, a que
             * tem o prazo mais curto e a que a ouvidoria já cobrou — fazer a
             * nova levar o caso a campo empurraria o prazo da antiga para o fim
             * da fila sem que nada acusasse.
             */
            $maisAntiga = $candidata->recebida_em->lte($vizinha->recebida_em) ? $candidata : $vizinha;
            $maisNova = $maisAntiga->is($candidata) ? $vizinha : $candidata;

            $propostas[] = new Proposta(
                agregada: $maisNova,
                principal: $maisAntiga,
                confianca: round($confianca, 3),
                motivo: implode('; ', $razoes).'.',
            );
        }

        /*
         * Da mais confiante para a menos, e só as primeiras: o coordenador olha
         * a lista de cima para baixo, e uma fila longa de propostas fracas faz
         * ele parar de ler — que é como um assistente útil vira ruído.
         */
        usort($propostas, static fn (Proposta $a, Proposta $b): int => $b->confianca <=> $a->confianca);

        return array_slice($propostas, 0, (int) config('demandas.agrupamento.limite_por_demanda', 5));
    }

    /**
     * Quanto as duas se parecem, e POR QUÊ — as duas coisas juntas, porque uma
     * confiança sem a frase que a explica é número que ninguém pode contestar.
     *
     * @return array{0: float, 1: list<string>}
     */
    private function pesar(Demanda $a, Demanda $b): array
    {
        $razoes = ['mesmo bairro ('.$a->bairro.')'];
        $confianca = 0.30;

        // ── Logradouro ──────────────────────────────────────────────────────
        $ruaA = Texto::chave((string) $a->logradouro);
        $ruaB = Texto::chave((string) $b->logradouro);

        if ($ruaA !== '' && $ruaA === $ruaB) {
            $confianca += 0.35;
            $razoes[] = 'o mesmo logradouro ('.$a->logradouro.')';

            $numeroA = (int) preg_replace('/\D/', '', (string) $a->numero);
            $numeroB = (int) preg_replace('/\D/', '', (string) $b->numero);

            if ($numeroA > 0 && $numeroB > 0 && abs($numeroA - $numeroB) <= 50) {
                $confianca += 0.10;
                $razoes[] = 'números a poucos metros ('.$a->numero.' e '.$b->numero.')';
            }
        }

        /*
         * ── Quem foi denunciado ──────────────────────────────────────────────
         *
         * O sinal MAIS FORTE que existe aqui, e de longe. Endereço o cidadão
         * escreve de memória e assunto o comércio de rua repete a cidade
         * inteira; o nome da fachada, não — quando duas pessoas escrevem "Bar do
         * Zeca", é o mesmo bar. Por isso pesa mais que o logradouro: é ele que
         * separa o mesmo estabelecimento de dois vizinhos na mesma rua, que é
         * exatamente o erro que a pré-triagem existe para não cometer.
         *
         * O documento, quando os dois lados o têm, é melhor ainda: é identidade,
         * não semelhança. Aí a confiança vai ao teto e a frase diz por quê.
         */
        $docA = (string) $a->documento_denunciado;
        $docB = (string) $b->documento_denunciado;

        if ($docA !== '' && $docA === $docB) {
            $confianca += 0.60;
            $razoes[] = 'o MESMO documento do denunciado ('.Documento::formatar($docA).')';
        } else {
            $fachadaA = Texto::chave((string) $a->estabelecimento);
            $fachadaB = Texto::chave((string) $b->estabelecimento);

            if ($fachadaA !== '' && $fachadaA === $fachadaB) {
                $confianca += 0.45;
                $razoes[] = 'o mesmo estabelecimento ('.$a->estabelecimento.')';
            }
        }

        // ── Assunto ─────────────────────────────────────────────────────────
        $comuns = array_intersect($this->palavras($a->assunto), $this->palavras($b->assunto));

        if (count($comuns) >= 2) {
            $confianca += min(0.25, count($comuns) * 0.06);
            $razoes[] = count($comuns).' palavras em comum no assunto ('.implode(', ', array_slice($comuns, 0, 4)).')';
        }

        // ── Distância ───────────────────────────────────────────────────────
        $metros = $this->metrosEntre($a, $b);

        if ($metros !== null && $metros <= self::METROS_DO_MESMO_PONTO) {
            $confianca += 0.20;
            $razoes[] = 'os pontos estão a '.round($metros).' m um do outro';
        }

        return [min(1.0, $confianca), $razoes];
    }

    /**
     * As palavras que distinguem, sem acento e sem as de ligação.
     *
     * @return list<string>
     */
    private function palavras(?string $texto): array
    {
        $limpo = Texto::chave((string) $texto);
        $partes = preg_split('/[^a-z0-9]+/', $limpo, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $partes,
            static fn (string $p): bool => mb_strlen($p) > 2 && ! in_array($p, self::LIGACAO, true),
        )));
    }

    /**
     * Distância em metros, ou nulo quando alguma das duas não tem coordenada.
     *
     * Fórmula de Haversine. Nulo é resposta: sem coordenada não se afirma
     * proximidade, e tratar a ausência como "longe" descartaria proposta boa.
     */
    private function metrosEntre(Demanda $a, Demanda $b): ?float
    {
        if ($a->latitude === null || $a->longitude === null || $b->latitude === null || $b->longitude === null) {
            return null;
        }

        $raioDaTerra = 6371000;
        $dLat = deg2rad($b->latitude - $a->latitude);
        $dLng = deg2rad($b->longitude - $a->longitude);

        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($dLng / 2) ** 2;

        return $raioDaTerra * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }

    /** O canal desta demanda produz relato repetido do mesmo fato? */
    private static function canalAgrupa(Demanda $demanda): bool
    {
        return (bool) config('demandas.canais.'.$demanda->canal.'.agrupa', false);
    }
}
