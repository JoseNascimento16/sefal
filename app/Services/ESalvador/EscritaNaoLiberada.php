<?php

namespace App\Services\ESalvador;

use RuntimeException;

/**
 * A escrita na API do e-Salvador foi PEDIDA com a integração ligada — e ela
 * ainda não está liberada.
 *
 * A API é de PRODUÇÃO (não há homologação) e, por decisão do dono em
 * 15/09/2026, só GET é permitido: nada de POST, PUT ou DELETE enquanto o sistema
 * não estiver maduro para agir num processo administrativo real. Esta exceção é
 * a trava que impede que ligar `ESALVADOR_LIGADA` por engano faça o SEFAL
 * escrever lá antes da hora — o ato local fica registrado, e quem clicou lê o
 * porquê.
 */
final class EscritaNaoLiberada extends RuntimeException
{
    public static function para(string $ato): self
    {
        return new self(
            "A integração está ligada, mas a ESCRITA no e-Salvador ({$ato}) ainda não foi liberada pela "
            .'SEMGE/SEMOP: a API é de produção e só aceita consulta por enquanto. Registre o ato aqui e faça-o '
            .'à mão no e-Salvador.',
        );
    }
}
