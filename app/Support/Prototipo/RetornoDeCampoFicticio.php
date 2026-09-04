<?php

namespace App\Support\Prototipo;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;

/**
 * PROTÓTIPO — a fila do Chefe de Setor: todo registro de fiscalização CONCLUÍDO
 * que voltou do campo na área dele.
 *
 * ⚠️ Nada aqui toca o banco. Os registros de partida são derivados das denúncias
 * (ver abaixo) e lidos de `config/prototipo_registros_de_campo.php`, e o que o
 * Chefe de Setor decide fica na SESSÃO dele.
 *
 * ── Por que a fila DERIVA, em vez de ter a própria lista ────────────────────
 *
 * "Registro de fiscalização concluído" já existe em dois lugares diferentes do
 * protótipo, e um deles é o trâmite da denúncia: quando a vistoria termina, o
 * passo que a fecha carrega o desfecho, o relato, as fotos, a coordenada, o
 * documento lavrado e — desde a entrega de 04/09/2026 — as CONSIDERAÇÕES e as
 * RECOMENDAÇÕES do fiscal.
 *
 * Copiar isso para uma segunda lista daria dois donos à MESMA vistoria: um dia o
 * trâmite diria "regularizado no local" e a fila continuaria dizendo
 * "notificado", e a demonstração mostraria as duas telas se contradizendo. Então
 * a fila deriva os registros do trâmite, na leitura.
 *
 * O arquivo de configuração tem só o que NÃO existe em lugar nenhum: as
 * fiscalizações AVULSAS — as que nasceram de operação planejada, de ronda da
 * equipe ou de pedido de outro órgão, sem denúncia atrás. Metade do trabalho da
 * equipe é assim, e uma fila que só mostrasse o que veio de reclamação
 * desenharia um setor que só reage.
 *
 * ── A DECISÃO é o que a sessão guarda, e só ela ─────────────────────────────
 *
 * A sessão guarda apenas o que o Chefe de Setor decidiu, indexado pelo
 * identificador do registro — nunca a lista inteira. Guardar a lista faria a
 * cópia da sessão envelhecer: bastaria alguém direcionar uma denúncia na outra
 * tela para a fila passar a mostrar o mundo de antes, sem nada acusar.
 *
 * ── Os identificadores ─────────────────────────────────────────────────────
 *
 * Avulsa usa o `id` do arquivo (1, 2, 3…); registro derivado de denúncia usa
 * `1000 + id da denúncia`. É a forma mais simples de os dois conviverem numa
 * fila só sem inventar chave composta — que o protótipo depois jogaria fora. No
 * sistema real a fiscalização é uma tabela, com o próprio identificador, e a
 * denúncia é apenas a origem dela.
 */
class RetornoDeCampoFicticio
{
    /** As decisões do Chefe de Setor, por identificador de registro. */
    private const CHAVE = 'prototipo.retorno-de-campo.decisoes';

    /** Onde os identificadores derivados de denúncia começam. Ver o cabeçalho. */
    private const FAIXA_DE_DENUNCIA = 1000;

    /** Esperando a leitura do Chefe de Setor — é a fila propriamente dita. */
    public const AGUARDANDO = 'Aguardando leitura';

    /** O Chefe de Setor leu e deu por encerrado o que era dele. */
    public const CIENTE = 'Ciente';

    /** O Chefe de Setor devolveu o ponto à equipe, com justificativa. */
    public const NOVA_VISTORIA = 'Nova vistoria determinada';

    /**
     * Os estados da fila, na ordem em que ela anda — o catálogo que a tela
     * oferece e que a validação aceita.
     *
     * @return list<string>
     */
    public static function estados(): array
    {
        return [self::AGUARDANDO, self::CIENTE, self::NOVA_VISTORIA];
    }

