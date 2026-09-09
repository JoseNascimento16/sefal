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

### RN-05b — A grade leva o SINAL; o parágrafo abre no clique

A listagem mostra **Módulo · Funcionalidade · Requisito**, e a coluna do requisito é o **selo** da
situação (Divergente / Sem requisito / Alinhada) — a resposta que a tela existe para dar. A
**observação**, que é onde mora a divergência escrita ou a origem da funcionalidade, abre na ficha do
clique na linha: ela é um parágrafo inteiro, e dentro da célula chegava a ocupar noventa linhas de
texto, esticando uma linha da grade a 1.785px.

O cuidado, aqui, foi não mandar para o detalhe o motivo de a tela existir. O que faz a varredura
funcionar é o **selo**; o parágrafo responde à decisão, e essa vem depois de achar. A linha que
**divergiu** ganha ainda a marca laranja na ponta esquerda (a mesma do resto do sistema para "isto
espera alguém") e vem primeiro na ordem inicial.

Duas colunas são **condicionais**, e a condição é o que os dados têm:

- **Origem** (Retaguarda / PWA) só entra quando o mapa tem mais de uma frente. Hoje é tudo
  Retaguarda, e a coluna repetiria a mesma palavra em toda linha; quando o aplicativo do fiscal
  chegar, ela aparece sozinha.
- **HU** só entra quando alguma linha aponta HU. Enquanto nenhuma aponta (RN-05), a coluna seria um
  travessão repetido — e o selo "Sem requisito" já diz isso, uma vez, no lugar certo.

Quem resolve as duas condições é o **servidor**, pela mesma leitura que monta as linhas; e as duas
colunas continuam na ficha e no arquivo, sempre. A régua da listagem está em
[`docs/padroes/listagem-clean.md`](../padroes/listagem-clean.md), e as colunas desta tela em
`config/listagens_da_retaguarda.php` (`sistema.requisitos`).

### RN-06 — Busca inteligente e exportação, como em qualquer listagem

Campo único que entende a frase (`sem requisito`, `divergente`, `alinhada`) e casa o resto do texto,
sem acento, contra módulo, funcionalidade, caminho no menu, HU e observação. Duas situações pedidas
na mesma frase **somam** (quem pede as duas quer ver as duas).

A exportação (PDF / Excel / Word) sai pelo ponto único do projeto e entrega o **recorte visível**,
com a situação já em palavras — o documento é lido fora do sistema, onde ninguém traduz código
interno. O arquivo é **mais rico que a tela**: leva também origem, caminho no menu, HU e a
observação inteira, que desceram da grade (RN-05b).

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
| 09/09/2026 | José Nascimento | Acompanhamento de Requisitos | **A grade ficou enxuta** (padrão [`docs/padroes/listagem-clean.md`](../padroes/listagem-clean.md), listagem `sistema.requisitos`): **Módulo · Funcionalidade · Requisito**, com **Origem** e **HU** como colunas condicionais (RN-05b). A **observação** e o **caminho no menu** saíram da grade e abrem na ficha do clique na linha; os códigos de HU deixaram de ser uma fileira de selos embaixo do nome da funcionalidade e a origem deixou de ser chip, virando texto. A linha divergente ganhou a marca laranja na ponta. Tudo continua no arquivo exportado (RN-06). | Ordem do dono (09/09/2026): _"as listagens estão muito poluídas, muita informação quebrando linha de forma irregular… deixe a informação detalhada para quando o usuário clicar"_. Medido no DOM antes: 6 colunas, alturas de linha de **147 a 1.785px** (a observação chegava a noventa linhas de texto numa célula), 34 de 60 células ocupando mais de uma linha, nenhuma linha clicável e a página rolando na horizontal em retrato estreito. Depois: 56px em toda linha, nenhuma célula quebrando e a página sem rolagem horizontal. |
| 25/08/2026 | José Nascimento | Acompanhamento de Requisitos | Criação da tela e do mapa em `config/acompanhamento_requisitos.php`, com o inventário das funcionalidades já entregues (todas sem requisito escrito, declarando a spec de design como origem). | Sem esse cruzamento, a régua de cada tela some com o tempo e a divergência entre o construído e o escrito só aparece no retorno da Qualidade. |
| 26/08/2026 | José Nascimento | Acompanhamento de Requisitos | O mapa passa a listar a **exportação de listagens**, que não tem item de menu. | O mapa é de funcionalidade entregue, não de linha do menu. Regra que vale em todas as telas é justamente a que ninguém lembra de conferir depois — e a lei que garante a cobertura só olha o menu, então essa linha nunca seria cobrada. |
| 26/08/2026 | José Nascimento | Acompanhamento de Requisitos | Correção da frase do subtítulo, que estava sem o "se" e terminava em interrogação sobrando. | É o primeiro texto que se lê na tela — e o primeiro que a Qualidade lê. |
