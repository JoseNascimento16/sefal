<?php

namespace App\Http\Controllers\Retaguarda\Parametrizacao;

use App\Models\TipoInfracao;
use App\Support\Parametrizacao\CampoLookup;
use App\Support\Parametrizacao\DefinicaoLookup;

/**
 * Tipos de Infração — o que o fiscal enquadra numa autuação.
 *
 * O comportamento inteiro vem do {@see ControllerDeLookup}; aqui só se declara o
 * que é próprio desta lista.
 */
class TiposDeInfracaoController extends ControllerDeLookup
{
    protected function definicao(): DefinicaoLookup
    {
        return new DefinicaoLookup(
            modelo: TipoInfracao::class,
            tela: 'tipos-de-infracao',
            componente: 'Retaguarda/Parametrizacao/TiposDeInfracao',
            titulo: 'Tipos de Infração',
            singular: 'Tipo de infração',
            genero: 'm',
            descricao: 'O que o fiscal enquadra ao autuar em rua. O nome curto é o que aparece na '
                .'lista do aparelho; a descrição explica o que aquele enquadramento abrange.',
            exemplo: 'Ex.: Área não autorizada',
            campos: [
                new CampoLookup(
                    chave: 'descricao',
                    rotulo: 'Descrição',
                    obrigatorio: false,
                    maximo: 500,
                    longo: true,
                    exemplo: 'Explique, em uma ou duas frases, o que este enquadramento abrange.',
                    ajuda: 'Apoio à escolha em rua — aparece junto do nome no aparelho do fiscal.',
                ),
            ],
        );
    }
}
