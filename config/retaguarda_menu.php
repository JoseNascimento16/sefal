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
    |   slug    — identidade da tela no CONTROLE DE ACESSO (ver abaixo);
    |   setores — a SEMENTE da matriz de permissões (ver abaixo).
    |
    | ── `slug` e `setores`: quem decide o acesso ────────────────────────────
    |
    | Declarar `slug` coloca a tela sob o Modo Gerente: o slug é a chave dela na
    | matriz `setor × tela × ação`, e daí em diante quem manda é a MATRIZ — tanto
    | para o menu quanto para as guardas de leitura e de ação. (Enquanto o
    | bloqueio não está ligado — ver `retaguarda.permissao_enforce` —, o item
    | continua à vista para quem ainda não tem a concessão: sumir do menu sem
    | recado nem registro é justamente o que o rollout evita.) O `slug` tem de
    | ser o primeiro trecho do caminho da rota (`/retaguarda/<slug>/...`), porque
    | é assim que a guarda de leitura descobre a que tela um endereço pertence;
    | `ModoGerenteTest` reprova se os dois discordarem.
    |
    | `setores` é a SEMENTE dessa matriz — a concessão inicial, aplicada uma vez
    | pelo `PermissoesSetorSeeder`. Depois disso, mudar esta lista não muda mais
    | nada: quem concede e quem tira é a tela do Modo Gerente. (O administrador
    | não é semeado: ele é desvio no código, não linha de matriz.)
    |
    | Item SEM `slug` fica fora do controle de acesso, e isso é deliberado em dois
    | casos — a tela inicial (barrá-la fecharia um loop de redirecionamento, já
    | que é para lá que a própria negativa manda o usuário) e a área da própria
    | conta (senha e dados pessoais não são decisão de gestor). Fora desses, item
    | restrito a setor SEM slug escaparia da matriz e daria dois donos à mesma
    | decisão: `ModoGerenteTest` reprova.
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
                [
                    'rotulo' => 'Relatórios',
                    'rota' => 'retaguarda.relatorios.index',
                    'icone' => 'relatorios',
                    'slug' => 'relatorios',
                    // Gestão da operação: o gestor emite; o fiscal, que trabalha
                    // em rua pelo aplicativo, não tem o que fazer aqui.
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Monitoramento',
                    'rota' => 'retaguarda.monitoramento.index',
                    'icone' => 'monitoramento',
                    'slug' => 'monitoramento',
                    // Diagnóstico do ambiente: quem responde por "o sistema está
                    // de pé?" é quem administra e quem gerencia a operação. O
                    // fiscal trabalha em rua, pelo aplicativo.
                    'setores' => ['administrador', 'gestor'],
                ],
                [
                    'rotulo' => 'Logs',
                    'rota' => 'retaguarda.logs.index',
                    'icone' => 'logs',
                    'slug' => 'logs',
                    // Só o administrador: a ocorrência guarda o endereço e o verbo
                    // de uma requisição que deu errado, e isso conta bastante
                    // sobre o que existe do outro lado.
                    'setores' => ['administrador'],
                ],
                [
                    'rotulo' => 'Modo Gerente',
                    'rota' => 'retaguarda.modo-gerente.index',
                    'icone' => 'permissoes',
                    'slug' => 'modo-gerente',
                    // Só o administrador, e por desvio (não por linha semeada):
                    // quem distribui acesso não pode distribuir a si mesmo o
                    // poder de distribuir acesso.
                    'setores' => ['administrador'],
                ],
            ],
        ],

    ],

];
