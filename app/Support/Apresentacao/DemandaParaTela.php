<?php

namespace App\Support\Apresentacao;

use App\Models\Demanda;
use App\Models\DemandaTramite;
use App\Support\Estrutura;
use App\Support\RetornoAoCanal;

/**
 * A demanda do banco na forma que a tela lê.
 *
 * Existe para que o model não precise conhecer a tela e a tela não precise
 * conhecer o model. O que está aqui é APRESENTAÇÃO: nomes de campo que o React
 * já usa, datas no formato que ele espera, o trâmite achatado. Nenhuma regra de
 * negócio mora neste arquivo — quem decide situação, prazo e agrupamento é o
 * {@see Demanda}.
 *
 * ## Datas saem em ISO, e isso não contraria a lei do dd/mm/aaaa
 *
 * A lei é sobre o que o USUÁRIO lê. Aqui a data é valor de comparação (a tela
 * ordena e compara com "hoje") e de `<input type="date">`. Quem escreve
 * dd/mm/aaaa é o componente, num lugar só. Mandar a data já formatada obrigaria
 * a tela a desformatar para comparar — e é aí que nasce a comparação de string
 * que diz que 02/01 vem antes de 10/12.
 */
class DemandaParaTela
{
    /**
     * O que a Caixa de Entrada e a tela de Denúncias mostram de cada demanda.
     *
     * Uma forma só para as duas telas — elas mostram a MESMA entidade, e dois
     * formatos obrigariam a manter dois mapeamentos da mesma coisa.
     *
     * @return array<string, mixed>
     */
    public static function completa(Demanda $demanda): array
    {
        return [
            'id' => $demanda->id,
            'protocolo' => $demanda->protocolo,

            // De onde veio e como chegou.
            'canal' => $demanda->canal,
            'origem' => self::rotuloDoCanal($demanda->canal),
            // Qual avulsa: pedido de superior ou ofício (nulo nos outros canais).
            'tipo_avulsa' => $demanda->tipo_avulsa,
            'tipo_avulsa_nome' => Demanda::TIPOS_AVULSA[$demanda->tipo_avulsa ?? ''] ?? null,
            'entrada' => $demanda->entrada,
            'documento_origem' => (string) ($demanda->numero_origem ?? ''),
            'protocolo_origem' => (string) ($demanda->numero_origem ?? ''),

            // Quem relatou.
            'anonima' => $demanda->anonima,
            'requerente' => $demanda->requerente,
            'documento' => $demanda->documento,
            'email' => $demanda->email,
            'telefone' => $demanda->telefone,
            // A Caixa de Entrada chama de "contato" o que as Denúncias chamam de
            // telefone. Os dois saem daqui apontando para a mesma coluna: a tela
            // que escolher um nome não cria um segundo campo no banco.
            'contato' => $demanda->telefone,

            // O que foi relatado.
            'assunto' => $demanda->assunto,
            /*
             * QUEM foi denunciado. Vai junto do assunto porque é a informação
             * que decide a pré-triagem: dois relatos de "mesas na calçada" na
             * mesma rua podem ser o mesmo bar ou dois estabelecimentos a
             * cinquenta metros um do outro, e o que separa os casos é o nome na
             * fachada — não o assunto, que é sempre o mesmo.
             */
            'estabelecimento' => (string) ($demanda->estabelecimento ?? ''),
            'denunciado' => (string) ($demanda->denunciado ?? ''),
            'documento_denunciado' => $demanda->documento_denunciado,
            'relato' => (string) ($demanda->relato ?? ''),
            'descricao' => (string) ($demanda->relato ?? ''),

            // Onde.
            'logradouro' => (string) ($demanda->logradouro ?? ''),
            'numero' => (string) ($demanda->numero ?? ''),
            'referencia' => (string) ($demanda->referencia ?? ''),
            'endereco' => self::endereco($demanda),
            'bairro' => (string) ($demanda->bairro ?? ''),
            'endereco_impreciso' => $demanda->endereco_impreciso,
            'latitude' => $demanda->latitude,
            'longitude' => $demanda->longitude,

            // Quando.
            'recebida_em' => $demanda->recebida_em->format('Y-m-d'),
            'recebida_em_hora' => $demanda->recebida_em->format('Y-m-d H:i'),
            'prazo' => $demanda->prazo_em?->format('Y-m-d'),
            'prazo_dias' => $demanda->diasDePrazo(),

            // Em que pé está.
            'situacao' => $demanda->situacao,
            /*
             * O RETORNO ao canal: o tipo que o canal pede (`tramite` / `processo`
             * / nulo) e o que já foi registrado. É o que faz o detalhe mostrar o
             * formulário ao chefe — ou a resposta dada, para quem chegar depois.
             */
            // A situação em três palavras, como a Caixa de Entrada a mostra, e se
            // o ciclo já fechou para o canal (é o que decide a aba "Respondidas").
            'situacao_resumida' => $demanda->situacaoResumida(),
            'respondida' => $demanda->respondida(),
            'passou_por_fiscalizacao' => $demanda->passouPorFiscalizacao(),
            /*
             * As FISCALIZAÇÕES desta demanda — uma por encaminhamento do chefe ao
             * líder —, com o caminho para abrir cada uma: todo o vai e vem do
             * processo fica consultável daqui, vistorias, fotos e documentos.
             */
            'fiscalizacoes' => $demanda->ciclos
                ->each(static fn ($c) => $c->setRelation('demanda', $demanda))
                ->map(CicloParaTela::resumo(...))
                ->values()
                ->all(),
            'retorno_ao_canal' => RetornoAoCanal::tipoDe($demanda),
            'retorno_em' => config("demandas.canais.{$demanda->canal}.retorno_em"),
            'resposta_ao_canal' => $demanda->respondida_ao_canal_em === null ? null : [
                'texto' => (string) $demanda->resposta_ao_canal,
                'em' => $demanda->respondida_ao_canal_em->format('Y-m-d H:i'),
                'por' => $demanda->respondidaPor?->name,
                'processo' => $demanda->processo_esalvador,
                // Enquanto a escrita na API está proibida, nada foi enviado: foi feito à mão.
                'enviado' => false,
            ],
            'area' => $demanda->area?->nome,
            /*
             * A equipe que o BAIRRO sugere, com as alternativas de divisa. É o
             * que a tela de encaminhamento pré-seleciona em cada linha: o chefe
             * confirma, em vez de escolher do zero trinta vezes. Nula quando o
             * bairro não está na estrutura — e a tela diz "sem equipe sugerida"
             * em vez de inventar uma.
             */
            'area_sugerida' => Estrutura::sugerirPorBairro($demanda->bairro),
            'equipe' => $demanda->equipe?->codigo,
            'operacao' => $demanda->operacao?->nome,

            // O agrupamento: dez denúncias que são um fato.
            'agrupada_em' => $demanda->principal?->protocolo,
            'agrupada_em_id' => $demanda->agrupada_em_id,
            'agregadas' => $demanda->relationLoaded('agregadas')
                ? $demanda->agregadas->map(static fn (Demanda $a): array => [
                    'id' => $a->id,
                    'protocolo' => $a->protocolo,
                    'documento_origem' => (string) ($a->numero_origem ?? ''),
                    'assunto' => $a->assunto,
                    'requerente' => $a->anonima ? null : $a->requerente,
                    'recebida_em' => $a->recebida_em->format('Y-m-d'),
                ])->values()->all()
                : [],
            'total_agregadas' => $demanda->relationLoaded('agregadas') ? $demanda->agregadas->count() : 0,

            'anexos' => $demanda->relationLoaded('anexos')
                ? $demanda->anexos->pluck('nome')->values()->all()
                : [],

            /*
             * O que a ÚLTIMA ida a campo produziu, no topo da demanda.
             *
             * É DERIVADO da fiscalização, nunca uma coluna: a grade mostra o
             * desfecho e a busca o reconhece como faceta, e uma cópia aqui
             * divergiria no dia em que a chefia mandasse nova vistoria. Nulo
             * enquanto ninguém foi ao ponto — e isso é resposta, não ausência.
             */
            ...self::ultimaIdaACampo($demanda),

            'tramites' => self::tramites($demanda),
        ];
    }

