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

    /*
     * (d) Exportação de listagem. É POST por causa do WAF (o recorte vai no
     * corpo), mas o que ela faz é LEITURA: recebe de volta as linhas que a tela
     * já entregou e as embala em PDF/XLSX/DOCX. A autorização aconteceu no GET
     * que montou a listagem — não há dado novo a alcançar aqui.
     *
     * Também não pertence a tela nenhuma: é disparada de qualquer grade do
     * sistema, então não há slug a que atribuí-la. Colocá-la na matriz criaria
     * uma segunda permissão para a MESMA decisão ("quem vê esta listagem"), e um
     * dia as duas discordariam — a tela abriria e o botão de exportar recusaria,
     * ou o contrário.
     */
    /*
     * (a) Emitir relatório é LEITURA, e a ação que a governa é a que abre a tela.
     *
     * A inferência leria "opera" (é POST e não é `.store`), e aí um setor com
     * "Vê" + "Só consulta" abriria a tela de Relatórios e teria recusado o único
     * botão que ela tem. Tela que abre para não fazer nada é pior que tela
     * fechada: a pessoa clica, é barrada e não entende por quê.
     *
     * É POST por causa do WAF (filtros e datas viajam no corpo), não porque
     * grave algo — nada é alterado ao emitir um documento.
     */
    'retaguarda.relatorios.gerar' => [
        'slug' => 'relatorios',
        'acao' => 'visivel',
        'motivo' => 'Emitir relatório é leitura: quem pode abrir a tela pode emitir. O POST existe por causa do WAF, não porque grave algo.',
    ],

    'retaguarda.exportar-listagem' => $livre(
        'Exporta o recorte que a tela já autorizou no GET; não é fronteira de dados nem pertence a uma tela.',
    ),

];