    /**
     * Todos os registros concluídos, do mais recente para o mais antigo.
     *
     * @return list<array<string, mixed>>
     */
    public static function registros(): array
    {
        $decisoes = self::decisoes();

        $registros = [
            ...self::derivadosDeDenuncia(),
            ...self::avulsos(),
        ];

        $registros = array_map(static function (array $r) use ($decisoes): array {
            $decisao = $decisoes[(string) $r['id']] ?? null;
            $estado = is_array($decisao) ? (string) $decisao['estado'] : self::AGUARDANDO;

            return [
                ...$r,
                'estado' => $estado,
                // O ato do Chefe de Setor sobre este registro, quando houve um.
                // Declarado sempre (nulo quando não houve) porque a tela o lê de
                // qualquer linha — chave ausente em metade delas viraria leitura
                // defensiva espalhada pelo front.
                'decisao' => is_array($decisao) ? $decisao['ato'] : null,
                // Quantos dias o registro está esperando a leitura da chefia. A
                // conta é do SERVIDOR porque a data é dele: no navegador ela
                // dependeria do relógio e do fuso da máquina de quem abre a tela,
                // e "há 3 dias" viraria "há 4" a partir das 21h num fuso negativo.
                // Nulo depois de decidido: o que já foi lido não está parado.
                'dias_parado' => $estado === self::AGUARDANDO
                    ? (int) Date::parse((string) $r['concluida_em'])->startOfDay()->diffInDays(now()->startOfDay())
                    : null,
            ];
        }, $registros);

        usort(
            $registros,
            static fn (array $a, array $b): int => [$b['concluida_em'], $b['id']] <=> [$a['concluida_em'], $a['id']],
        );

        return array_values($registros);
    }

    /** Um registro pelo identificador, ou null. */
    public static function registro(int $id): ?array
    {
        foreach (self::registros() as $registro) {
            if ((int) $registro['id'] === $id) {
                return $registro;
            }
        }

        return null;
    }

    /**
     * O Chefe de Setor DÁ CIÊNCIA: leu o retorno e o que era dele está encerrado.
     *
     * @param  list<int>  $ids
     * @return array{alterados: int, ignorados: int}
     */
    public static function darCiencia(array $ids, ?string $observacao = null): array
    {
        return self::decidir(
            $ids,
            self::CIENTE,
            'Ciência do retorno de campo',
            trim((string) ($observacao ?? '')) === ''
                ? 'Retorno lido e encerrado pela chefia da área.'
                : trim((string) $observacao),
        );
    }

    /**
     * O Chefe de Setor MANDA A EQUIPE VOLTAR ao ponto, com justificativa.
     *
     * A justificativa é obrigatória (a exigência mora no controller): mandar a
     * equipe de volta é gastar o trabalho dela de novo, e "voltar lá" não conta à
     * equipe o que ela deve procurar desta vez.
     *
     * @param  list<int>  $ids
     * @return array{alterados: int, ignorados: int}
     */
    public static function pedirNovaVistoria(array $ids, string $justificativa): array
    {
        return self::decidir(
            $ids,
            self::NOVA_VISTORIA,
            'Nova vistoria determinada pela chefia',
            trim($justificativa),
        );
    }

    /** Devolve a fila ao estado de partida — existe porque é protótipo. */
    public static function reiniciar(): void
    {
        Session::forget(self::CHAVE);
    }

    public static function alterada(): bool
    {
        return Session::has(self::CHAVE);
    }

    /**
     * Aplica a mesma decisão a um lote, e devolve o efeito dela.
     *
     * O lote é o caso normal: a equipe volta da rua com seis pontos vistoriados,
     * e a chefia lê os seis de uma vez. Um caminho para o lote e outro para o
     * registro isolado seriam a mesma regra com dois donos.
     *
     * @param  list<int>  $ids
     * @return array{alterados: int, ignorados: int}
     */
    private static function decidir(array $ids, string $estado, string $oQue, string $detalhe): array
    {
        $decisoes = self::decisoes();
        $alterados = 0;
        $ignorados = 0;

        foreach ($ids as $id) {
            if (self::registro((int) $id) === null) {
                $ignorados++;

                continue;
            }

            $decisoes[(string) (int) $id] = [
                'estado' => $estado,
                'ato' => [
                    'em' => now()->format('Y-m-d H:i'),
                    // Nullsafe: a tela é autenticada, mas uma decisão aplicada
                    // fora da requisição (comando, teste) não tem quem assinar.
                    'quem' => (string) (Auth::user()?->name ?? 'Chefia da área'),
                    'o_que' => $oQue,
                    'detalhe' => $detalhe,
                ],
            ];

            $alterados++;
        }

        Session::put(self::CHAVE, $decisoes);

        return ['alterados' => $alterados, 'ignorados' => $ignorados];
    }

