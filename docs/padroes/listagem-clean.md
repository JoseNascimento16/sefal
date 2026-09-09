# Padrão de LISTAGEM — grade enxuta, detalhe no clique, arquivo completo

> **Ordem do dono, 09/09/2026:** _"As listagens estão muito poluídas, muita informação quebrando
> linha de forma irregular. Deixe as listagens mais CLEAN, deixando a informação detalhada para
> quando o usuário clicar e quiser mais informações. **Adote como padrão no sistema.**"_

Este documento é a régua. Vale para **toda** listagem da Retaguarda — a aba "Localizar" das telas de
cadastro e a grade operacional (caixas de entrada, filas, consultas, painéis, logs) — e para as que
ainda vão nascer.

Onde ela é aplicada, listagem por listagem: [`config/listagens_da_retaguarda.php`](../../config/listagens_da_retaguarda.php).
Onde ela é travada: [`tests/Feature/ListagemCleanTest.php`](../../tests/Feature/ListagemCleanTest.php).

---

## O diagnóstico — por que esta régua existe

O print que gerou a ordem era a fila de Fiscalizações. Ela tinha:

- **sete colunas**, com o texto livre do fiscal dentro de uma delas ("Três permissionários de barraca
  de praia, todos com permissão regular no trecho");
- **texto secundário empilhado embaixo do primário** em quatro das sete: "Costa Azul · Área 5" sob o
  endereço, "Barracas de chapa com toldo" sob a descrição, o nome do fiscal sob a equipe, o número da
  notificação sob o desfecho;
- e, por consequência, **altura de linha variando de ~60 a ~160 px**.

O defeito não é estético. Uma grade tem **uma** vantagem sobre um cartão: o olho desce a coluna e
compara. Com a linha mudando de altura a cada registro, a coluna deixa de ser uma coluna — passa a
ser uma pilha de blocos de tamanhos diferentes, e quem lê perde exatamente a leitura que foi buscar
ali. A informação empilhada não estava a mais: estava **no lugar errado**.

---

## A régua

### 1. Uma linha por registro, altura fixa

Nada quebra linha dentro da célula. O que não couber é **truncado com reticências**, com o texto
inteiro no `title` **e** no detalhe.

A altura é declarada na célula (`table.data-table.enxuta td { height: 56px }`), **não derivada do
conteúdo**. Derivada, a linha que tem selo fica alguns pixels mais alta que a que tem só texto — e
volta a irregularidade, em escala menor e mais difícil de nomear.

> **O corte é do CSS, nunca um `substring` no dado.** `text-overflow: ellipsis` esconde do olho e
> mantém o texto inteiro no DOM: leitor de tela, busca do navegador (Ctrl+F) e cópia continuam
> vendo o valor completo. Cortar no JavaScript apagaria a informação para quem não vê a tela.
>
> A exceção é quando a tela **resume de verdade** (o primeiro selo de uma lista, com "+2"). Aí o que
> ficou de fora só existe na dica, e ela passa a ser obrigatória — e também `aria-label`.

#### Duas linhas, quando a frase É a informação (`linhas: 2`)

Há coluna em que o corte não esconde o fim de um dado — esconde **o dado**. A recomendação do fiscal
é o caso: `Voltar ao ponto no venci…` não diz nada, e é justamente a frase que o Chefe de Setor foi
ler para decidir. Para esses casos a coluna declara `'linhas' => 2` no catálogo, e a célula:

- **quebra a linha** (`.celula-2l`), com `overflow-wrap: break-word` — **nunca `anywhere`**, que faz
  a largura mínima do texto virar um caractere e o navegador quebrar palavra por palavra (medido: 88
  px de conteúdo numa célula de 32);
- usa a fonte **um ponto menor** (12,4 px em vez de 13,5), que é o que faz duas linhas caberem na
  altura de antes;
- tem **teto de altura igual à altura da linha** — não calculado a partir do texto: quando o
  conteúdo é um **selo**, o acolchoamento dele entra na conta (duas linhas de texto = 52 px de selo);
- e o **contador "+N" vai DENTRO do selo**, fluindo com as palavras. Fora dele é outra caixa na
  linha: com o selo ocupando a largura toda da célula, ela descia para uma terceira linha e
  estourava a altura.

**A linha da grade não cresce** — é isso que mantém a régua de pé. Quem declara `linhas: 2` está
dizendo "aqui a frase vale duas linhas", não "aqui a régua não vale". Se um dia três colunas de uma
mesma grade pedirem isso, o problema não é o teto: é que a tela está tentando ser a ficha.

### 2. No máximo cinco colunas

Cinco respostas: **quando · onde · quem · o que deu · em que estado**. Coluna a mais é decisão de não
olhar nenhuma.

O teto é conferido por teste, nas **duas** resoluções da grade (com e sem a coluna condicional do
item 4) — testar só uma deixaria a outra passar de seis sem nada acusar.

### 3. Texto livre não entra na grade

Relato, "quem foi encontrado" escrito em frase, justificativa, observação, descrição, foco,
recomendação em frase inteira, assunto longo: nada disso é coluna. Na linha cabe, no máximo, **um
selo dizendo a categoria**.

A lista dos campos de texto livre do sistema está no topo de
[`config/listagens_da_retaguarda.php`](../../config/listagens_da_retaguarda.php) (`texto_livre`), e é
**global** de propósito: se cada autor declarasse os seus, bastaria esquecer de declarar para o campo
passar.

### 4. Sem sub-linha secundária dentro da célula

Bairro, área, equipamento, nome do fiscal sob a equipe, código sob o nome — tudo isso desce para o
detalhe. Se **um** deles é essencial para varrer, ele vira **coluna própria e curta**; nunca texto
empilhado.

E "essencial para varrer" depende de **quem está olhando**. A área é o caso concreto: o Chefe de
Setor só vê a dele (a coluna repetiria a mesma palavra em toda linha, gastando largura sem
informação), enquanto o Coordenador varre cinco áreas e para ele a área **é** o que torna a fila
navegável. Isso se resolve com **coluna condicional** (`'quando' => 'varias-areas'` no catálogo,
resolvida no servidor), não com sub-linha.

> A conta de quem vê quantas áreas é a **mesma** que já decide o recorte dos dados
> (`PapelNaArea`). Repeti-la na tela criaria um segundo dono para a resposta.

A condição também pode ser **o que os dados têm**, e não quem olha — é o mesmo defeito visto do outro
lado: coluna que mostra o mesmo valor em toda linha não informa nada. No Acompanhamento de
Requisitos, `origem` só é coluna quando existe funcionalidade de mais de uma frente (hoje é tudo
Retaguarda) e `hus` só é coluna quando alguma linha aponta HU (hoje nenhuma aponta, e o selo "Sem
requisito" já diz isso uma vez). Quem resolve continua sendo o **servidor**, pela mesma leitura que
monta as linhas.

> Toda coluna condicional precisa do **flip** no teste: a resolução em que ela entra E a em que ela
> sai. Sem a primeira metade, uma condição travada em `false` passa para sempre — e a coluna nunca
> volta no dia em que fizesse falta.

### 5. A linha inteira abre o detalhe

Sempre por [`resources/js/lib/linha-clicavel.ts`](../../resources/js/lib/linha-clicavel.ts), que já
resolve clique, foco, `Enter`/`Espaço` e a dica de acesso. **Nunca** um ícone de lupa no fim da
linha: ele gasta uma coluna, é alvo pequeno e só existe para quem usa mouse.

### 6. A EXPORTAÇÃO continua completa

**Enxugar é da TELA.** O arquivo (PDF/XLSX/DOCX) é lido por quem decide, fora do sistema, e mantém as
colunas detalhadas.

Esta é a regra mais fácil de quebrar sem perceber, e por isso a mais protegida: quem apaga a coluna da
grade apaga a linha vizinha do `<BotaoExportar>` no mesmo impulso, e o arquivo perde o dado **em
silêncio** — ninguém reclama de uma coluna que nunca viu. Por isso o catálogo declara as três listas
no mesmo lugar:

| Chave | O que é |
|---|---|
| `grade` | as colunas visíveis, na ordem |
| `detalhe` | o que **desceu** da grade para a ficha do registro |
| `exportacao` | as colunas do arquivo |

E o teste cruza: `grade ⊆ exportacao`, `detalhe ⊆ exportacao`, `detalhe` não vazio, e a exportação
estritamente mais rica que a grade. Mover um campo da tela para a ficha sem pôr a coluna na
exportação **reprova**, nominalmente.

### 7. Número à direita, data curta, um selo por linha

- Número/quantidade alinhado à direita; identificador (protocolo, código) à esquerda, sem quebra.
- Data em `dd/mm/aaaa` — a **hora** vai para a dica e para o detalhe. (Lei do projeto: nunca ISO à
  vista.)

  > **Exceção declarada — a coluna "Quando" dos Logs.** Numa listagem de diagnóstico a data sozinha
  > não identifica a ocorrência: um surto de erros põe dezenas no mesmo dia, e "o que aconteceu
  > agora" é a pergunta da tela. Lá a coluna leva `dd/mm/aaaa hh:mm`, numa linha só, sem sub-linha —
  > a geometria continua de pé e a data continua em BR. Exceção **declarada no catálogo**, com o
  > motivo escrito: se ela virar hábito, o item 7 morre por mil exceções tácitas.
- **Um selo de ESTADO por linha** — o da coluna de situação/estado, com cor semântica. Marca de
  exceção (prazo vencido, endereço impreciso) entra como **cor no texto** ou **um ícone**, nunca como
  um segundo chip: dois chips na mesma linha voltam a empilhar conteúdo na célula.
- Valor categórico curto que não é o estado (equipes, "Permissionário / Sem permissão") vai como
  **texto**, não como chip.

---

## Antes e depois — Fiscalizações, aba "A decidir"

A tela do print.

**Antes** (7 colunas, 4 delas com sub-linha):

| Concluída | Ponto | Equipe e fiscal | Desfecho | Recomendação do fiscal | Estado |
|---|---|---|---|---|---|
| 08/09/2026 14:20<br>_há 2 dias na fila_ | Rua da Paciência, 120<br>_Rio Vermelho · Área 4_ | Equipe C1<br>_Ana Prado_ | Notificado<br>`Notificação nº 194903` | `Voltar ao ponto no vencimento` `Abrir SGCI` | `Aguardando leitura` |

**Depois** (4 colunas para o Chefe de Setor, 5 para o Coordenador):

| Concluída | Ponto | _(Área)_ | Desfecho | Recomendação do fiscal |
|---|---|---|---|---|
| 08/09/2026 | Rua da Paciência, 120 | _Área 4_ | Notificado | `Voltar ao ponto no vencimento` +1 |

O que mudou, e por quê:

- **`Estado` saiu.** A aba já filtra por "aguardando leitura": a coluna repetia a mesma palavra em
  toda linha. A marca laranja na ponta da linha continua dizendo que aquilo espera alguém.
- **`Equipe e fiscal` desceu.** A decisão da chefia é sobre o **ponto**; a assinatura de quem foi
  importa ao abrir o registro. As duas continuam no arquivo.
- **`Bairro` e o número do documento desceram** — eram as sub-linhas que dobravam a altura.
- **`Área` virou coluna condicional**, pelo item 4.
- **A recomendação** continua na grade (é o motivo de a fila existir), mas como **a primeira** frase
  mais "+N"; a lista inteira está na dica, na ficha e no arquivo.
- **A hora e o "há N dias na fila"** foram para a dica e para a ficha.

### Os números, medidos no DOM (1440×900)

Não é impressão: a geometria era o defeito, e ela é medível. "Alturas" é o conjunto de alturas de
linha **distintas** encontradas na mesma grade — mais de um valor já significa que a coluna deixou de
dar régua vertical.

| Listagem | Colunas (antes → depois) | Alturas antes | Alturas depois | Maior − menor (antes → depois) |
|---|---|---|---|---|
| Fiscalizações · A decidir | 6 → 5 (4 para quem tem uma área) | 93, 98, 135, 139, 148, 156 | 56 | 63 px → **0** |
| Fiscalizações · Acervo | 8 → 5 | 114, 135, 139, 156, 177 | 56 | 63 px → **0** |
| Denúncias · A triar | 9 → 5 | 114, 135 | 56 | 21 px → **0** |
| Cadastro de Operação | 6 → 5 | 114, 135, 156 | 56 | 42 px → **0** |
| Ambulantes · Localizar | 6 → 5 | 87 | 56 | 0 → **0** |
| Caixa de Entrada | 9 → 5 | 93, 114, 135 | 56 | 42 px → **0** |
| Sistema · Logs | 7 → 5 | 93, 100, 135, 156 | 56 | 63 px → **0** |
| Sistema · Acompanhamento de Requisitos | 6 → 3 (5 com as condicionais) | 147, 167, 284, 303, 342, 1005, 1785 | 56 | **1.638 px → 0** |

E, no "depois", em todas elas: **nenhuma** célula quebrando linha, **nenhuma** célula truncada sem o
texto inteiro no `title`, e a página sem rolagem horizontal — nem em 1440×900 nem em 560×820 (aí a
tabela rola dentro do `.table-wrap`, como deve).

Para comparação do "antes": na fila de Fiscalizações, **58 das 60 células** ocupavam mais de uma linha
de texto, e a pior chegava a **sete**. No Acompanhamento de Requisitos a pior chegava a **noventa** —
era a observação, um parágrafo inteiro dentro da célula —, e a página inteira rolava na horizontal em
retrato estreito (565px de conteúdo em 560px de viewport). Depois: nenhuma célula quebrando linha em
nenhuma das duas, nenhuma truncada sem o texto no `title`, e a rolagem horizontal só dentro do
`.table-wrap`.

### As duas listagens de DIAGNÓSTICO — por que elas escolhem outras colunas

Logs e Acompanhamento de Requisitos não movem trabalho: elas respondem a uma pergunta de
diagnóstico, e isso muda o que a grade precisa mostrar.

- **Logs** — quem abre procura *o que quebrou, onde e quando*, quase sempre o mais recente ou o que
  se repete. Grade: **Quando · Código · Tipo do erro · Onde · Usuário**. A **mensagem** da exceção
  sai (é prosa, e das longas — era ela que ocupava seis linhas na célula) e abre no clique junto do
  **rastro**, que é onde os dois de fato se leem. O **código** fica porque é a razão de a tela
  existir: a pessoa dita o que apareceu na página dela e quem atende cai na ocorrência exata.
- **Acompanhamento de Requisitos** — quem abre procura *o que está fora do requisito*. Grade:
  **Módulo · Funcionalidade · Requisito**, com origem e HU condicionais. O selo de situação é o
  coração da tela e **fica**; a observação, que descreve a divergência, abre no clique. Foi o cuidado
  central aqui: enxugar não pode mandar para o detalhe o motivo de a tela existir — o **sinal** fica
  na grade, o **parágrafo** desce.

---

## E quando a informação parece essencial demais para sair da grade?

É a pergunta que sempre aparece, e ela quase sempre tem uma destas cinco respostas — nesta ordem:

1. **Ela é essencial para VARRER, ou para DECIDIR depois de achar?** Só a primeira justifica coluna.
   "Preciso ver isso quando abro o registro" é detalhe, não coluna.
2. **A BUSCA já responde?** A barra é o filtro único da tela e é acento-insensível: documento, número
   de permissão, "prazo vencido", "Área 5", "não identificado" são achados por lá. Campo que serve
   para **procurar** não precisa de coluna para ser **lido**.
3. **Cabe na DICA?** Se o valor completo do que já está na coluna basta (bairro junto do endereço,
   chefia junto da área, hora junto da data), ele vai no `title` — e no detalhe.
4. **Vira uma coluna CURTA, trocando outra?** O teto é cinco. Se ela entra, alguma sai — e a decisão
   é qual das duas quem usa a tela olha mais.
5. **É condicional?** Se é essencial para um perfil e constante para outro, ela é coluna com
   `quando`, resolvida no servidor.

E, valendo para todas as cinco: **sair da grade não é sair do sistema.** O campo continua na ficha do
clique e no arquivo exportado — e o teste não deixa você esquecer a segunda parte.

---

## Como aplicar numa listagem nova

1. Declare a listagem em [`config/listagens_da_retaguarda.php`](../../config/listagens_da_retaguarda.php):
   `tela`, `grade` (≤ 5, sem texto livre), `detalhe`, `exportacao`. O comentário de cada listagem
   existente diz **quem varre aquela tela e procurando o quê** — escreva o seu também: é a
   justificativa que a próxima pessoa vai querer ler antes de acrescentar a sexta coluna.
2. No controller, passe a listagem para a tela:
   `'listagens' => ListagensDaRetaguarda::para('minha-listagem')` — e, se houver coluna condicional,
   o contexto: `ListagensDaRetaguarda::para($ids, ['varias-areas' => ...])`.
3. Na tela, monte o cabeçalho e as células a partir da **mesma** lista:
   ```tsx
   <table className="data-table enxuta">
       <thead><tr><CabecaDaGrade grade={listagem.grade} ord={ord} acessores={acessores} /></tr></thead>
       <tbody>
           {pag.visiveis.map((r) => (
               <tr key={r.id} {...linhaClicavel(() => abrir(r), 'Abrir o registro')}>
                   {listagem.grade.map((coluna) => {
                       const { conteudo, dica } = celula(r, coluna.chave);
                       return <Celula key={coluna.chave} coluna={coluna} dica={dica}>{conteudo}</Celula>;
                   })}
               </tr>
           ))}
       </tbody>
   </table>
   ```
   Cabeçalho e células saem da mesma lista de propósito: escritos em dois lugares, uma coluna nova
   entra só num deles e a grade passa a mostrar o valor sob o título errado.
4. `<BotaoExportar colunas={listagem.exportacao} linhas={...} />` — e as linhas são o **recorte
   visível inteiro** (`ord.itens`), nunca `pag.visiveis`.
5. Garanta que o **detalhe** mostra o que desceu. Duas formas sancionadas, e nenhuma terceira:
   - a **aba do registro** dentro do mesmo cartão (telas de cadastro e de tramitação, onde o detalhe
     é uma vista completa com ações) — Ambulantes, Operações, Caixa de Entrada, Denúncias;
   - a **linha de detalhe** expandida (`tr.linha-detalhe`) ou a **folha sobreposta**
     ([`sobreposicao.tsx`](../../resources/js/components/retaguarda/sobreposicao.tsx)) quando a
     listagem é uma fila e o detalhe é leitura rápida — Fiscalizações.
6. Rode `tests/Feature/ListagemCleanTest.php`. Ele é nominal: diz qual listagem, qual coluna e o quê.

### Exceções declaradas do CSS

`table.data-table.enxuta` prende a altura e proíbe a quebra de linha. Duas células escapam, e só
duas: `td.tabela-vazia` (é uma frase, e precisa quebrar) e `tr.linha-detalhe td` (é ficha, não
registro). Fora dessas, célula que quebra linha é defeito.

---

## Changelog

| Data | Autor | Alteração | Motivo |
|---|---|---|---|
| 09/09/2026 | José Nascimento | A régua ganha a **terceira exceção declarada**: coluna com `'linhas' => 2` quebra a linha com a fonte um ponto menor e teto igual à altura da linha. Aplicada à **recomendação do fiscal** na fila de Fiscalizações. | O corte escondia a própria informação que se ia ler: "Voltar ao ponto no venci…" não diz nada, e a frase é o que faz o Chefe de Setor decidir. Pedido do dono, medido depois: 10 células, 0 cortadas, todas as linhas em 56px. |
| 09/09/2026 | José Nascimento | A régua alcança as **duas listagens de Sistema** — Logs (`sistema.logs`) e Acompanhamento de Requisitos (`sistema.requisitos`) —, as duas entram no teste-lei e ele **cresceu**: passou a cobrar o **flip** da coluna condicional (a resolução em que ela entra e a em que ela sai). `mensagem` e `nota` entraram na lista global de texto livre, e o item 7 ganhou **uma exceção declarada**: a coluna "Quando" dos Logs leva a hora. | As duas ficaram de fora da primeira aplicação, e listagem fora da varredura é a que apodrece. Eram, medidas, as piores da casa: a observação do acompanhamento chegava a noventa linhas de texto numa célula (linha de 1.785px) e a mensagem de exceção a seis. Como são telas de diagnóstico, e não de fluxo, a escolha de colunas é outra — está escrita acima, junto do motivo. |
| 09/09/2026 | José Nascimento | Documento criado; régua aplicada nas sete listagens existentes (Fiscalizações A decidir/Acervo, Denúncias A triar/A direcionar/Todas, Cadastro de Operação, Ambulantes, Caixa de Entrada); catálogo em `config/listagens_da_retaguarda.php`; grade enxuta em CSS; teste-lei. | Ordem do dono: listagens poluídas, com texto livre na célula e altura de linha irregular. |
