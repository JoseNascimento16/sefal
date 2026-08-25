<?php

namespace App\Relatorios\Suporte;

/**
 * Valida os pares de datas (período) de um relatório: a data inicial não pode
 * ser posterior à final.
 *
 * Sem esta guarda, o período invertido roda a consulta com intervalo impossível e
 * devolve um documento VAZIO — que quem pediu vai ler como "não houve
 * movimento", e não como "você inverteu as datas".
 *
 * Casa cada filtro-data de início com o de fim do mesmo grupo (mesmo prefixo
 * antes do sufixo), cobrindo as duas convenções em uso: `data_inicial`↔`data_final`
 * e `data_ini`↔`data_fim`.
 */
class PeriodoRelatorio
{
    /** Sufixos que marcam o INÍCIO de um período (mais longos primeiro). */
    private const SUFIXOS_INICIO = ['_inicial', '_inicio', '_ini'];

    /** Sufixos que marcam o FIM de um período (mais longos primeiro). */
    private const SUFIXOS_FIM = ['_final', '_fim'];

    /**
     * A mensagem do 1º período invertido encontrado, ou null se está tudo certo.
     *
     * @param  array<int, FiltroDef>  $definicoes
     * @param  array<string, mixed>  $valores
     */
    public static function erro(array $definicoes, array $valores): ?string
    {
        foreach (self::pares($definicoes) as $par) {
            $inicio = self::iso($valores[$par['inicio']->nome] ?? null);
            $fim = self::iso($valores[$par['fim']->nome] ?? null);

            if ($inicio !== null && $fim !== null && $inicio > $fim) {
                return "A {$par['inicio']->label} não pode ser posterior à {$par['fim']->label}.";
            }
        }

        return null;
    }

    /**
     * @param  array<int, FiltroDef>  $definicoes
     * @return array<int, array{inicio: FiltroDef, fim: FiltroDef}>
     */
    private static function pares(array $definicoes): array
    {
        $inicios = [];
        $fins = [];

        foreach ($definicoes as $def) {
            if ($def->tipo !== 'data') {
                continue;
            }
            if (($grupo = self::grupo($def->nome, self::SUFIXOS_INICIO)) !== null) {
                $inicios[$grupo] = $def;
            } elseif (($grupo = self::grupo($def->nome, self::SUFIXOS_FIM)) !== null) {
                $fins[$grupo] = $def;
            }
        }

        $pares = [];
        foreach ($inicios as $grupo => $inicio) {
            if (isset($fins[$grupo])) {
                $pares[] = ['inicio' => $inicio, 'fim' => $fins[$grupo]];
            }
        }

        return $pares;
    }

    /**
     * O prefixo do grupo, se o nome terminar com um dos sufixos.
     *
     * @param  list<string>  $sufixos
     */
    private static function grupo(string $nome, array $sufixos): ?string
    {
        foreach ($sufixos as $sufixo) {
            if (str_ends_with($nome, $sufixo)) {
                return substr($nome, 0, -strlen($sufixo));
            }
        }

        return null;
    }

    /** Normaliza para 'AAAA-MM-DD' comparável, ou null se vazio/inválido. */
    private static function iso(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : null;
    }
}