    /**
     * O resumo da última ida a campo — desfecho, o que se encontrou e o papel.
     *
     * @return array<string, mixed>
     */
    private static function ultimaIdaACampo(Demanda $demanda): array
    {
        $ultima = null;

        foreach ($demanda->tramites as $passo) {
            if ($passo->fiscalizacao !== null && $passo->fiscalizacao->desfecho !== null) {
                $ultima = $passo->fiscalizacao;
            }
        }

        if ($ultima === null) {
            return ['desfecho' => null, 'campo' => null, 'documento' => null];
        }

        return [
            'desfecho' => $ultima->desfecho,
            'campo' => [
                'quando' => ($ultima->concluida_em ?? $ultima->aberta_em)->format('Y-m-d'),
                'encontrado' => $ultima->alvo,
                'ambulante' => $ultima->ambulante?->nome,
                'equipamento' => $ultima->equipamento,
                'relato' => $ultima->consideracoes,
                'fotos' => $ultima->fotos->count(),
                'gps' => $ultima->latitude === null || $ultima->longitude === null
                    ? null
                    : $ultima->latitude.', '.$ultima->longitude,
                'precisao_m' => $ultima->precisao_m,
            ],
            'documento' => $ultima->documento === null
                ? null
                : DocumentoParaTela::completo($ultima->documento, $demanda->protocolo),
        ];
    }

