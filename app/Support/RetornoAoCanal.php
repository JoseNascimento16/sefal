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
     * Registra o retorno — e envia, quando a integração estiver ligada e liberada.
     *
     * @throws DomainException quando o ato não cabe (canal sem retorno, demanda não concluída, já respondida)
     * @throws EscritaNaoLiberada quando a integração está ligada mas a escrita não foi liberada
     */
    public function registrar(Demanda $demanda, User $autor, string $texto, ?string $processo): DemandaTramite
    {
        $tipo = self::tipoDe($demanda);

        if ($tipo === null) {
            throw new DomainException(
                'Este canal não recebe retorno pelo sistema: o Fala Salvador é respondido pelo líder no próprio '
                .'canal; ofício e pedido de licença não voltam por aqui.',
            );
        }

        if ($demanda->situacao !== Demanda::CONCLUIDA) {
            throw new DomainException(
                "A demanda {$demanda->protocolo} está \"{$demanda->situacao}\": só o que foi CONCLUÍDO volta ao canal — "
                .'responder antes do resultado seria prometer o que a fiscalização ainda não apurou.',
            );
        }

        if ($demanda->respondida_ao_canal_em !== null) {
            throw new DomainException(
                "A demanda {$demanda->protocolo} já teve o retorno registrado em "
                .$demanda->respondida_ao_canal_em->format('d/m/Y H:i').'. O retorno é um só; para complementar, use o trâmite.',
            );
        }

        $identificador = $tipo === self::TRAMITE
            ? trim((string) ($processo ?? $demanda->numero_origem ?? ''))
            : trim((string) $processo);

        if ($identificador === '' && ! $this->esalvador->ligado()) {
            throw new DomainException(
                $tipo === self::TRAMITE
                    ? 'A demanda não tem o número do processo de origem: informe-o para registrar a resposta.'
                    : 'Informe o número do processo que você abriu no e-Salvador: com a integração desligada, é ele que prova a abertura.',
            );
        }

        // Com a integração ligada e liberada, envia; desligada, devolve null e nada sai.
        $enviado = $tipo === self::TRAMITE
            ? $this->esalvador->responderProcesso($identificador, $texto)
            : $this->esalvador->abrirProcesso([
                'assunto' => $demanda->assunto,
                'requerente' => $demanda->requerente,
                'descricao' => $texto,
            ]);

        if ($enviado !== null && $identificador === '') {
            $identificador = (string) ($enviado['identificador'] ?? '');
        }

        return $demanda->registrar(
            acao: $tipo === self::TRAMITE ? 'Resposta registrada no e-Salvador' : 'Processo aberto no e-Salvador',
            situacao: $demanda->situacao,
            papel: DemandaTramite::PAPEL_CHEFE_DE_SETOR,
            autor: $autor,
            detalhe: $texto,
            campos: array_filter([
                'Processo no e-Salvador' => $identificador,
                'Enviado pela integração' => $enviado === null ? 'Não — registrado aqui e feito à mão no e-Salvador' : 'Sim',
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
