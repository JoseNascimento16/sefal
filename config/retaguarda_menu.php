<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Menu da Retaguarda
    |--------------------------------------------------------------------------
    |
    | FONTE ÚNICA do menu lateral. Quem monta a barra é o
    | `HandleInertiaRequests`, que resolve a rota, descarta o item cuja rota
    | ainda não existe e entrega ao front só o que aquele usuário pode ver.
    |
    | Cada seção tem `rotulo`, `itens` e, opcionalmente, `vazio` — o texto que
    | aparece quando a seção existe no plano mas ainda não tem tela pronta. É
    | melhor dizer "chega nas próximas entregas" do que esconder a seção: quem
    | usa o sistema enxerga o caminho que está sendo construído.
    |
    | Cada item tem:
    |   rotulo  — o nome que aparece na tela;
    |   rota    — nome da rota (não a URL: o endereço muda, o nome não);
    |   icone   — chave do ícone, traduzida em `resources/js/lib/icones-menu.ts`;
    |   setores — quem vê. Lista VAZIA = todo usuário autenticado; com setores,
    |             só quem pertence a um deles (o administrador vê tudo).
    |
    */

    'secoes' => [

        [
            'rotulo' => 'Painel',
            'itens' => [
                [
                    'rotulo' => 'Início',
                    'rota' => 'retaguarda.inicio',
                    'icone' => 'inicio',
                    'setores' => [],
                ],
            ],
        ],

        [
            'rotulo' => 'Fiscalização',
            'vazio' => 'Permissionários, fiscalizações e áreas chegam nas próximas entregas.',
            'itens' => [],
        ],

        [
            'rotulo' => 'Sistema',
            'itens' => [
                [
                    'rotulo' => 'Meu Perfil',
                    'rota' => 'profile.edit',
                    'icone' => 'perfil',
                    'setores' => [],
                ],
            ],
        ],

    ],

];