    /** @return array<string, array<string, mixed>> */
    private static function decisoes(): array
    {
        /** @var array<string, array<string, mixed>>|null $guardadas */
        $guardadas = Session::get(self::CHAVE);

        return is_array($guardadas) ? $guardadas : [];
    }

    /**
     * Os registros que vieram de DENÚNCIA — um por denúncia que já teve desfecho
     * de campo, montado a partir do ÚLTIMO passo do trâmite que declarou um.
     *
     * "Último" e não "primeiro" porque a vistoria pode ter mais de um desfecho ao
     * longo da vida do registro (notificado, depois regularizado): o que voltou
     * para a chefia é onde a coisa parou.
     *
     * O documento entra só como tipo e número. A leitura do papel inteiro é do
     * trâmite da denúncia — a fila precisa dizer QUE houve documento, para a
     * chefia saber que há prazo correndo, e não repetir o impresso.
     *
     * @return list<array<string, mixed>>
     */
    private static function derivadosDeDenuncia(): array
    {
        $registros = [];

        foreach (DenunciasFicticias::todas() as $denuncia) {
            $passo = self::passoDoDesfecho((array) ($denuncia['tramites'] ?? []));

            if ($passo === null) {
                continue;
            }

            $canal = (string) config(
                'prototipo_denuncias.canais.'.((string) $denuncia['canal']).'.nome',
                (string) $denuncia['canal'],
            );

            $campo = is_array($passo['campo'] ?? null) ? (array) $passo['campo'] : [];
            $documento = is_array($passo['documento'] ?? null) ? (array) $passo['documento'] : null;
            $id = self::FAIXA_DE_DENUNCIA + (int) $denuncia['id'];

            $registros[] = [
                'id' => $id,
                'protocolo' => sprintf('FIS-%04d', $id),
                'origem' => 'Denúncia',
                // O que amarra o registro ao que o originou: é por aqui que quem
                // lê a fila sabe onde procurar o percurso inteiro.
                'referencia' => "{$canal} · ".((string) $denuncia['protocolo']),
                'denuncia_protocolo' => (string) $denuncia['protocolo'],
                'concluida_em' => (string) $passo['em'],
                'area' => (string) ($denuncia['area'] ?? ''),
                'equipe' => (string) ($denuncia['equipe'] ?? ''),
                'fiscal' => (string) $passo['quem'],
                'endereco' => self::endereco(
                    (string) ($denuncia['logradouro'] ?? ''),
                    $denuncia['numero'] ?? null,
                ),
                'bairro' => (string) ($denuncia['bairro'] ?? ''),
                'ponto_de_referencia' => trim((string) ($denuncia['referencia'] ?? '')) === ''
                    ? null
                    : (string) $denuncia['referencia'],
                'gps' => $campo['gps'] ?? null,
                'precisao_m' => isset($campo['precisao_m']) ? (int) $campo['precisao_m'] : null,
                'desfecho' => (string) $passo['desfecho'],
                'documento' => $documento === null ? null : [
                    'tipo' => (string) $documento['tipo'],
                    'numero' => (string) $documento['numero'],
                ],
                'consideracoes' => $passo['consideracoes'] ?? null,
                'recomendacoes' => array_values((array) ($passo['recomendacoes'] ?? [])),
                // A situação em que a DENÚNCIA ficou. A chefia precisa dela para
                // saber se ainda há prazo correndo: "Notificação emitida" com a
                // denúncia em "Aguardando regularização" é caso aberto; a mesma
                // notificação com a denúncia "Concluída" é caso encerrado.
                'situacao_da_origem' => (string) $denuncia['situacao'],
            ];
        }

        return $registros;
    }

