<?php

namespace App\Support\Apresentacao;

use App\Models\DocumentoCampo;
use App\Support\Texto;

/**
 * O documento lavrado em rua, na forma do IMPRESSO.
 *
 * A Notificação Preliminar e o Auto de Apreensão são formulários de papel, com
 * campos e caixas próprios. A Retaguarda os LÊ (não os emite), e quem lê precisa
 * ver a mesma coisa que está na via que o notificado levou — inclusive os
 * motivos assinalados e as penalidades previstas.
 *
 * ## A redação mora no CATÁLOGO, e o documento guarda só a CHAVE
 *
 * `motivos`, `sanções`, `fundamentação` e `destinação` são gravados como chave
 * (`puxada`, `autuacao`, `48h`) e resolvidos aqui contra
 * `config/prototipo_documentos_campo.php`. Duas consequências deliberadas:
 *
 *  - a redação do impresso pode ser corrigida sem reescrever o passado;
 *  - o relatório soma por chave, e não por frase — texto melhorado não vira
 *    dois motivos diferentes na contagem.
 *
 * ⚠️ Chave desconhecida DESAPARECE da lista, sem estourar. É a pior forma de
 * errar aqui (o documento chega com menos motivos do que o fiscal marcou, e
 * nada acusa), e por isso existe teste-lei conferindo que toda chave semeada
 * existe no catálogo.
 *
 * ## Os dois formulários não têm os mesmos campos
 *
 * A NP fala de prazo para regularizar; o AA fala de guarda dos bens e não tem
 * prazo de regularização nenhum. Um molde só para os dois faria a tela mostrar
 * "vence em" num papel que nunca vence.
 */
class DocumentoParaTela
{
    /** @return array<string, mixed> */
    public static function completo(DocumentoCampo $documento, ?string $referencia = null): array
    {
        $comum = [
            'tipo' => $documento->tipo,
            'numero' => $documento->numero,
            'titulo' => (string) config('prototipo_documentos_campo.titulos.'.$documento->tipo, ''),
            'lavrado_em' => $documento->emitido_em->format('Y-m-d'),
            'notificado' => $documento->notificado,
            /*
             * A linha "Agente fiscal" do impresso pede nome E MATRÍCULA: é a
             * matrícula que identifica quem lavrou numa eventual defesa, e o nome
             * sozinho não serve para isso.
             */
            'agente' => self::agente($documento),
            'assinaturas' => self::assinaturas($documento),
        ];

        return $documento->tipo === DocumentoCampo::AUTO
            ? [...$comum, ...self::autoDeApreensao($documento, $referencia)]
            : [...$comum, ...self::notificacao($documento, $referencia)];
    }

    /**
     * A NOTIFICAÇÃO PRELIMINAR: dá prazo para regularizar.
     *
     * @return array<string, mixed>
     */
    private static function notificacao(DocumentoCampo $documento, ?string $referencia): array
    {
        $dados = (array) ($documento->dados ?? []);
        $prazo = (array) config('prototipo_documentos_campo.prazos_np.'.((string) $documento->prazo_chave), []);

        $motivos = [];

        foreach ((array) ($documento->motivos ?? []) as $chave) {
            $motivo = (array) config('prototipo_documentos_campo.motivos_np.'.((string) $chave), []);

            if ($motivo === []) {
                continue;
            }

            $complemento = trim((string) ($dados['complementos'][(string) $chave] ?? ''));
            $motivos[] = (string) $motivo['texto'].($complemento === '' ? '' : ": {$complemento}");
        }

        // A 20ª caixa do impresso é "Outros", campo livre — e ela vai no FIM da
        // lista, como está no papel.
        if (trim((string) ($dados['outros'] ?? '')) !== '') {
            $motivos[] = 'Outros: '.trim((string) $dados['outros']);
        }

        return [
            'vence_em' => $documento->prazo_ate?->format('Y-m-d'),
            'vence_em_dias' => $documento->diasDePrazo(),
            'prazo_rotulo' => isset($prazo['rotulo']) ? (string) $prazo['rotulo'] : null,
            'campos' => [
                ['rotulo' => 'Referência', 'valor' => self::ou($referencia)],
                ['rotulo' => 'Nome', 'valor' => self::ou($documento->notificado)],
                ['rotulo' => 'Endereço', 'valor' => self::ou($dados['endereco'] ?? null)],
                ['rotulo' => 'Inscrição / Processo nº', 'valor' => self::ou($dados['inscricao'] ?? null)],
                ['rotulo' => 'Atividade', 'valor' => self::ou($dados['atividade'] ?? null)],
                ['rotulo' => 'Local da atividade', 'valor' => self::ou($dados['local'] ?? null)],
                ['rotulo' => 'Barraca / Box / Lote / Qda', 'valor' => self::ou($dados['equipamento'] ?? null)],
            ],
            'listas' => [
                [
                    'titulo' => Texto::plural(count($motivos), 'Motivo assinalado', 'Motivos assinalados'),
                    'itens' => $motivos,
                ],
                [
                    'titulo' => 'Penalidades previstas',
                    'itens' => array_values(array_filter(array_map(
                        static fn (string $chave): ?string => config('prototipo_documentos_campo.sancoes_np.'.$chave),
                        array_map('strval', (array) ($documento->sancoes ?? [])),
                    ))),
                ],
            ],
            'rodape' => (string) config('prototipo_documentos_campo.rodape', ''),
        ];
    }

