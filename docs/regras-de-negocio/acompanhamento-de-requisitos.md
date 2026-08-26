# Acompanhamento de Requisitos

**Onde fica:** Menu → Sistema → Acompanhamento de Requisitos (`/retaguarda/acompanhamento-de-requisitos`).
**Quem usa:** administrador.

A tela cruza **cada funcionalidade entregue** com o **requisito escrito** (a História de Usuário)
que a especifica. A pergunta que ela responde não é "está construída?" — isso se vê usando o
sistema — e sim **"o que está construído ainda condiz com o que foi escrito?"**.

É a pergunta que ninguém responde de cabeça depois de algumas semanas. Quando não a respondemos
antes do MR, quem a responde é a Qualidade, em forma de card de retorno.

---

## Regras vigentes

### RN-01 — A fonte é o repositório, e a tela é só leitura

O mapa vive em [`config/acompanhamento_requisitos.php`](../../config/acompanhamento_requisitos.php),
versionado junto com o código que ele descreve. Não há como editar uma linha pela tela, e isso é
decisão de **fonte única**: com dois donos, a linha mudaria na tela e continuaria velha no arquivo
que o time lê na revisão — e um dia os dois discordariam sem ninguém perceber.

Nenhuma mutação mora sob o caminho da tela, e o teste reprova se alguma nascer.

### RN-02 — Três situações, e cada uma obriga a dizer alguma coisa

| `hu_status` | Significa | O que a linha é obrigada a trazer |
|---|---|---|
| `sim` | Existe requisito escrito e o comportamento está alinhado a ele | os códigos das HUs em `hus` |
| `desatualizada` | Existe requisito escrito, mas o comportamento **divergiu** | os códigos das HUs **e** a divergência descrita na `nota` |
| `nao` | Não há requisito escrito | a **origem** da funcionalidade na `nota` |

Nenhuma linha nasce muda. Dizer "tem HU" sem apontar qual não ajuda quem vai procurar o requisito;
dizer "não tem HU" sem contar de onde a funcionalidade veio deixa a tela órfã — semanas depois
ninguém sabe se ela nasceu da spec, de um organograma ou de um pedido de corredor.

### RN-03 — Funcionalidade nova nasce com a linha, no MESMO commit

Tela, rotina ou integração nova entra aqui junto com o código. Alteração de funcionalidade
existente **reavalia** o `hu_status`: se o comportamento passou a divergir do requisito escrito, a
linha vira `desatualizada` com a divergência na `nota`; se o requisito foi realinhado, volta a `sim`.

**Divergência silenciosa é o que faz um requisito virar ficção.**

A lei é travada por teste: toda tela do menu precisa ter linha aqui, e a ligação é a **rota** (nome
de tela muda; rota não). Menu novo sem linha reprova a suíte.

### RN-04 — Os números são a conta das linhas, nunca escritos à mão

O resumo e o agrupamento por módulo saem das linhas a cada abertura da tela. Número escrito
envelhece na primeira funcionalidade nova, e um painel que conta errado é pior que painel nenhum —
ninguém desconfia dele.

São **duas contas diferentes**, e confundi-las esconde o problema:

- **cobertura** (`percentComHu`): quanto do sistema tem requisito escrito;
- **alinhamento** (`percentAlinhada`): das que têm requisito escrito, quantas ainda condizem com ele.

Dá para ter cobertura de 100% com o requisito todo desatualizado.

### RN-05 — Hoje o projeto não tem HU escrita, e a tela diz isso em voz alta

A régua atual é a **spec de design aprovada com o dono**, não uma HU. Por isso toda linha nasce
`nao`, declarando essa origem na nota. Quando as HUs forem redigidas, cada linha ganha os códigos e
passa a `sim`. Enquanto isso, a leitura honesta da tela é "0% de requisito escrito" — e é
exatamente essa a situação.

### RN-06 — Busca inteligente e exportação, como em qualquer listagem

Campo único que entende a frase (`sem requisito`, `divergente`, `alinhada`) e casa o resto do texto,
sem acento, contra módulo, funcionalidade, caminho no menu, HU e observação. Duas situações pedidas
na mesma frase **somam** (quem pede as duas quer ver as duas).

A exportação (PDF / Excel / Word) sai pelo ponto único do projeto e entrega o **recorte visível**,
com a situação já em palavras — o documento é lido fora do sistema, onde ninguém traduz código
interno.

---

## Fora de escopo (por ora)

- **Geração do documento de HU.** Não há HU redigida neste projeto, então não existe coluna de
  download nem gerador. Quando houver, isso é outra entrega — e a coluna nasce lá.
- **Acompanhamento de Telas** ("está construída?"). Decisão do dono: o projeto tem esta tela, não as
  duas.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/08/2026 | José Nascimento | Acompanhamento de Requisitos | Criação da tela e do mapa em `config/acompanhamento_requisitos.php`, com o inventário das funcionalidades já entregues (todas sem requisito escrito, declarando a spec de design como origem). | Sem esse cruzamento, a régua de cada tela some com o tempo e a divergência entre o construído e o escrito só aparece no retorno da Qualidade. |
| 26/08/2026 | José Nascimento | Acompanhamento de Requisitos | O mapa passa a listar a **exportação de listagens**, que não tem item de menu. | O mapa é de funcionalidade entregue, não de linha do menu. Regra que vale em todas as telas é justamente a que ninguém lembra de conferir depois — e a lei que garante a cobertura só olha o menu, então essa linha nunca seria cobrada. |
