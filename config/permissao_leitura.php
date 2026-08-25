<?php

/*
|--------------------------------------------------------------------------
| Leituras alcançadas de FORA da tela dona
|--------------------------------------------------------------------------
|
| A guarda de leitura deduz a tela pelo caminho (`/retaguarda/{tela}/...`).
| Isso acerta em quase tudo, e erra em dois casos reais — errar aqui significa
| TIRAR de quem trabalha algo que ele já fazia:
|
|   (a) utilitário compartilhado — endereço que mora sob o caminho de uma tela
|       mas é chamado por um componente reusado em várias (uma busca de CEP,
|       por exemplo). Valor `'*'`: qualquer pessoa autenticada.
|
|   (b) documento de um fluxo aberto de dentro de outro. Valor = lista de
|       telas: passa quem puder ver qualquer uma delas. A tela dona não precisa
|       ser listada — ela já passa pela conferência normal.
|
| Chave = nome da rota.
|
| ⚠️ Isto é LEITURA. Gravar continua sendo da outra guarda: uma tela aberta a
| todos não significa que todos gravem nela.
|
| ⚠️ E isto NÃO serve para "consertar" uma tela que devia ter sido concedida ao
| setor — isso é decisão de matriz, tomada na tela do Modo Gerente.
|
| Está vazio porque hoje nenhum endereço de leitura é compartilhado entre
| telas: cada um é alcançado pela própria. Ao criar rota GET sob o caminho de
| uma tela, a pergunta a fazer é "quem mais chama isto?" — componente
| compartilhado é a armadilha.
|
*/

return [
];