    /**
     * O trâmite achatado para leitura.
     *
     * @return list<array<string, mixed>>
     */
    public static function tramites(Demanda $demanda): array
    {
        return $demanda->tramites->map(static fn (DemandaTramite $t): array => [
            /*
             * O conteúdo da ida a campo é LIDO da fiscalização, nunca copiado
             * para dentro do passo: o relato, o desfecho e as recomendações têm
             * um dono só. Copiá-los aqui faria o trâmite mostrar o texto antigo
             * no dia em que o fiscal corrigisse o registro.
             *
             * As chaves existem SEMPRE (nulas quando o passo não veio de campo)
             * porque a tela lê qualquer passo do mesmo jeito — chave ausente em
             * metade deles viraria leitura defensiva espalhada pelo front.
             */
            ...self::doCampo($t),
            'em' => $t->ocorrida_em->format('Y-m-d H:i'),
            'quem' => $t->quem(),
            'papel' => $t->papel,
            'o_que' => $t->acao,
            'detalhe' => (string) ($t->detalhe ?? ''),
            'situacao' => $t->situacao,
            /*
             * Os campos vão como LISTA de rótulo+valor, e não como mapa: o JSON
             * de um objeto PHP vazio vira `[]` e o de um preenchido vira `{}` —
             * a tela receberia dois tipos diferentes para a mesma chave e
             * quebraria no `.map` exatamente no caso sem campos.
             */
            'campos' => array_map(
                static fn (string $rotulo, mixed $valor): array => [
                    'rotulo' => $rotulo,
                    'valor' => (string) $valor,
                ],
                array_keys($t->campos ?? []),
                array_values($t->campos ?? []),
            ),
        ])->values()->all();
    }

    /**
     * O que a ida a campo acrescenta ao passo do trâmite.
     *
     * @return array<string, mixed>
     */
    private static function doCampo(DemandaTramite $passo): array
    {
        $f = $passo->fiscalizacao;

        if ($f === null) {
            return [
                'fiscalizacao' => null,
                'desfecho' => null,
                'consideracoes' => null,
                'recomendacoes' => [],
                'fotos' => [],
                'gps' => null,
                'precisao_m' => null,
                'documento' => null,
                'campo' => null,
            ];
        }

        return [
            'fiscalizacao' => $f->protocolo,
            'desfecho' => $f->desfecho,
            // A leitura do fiscal: é por ela que a chefia entende o que ele está
            // PEDINDO, e não só como a vistoria terminou.
            'consideracoes' => $f->consideracoes,
            // As CHAVES, nunca as frases: é a chave que o relatório soma.
            'recomendacoes' => $f->recomendacoes->pluck('chave')->values()->all(),
            'fotos' => $f->fotos->map(
                static fn ($foto): string => (string) ($foto->legenda ?? basename($foto->caminho)),
            )->values()->all(),
            'gps' => $f->latitude === null || $f->longitude === null
                ? null
                : $f->latitude.', '.$f->longitude,
            'precisao_m' => $f->precisao_m,
            /*
             * O documento vai na forma do IMPRESSO — com os campos e as caixas
             * do papel —, e não como um resumo: quem lê a denúncia precisa ver a
             * mesma coisa que está na via que o notificado levou.
             */
            'documento' => $f->documento === null
                ? null
                : DocumentoParaTela::completo($f->documento, $passo->demanda?->protocolo),
            /*
             * O que a equipe encontrou no ponto. A chave existe SEMPRE (nula fora
             * do passo de campo) porque a tela lê qualquer passo do mesmo jeito.
             */
            'campo' => [
                'quando' => ($f->concluida_em ?? $f->aberta_em)->format('Y-m-d'),
                'encontrado' => $f->alvo,
                'ambulante' => $f->ambulante?->nome,
                'equipamento' => $f->equipamento,
                'relato' => $f->consideracoes,
                'fotos' => $f->fotos->count(),
                'gps' => $f->latitude === null || $f->longitude === null
                    ? null
                    : $f->latitude.', '.$f->longitude,
                'precisao_m' => $f->precisao_m,
            ],
        ];
    }

    /** O endereço numa linha, como a grade mostra. */
    private static function endereco(Demanda $demanda): string
    {
        $partes = array_filter([
            $demanda->logradouro,
            $demanda->numero === null || $demanda->numero === '' ? null : 'nº '.$demanda->numero,
        ]);

        $linha = implode(', ', $partes);

        return $linha === '' ? (string) ($demanda->bairro ?? '') : $linha;
    }

    /**
     * O nome do canal como uma pessoa o lê.
     *
     * A tabela vive em `config/demandas.php` para que tela, validação e
     * relatório leiam a MESMA lista — escrita em cada um deles, um dia
     * discordariam e o relatório somaria "e-Salvador" e "E-Salvador" separados.
     */
    public static function rotuloDoCanal(string $canal): string
    {
        return (string) (config('demandas.canais.'.$canal.'.nome') ?? $canal);
    }
}
