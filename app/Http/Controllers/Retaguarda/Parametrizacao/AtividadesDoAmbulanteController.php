<?php

namespace App\Http\Controllers\Retaguarda\Parametrizacao;

use App\Models\AtividadeAmbulante;
use App\Models\Permissionario;
use App\Support\Parametrizacao\DefinicaoLookup;
use App\Support\Texto;
use Illuminate\Http\RedirectResponse;

/**
 * Atividades do Ambulante — o que o permissionário vende ou faz.
 *
 * É a primeira lista APONTADA por registro de operação: o cadastro de
 * permissionário guarda a atividade autorizada. Por isso esta é a única das seis
 * que sobrescreve o `destroy()` — ver o método.
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

    /**
     * Recusa excluir uma atividade que algum permissionário aponta.
     *
     * Excluir deixaria esses cadastros apontando para o nada — e o banco, que
     * tem a chave estrangeira, responderia com um erro cru de integridade, que
     * para quem está na tela é o sistema quebrando sem motivo. A recusa acontece
     * ANTES, na tela de onde a pessoa clicou, dizendo **quantos** cadastros
     * dependem da atividade e o que fazer no lugar: **inativar**, que tira o
     * valor das escolhas novas e mantém legível o que já foi gravado.
     *
     * A contagem é explícita, e não uma relação no model, de propósito: é a
     * pergunta inteira que se faz aqui, e uma relação convidaria a carregar os
     * registros só para contá-los.
     */
    public function destroy(int $item): RedirectResponse
    {
        $vinculados = Permissionario::query()->where('atividade_id', $item)->count();

        if ($vinculados > 0) {
            return back()->with(
                'flash.erro',
                'Esta atividade não pode ser excluída: '
                .Texto::contar($vinculados, 'permissionário a tem', 'permissionários a têm')
                .' como atividade autorizada. Para tirá-la de circulação sem apagar o histórico, '
                .'desmarque "Ativo".',
            );
        }

        return parent::destroy($item);
    }
}
