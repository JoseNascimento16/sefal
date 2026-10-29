# Saúde dos Fluxos

**Onde fica:** Menu → Sistema → **Saúde dos Fluxos** (`/retaguarda/saude-dos-fluxos`).
**Quem usa:** **só o administrador**.

O fluxograma do trabalho do SEFAL de ponta a ponta: da chegada da demanda, por cada canal, até cada
destino final, passando pela mesa do Chefe de Setor, pela Fiscalização do líder e pelo aplicativo do
fiscal. Serve para ver, num desenho só, o que o sistema **atende hoje** e o que ainda está
**previsto**.

**Origem:** pedido do dono (27/10/2026), a partir da tela de mesmo nome do CODECON, que foi trazida com
a mesma estrutura. Não há HU escrita: a linha em
[`config/acompanhamento_requisitos.php`](../../config/acompanhamento_requisitos.php) nasce
`hu_status => 'nao'`.

---

## Regras vigentes

### RN-01 — Visão geral e uma aba por fluxo

A tela abre na **Visão geral**, com todos os fluxos do catálogo: quantos trechos cada um tem, quantos o
sistema atende hoje e quais ainda são só previstos (exportável). Cada fluxo tem a sua aba. No SEFAL são
dois: a **fiscalização de ambulantes** de ponta a ponta e o **aplicativo do fiscal** visto de dentro
(tudo o que o fiscal faz no APP, a partir do Início); um fluxo novo é uma classe em
`app/Support/SaudeFluxos/Fluxos/` registrada em `CatalogoFluxos`, e ganha a aba e a linha sozinho.

### RN-02 — Cada quadradinho diz se o trecho é atendido ou previsto

Contorno cheio é o trecho que o sistema **atende hoje**; cinza pontilhado é o **previsto** — tem
lugar no fluxo, mas o sistema ainda não o percorre. A legenda conta os dois. Hoje são previstos: na
fiscalização, a chegada das demandas e o envio da resposta pelas integrações (API) do e-Salvador, do
e-Protocolo, do Fala Salvador e do SGCI; no aplicativo, a ficha do SGCI e a sugestão de operações
por IA na Retaguarda a partir do censo.

### RN-03 — Clicar num quadradinho abre o trecho

O painel lateral mostra quem age, a descrição do trecho, **onde** ele acontece (a tela da Retaguarda,
do aplicativo ou o canal de fora) e a etapa do passo a passo a que pertence. Fecha com Esc, com o X ou
clicando fora.

### RN-04 — Passo a passo, formas de conclusão e texto exportável

Embaixo do desenho ficam o **passo a passo** completo (cada etapa com atalhos para os trechos) e
**todas as formas de conclusão** (quem encerra e por qual caminho). "Exportar texto" baixa o mesmo
conteúdo em Markdown, com os trechos previstos à parte.

### RN-05 — Fonte única: o catálogo do fluxo

A tela, o texto exportado e o passo a passo saem do mesmo catálogo
(`App\Support\SaudeFluxos\Fluxos\FluxoDaFiscalizacao`). **Alterou o fluxo de trabalho? Atualize o
catálogo no mesmo commit.** Testes travam o desenho: id e posição únicos, seta só entre trechos que
existem, nenhum trecho solto, entrada que leva a algum lugar, fim que só recebe, passo a passo que
cobre todos os trechos.

### RN-06 — O desenho

SVG próprio, sem biblioteca de diagrama: as setas andam só pelos corredores entre os quadradinhos, sem
cortar nenhum, cada uma num tom frio próprio. Zoom pelos botões ou por Shift + rolagem do mouse;
ampliado, o desenho se arrasta com o mouse.

### RN-07 — O que ainda não existe aqui

No CODECON, cada quadradinho também fica verde ou **vermelho** pela última verificação do ambiente
(parametrização, armazenamento, jornada sintética que percorre o fluxo). No SEFAL essa parte ainda não
existe: a tela só desenha o fluxo. O componente já tem os estados de saúde prontos para quando ela vier.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 28/10/2026 | José Nascimento | Saúde dos Fluxos | Aplicativo do fiscal: a última localização do ambulante pelo censo e o censo no mapa de calor passam a atendidos. | Entregues no mesmo dia. |
| 28/10/2026 | José Nascimento | Saúde dos Fluxos | Aplicativo do fiscal: sai o "cadastrar ambulante em campo" (o SEFAL não cadastra ambulante; a base é do SGCI) e o censo ganha os destinos previstos — última localização no mapa, mapa de calor e sugestão de operações por IA. | Decisão do dono, 28/10/2026. |
| 28/10/2026 | José Nascimento | Saúde dos Fluxos | Nova aba **Aplicativo do fiscal**: tudo o que o fiscal faz no APP — entrar no plantão, a fila, o mapa, o calor, a operação, a ronda, o levantamento de rua, a vistoria até a via impressa, o retorno da notificação e o fim do plantão. Previstos: a ficha do SGCI e o cadastro do ambulante em campo. | Pedido do dono: visualizar o que os fiscais podem fazer no APP e o que ele já atende. |
| 27/10/2026 | José Nascimento | Saúde dos Fluxos | Tela criada (RN-01 a RN-07), com o fluxo da fiscalização de ambulantes de ponta a ponta, incluindo o aplicativo do fiscal. | Pedido do dono: visualizar o que o sistema atende hoje ou está previsto de atender, da chegada das demandas aos destinos finais. |
| 28/10/2026 | José Nascimento | Saúde dos Fluxos | O previsto passa a ser desenhado em **cinza pontilhado**. Entram as integrações previstas (e-Salvador, e-Protocolo, Fala Salvador e SGCI · licenças); o retorno da Notificação Preliminar passa a atendido, com o retorno vencido caindo no líder; o líder que devolve sem vistoria leva o caso de volta à triagem; a deliberação do Chefe depois da fiscalização deixa de levar a "Devolvida" e "Arquivada"; o desfecho deixa claro o "orientado, desfez a montagem e saiu". | Pedido do dono, 28/10/2026. |
| 28/10/2026 | José Nascimento | Saúde dos Fluxos | Entram as respostas previstas pelas APIs do e-Protocolo, do Fala Salvador e do SGCI (licenças), ao lado da do e-Salvador. | Pedido do dono, 28/10/2026. |
