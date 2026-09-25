<?php

namespace App\Support\Apresentacao;

use App\Models\DocumentoCampo;
use App\Models\Fiscalizacao;
use Illuminate\Support\Facades\Date;

/**
 * A fiscalização do banco na forma que a fila do Chefe de Setor lê.
 *
 * Mesma razão dos outros mapeadores: apresentação num lugar só. Duas contas
 * moram aqui porque dependem da data do SERVIDOR e não podem ser feitas no
 * navegador — lá elas dependeriam do relógio e do fuso da máquina de quem abre a
 * tela, e "há 3 dias" viraria "há 4" a partir das 21h num fuso negativo:
 *
 *  - `dias_parado`, quanto o registro espera a leitura da chefia;
 *  - `vence_em`, o prazo do documento entregue ao notificado.
 */
class FiscalizacaoParaTela
{
    /** @return array<string, mixed> */
    public static function completa(Fiscalizacao $f): array
    {
        $demanda = $f->demanda;

        return [
            'id' => $f->id,
            'protocolo' => $f->protocolo,

            /*
             * A ORIGEM em palavras — o que a chefia lê. A coluna guarda a chave
             * (`demanda`/`operacao`/`avulsa`), que é o que a regra usa; aqui ela
             * vira frase, e o nome da operação ou o protocolo da denúncia entra
             * como `referencia`.
             */
            'origem' => self::origemEmPalavras($f),
            'referencia' => $f->operacao?->nome ?? $demanda?->protocolo ?? 'Ronda da equipe',
            'denuncia_protocolo' => $demanda?->protocolo,
            'situacao_da_origem' => $demanda?->situacao,

            'concluida_em' => ($f->concluida_em ?? $f->aberta_em)->format('Y-m-d H:i'),
            'area' => (string) ($f->equipe?->area?->nome ?? ''),
            'equipe' => (string) ($f->equipe?->codigo ?? ''),
            'fiscal' => self::assinatura($f),

            'endereco' => self::endereco($f),
            'bairro' => (string) ($f->bairro ?? ''),
            'ponto_de_referencia' => $f->ponto_de_referencia,
            // O GPS em texto, como a tela mostra. A verdade são as duas colunas
            // decimais; isto é leitura.
            'gps' => $f->latitude === null || $f->longitude === null
                ? null
                : $f->latitude.', '.$f->longitude,
            'latitude' => $f->latitude,
            'longitude' => $f->longitude,
            'precisao_m' => $f->precisao_m,

            'alvo' => $f->alvo,
            'equipamento' => $f->equipamento,
            'ambulante' => $f->ambulante?->nome,
            'fotos' => $f->relationLoaded('fotos')
                ? $f->fotos->map(static fn ($foto): string => (string) ($foto->legenda ?? basename($foto->caminho)))->values()->all()
                : [],
            // As mesmas fotos como ARQUIVOS: para ver e baixar (dono, 25/09/2026).
            'arquivos' => ($f->relationLoaded('fotos') ? $f->fotos : $f->fotos()->get())
                ->map(ArquivoParaTela::foto(...))->values()->all(),

            'desfecho' => $f->desfecho,
            'consideracoes' => $f->consideracoes,
            'recomendacoes' => $f->relationLoaded('recomendacoes') ? $f->chavesDeRecomendacao() : [],

            'documento' => self::documento($f->documento),
            /*
             * O PRAZO como a fila o lê — a única informação que continua correndo
             * depois de a chefia dar ciência, e por isso ela mora no acervo.
             *
             * Só a Notificação Preliminar produz prazo: ela dá um tempo para
             * regularizar, e alguém tem de voltar no vencimento. O Auto de
             * Apreensão não pede volta ao ponto, e por isso devolve nulo — não
             * zero: a tela que confundir os dois mostraria "vence hoje" num papel
             * que nunca vence.
             *
             * `vencido` vem junto com `dias` e SAI DA MESMA CONTA: dois campos
             * para a mesma verdade, calculados em lugares diferentes, um dia
             * diriam "vence em 3 dias" e "vencido" ao mesmo tempo.
             */
            'prazo' => self::prazo($f->documento),

            // ── O ato da chefia ─────────────────────────────────────────────
            'estado' => $f->situacao,
            /*
             * Quantos dias o registro está esperando a leitura da chefia. Nulo
             * depois de decidido: o que já foi lido não está parado, e um número
             * ali faria a fila cobrar o que já saiu dela.
             */
            'dias_parado' => $f->situacao === Fiscalizacao::AGUARDANDO_LEITURA && $f->concluida_em !== null
                ? (int) $f->concluida_em->startOfDay()->diffInDays(Date::now()->startOfDay())
                : null,
            /*
             * O ato do Chefe de Setor sobre este registro, quando houve um.
             * Declarado sempre (nulo quando não houve) porque a tela o lê de
             * qualquer linha — chave ausente em metade delas viraria leitura
             * defensiva espalhada pelo front.
             */
            'decisao' => self::decisao($f),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function documento(?DocumentoCampo $documento): ?array
    {
        if ($documento === null) {
            return null;
        }

        return [
            'tipo' => $documento->tipo,
            'numero' => $documento->numero,
            'vence_em' => $documento->prazo_ate?->format('Y-m-d'),
            'dias_de_prazo' => $documento->diasDePrazo(),
            'prazo_rotulo' => $documento->prazo_chave === null
                ? null
                : (string) config('prototipo_documentos_campo.prazos_np.'.$documento->prazo_chave.'.rotulo'),
            'notificado' => $documento->notificado,
        ];
    }

    /**
     * A decisão da chefia, lida do último passo da demanda quando ela existe.
     *
     * Registro avulso não tem demanda atrás, e por isso a decisão dele mora na
     * própria fiscalização — a situação diz o QUE foi decidido, e é isso que a
     * fila mostra.
     *
     * @return array<string, mixed>|null
     */
    private static function decisao(Fiscalizacao $f): ?array
    {
        if ($f->situacao === Fiscalizacao::AGUARDANDO_LEITURA || $f->situacao === Fiscalizacao::EM_CAMPO) {
            return null;
        }

        $passo = $f->demanda?->ultimoTramite();

        return [
            'estado' => $f->situacao,
            'em' => ($f->decidida_em ?? $passo?->ocorrida_em ?? $f->updated_at)?->format('Y-m-d H:i'),
            // O autor vem do PRÓPRIO registro: a avulsa não tem trâmite de
            // demanda atrás, e ler só de lá deixaria metade das decisões sem dono.
            'quem' => $f->decididaPor?->name ?? $passo?->quem(),
            'detalhe' => $f->decisao_detalhe ?? $passo?->detalhe,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function prazo(?DocumentoCampo $documento): ?array
    {
        if ($documento?->prazo_ate === null) {
            return null;
        }

        $dias = $documento->diasDePrazo();

        return [
            'vence_em' => $documento->prazo_ate->format('Y-m-d'),
            'dias' => $dias,
            'vencido' => $dias !== null && $dias < 0,
            /*
             * O prazo em PALAVRAS. Sai daqui, e não da tela, porque o relatório
             * imprime a mesma frase — e escrita nos dois lugares ela diria
             * "vencido há 2 dias" num e "2 dias em atraso" no outro, para o mesmo
             * documento.
             */
            'texto' => self::prazoEmPalavras($dias),
            'rotulo' => $documento->prazo_chave === null
                ? null
                : (string) config('prototipo_documentos_campo.prazos_np.'.$documento->prazo_chave.'.rotulo'),
            'notificado' => $documento->notificado,
        ];
    }

    /** O prazo em palavras, para a grade e para o relatório. */
    private static function prazoEmPalavras(?int $dias): string
    {
        return match (true) {
            $dias === null => '—',
            $dias < -1 => 'vencido há '.abs($dias).' dias',
            $dias === -1 => 'vencido ontem',
            $dias === 0 => 'vence hoje',
            $dias === 1 => 'vence amanhã',
            default => 'vence em '.$dias.' dias',
        };
    }

    /** Quem assina a vistoria: o fiscal e a equipe dele. */
    private static function assinatura(Fiscalizacao $f): string
    {
        $nome = trim((string) ($f->fiscal?->name ?? ''));
        $equipe = (string) ($f->equipe?->codigo ?? '');

        if ($nome === '') {
            return $equipe === '' ? 'Equipe não informada' : "Equipe {$equipe}";
        }

        return $equipe === '' ? $nome : "{$nome} · Equipe {$equipe}";
    }

    private static function endereco(Fiscalizacao $f): string
    {
        $numero = trim((string) ($f->numero ?? ''));

        return trim((string) $f->logradouro).($numero === '' ? '' : ', '.$numero);
    }

    /**
     * De onde veio a ordem, em palavras.
     *
     * O protótipo declarava isso como texto livre ("Operação planejada", "Ronda
     * da equipe"); aqui a frase é DERIVADA da origem gravada, para que o
     * relatório possa somar por chave e a tela continuar lendo frase.
     */
    private static function origemEmPalavras(Fiscalizacao $f): string
    {
        return match ($f->origem) {
            Fiscalizacao::ORIGEM_DEMANDA => 'Denúncia direcionada',
            Fiscalizacao::ORIGEM_OPERACAO => 'Operação planejada',
            default => 'Ronda da equipe',
        };
    }
}
