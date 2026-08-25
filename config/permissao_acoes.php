<?php

/*
|--------------------------------------------------------------------------
| Exceções da guarda de AÇÕES
|--------------------------------------------------------------------------
|
| Este arquivo NÃO é a lista do que está protegido — é a lista do que FOGE da
| regra. Toda mutação sob `/retaguarda` é atribuída automaticamente à tela do
| seu caminho, com a ação lida da convenção de nomes (`.store` inclui,
| DELETE/`.destroy` exclui, o resto opera). Rota nova, portanto, nasce
| protegida sem ninguém declarar nada.
|
| Só entra aqui quem escapa da inferência:
|
|   (a) a ação não é a inferida — declare `acao`;
|   (b) o caminho não é o slug da tela — declare `slug`;
|   (c) a mutação mora sob o caminho de UMA tela mas é legítima a partir de
|       outra — declare `slugs` (passa quem tiver a ação em qualquer uma);
|   (d) a rota está reconhecidamente FORA do alcance do Modo Gerente —
|       declare `livre`.
|
| `motivo` é OBRIGATÓRIO em toda declaração, e `PermissaoAcaoCoberturaTest`
| reprova quem esquecer. Exceção sem justificativa escrita é brecha esperando
| para ser copiada: o próximo dev vê a linha, acha que é o padrão e acrescenta
| a dele.
|
| ⚠️ Nada aqui serve para "conceder" uma tela a um setor. Isso é decisão de
| matriz, tomada na tela do Modo Gerente — não em código.
|
*/

/** A rota está fora do alcance do Modo Gerente, e aqui está o porquê. */
$livre = fn (string $motivo): array => ['livre' => true, 'motivo' => $motivo];

return [

    /*
     * (d) A própria conta. Trocar os próprios dados e a própria senha não é
     * decisão de gestor: colocar isso na matriz permitiria trancar alguém fora
     * da conta dele — e, no caso da senha, deixá-lo sem como recuperá-la. As
     * telas moram sob `/retaguarda/perfil`, que de propósito não declara `slug`
     * no menu, então a inferência não as atribui a tela nenhuma.
     */
    'profile.update' => $livre('Dados da própria conta — não é decisão de gestor.'),
    'user-password.update' => $livre('Senha da própria conta — não é decisão de gestor.'),

];
