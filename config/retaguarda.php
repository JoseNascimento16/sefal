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
    | administrador   — enxerga e administra tudo;
    | chefe-de-setor  — UMA pessoa, que responde pelo setor inteiro: recebe tudo
    |                   que chega (do e-Salvador, do Fala Salvador, por ligação),
    |                   pré-tria, encaminha a um líder de equipe ou devolve, lê o
    |                   que volta e responde ao e-Salvador. Sem recorte.
    | lider-de-equipe — o encarregado de UMA equipe (o "encarregado" do documento
    |                   das áreas): recebe o que o chefe encaminhou à sua equipe,
    |                   direciona aos fiscais, recebe o retorno da rua e dá ciência
    |                   ou pede nova vistoria. Recortado pela própria equipe.
    | fiscal          — usa o PWA em rua e registra fiscalizações.
    |
    | ⚠️ Não há `coordenador`. Os coordenadores existem, mas trabalham no
    | e-Salvador e direcionam de lá para a caixa do setor — não entram aqui. O
    | setor existiu até 22/09/2026 e foi removido pela migration
    | `2026_09_22_090000_lider_de_equipe_e_fim_do_coordenador`.
    |
    | ⚠️ Os dois últimos NASCERAM com outro nome — `administrativo` e `gestor` —, e
    | a renomeação (decisão do dono, 04/09/2026) alcançou o SLUG, não só o rótulo:
    | slug é a chave da matriz de permissões e do vínculo do usuário, então a
    | migration `2026_09_04_090000_renomeia_papeis_para_coordenador_e_chefe_de_setor`
    | renomeia as linhas já gravadas. As MATRÍCULAS de demonstração (`gestor1`,
    | `administrativo1`) ficam como estão: matrícula identifica gente, não cargo.
    |
    */

    'setores' => [
        'administrador' => 'Administrador',
        'chefe-de-setor' => 'Chefe de Setor',
        'lider-de-equipe' => 'Líder de Equipe',
        'fiscal' => 'Fiscal',
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
