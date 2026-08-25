<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Setores (perfis de acesso da Retaguarda)
    |--------------------------------------------------------------------------
    |
    | Catálogo fechado de setores do sistema. É a FONTE ÚNICA: a tabela `setores`
    | nasce daqui (SetoresSeeder, idempotente por slug) e os comandos de bootstrap
    | validam contra esta lista. Um usuário pertence a N setores (`user_setores`).
    |
    | administrador — enxerga e administra tudo;
    | fiscal        — usa o PWA em rua e registra fiscalizações;
    | gestor        — valida cadastros de campo e acompanha a operação.
    |
    */

    'setores' => [
        'administrador' => 'Administrador',
        'fiscal' => 'Fiscal',
        'gestor' => 'Gestor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforcement do Modo Gerente
    |--------------------------------------------------------------------------
    |
    | Quanto as guardas de permissão (`Permissao` para leitura, `PermissaoAcao`
    | para as mutações) realmente PODEM barrar:
    |
    |   off   — não conferem nada; é como se não existissem;
    |   log   — conferem e REGISTRAM o que seriam capazes de barrar, sem barrar;
    |   block — barram de verdade, sempre dizendo o motivo a quem foi barrado.
    |
    | O padrão é `log` de propósito. Ligar o bloqueio no mesmo passo em que o
    | catálogo de telas nasce é como estrear a fechadura antes de saber quantas
    | portas a casa tem: cada tela das próximas entregas entra na matriz, e um
    | esquecimento de concessão viraria gente sem acesso ao próprio trabalho. O
    | modo `log` deixa o rastro de tudo que SERIA barrado — dá para conferir a
    | matriz contra a realidade antes de virar a chave (isso acontece no fim da
    | Fase 1).
    |
    */

    'permissao_enforce' => env('PERMISSAO_ENFORCE', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Telas cuja conferência NÃO espera o rollout
    |--------------------------------------------------------------------------
    |
    | Estas são barradas de verdade sempre, seja qual for o modo acima.
    |
    | O motivo é o próprio Modo Gerente: é a tela que DISTRIBUI acesso. Deixá-la
    | sujeita ao modo de observação abriria, para qualquer pessoa autenticada, a
    | tela onde se concede tudo o mais — e o rollout gradual, que existe para não
    | tirar acesso de ninguém por engano, teria dado acesso a todos ao contrário.
    |
    | A régua é a mesma (a matriz de permissões); o que não se aplica aqui é o
    | adiamento do bloqueio.
    |
    */

    'permissao_sempre' => [
        'modo-gerente',
    ],

];
