<?php

namespace App\Services\Monitoramento;

/**
 * O resultado de UMA verificação.
 *
 * O `detalhe` é escrito em linguagem de NEGÓCIO, e sempre com a CONSEQUÊNCIA
 * junto: quem lê é o gestor tentando entender o que parou, não quem escreveu o
 * código. "Nenhum administrador ativo — ninguém consegue distribuir acesso"
 * explica; "0 registros em users where admin = 1" não explica nada.
 *
 * Verde também informa: o detalhe do `ok` diz o que foi conferido ("2 contas de
 * administrador ativas"), para a tela saudável não ser uma fileira muda.
 *
 * Severidade honesta, sem inflar: `falha` é fluxo QUEBRADO; `aviso` é degradado
 * ou arriscado, mas andando. Se tudo for falha, nada é.
 */
class ResultadoCheck
{
    public const OK = 'ok';

    public const FALHA = 'falha';

    public const AVISO = 'aviso';

    private function __construct(
        public readonly string $status,
        public readonly string $detalhe,
    ) {}

    public static function ok(string $detalhe): self
    {
        return new self(self::OK, $detalhe);
    }

    /** O fluxo QUEBRA sem isso. */
    public static function falha(string $detalhe): self
    {
        return new self(self::FALHA, $detalhe);
    }

    /** Degrada ou é arriscado, mas o fluxo anda. */
    public static function aviso(string $detalhe): self
    {
        return new self(self::AVISO, $detalhe);
    }
}
