<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Gerador PADRÃO de número de protocolo do sistema.
 *
 * Formato: `<PREFIXO><YYYYMMDD><NNN>` — ex.: `AMB20260902001`.
 * - PREFIXO: sigla do domínio (AMB = Ambulante, FI = Fiscalização, …). O prefixo `PER` continua
 *   nos cadastros que nasceram antes de a entidade se chamar Ambulante: código gravado é
 *   identidade, e identidade não se reescreve por estética.
 * - YYYYMMDD: data de referência do registro.
 * - NNN: sequencial de 3 dígitos que **reinicia todo dia** (por prefixo + data). Passando de 999 o
 *   número simplesmente cresce (4 dígitos) — o `str_pad` é piso, não teto.
 *
 * Use SEMPRE este helper quando uma tela/regra pedir protocolo, registrando o prefixo do domínio:
 * é a FONTE ÚNICA da numeração, e duas implementações divergem no primeiro ajuste.
 */
class Protocolo
{
    /**
     * Próximo protocolo para o (prefixo, data), reservado de forma ATÔMICA.
     *
     * Usa um contador travado por linha (`protocolo_contadores`) em vez de `count()+1`, eliminando
     * a corrida que duplica protocolo sob concorrência. A linha do dia é criada sob demanda; a
     * partir daí o `lockForUpdate` serializa os concorrentes.
     *
     * `$data` omitida = hoje. `$modelClass` é opcional e serve a um caso só: já existem registros
     * gravados com protocolo daquele dia mas ainda não existe a linha do contador (banco restaurado,
     * carga anterior) — informando o model, o contador nasce a partir do que já foi gravado em vez
     * de recomeçar do 001 e colidir. Sem ele, começa em 001.
     *
     * @param  class-string<Model>|null  $modelClass  Model onde o protocolo é gravado
     * @param  string  $coluna  Coluna que guarda o protocolo (default `protocolo`)
     */
    public static function proximo(string $prefixo, ?DateTimeInterface $data = null, ?string $modelClass = null, string $coluna = 'protocolo'): string
    {
        $data ??= now();
        $pref = strtoupper($prefixo);
        $dataKey = $data->format('Ymd');

        // 1) Garante a linha do contador (idempotente, sem exceção em corrida de criação).
        if (! DB::table('protocolo_contadores')->where('prefixo', $pref)->where('data', $dataKey)->exists()) {
            $inicial = $modelClass === null
                ? 1
                : $modelClass::query()->where($coluna, 'like', $pref.$dataKey.'%')->count() + 1;

            DB::table('protocolo_contadores')->insertOrIgnore([
                'prefixo' => $pref,
                'data' => $dataKey,
                'proximo' => $inicial,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2) Reserva o próximo número travando a linha (atômico).
        $sequencial = DB::transaction(function () use ($pref, $dataKey) {
            $contador = DB::table('protocolo_contadores')
                ->where('prefixo', $pref)
                ->where('data', $dataKey)
                ->lockForUpdate()
                ->first();

            $proximo = (int) $contador->proximo;

            DB::table('protocolo_contadores')
                ->where('prefixo', $pref)
                ->where('data', $dataKey)
                ->update(['proximo' => $proximo + 1, 'updated_at' => now()]);

            return $proximo;
        });

        return self::formatar($prefixo, $data, $sequencial);
    }

    /** Monta o protocolo a partir de um sequencial já conhecido (útil em backfill). */
    public static function formatar(string $prefixo, DateTimeInterface $data, int $sequencial): string
    {
        return strtoupper($prefixo).$data->format('Ymd').str_pad((string) $sequencial, 3, '0', STR_PAD_LEFT);
    }
}
