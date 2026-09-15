<?php

namespace App\Support\Agrupamento;

use App\Models\Demanda;
use App\Models\SugestaoAgrupamento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * A varredura que procura denúncias repetidas e deixa PROPOSTAS na mesa.
 *
 * O trabalho dela é achar candidatas e gravar o que o analisador propôs. Ela não
 * agrupa nada: agrupar é ato de gente, e o dia em que uma varredura noturna
 * começar a agrupar sozinha, ninguém vai saber por que dez protocolos viraram um.
 *
 * ## Ela propõe GRUPO, não par — e a diferença decide se a tela presta
 *
 * O analisador compara de dois em dois, que é como se compara. Publicar isso
 * cru seria inútil e perigoso:
 *
 *  - **inútil**: seis denúncias do mesmo bar produzem quinze pares. O
 *    coordenador decidiria quinze vezes para juntar seis casos, e desistiria
 *    antes da quinta;
 *  - **perigoso**: os pares apontam em cadeia (A←B, B←C). Aceitar dois deles
 *    tentaria agregar a uma agregada — que o model recusa, com razão, mas só
 *    depois de a pessoa ter clicado.
 *
 * Por isso os pares são fechados em GRUPOS aqui (quem se liga a quem, por
 * transitividade), e cada grupo produz uma proposta por membro apontando para a
 * MESMA principal: a mais antiga de todas. Seis denúncias viram cinco propostas
 * que convergem, e aceitar as cinco em qualquer ordem dá no mesmo resultado.
 *
 * ⚠️ A transitividade também traz para o grupo quem se parece com um membro sem
 * se parecer com os outros — o restaurante da esquina, na mesma rua. Isso é
 * deliberado: ele entra como UMA proposta, que o coordenador recusa
 * individualmente, e a recusa dele não derruba as outras.
 *
 * ## O que ela NÃO propõe de novo
 *
 * Par já decidido — aceito ou RECUSADO — não volta. A recusa é a informação mais
 * cara desta tela: é o coordenador dizendo "são dois bares diferentes", e propor
 * o mesmo par toda noite faria ele parar de ler a lista inteira. Um assistente
 * que insiste no que já foi negado é pior do que nenhum.
 *
 * ## A janela
 *
 * Só denúncias recebidas dentro de `janela_em_dias` se comparam entre si. Sem
 * ela, o sistema proporia agrupar a reclamação de hoje com a de seis meses atrás
 * sobre o mesmo ponto — que é OUTRO fato, já respondido, e cujo protocolo não
 * pode ser reaberto para caber num caso novo.
 */
class VarreduraDeAgrupamento
{
    public function __construct(private readonly Analisador $analisador) {}

    /**
     * Varre as demandas abertas e grava as propostas novas.
     *
     * @param  list<string>|null  $areas  recorte de quem chamou; nulo = tudo
     * @return array{propostas: int, analisadas: int, grupos: int, ja_decididas: int}
     */
    public function executar(?array $areas = null): array
    {
        $abertas = $this->abertas($areas);
        $pares = $this->pares($abertas);
        $decididos = $this->paresJaDecididos();

        $grupos = $this->agrupar(array_keys($pares), $abertas);

        $propostas = 0;
        $repetidos = 0;
        $comProposta = 0;

        foreach ($grupos as $membros) {
            $principal = $this->maisAntiga($membros);

            $criadasNoGrupo = 0;

            foreach ($membros as $membro) {
                if ($membro->is($principal)) {
                    continue;
                }

                $chave = $this->chaveDoPar($membro->id, $principal->id);

                if (isset($decididos[$chave])) {
                    $repetidos++;

                    continue;
                }

                /*
                 * O motivo é o do par que de fato ligou os dois. Quando o membro
                 * entrou no grupo por transitividade — parecido com outro membro,
                 * não com a principal —, isso é DITO: o coordenador precisa saber
                 * que a ligação é indireta antes de aceitar.
                 */
                $direto = $pares[$this->chaveDoPar($membro->id, $principal->id)] ?? null;

                SugestaoAgrupamento::updateOrCreate(
                    ['demanda_id' => $membro->id, 'principal_id' => $principal->id],
                    [
                        'origem' => $this->analisador->origem(),
                        'confianca' => $direto['confianca'] ?? $this->melhorLigacaoIndireta($membro, $pares),
                        'motivo' => $direto['motivo']
                            ?? 'Ligada ao grupo por outra denúncia da mesma rua, e não diretamente a esta: '
                                .$this->motivoIndireto($membro, $pares),
                        'estado' => SugestaoAgrupamento::SUGERIDA,
                    ],
                );

                $propostas++;
                $criadasNoGrupo++;
            }

            if ($criadasNoGrupo > 0) {
                $comProposta++;
            }
        }

        return [
            'propostas' => $propostas,
            'analisadas' => $abertas->count(),
            'grupos' => $comProposta,
            'ja_decididas' => $repetidos,
        ];
    }

    /**
     * As demandas que entram na comparação.
     *
     * @param  list<string>|null  $areas
     * @return Collection<int, Demanda>
     */
    private function abertas(?array $areas): Collection
    {
        $desde = Date::now()->subDays((int) config('demandas.agrupamento.janela_em_dias', 30));

        $consulta = Demanda::deTrabalho()
            ->whereIn('situacao', Demanda::ABERTAS)
            ->where('recebida_em', '>=', $desde)
            ->orderBy('recebida_em');

        if ($areas !== null) {
            $consulta->whereHas('area', static fn ($q) => $q->whereIn('nome', $areas));
        }

        return $consulta->get();
    }

