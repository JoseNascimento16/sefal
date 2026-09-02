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
    | administrativo— o setor de retaguarda que RECEBE o que chega de fora: tria a
    |                 denúncia das ouvidorias e registra o que vem em papel. NÃO é
    |                 o administrador do sistema (decisão do dono, 02/09/2026) — é
    |                 quem faz o trabalho administrativo. O administrador também
    |                 pode fazer esse trabalho, por ser administrador;
    | fiscal        — usa o PWA em rua e registra fiscalizações;
    | gestor        — gestor de uma ÁREA de fiscalização: direciona o que foi
    |                 encaminhado à área dele, valida cadastros de campo e
    |                 acompanha a operação.
    |
    */

    'setores' => [
        'administrador' => 'Administrador',
        'administrativo' => 'Administrativo',
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
    | O padrão é `block` desde o fim da Fase 1. Ele NASCEU `log` de propósito:
    | ligar o bloqueio no mesmo passo em que o catálogo de telas nasce seria
    | estrear a fechadura antes de saber quantas portas a casa tem — cada tela
    | das entregas seguintes entra na matriz, e um esquecimento de concessão
    | viraria gente sem acesso ao próprio trabalho. O modo `log` deixou o rastro
    | de tudo que SERIA barrado, a matriz foi conferida contra o catálogo real e
    | a chave virou.
    |
    | Consequência para quem entrega tela nova daqui em diante: a tela precisa
    | entrar no catálogo (`CatalogoFuncionalidades`) E ganhar concessão na
    | semente da matriz (`PermissoesSetorSeeder`, alimentada pela lista `setores`
    | de cada item do menu). Tela controlável sem concessão é tela que ninguém
    | abre — e agora ela barra de verdade, não só registra. `off` e `log`
    | continuam existindo para diagnóstico pontual em ambiente controlado; não
    | são caminho de produção.
    |
    */

    'permissao_enforce' => env('PERMISSAO_ENFORCE', 'block'),

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
