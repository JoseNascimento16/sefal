<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\SugestaoAgrupamento;
use App\Support\Agrupamento\AnalisadorPorRegra;
use App\Support\Agrupamento\VarreduraDeAgrupamento;
use App\Support\PapelNaArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * A PRÉ-TRIAGEM: dez denúncias que são um fato.
 *
 * O e-Salvador não entrega casos organizados — entrega o que cada cidadão
 * escreveu. Dez pessoas relatam "mesas e cadeiras atrapalhando a via" em dez
 * protocolos e, quando a equipe chega, é o mesmo ambulante. Esta tela é onde o
 * coordenador junta os dez num registro só, ANTES de mandar alguém à rua.
 *
 * ## A máquina PROPÕE; quem agrupa é gente
 *
 * A varredura ({@see VarreduraDeAgrupamento}) deixa propostas na mesa, com a
 * confiança e o MOTIVO escrito. Nenhuma delas agrupa nada sozinha, e isso é
 * desenho, não cautela: o dia em que uma varredura noturna começar a agrupar por
 * conta própria, ninguém vai saber por que dez protocolos viraram um — e a
 * ouvidoria vai cobrar nove respostas que o sistema acha que já deu.
 *
 * A recusa é registrada com o mesmo cuidado da aceitação. Ela é a informação
 * mais cara daqui: é o coordenador dizendo "são dois bares diferentes", e é ela
 * que impede a varredura de propor o mesmo par amanhã.
 *
 * ## Agrupar à mão continua valendo
 *
 * O coordenador conhece a rua melhor que qualquer regra. Ele agrupa dois casos
 * que a máquina não ligou, e desagrupa o que ela ligou errado — as duas ações
 * exigem MOTIVO por escrito, porque as duas mudam o que o cidadão vai receber
 * como resposta.
 *
 * ## Por que as rotas nascem sob dois caminhos
 *
 * O mesmo controller atende `/retaguarda/denuncias/agrupamento/…` e
 * `/retaguarda/caixa-de-entrada/agrupamento/…`. A guarda de acesso deduz a tela
 * do primeiro trecho do caminho, então cada porta herda a permissão da tela onde
 * o coordenador já está — em vez de a pré-triagem virar uma terceira tela, com
 * uma terceira concessão para alguém esquecer de dar.
 */
class AgrupamentoDeDemandasController extends Controller
{
    /**
     * Roda a varredura e deixa as propostas na tela.
     *
     * É disparada por gente, e não por agendador, enquanto a régua estiver sendo
     * calibrada: o coordenador aperta, olha o que veio e diz se presta. Quando a
     * confiança do que ela propõe estiver assentada, ela vira tarefa da noite —
     * e aí o que muda é o gatilho, não a regra.
     */
    public function varrer(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $recortada = PapelNaArea::recorta($usuario);

        $efeito = (new VarreduraDeAgrupamento(new AnalisadorPorRegra))->executar(
            $recortada ? PapelNaArea::areas($usuario) : null,
        );

        /*
         * O RECORTE é dito na frase, e não deduzido por quem lê.
         *
         * Para o Chefe de Setor a varredura só olha a área dele. Sem essa
         * ressalva, "nenhuma repetição entre as denúncias abertas" parece falar
         * do universo — e ele conclui que a funcionalidade não faz nada, quando
         * ela fez exatamente o que devia e não achou nada NA ÁREA DELE.
         */
        $areas = $recortada ? PapelNaArea::areas($usuario) : [];
        $ondeOlhou = $areas === []
            ? ''
            : ' (só '.(count($areas) === 1 ? 'a área' : 'as áreas').' '.implode(', ', $areas).')';

        if ($efeito['propostas'] === 0) {
            /*
             * Nenhuma proposta é NOTÍCIA, não silêncio: significa que a fila não
             * tem repetição aparente — e o coordenador precisa saber disso para
             * não ficar esperando uma lista que não vem.
             */
            return back()->with('flash.sucesso', $efeito['ja_decididas'] > 0
                ? "Nenhuma denúncia repetida nova entre as {$efeito['analisadas']} abertas{$ondeOlhou}. "
                    ."{$efeito['ja_decididas']} já tinham sido decididas antes."
                : "Nenhuma denúncia repetida entre as {$efeito['analisadas']} abertas{$ondeOlhou}.");
        }

        return back()->with('flash.sucesso', $efeito['propostas'] === 1
            ? "1 possível repetição encontrada entre as {$efeito['analisadas']} denúncias abertas{$ondeOlhou}. Confira antes de agrupar."
            : "{$efeito['propostas']} possíveis repetições encontradas entre as {$efeito['analisadas']} denúncias abertas{$ondeOlhou}. Confira antes de agrupar.");
    }

