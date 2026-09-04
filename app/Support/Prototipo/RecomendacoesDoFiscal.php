<?php

namespace App\Support\Prototipo;

/**
 * O catálogo de RECOMENDAÇÕES do fiscal, na forma que cada lado precisa dele.
 *
 * ── Por que existe uma classe para ler um `config` ──────────────────────────
 *
 * Porque o catálogo tem DUAS redações por chave (ver
 * `config/prototipo_denuncias.php`), e duas telas precisam da mesma delas — o
 * trâmite da denúncia e a fila do Retorno de Campo. A conversão "chave → frase
 * que a Retaguarda mostra" escrita nos dois controllers seria a mesma regra com
 * dois donos: no dia em que o catálogo ganhasse uma terceira redação, ou o
 * fallback de chave desconhecida mudasse, só um dos lados aprenderia.
 *
 * ── O que cada redação serve ────────────────────────────────────────────────
 *
 * `curto` é a pílula do CELULAR; `explicito` é o que a Retaguarda mostra a quem
 * decide. É decisão do dono: "unifique usando a redação curta no app e a
 * explícita no retaguarda". Por isso `explicitos()` é o único mapa que sai
 * daqui para as telas — o `curto` fica no catálogo porque este é o lado que
 * fica quando o aplicativo passar a consumir a lista do servidor.
 *
 * ⚠️ Chave DESCONHECIDA não desaparece: quem resolve (a tela) mostra a chave
 * crua. Recomendação que evapora em silêncio é pior que recomendação feia — a
 * chefia decidiria sem saber que o fiscal pediu alguma coisa.
 */
class RecomendacoesDoFiscal
{
    /**
     * O catálogo inteiro, chave => ['curto' => …, 'explicito' => …].
     *
     * @return array<string, array{curto: string, explicito: string}>
     */
    public static function catalogo(): array
    {
        $catalogo = [];

        foreach ((array) config('prototipo_denuncias.recomendacoes_do_fiscal', []) as $chave => $redacoes) {
            $catalogo[(string) $chave] = [
                'curto' => (string) (((array) $redacoes)['curto'] ?? ''),
                'explicito' => (string) (((array) $redacoes)['explicito'] ?? ''),
            ];
        }

        return $catalogo;
    }

    /**
     * O mapa que as telas da Retaguarda recebem: chave => redação EXPLÍCITA.
     *
     * Vai como catálogo do servidor, e não resolvido no registro, pelo mesmo
     * motivo dos outros catálogos desta demonstração: a tela também precisa da
     * lista inteira (a busca reconhece recomendação como faceta), e resolver no
     * servidor deixaria a tela sem saber quais existem.
     *
     * @return array<string, string>
     */
    public static function explicitos(): array
    {
        return array_map(
            static fn (array $redacoes): string => $redacoes['explicito'],
            self::catalogo(),
        );
    }

    /** As chaves do catálogo, na ordem em que ele as declara. */
    /** @return list<string> */
    public static function chaves(): array
    {
        return array_keys(self::catalogo());
    }
}
