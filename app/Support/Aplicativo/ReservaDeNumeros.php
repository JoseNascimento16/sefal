<?php

namespace App\Support\Aplicativo;

use App\Models\DocumentoCampo;
use App\Models\NumeroReservado;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Os números oficiais de Notificação e Auto que o fiscal leva para a rua.
 *
 * O aplicativo pede reposição sempre que fala com o servidor; cada fiscal fica
 * com {@see ESTOQUE} números livres de cada tipo. O número só deixa de ser dele
 * quando volta preso a um documento ({@see usar()}).
 */
final class ReservaDeNumeros
{
    /** Quantos números livres de cada tipo o aparelho carrega. */
    public const ESTOQUE = 3;

    /**
     * Onde a numeração do sistema começa — depois dos blocos de papel já usados
     * pela SEMOP (e dos números que a demonstração semeou).
     */
    public const INICIO = [DocumentoCampo::NOTIFICACAO => 194906, DocumentoCampo::AUTO => 160052];

    /**
     * Completa o estoque do fiscal e devolve os números livres dele, por tipo.
     *
     * @return array<string, list<string>>
     */
    public function repor(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $livres = [];

            foreach (DocumentoCampo::TIPOS as $tipo) {
                $atuais = $this->livresDe($user, $tipo);

                for ($i = count($atuais); $i < self::ESTOQUE; $i++) {
                    $numero = (string) $this->proximo($tipo);

                    NumeroReservado::create([
                        'tipo' => $tipo,
                        'numero' => $numero,
                        'user_id' => $user->id,
                        'reservado_em' => Date::now(),
                    ]);

                    $atuais[] = $numero;
                }

                $livres[$tipo] = $atuais;
            }

            return $livres;
        });
    }

    /**
     * O número veio de volta num documento: confere que é deste fiscal e o dá
     * por usado. Devolve o motivo da recusa, ou `null` quando está tudo certo.
     *
     * Um envio repetido do MESMO registro (a fila do aparelho reenviando) acha o
     * número já usado por esta vistoria e passa.
     */
    public function usar(User $user, string $tipo, string $numero, int $fiscalizacaoId): ?string
    {
        $reserva = NumeroReservado::where('tipo', $tipo)->where('numero', $numero)->lockForUpdate()->first();

        if ($reserva === null || $reserva->user_id !== $user->id) {
            return "O número {$numero} não foi reservado para você. Atualize o aplicativo com sinal para receber números novos e lavre o documento de novo.";
        }

        if ($reserva->usado_em !== null && $reserva->fiscalizacao_id !== $fiscalizacaoId) {
            return "O número {$numero} já foi usado em outro documento.";
        }

        $reserva->forceFill(['usado_em' => $reserva->usado_em ?? Date::now(), 'fiscalizacao_id' => $fiscalizacaoId])->save();

        return null;
    }

    /** @return list<string> */
    private function livresDe(User $user, string $tipo): array
    {
        return NumeroReservado::where('user_id', $user->id)
            ->where('tipo', $tipo)
            ->whereNull('usado_em')
            ->orderBy('id')
            ->pluck('numero')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    /** O primeiro número que ninguém usou nem reservou. */
    private function proximo(string $tipo): int
    {
        $maiores = [
            self::INICIO[$tipo] - 1,
            ...NumeroReservado::where('tipo', $tipo)->pluck('numero')->map(fn ($n) => (int) $n)->all(),
            ...DocumentoCampo::where('tipo', $tipo)->pluck('numero')->map(fn ($n) => (int) $n)->all(),
        ];

        return max($maiores) + 1;
    }
}
