<?php

/*
|--------------------------------------------------------------------------
| Integração com o e-Salvador
|--------------------------------------------------------------------------
|
| O e-Salvador é o sistema de PROCESSO ADMINISTRATIVO ELETRÔNICO da Prefeitura
| (SEMGE). Ele não sabe o que é uma denúncia: sabe processo, requerente e
| trâmite. O que chega para a SEMOP como "denúncia de ambulante" é um PROCESSO
| classificado por grupo → assunto → subassunto, e é por essa classificação —
| e só por ela — que a integração reconhece o que é dela.
|
| Por isso os ids abaixo são PARÂMETRO, e não constante no código: eles saem do
| catálogo do e-Salvador (`GET /grupos`, `GET /assuntos/{grupo}`), pertencem à
| SEMGE, e mudam sem nos avisar. Fixá-los no código faria a integração parar de
| reconhecer as denúncias sem ninguém entender por quê.
|
| Estudo completo do contrato: docs/integracoes/esalvador.md
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Onde
    |--------------------------------------------------------------------------
    |
    | A URL base sem barra no fim. O `login` mora fora dela porque a
    | documentação o publica com caminho próprio.
    |
    */

    'url' => rtrim((string) env('ESALVADOR_URL', 'https://apiesalvador.salvador.ba.gov.br/api'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Credenciais
    |--------------------------------------------------------------------------
    |
    | São TRÊS, e as três vêm de lugares diferentes:
    |
    |  - `nome` e `password` são entregues pela equipe do e-Salvador (SEMGE)
    |    junto com a liberação do nosso IP — sem essa liberação nenhuma chamada
    |    passa, e o sintoma é 401 em tudo, inclusive no login;
    |  - `token` é gerado por uma PESSOA dentro do e-Salvador (Ajuda →
    |    Integração Token). Ele identifica o servidor em nome de quem a
    |    integração age, não expira, e NÃO fica guardado lá: se perdermos,
    |    gera-se outro e o antigo morre na hora.
    |
    | Nada disso entra no repositório.
    |
    */

    'nome' => env('ESALVADOR_NOME'),
    'password' => env('ESALVADOR_PASSWORD'),
    'token' => env('ESALVADOR_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | A caixa que olhamos
    |--------------------------------------------------------------------------
    |
    | A API entrega a caixa de UMA unidade por vez, escolhida com
    | `PUT /seleciona-caixa`. `unidade` é o id da unidade da SEMOP que recebe as
    | denúncias; `orgao` é o id do órgão dela, usado ao devolver o resultado.
    |
    */

    'unidade' => env('ESALVADOR_UNIDADE'),
    'orgao' => env('ESALVADOR_ORGAO'),

    /*
    |--------------------------------------------------------------------------
    | O que, no catálogo deles, é denúncia nossa
    |--------------------------------------------------------------------------
    |
    | Lista de ids — um processo entra no SEFAL se o assunto (ou o subassunto)
    | dele estiver aqui. Vazio = a integração não reconhece nada, e é esse o
    | estado até a SEMOP dizer quais são os códigos: adivinhar id de catálogo
    | alheio é a forma mais rápida de importar processo de outro setor.
    |
    | Descubra os ids com `GET /grupos` e `GET /assuntos/{id_grupo}`.
    |
    */

    'grupos' => array_filter(array_map('trim', explode(',', (string) env('ESALVADOR_GRUPOS', '')))),
    'assuntos' => array_filter(array_map('trim', explode(',', (string) env('ESALVADOR_ASSUNTOS', '')))),
    'subassuntos' => array_filter(array_map('trim', explode(',', (string) env('ESALVADOR_SUBASSUNTOS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Como a leitura se comporta
    |--------------------------------------------------------------------------
    |
    | `janela_em_dias` respeita o teto da API: consulta por período aceita no
    | máximo 90 dias, e exige data de início E de fim juntas.
    |
    | `por_pagina` idem: o `per_page` deles vai até 100.
    |
    | `ligada` é o interruptor. Desligada, nada sai daqui para a rede — é o
    | estado de quem ainda não recebeu credencial nem liberação de IP, que é
    | onde estamos.
    |
    */

    'ligada' => (bool) env('ESALVADOR_LIGADA', false),
    'janela_em_dias' => min(90, (int) env('ESALVADOR_JANELA_EM_DIAS', 30)),
    'por_pagina' => min(100, (int) env('ESALVADOR_POR_PAGINA', 100)),

    /*
    | O token JWT deles dura 3600s. Guardamos por um pouco menos para nunca
    | apresentar um token que expira no caminho.
    */
    'token_valido_por' => (int) env('ESALVADOR_TOKEN_VALIDO_POR', 3300),

];
