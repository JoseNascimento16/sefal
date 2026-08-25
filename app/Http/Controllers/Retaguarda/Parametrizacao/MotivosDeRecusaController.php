<?php

namespace App\Http\Controllers\Retaguarda\Parametrizacao;

use App\Models\MotivoRecusa;
use App\Support\Parametrizacao\DefinicaoLookup;

/**
 * Motivos de Recusa — por que um cadastro feito em campo não foi aceito.
 */
class MotivosDeRecusaController extends ControllerDeLookup
{
    protected function definicao(): DefinicaoLookup
    {
        return new DefinicaoLookup(
            modelo: MotivoRecusa::class,
            tela: 'motivos-de-recusa',
            componente: 'Retaguarda/Parametrizacao/MotivosDeRecusa',
            titulo: 'Motivos de Recusa',
            singular: 'Motivo de recusa',
            genero: 'm',
            descricao: 'O que o Gestor responde quando devolve um cadastro feito em rua. O fiscal '
                .'lê este texto no aparelho, então ele precisa dizer o que corrigir.',
            exemplo: 'Ex.: Foto ilegível',
        );
    }
}