    /**
     * Os pares que o analisador propôs, indexados pela chave estável do par.
     *
     * @param  Collection<int, Demanda>  $abertas
     * @return array<string, array{confianca: float, motivo: string, a: int, b: int}>
     */
    private function pares(Collection $abertas): array
    {
        $pares = [];

        foreach ($abertas as $candidata) {
            foreach ($this->analisador->analisar($candidata, $abertas) as $proposta) {
                $chave = $this->chaveDoPar($proposta->agregada->id, $proposta->principal->id);

                // As duas pontas produzem o mesmo par, visto de lados opostos.
                // Fica a leitura mais confiante das duas.
                if (($pares[$chave]['confianca'] ?? -1) >= $proposta->confianca) {
                    continue;
                }

                $pares[$chave] = [
                    'confianca' => $proposta->confianca,
                    'motivo' => $proposta->motivo,
                    'a' => $proposta->agregada->id,
                    'b' => $proposta->principal->id,
                ];
            }
        }

        return $pares;
    }

    /**
     * Fecha os pares em grupos por transitividade.
     *
     * Conjuntos disjuntos com compressão de caminho — o suficiente para uma fila
     * de triagem, e sem dependência nova.
     *
     * @param  list<string>  $chaves
     * @param  Collection<int, Demanda>  $abertas
     * @return list<list<Demanda>>
     */
    private function agrupar(array $chaves, Collection $abertas): array
    {
        $pai = [];

        $raiz = function (int $x) use (&$pai, &$raiz): int {
            while (($pai[$x] ?? $x) !== $x) {
                $pai[$x] = $pai[$pai[$x]] ?? $pai[$x];
                $x = $pai[$x];
            }

            return $x;
        };

        foreach ($chaves as $chave) {
            [$a, $b] = array_map('intval', explode(':', $chave));
            $ra = $raiz($a);
            $rb = $raiz($b);

            if ($ra !== $rb) {
                $pai[$rb] = $ra;
            }
        }

        $porRaiz = [];

        foreach ($abertas as $demanda) {
            if (! array_key_exists($demanda->id, $pai) && ! $this->apareceEmAlgumPar($demanda->id, $chaves)) {
                // Denúncia que não se parece com nenhuma outra não forma grupo.
                continue;
            }

            $porRaiz[$raiz($demanda->id)][] = $demanda;
        }

        return array_values(array_filter(
            $porRaiz,
            static fn (array $membros): bool => count($membros) > 1,
        ));
    }

    /** @param  list<string>  $chaves */
    private function apareceEmAlgumPar(int $id, array $chaves): bool
    {
        foreach ($chaves as $chave) {
            [$a, $b] = array_map('intval', explode(':', $chave));

            if ($a === $id || $b === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * A PRINCIPAL do grupo: a mais antiga.
     *
     * É a que espera há mais tempo, a que tem o prazo mais curto e a que a
     * ouvidoria já cobrou. Escolher outra empurraria o prazo dela para o fim da
     * fila sem que nada acusasse.
     *
     * @param  list<Demanda>  $membros
     */
    private function maisAntiga(array $membros): Demanda
    {
        usort(
            $membros,
            static fn (Demanda $a, Demanda $b): int => [$a->recebida_em, $a->id] <=> [$b->recebida_em, $b->id],
        );

        return $membros[0];
    }

    /**
     * A melhor confiança com que este membro se liga a ALGUÉM do grupo.
     *
     * Serve à proposta indireta: o número não pode ser o do par direto (não
     * existe), e não pode ser inventado. O que existe é a força da ligação que o
     * trouxe para cá.
     *
     * @param  array<string, array{confianca: float, motivo: string, a: int, b: int}>  $pares
     */
    private function melhorLigacaoIndireta(Demanda $membro, array $pares): float
    {
        $melhor = 0.0;

        foreach ($pares as $par) {
            if ($par['a'] === $membro->id || $par['b'] === $membro->id) {
                $melhor = max($melhor, $par['confianca']);
            }
        }

        /*
         * A indireta vale MENOS que a ligação que a produziu, e isso é
         * deliberado: ela é uma inferência a mais, e a fila é ordenada por
         * confiança — a direta tem de aparecer primeiro.
         */
        return round($melhor * 0.8, 3);
    }

    /** @param  array<string, array{confianca: float, motivo: string, a: int, b: int}>  $pares */
    private function motivoIndireto(Demanda $membro, array $pares): string
    {
        foreach ($pares as $par) {
            if ($par['a'] === $membro->id || $par['b'] === $membro->id) {
                return $par['motivo'];
            }
        }

        return 'sem ligação direta registrada.';
    }

    /**
     * Os pares que já receberam decisão humana — aceitos e recusados.
     *
     * @return array<string, true>
     */
    private function paresJaDecididos(): array
    {
        $mapa = [];

        foreach (SugestaoAgrupamento::where('estado', '!=', SugestaoAgrupamento::SUGERIDA)->get() as $s) {
            $mapa[$this->chaveDoPar($s->demanda_id, $s->principal_id)] = true;
        }

        return $mapa;
    }

    /**
     * A chave do par, em ordem estável.
     *
     * Estável porque o par (7, 12) e o par (12, 7) são o MESMO par: a proposta
     * nasce das duas pontas, e sem a ordenação a recusa de um lado não impediria
     * a proposta do outro.
     */
    private function chaveDoPar(int $a, int $b): string
    {
        return min($a, $b).':'.max($a, $b);
    }
}