    /**
     * ACEITA uma proposta: a denúncia passa a ser respondida pelo registro que
     * vai a campo.
     */
    public function aceitar(Request $request, SugestaoAgrupamento $sugestao): RedirectResponse
    {
        $dados = $request->validate([
            'observacao' => ['nullable', 'string', 'max:500'],
        ]);

        if ($sugestao->estado !== SugestaoAgrupamento::SUGERIDA) {
            return back()->with('flash.erro', 'Essa sugestão já foi decidida. Recarregue a tela.');
        }

        $agregada = $sugestao->demanda;
        $principal = $sugestao->principal;

        if ($agregada === null || $principal === null) {
            return back()->with('flash.erro', 'Uma das denúncias da sugestão não existe mais. Recarregue a tela.');
        }

        try {
            $agregada->agruparEm(
                $principal,
                $request->user(),
                /*
                 * O motivo que fica registrado é o da MÁQUINA, mais o que o
                 * coordenador acrescentou. Guardar só a observação dele apagaria
                 * o raciocínio que ele aceitou — e quem lesse o trâmite depois
                 * veria um agrupamento sem fundamento.
                 */
                trim($sugestao->motivo.' '.((string) ($dados['observacao'] ?? ''))),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('flash.erro', $e->getMessage());
        }

        $sugestao->decidir(SugestaoAgrupamento::ACEITA, $request->user(), $dados['observacao'] ?? null);

        // As outras propostas sobre a mesma denúncia perdem o sentido: ela já
        // tem dono. Deixá-las na tela ofereceria agrupá-la uma segunda vez.
        $this->descartarPendentesDe($agregada);

        return back()->with(
            'flash.sucesso',
            "{$agregada->protocolo} passou a ser respondida por {$principal->protocolo}. "
            .'A resposta da fiscalização vale para as duas.',
        );
    }

    /**
     * RECUSA uma proposta — e a recusa não some.
     *
     * É ela que impede a varredura de propor o mesmo par amanhã. Um assistente
     * que insiste no que já foi negado é pior do que nenhum: o coordenador para
     * de ler a lista, e aí as propostas boas também se perdem.
     */
    public function recusar(Request $request, SugestaoAgrupamento $sugestao): RedirectResponse
    {
        $dados = $request->validate([
            'observacao' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'observacao.required' => 'Escreva por que não são o mesmo caso — é o que impede o sistema de propor isso de novo.',
            'observacao.min' => 'O motivo está curto demais para orientar a próxima varredura.',
        ]);

        if ($sugestao->estado !== SugestaoAgrupamento::SUGERIDA) {
            return back()->with('flash.erro', 'Essa sugestão já foi decidida. Recarregue a tela.');
        }

        $sugestao->decidir(SugestaoAgrupamento::RECUSADA, $request->user(), $dados['observacao']);

        return back()->with('flash.sucesso', 'Sugestão recusada. O sistema não vai propor esse par de novo.');
    }

    /**
     * JUNTA à mão as que o coordenador escolheu — o caminho para o que a regra
     * não achou.
     *
     * ## Por que ele precisa existir
     *
     * A varredura enxerga o que a regra alcança: mesmo bairro, mesma rua,
     * palavras em comum, o nome da fachada. Ela não enxerga "é aquele camelô da
     * banca azul que todo mundo descreve de um jeito diferente", nem o caso em
     * que um cidadão escreveu o nome da rua errado. Sem uma porta manual, esses
     * casos ficam sem saída nenhuma: o coordenador vê que são o mesmo fato e não
     * tem o que clicar — e acaba mandando a equipe duas vezes ao mesmo ponto,
     * que é exatamente o que a pré-triagem existe para evitar.
     *
     * A régua aqui é mais baixa que a da máquina, e de propósito: quem conhece a
     * rua é ele. O que o sistema exige em troca é o MOTIVO por escrito, porque é
     * o texto que o cidadão encontra no trâmite do protocolo dele.
     *
     * ## Por que N de uma vez, e não uma a uma
     *
     * Dez relatos do mesmo ponto é o caso comum, não a exceção. Juntar um por um
     * faria o coordenador escrever o mesmo motivo dez vezes — e, na décima, ele
     * escreveria "idem".
     */
    public function juntar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'demandas' => ['required', 'array', 'min:2'],
            'demandas.*' => ['integer'],
            'principal_id' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'demandas.min' => 'Escolha ao menos duas denúncias para juntar.',
            'principal_id.required' => 'Escolha o registro que vai levar o caso a campo.',
            'motivo.required' => 'Escreva por que são o mesmo caso: é o que o cidadão vai ler no trâmite da denúncia dele.',
            'motivo.min' => 'O motivo está curto demais para explicar o agrupamento a quem ler depois.',
        ]);

        /*
         * A principal tem de estar ENTRE as escolhidas. Sem essa checagem daria
         * para pendurar a leva num registro que o coordenador não estava olhando
         * — e ele não teria como perceber.
         */
        if (! in_array($dados['principal_id'], $dados['demandas'], true)) {
            return back()->with('flash.erro', 'O registro que vai a campo precisa estar entre as denúncias escolhidas.');
        }

        $principal = Demanda::find($dados['principal_id']);

        if ($principal === null) {
            return back()->with('flash.erro', 'O registro escolhido não existe mais. Recarregue a tela.');
        }

        $agregadas = Demanda::whereIn('id', $dados['demandas'])
            ->where('id', '!=', $principal->id)
            ->get();

        $juntadas = 0;
        $recusadas = [];

        foreach ($agregadas as $agregada) {
            try {
                $agregada->agruparEm($principal, $request->user(), $dados['motivo']);
                $this->descartarPendentesDe($agregada);
                $juntadas++;
            } catch (InvalidArgumentException $e) {
                // A recusa do model vem com o motivo escrito. Guardada e dita no
                // fim: parar na primeira deixaria o coordenador sem saber quais
                // das dez entraram.
                $recusadas[] = $agregada->protocolo.' — '.$e->getMessage();
            }
        }

        if ($juntadas === 0) {
            return back()->with('flash.erro', 'Nenhuma denúncia foi juntada. '.implode(' ', $recusadas));
        }

        $frase = $juntadas === 1
            ? "1 denúncia passou a ser respondida por {$principal->protocolo}."
            : "{$juntadas} denúncias passaram a ser respondidas por {$principal->protocolo}.";

        return back()->with(
            'flash.sucesso',
            $recusadas === [] ? $frase : $frase.' Ficaram de fora: '.implode(' ', $recusadas),
        );
    }

    /**
     * AGRUPA à mão — o coordenador conhece a rua melhor que qualquer regra.
     */
    public function agrupar(Request $request, Demanda $demanda): RedirectResponse
    {
        $dados = $request->validate([
            'principal_id' => ['required', 'integer', 'different:demanda'],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'principal_id.required' => 'Escolha o registro que vai levar o caso a campo.',
            'motivo.required' => 'Escreva por que são o mesmo caso: é o que o cidadão vai ler no trâmite da denúncia dele.',
            'motivo.min' => 'O motivo está curto demais para explicar o agrupamento a quem ler depois.',
        ]);

        $principal = Demanda::find((int) $dados['principal_id']);

        if ($principal === null) {
            return back()->with('flash.erro', 'O registro escolhido não existe mais. Recarregue a tela.');
        }

        try {
            $demanda->agruparEm($principal, $request->user(), $dados['motivo']);
        } catch (InvalidArgumentException $e) {
            // A recusa do model vem com o motivo escrito: ela chega ao usuário
            // como está, em vez de virar "não foi possível".
            return back()->with('flash.erro', $e->getMessage());
        }

        $this->descartarPendentesDe($demanda);

        return back()->with(
            'flash.sucesso',
            "{$demanda->protocolo} passou a ser respondida por {$principal->protocolo}.",
        );
    }

    /**
     * DESAGRUPA — e isso é barato de propósito.
     *
     * A associação pode estar errada: "mesas na calçada" pode ser dois
     * estabelecimentos a cinquenta metros um do outro. Como nada foi fundido,
     * desagrupar devolve a denúncia à fila no estado em que ela chegou. Se o
     * coordenador tivesse de temer a irreversibilidade, deixaria o erro de pé.
     */
    public function desagrupar(Request $request, Demanda $demanda): RedirectResponse
    {
        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motivo.required' => 'Escreva por que não são o mesmo caso: a denúncia volta à triagem e alguém vai precisar entender por quê.',
            'motivo.min' => 'O motivo está curto demais para explicar a quem ler depois.',
        ]);

        if (! $demanda->agregada()) {
            return back()->with('flash.erro', 'Essa denúncia não está agrupada. Recarregue a tela.');
        }

        $demanda->desagrupar($request->user(), $dados['motivo']);

        return back()->with(
            'flash.sucesso',
            "{$demanda->protocolo} voltou para a triagem, com o motivo registrado.",
        );
    }

    /**
     * LIBERA a denúncia da pré-triagem: ela passa a ser um caso entendido.
     *
     * É o fim da etapa, e o começo da outra. Enquanto está em pré-triagem, a
     * pergunta é "quantos fatos isto é?"; depois dela, a pergunta é "o que se faz
     * com este fato?" — e essa é a da Caixa, onde o coordenador encaminha ou
     * devolve. São duas decisões diferentes, e misturá-las foi o que fez a leva
     * crua do e-Salvador chegar à mesa como dez casos.
     *
     * ── Por que o ato é explícito, e não automático ─────────────────────────
     *
     * Nada impediria o sistema de mandar para a Caixa tudo que a varredura não
     * ligou a ninguém. Mas "a máquina não achou repetição" não é o mesmo que
     * "alguém olhou": a varredura só enxerga o que a regra alcança, e é o
     * coordenador que conhece a rua. Liberar é ele dizendo que olhou.
     *
     * ── Liberar NÃO desfaz agrupamento ──────────────────────────────────────
     *
     * As agregadas seguem penduradas na principal e vão junto com ela — não
     * ganham vida própria ao passar. Por isso o filtro é `emPreTriagem`, que já
     * exclui quem virou agregada (ela está em `Agrupada`).
     */
    public function liberar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'demandas' => ['required', 'array', 'min:1'],
            'demandas.*' => ['integer'],
        ], [
            'demandas.required' => 'Escolha ao menos uma denúncia para liberar.',
        ]);

        $demandas = Demanda::emPreTriagem()
            ->whereIn('id', $dados['demandas'])
            ->get();

        if ($demandas->isEmpty()) {
            return back()->with('flash.erro', 'Nenhuma dessas denúncias está em pré-triagem. Recarregue a tela.');
        }

        foreach ($demandas as $demanda) {
            $demanda->registrar(
                acao: 'Pré-triagem concluída',
                situacao: Demanda::RECEBIDA,
                papel: DemandaTramite::PAPEL_COORDENADOR,
                autor: $request->user(),
                detalhe: $demanda->agregadas()->count() > 0
                    ? 'Caso consolidado: responde também pelas denúncias agregadas a ele.'
                    : 'Sem repetição a consolidar. Segue para a triagem do coordenador.',
            );
        }

        /*
         * Só caem as propostas em que a liberada era a AGREGADA.
         *
         * Ela não pode mais ser agrupada — agregar só vale para quem ainda está
         * em pré-triagem —, então a proposta virou uma decisão que ninguém pode
         * tomar. Já as propostas em que ela é a PRINCIPAL continuam de pé, e é
         * assim que tem de ser: quando a leva nova repete o que já está em campo,
         * é o caso em campo que responde pelas novas. Descartar os dois lados
         * fazia liberar a principal apagar o grupo inteiro — o coordenador
         * clicava em "liberar" e as seis propostas sumiam da mesa dele.
         */
        SugestaoAgrupamento::where('estado', SugestaoAgrupamento::SUGERIDA)
            ->whereIn('demanda_id', $demandas->pluck('id'))
            ->update([
                'estado' => SugestaoAgrupamento::RECUSADA,
                'observacao' => 'Descartada: a denúncia saiu da pré-triagem sem ser agrupada.',
                'decidida_em' => now(),
            ]);

        return back()->with('flash.sucesso', $demandas->count() === 1
            ? "{$demandas->first()->protocolo} passou para a Caixa, à espera do encaminhamento."
            : "{$demandas->count()} denúncias passaram para a Caixa, à espera do encaminhamento.");
    }

    /**
     * As propostas pendentes que envolvem esta denúncia deixam de fazer sentido
     * quando ela ganha um dono.
     *
     * Descartadas com o estado `recusada` e um motivo que diz o que houve — e
     * não apagadas: a varredura precisa saber que este par não deve voltar, e
     * uma linha apagada não conta história nenhuma.
     */
    private function descartarPendentesDe(Demanda $demanda): void
    {
        SugestaoAgrupamento::where('estado', SugestaoAgrupamento::SUGERIDA)
            ->where(fn ($q) => $q->where('demanda_id', $demanda->id)->orWhere('principal_id', $demanda->id))
            ->update([
                'estado' => SugestaoAgrupamento::RECUSADA,
                'observacao' => 'Descartada: a denúncia já foi agrupada por outro caminho.',
                'decidida_em' => now(),
            ]);
    }
}
