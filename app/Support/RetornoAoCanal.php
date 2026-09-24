<?php

namespace App\Support;

use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Models\User;
use App\Services\ESalvador\ESalvador;
use App\Services\ESalvador\EscritaNaoLiberada;
use DomainException;
use Illuminate\Support\Facades\Date;

/**
 * O RETORNO AO CANAL — o ato do Chefe de Setor que fecha o ciclo de uma demanda.
 *
 * Concluído o trabalho de campo, o chefe devolve o resultado a quem pediu:
 *
 *  - demanda do **e-Salvador** → RESPOSTA no processo de origem (lá, um trâmite);
 *  - demanda **avulsa** (ligação/e-mail de superior) → ABERTURA de um processo
 *    no e-Salvador com o resultado, porque a avulsa não tem processo.
 *
 * Qual dos dois cabe a cada canal está na config (`demandas.canais.*.retorno`):
 * `tramite`, `processo` ou nada — o Fala Salvador é respondido pelo líder no
 * próprio canal, e ofício/licença não voltam por sistema.
 *
 * A escrita na API está proibida por enquanto ({@see ESalvador}): o ato fica
 * REGISTRADO aqui — texto, quem, quando, número do processo — e o chefe o faz à
 * mão no e-Salvador. Por isso, com a integração desligada, o número do processo
 * é OBRIGATÓRIO na abertura (é a prova de que ele abriu) e vem preenchido na
 * resposta (é o `numero_origem`). Quando a escrita for liberada, o mesmo ato
 * envia e grava o que a API devolver.
 */
class RetornoAoCanal
{
    public const TRAMITE = 'tramite';

    public const PROCESSO = 'processo';

    public function __construct(private readonly ESalvador $esalvador) {}

    /** O tipo de retorno que o canal da demanda pede, ou `null` se não há retorno por sistema. */
    public static function tipoDe(Demanda $demanda): ?string
    {
        $tipo = config("demandas.canais.{$demanda->canal}.retorno");

        return in_array($tipo, [self::TRAMITE, self::PROCESSO], true) ? $tipo : null;
    }

    /**
     * O ato cabe AGORA? — o motivo quando não cabe, `null` quando cabe.
     *
     * Cabe quando a fiscalização já produziu resultado: a demanda está
     * `Concluída`, ou VOLTOU ao chefe (`Recebida`) depois de ter ido a campo — é
     * o "encaminhar ao Chefe de Setor" do líder, para ele deliberar. E uma vez só.
     */
    public static function impedimento(Demanda $demanda): ?string
    {
        if (self::tipoDe($demanda) === null) {
            return 'Este canal não recebe retorno pelo sistema: o Fala Salvador é respondido pelo líder no próprio canal.';
        }

        if ($demanda->respondida_ao_canal_em !== null) {
            return "A demanda {$demanda->protocolo} já teve o retorno registrado em "
                .$demanda->respondida_ao_canal_em->format('d/m/Y H:i').'. O retorno é um só; para complementar, use o trâmite.';
        }

        $voltouDaRua = in_array($demanda->situacao, [Demanda::RECEBIDA, Demanda::EM_PRE_TRIAGEM], true)
            && $demanda->passouPorFiscalizacao();

        if ($demanda->situacao !== Demanda::CONCLUIDA && ! $voltouDaRua) {
            return "A demanda {$demanda->protocolo} está \"{$demanda->situacao}\": só o que a fiscalização já CONCLUIU volta ao "
                .'canal — responder antes do resultado seria prometer o que ninguém apurou.';
        }

        return null;
    }

    /**
     * Registra o retorno — e envia, quando a integração estiver ligada e liberada.
     *
     * `$semProcesso` é a deliberação que só a AVULSA tem: o chefe encerra com a
     * fiscalização, sem abrir processo no e-Salvador (dono, 24/09/2026).
     *
     * @throws DomainException quando o ato não cabe
     * @throws EscritaNaoLiberada quando a integração está ligada mas a escrita não foi liberada
     */
    public function registrar(Demanda $demanda, User $autor, string $texto, ?string $processo, bool $semProcesso = false): DemandaTramite
    {
        if (($motivo = self::impedimento($demanda)) !== null) {
            throw new DomainException($motivo);
        }

        $tipo = self::tipoDe($demanda);
        $onde = (string) (config("demandas.canais.{$demanda->canal}.retorno_em") ?? 'e-Salvador');

        if ($semProcesso && $tipo !== self::PROCESSO) {
            throw new DomainException('Encerrar sem processo é deliberação da avulsa; a demanda de canal é respondida no canal.');
        }

        $identificador = match (true) {
            $semProcesso => '',
            $tipo === self::TRAMITE => trim((string) ($processo ?? $demanda->numero_origem ?? '')),
            default => trim((string) $processo),
        };

        $pelaIntegracao = $onde === 'e-Salvador' && $this->esalvador->ligado();

        if (! $semProcesso && $identificador === '' && ! $pelaIntegracao) {
            throw new DomainException(
                $tipo === self::TRAMITE
                    ? 'A demanda não tem o número do processo de origem: informe-o para registrar a resposta.'
                    : "Informe o número do processo que você abriu no {$onde} — ou encerre sem processo: com a integração desligada, é o número que prova a abertura.",
            );
        }

        /*
         * Só o e-Salvador tem cliente. Desligado, ele devolve null e nada sai;
         * ligado, recusa a escrita até ela ser liberada. O e-Protocolo não tem API:
         * o retorno é registrado aqui e feito lá, à mão.
         */
        $enviado = null;

        if (! $semProcesso && $onde === 'e-Salvador') {
            $enviado = $tipo === self::TRAMITE
                ? $this->esalvador->responderProcesso($identificador, $texto)
                : $this->esalvador->abrirProcesso([
                    'assunto' => $demanda->assunto,
                    'requerente' => $demanda->requerente,
                    'descricao' => $texto,
                ]);
        }

        if ($enviado !== null && $identificador === '') {
            $identificador = (string) ($enviado['identificador'] ?? '');
        }

        $acao = match (true) {
            $semProcesso => 'Encerrada com a fiscalização, sem processo',
            $tipo === self::TRAMITE => "Resposta registrada no {$onde}",
            default => "Processo aberto no {$onde}",
        };

        return $demanda->registrar(
            acao: $acao,
            // Respondida, a demanda está concluída — inclusive a que voltou ao chefe.
            situacao: Demanda::CONCLUIDA,
            papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR,
            autor: $autor,
            detalhe: $texto,
            campos: array_filter([
                "Processo no {$onde}" => $identificador,
                'Deliberação' => $semProcesso ? 'Encerrar sem abrir processo' : null,
                'Enviado pela integração' => $semProcesso ? null
                    : ($enviado === null ? "Não — registrado aqui e feito à mão no {$onde}" : 'Sim'),
            ]),
            mudancas: [
                'respondida_ao_canal_em' => Date::now(),
                'resposta_ao_canal' => $texto,
                'processo_esalvador' => $identificador !== '' ? $identificador : null,
                'respondida_por_id' => $autor->id,
            ],
        );
    }
}
