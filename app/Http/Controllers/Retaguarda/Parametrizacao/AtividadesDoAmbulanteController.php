<?php

namespace App\Http\Controllers\Retaguarda\Parametrizacao;

use App\Models\AtividadeAmbulante;
use App\Support\Parametrizacao\DefinicaoLookup;

/**
 * Atividades do Ambulante — o que o permissionário vende ou faz.
 *
 * ⚠️ Esta é a primeira lista que passará a ser APONTADA por registro de
 * operação: o cadastro de permissionário guarda a atividade autorizada. Quando
 * ele existir, excluir uma atividade em uso deixaria esses cadastros apontando
 * para o nada — e a resposta certa passa a ser inativar. O lugar de barrar isso
 * é o `impedimentoParaExcluir()` da base, sobrescrito aqui.
 *
 * Hoje ninguém aponta para cá, então não há o que barrar: uma checagem contra
 * uma tabela que não existe seria código morto tratando de um caso impossível.
 */
class AtividadesDoAmbulanteController extends ControllerDeLookup
{
    protected function definicao(): DefinicaoLookup
    {
        return new DefinicaoLookup(
            modelo: AtividadeAmbulante::class,
            tela: 'atividades-do-ambulante',
            componente: 'Retaguarda/Parametrizacao/AtividadesDoAmbulante',
            titulo: 'Atividades do Ambulante',
            singular: 'Atividade',
            genero: 'f',
            descricao: 'O ramo autorizado na permissão — o que a pessoa vende ou faz no ponto. '
                .'É por aqui que a fiscalização confere se a atividade encontrada é a permitida.',
            exemplo: 'Ex.: Alimentos preparados',
        );
    }
}
