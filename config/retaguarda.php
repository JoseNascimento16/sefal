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

];