    /**
     * O AUTO DE APREENSÃO: os bens são recolhidos, e o prazo dele é o de GUARDA.
     *
     * @return array<string, mixed>
     */
    private static function autoDeApreensao(DocumentoCampo $documento, ?string $referencia): array
    {
        $dados = (array) ($documento->dados ?? []);
        $fundamentacao = (array) config('prototipo_documentos_campo.fundamentacao', []);
        $segub = (array) config('prototipo_documentos_campo.segub', []);
        $guarda = (array) config('prototipo_documentos_campo.prazos_guarda.'.((string) $documento->guarda_prazo), []);
        $itens = array_values((array) ($documento->itens ?? []));
        $decretos = array_values((array) ($dados['decretos'] ?? []));
        $volumes = array_sum(array_map(static fn (array $i): int => (int) ($i['quantidade'] ?? 0), $itens));

        return [
            /*
             * O Auto não tem prazo de regularização: o prazo dele é o de GUARDA
             * dos bens, que não é conta a fazer sobre a denúncia. Nulo — e não
             * zero: a tela que confundir os dois mostraria "vence hoje" num papel
             * que nunca vence.
             */
            'vence_em' => null,
            'vence_em_dias' => null,
            'prazo_rotulo' => null,
            'campos' => [
                ['rotulo' => 'Referência', 'valor' => self::ou($referencia)],
                ['rotulo' => 'Sr.(a)', 'valor' => self::ou($documento->notificado)],
                ['rotulo' => 'CPF nº', 'valor' => self::ou($documento->documento_notificado)],
                ['rotulo' => 'Equipamento tipo', 'valor' => self::ou($dados['equipamento'] ?? null)],
                ['rotulo' => 'Como atividade', 'valor' => self::ou($dados['atividade'] ?? null)],
                ['rotulo' => 'Localizado na', 'valor' => self::ou($dados['local'] ?? null)],
                ['rotulo' => 'Fundamento', 'valor' => self::ou($fundamentacao['lei'] ?? null)],
                /*
                 * O impresso põe a desinência de plural entre parênteses nestes
                 * rótulos — a folha é impressa em branco e não sabe quantos
                 * decretos serão citados. Aqui sabemos: a quantidade está na mão,
                 * e empurrar a concordância para quem lê é o que o projeto proíbe.
                 */
                [
                    'rotulo' => Texto::plural(count($decretos), 'Decreto', 'Decretos'),
                    'valor' => self::ou(implode('; ', $decretos)),
                ],
                ['rotulo' => 'Artigos', 'valor' => self::ou($dados['artigos'] ?? ($fundamentacao['artigos_padrao'] ?? null))],
                ['rotulo' => 'Portaria nº', 'valor' => self::ou($dados['portaria'] ?? ($fundamentacao['portaria_padrao'] ?? null))],
                ['rotulo' => 'Guarda', 'valor' => self::ou(((string) ($segub['nome'] ?? '')).' — '.((string) ($segub['endereco'] ?? '')))],
                ['rotulo' => 'Prazo máximo de guarda', 'valor' => self::ou(
                    isset($guarda['rotulo']) ? $guarda['rotulo'].' ('.((string) ($guarda['extenso'] ?? '')).')' : null,
                )],
                ['rotulo' => 'Após o prazo, os bens serão', 'valor' => self::ou(
                    config('prototipo_documentos_campo.destinacoes_guarda.'.((string) $documento->guarda_destinacao)),
                )],
            ],
            'listas' => [[
                'titulo' => 'Discriminação do material apreendido',
                'itens' => array_map(
                    static fn (array $i): string => ((int) ($i['quantidade'] ?? 0)).' '
                        .((string) ($i['unidade'] ?? 'un')).' — '.((string) ($i['descricao'] ?? '')),
                    $itens,
                ),
            ]],
            'rodape' => Texto::contar($volumes, 'volume', 'volumes').' '
                .Texto::plural($volumes, 'recolhido e encaminhado', 'recolhidos e encaminhados')
                .' ao '.((string) ($segub['nome'] ?? 'SEGUB')).' — '.((string) ($segub['endereco'] ?? '')),
        ];
    }

    /**
     * As assinaturas do papel, com o estado de cada uma.
     *
     * RECUSA é ato registrado, não ausência: sem o estado, "não assinou" e
     * "ninguém pediu" seriam a mesma coisa para quem lê o documento depois.
     *
     * @return list<array<string, string>>
     */
    private static function assinaturas(DocumentoCampo $documento): array
    {
        $dados = (array) ($documento->dados ?? []);

        return array_values(array_map(
            static fn (array $a): array => array_filter([
                'rotulo' => (string) ($a['rotulo'] ?? ''),
                'estado' => (string) ($a['estado'] ?? 'pendente'),
                'nome' => isset($a['nome']) ? (string) $a['nome'] : null,
            ], static fn (?string $v): bool => $v !== null),
            (array) ($dados['assinaturas'] ?? []),
        ));
    }

    /** Quem lavrou o documento, como o impresso o identifica. */
    private static function agente(DocumentoCampo $documento): string
    {
        $fiscal = $documento->fiscalizacao?->fiscal;

        if ($fiscal === null) {
            return '—';
        }

        return $fiscal->name.', matrícula '.mb_strtoupper($fiscal->login);
    }

    /** O valor, ou o travessão que o impresso usa para campo em branco. */
    private static function ou(?string $valor): string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? '—' : $texto;
    }
}