    /**
     * Os registros AVULSOS — operação, ronda ou pedido de outro órgão.
     *
     * A área e o nome de quem assinou saem da estrutura de áreas e equipes, e não
     * do arquivo de dados: nome escrito lá daria dois donos ao mesmo cadastro, e
     * um fiscal removido da equipe continuaria assinando vistoria.
     *
     * @return list<array<string, mixed>>
     */
    private static function avulsos(): array
    {
        return array_values(array_map(static function (array $bruto): array {
            $codigo = (string) $bruto['equipe'];
            $equipe = EstruturaFicticia::equipeDoCodigo($codigo);
            $fiscais = array_values((array) ($equipe['fiscais'] ?? []));
            $indice = max(0, (int) ($bruto['fiscal'] ?? 1) - 1);
            $fiscal = (array) ($fiscais[$indice] ?? $fiscais[0] ?? []);
            $nome = trim((string) ($fiscal['nome'] ?? ''));

            return [
                'id' => (int) $bruto['id'],
                'protocolo' => sprintf('FIS-%04d', (int) $bruto['id']),
                'origem' => (string) $bruto['origem'],
                'referencia' => (string) $bruto['referencia'],
                // Avulsa não veio de denúncia: a chave existe com valor neutro
                // para a tela ler qualquer linha do mesmo jeito.
                'denuncia_protocolo' => null,
                'concluida_em' => now()->subHours((int) $bruto['concluida_ha_horas'])->format('Y-m-d H:i'),
                'area' => (string) ($equipe['nome'] ?? ''),
                'equipe' => $codigo,
                'fiscal' => $nome === '' ? "Equipe {$codigo}" : "{$nome} · Equipe {$codigo}",
                'endereco' => self::endereco((string) $bruto['logradouro'], $bruto['numero'] ?? null),
                'bairro' => (string) $bruto['bairro'],
                'ponto_de_referencia' => $bruto['ponto_de_referencia'] ?? null,
                'gps' => $bruto['gps'] ?? null,
                'precisao_m' => isset($bruto['precisao_m']) ? (int) $bruto['precisao_m'] : null,
                'desfecho' => (string) $bruto['desfecho'],
                'documento' => is_array($bruto['documento'] ?? null) ? [
                    'tipo' => (string) $bruto['documento']['tipo'],
                    'numero' => (string) $bruto['documento']['numero'],
                ] : null,
                'consideracoes' => trim((string) ($bruto['consideracoes'] ?? '')) === ''
                    ? null
                    : trim((string) $bruto['consideracoes']),
                'recomendacoes' => array_values((array) ($bruto['recomendacoes'] ?? [])),
                // Avulsa não tem processo atrás: a origem dela é o próprio
                // trabalho planejado, e ela se encerra na leitura da chefia.
                'situacao_da_origem' => null,
            ];
        }, (array) config('prototipo_registros_de_campo.registros', [])));
    }

    /**
     * O último passo do trâmite que declarou desfecho, ou null se a denúncia
     * ainda não foi a campo.
     *
     * @param  list<array<string, mixed>>  $tramites
     * @return array<string, mixed>|null
     */
    private static function passoDoDesfecho(array $tramites): ?array
    {
        $encontrado = null;

        foreach ($tramites as $passo) {
            if (trim((string) ($passo['desfecho'] ?? '')) !== '') {
                $encontrado = $passo;
            }
        }

        return $encontrado;
    }

    /** "Rua Chile, 44" — ou só a rua, quando o número não existe. */
    private static function endereco(string $logradouro, mixed $numero): string
    {
        $numero = trim((string) ($numero ?? ''));

        return $numero === '' ? $logradouro : "{$logradouro}, {$numero}";
    }
}
