# Design da Retaguarda — a casca, e o que vale para as telas

**Onde fica:** vale para toda tela autenticada da Retaguarda.
**Quem usa:** quem constrói tela nova, e quem altera tela existente.

Este doc registra as **decisões de desenho** aprovadas pelo dono — não é catálogo
de cor (isso está em `resources/css/retaguarda.css`, com o motivo de cada token ao
lado). O que está aqui é o que uma tela nova precisa saber para nascer parecida
com as outras, e o que já está decidido para as telas que ainda vão existir.

> Os mockups aprovados ficam em **`E:\ferramental\design-exemplos`** — fora do
> repositório, porque são material de trabalho e não produto: `shell-layouts/` (a
> casca, a doca e a tela de mapa), `login-splits/` e `overlays-boas-vindas/`. Se
> precisar deles e não estiverem no disco, peça ao dono.

---

## Regras vigentes

### RN-01 — A casca é "editorial curva": moldura escura, trabalho claro

O menu é um painel **navy com o canto direito curvado**, e o miolo é claro. A
curva faz o miolo parecer uma folha apoiada sobre o painel, em vez de duas colunas
encostadas.

O painel é **escuro nos dois temas**, e isso é decisão, não esquecimento: ele é
moldura. No tema claro, um painel branco encostado num miolo branco apagaria a
divisão — e a identidade do sistema (a cidade de noite ao lado da mesa iluminada)
vive justamente nesse contraste. Por isso as cores dele têm tokens próprios
(`--sm-menu-*`) que a troca de tema **não** alcança.

No pé do painel há uma **malha de ruas** decorativa. Nenhum traço ali é dado do
sistema — é a cidade por baixo do menu, e nada mais.

### RN-02 — Não há barra superior: o topo da tela é o cabeçalho da PÁGINA

A tela abre com **sobrancelha da seção** (azul, espaçada), **título grande com
ponto azul final** ("Permissionários.") e **subtítulo**. É o `.rt-page-head`, que
cada tela já escrevia — o que mudou foi o peso: ele passou a ser o topo da tela.

O ponto final é do CSS (`.rt-page-head h1::after`), e não digitado em cada tela:
assim toda tela nova nasce com a assinatura sem ninguém lembrar dela. **Não
escreva o ponto no texto do `<h1>`** — sairia dobrado.

O que era da barra se dividiu:

| Antes, na barra | Agora |
|---|---|
| Trilha de navegação | A sobrancelha + o título dizem os mesmos dois níveis, com mais presença. A trilha só é desenhada quando tem **mais de um nível** (aí ela diz o caminho de volta, que o título não diz) |
| Identidade e **Sair** | Cartão do usuário no pé do painel |
| Tema e avisos | Cluster discreto no canto superior direito do miolo (`.rt-cluster`) — sem fundo, sem borda, sem reservar altura |
| Botão de abrir o menu | Não existe: o menu está **sempre à vista** (RN-04) |

**Por quê:** a barra gastava 68px de altura em toda tela para repetir, em corpo 13,
o que o título já dizia em corpo 42.

### RN-03 — O menu mostra NÚMERO VIVO onde o número muda a decisão

Um item pode trazer um número à direita. Ele é declarado no item
(`config/retaguarda_menu.php`, chave `contador`) e apurado por
`App\Support\ContadoresDoMenu`, que decide **como contar** e **com que tom**:

- **neutro** — é tamanho ("128 cadastrados"). Informa, não cobra. Zero aparece:
  lista vazia é um tamanho honesto;
- **alerta** — é **fila** ("7 aguardando conferência"). Sai em laranja, a mesma cor
  de incidência do resto do sistema, e **não aparece em zero**: um "0" laranja
  chama atenção para dizer que não há nada a fazer.

Duas leis, travadas por teste:

1. **barato** — cada contador é UMA contagem, sem junção. O menu é montado em toda
   requisição: contador em item que ninguém consulta é enfeite que se paga em todas
   as telas;
2. **melhor esforço** — contagem que falha (banco fora do ar, tabela ainda não
   migrada) faz o item aparecer **sem número**, nunca derruba a tela. Menu é
   navegação: tem de existir justamente quando algo está errado.

⚠️ **Só declare contador onde o número muda a decisão de quem olha.** A tentação é
pôr número em tudo; o efeito é uma coluna de selos que ninguém lê e uma consulta a
mais por requisição para cada um.

### RN-04 — Um menu, duas formas — e nenhuma escondida

| Forma | Quando | Como é |
|---|---|---|
| **Painel estendido** (292px) | ≥ 1100px, por preferência da pessoa | Nome do item, contador à direita, cartão do usuário no pé |
| **Doca** (96px) | preferência da pessoa, **ou** largura < 1100px | Cartão navy flutuante, destacado das bordas; ícone + rótulo curto; contador virando selo no canto |
| **Barra inferior** | < 620px | A mesma doca, deitada no pé da tela, itens rolando na horizontal |

A escolha entre painel e doca é da **pessoa** e fica guardada no navegador dela
(`localStorage`) — é conforto de quem opera, não dado do sistema. Abaixo de 1100px
a largura manda: a doca vale sozinha, e a preferência **não é apagada** (volta a
valer quando a janela crescer).

**Não existe menu atrás de um botão.** Havia: abaixo de 900px o painel deslizava
por cima com um véu, e o menu ficava a dois toques de distância — inalcançável para
quem não notasse o hambúrguer. Agora ele está sempre em tela, em uma das três
formas.

A **barra inferior** abaixo de 620px é o mesmo lugar em que o aplicativo do fiscal
põe a navegação: quem usa os dois não reaprende nada, e no telefone a faixa do
polegar é onde a navegação pertence.

⚠️ Na doca o rótulo é `curto` (~9 letras) e o **nome inteiro** vai no `title` e no
rótulo acessível: o recorte é visual, e ninguém deve depender dele para saber onde
clica. Item novo que comece por uma palavra ambígua ("Tipos de…") **declara
`curto`** na config — senão a doca mostra três itens chamados "TIPOS".

### RN-05 — Na grade, a linha é um CARTÃO

Cada linha tem raio, sombra leve e respiro entre as vizinhas. O registro é uma
coisa do mundo (uma pessoa que trabalha na rua), e o cartão lhe dá um corpo.

- **Linha com pendência** ganha a **borda esquerda laranja** (classe `pendente` na
  `<tr>`, via `linhaClicavel(..., pendente && 'pendente')`). É o que faz a fila
  saltar ao olho sem ler coluna por coluna;
- **Chip de situação** leva um **ponto de cor com halo** antes da palavra
  (`<span class="selo-dot" />` dentro do `.selo`): o estado é lido de relance, e a
  palavra confirma. O ponto herda a cor do selo — um dono só para a cor do estado;
- **Busca** com exemplo real no próprio campo ("…ex.: *irregulares sem
  documento*"): o rótulo ensina o que a busca aceita, o exemplo ensina **como se
  pergunta**.

⚠️ Segue sendo `<table>`. A semântica de tabela é o que faz o leitor de tela
anunciar "coluna Situação, Regular" em vez de ler quatro textos soltos — o cartão é
aparência (`border-spacing` no lugar de `border-collapse`), não estrutura.

### RN-06 — Números do dia: derivados do que a tela já mostra

Quando a tela tem números de cabeçalho (o `.rt-numeros`, à direita do título), eles
saem da **mesma lista que a grade desenha** — não de uma consulta própria. Assim
não podem discordar do que está logo abaixo, e não custam nada ao servidor.

E eles contam a lista **inteira**, não o resultado da busca: respondem "como está o
cadastro", não "quantos casaram com o que eu digitei". Mudar de valor enquanto
alguém digita faria o painel de números virar um segundo resultado de busca.

### RN-07 — Telas de MAPA usarão o padrão imersivo (decidido, ainda não construído)

Não há tela de mapa na Retaguarda hoje (mapa vivo e mapa de calor são de fase
posterior — ver `docs/PENDENCIAS.md`). Quando existirem, **não** usarão a casca
desta RN-01: usarão o padrão **imersivo** do mockup aprovado, que é outro registro
para outro trabalho.

O que está decidido:

- **o mapa é o fundo**, sangrando de borda a borda — não um cartão dentro do
  miolo. Quem abre um mapa está olhando a cidade, não uma tela com um mapa dentro;
- **navegação em pílula** flutuando no topo, sobre o mapa (não o painel lateral):
  em tela de mapa, largura é informação;
- **painéis de vidro** (fundo translúcido escuro, borda de 1px azul, desfoque
  atrás) para os blocos de leitura — "a cidade agora", foco do dia, últimos
  registros — e para o cartão de detalhe do ponto selecionado;
- **pontos pulsando**, com o anel expandindo: laranja para o que está fora do
  esperado, azul para o que é rotina. A mesma gramática do splash de boas-vindas;
- **manchas de calor** radiais para concentração, sem número em cima delas: o
  número fica no painel, a mancha diz onde olhar.

⚠️ Isto **não** autoriza construir a tela: a decisão registrada é de desenho, e o
fluxo (o que o mapa mostra, quem pode ver, de onde vem o ponto) continua sendo da
fase que a construir.

---

## Fora de escopo (por ora)

- **Tema claro no painel do menu.** Ele é moldura escura por decisão (RN-01).
- **Menu configurável por pessoa** (fixar itens, reordenar). A lista é curta e
  igual para todos de propósito; quem manda no que aparece é a permissão.
- **Contador em todo item.** Ver a advertência da RN-03.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 27/08/2026 | José Nascimento | Casca da Retaguarda | Criação do doc. A casca passa a ser a "editorial curva" (RN-01), a barra superior sai e o topo da tela vira o cabeçalho da página (RN-02), o menu ganha número vivo declarado por item (RN-03) e duas formas — painel e doca, com barra inferior no telefone (RN-04); as linhas de grade viram cartões com marca de pendência e chip com ponto (RN-05); números de cabeçalho saem da própria lista da tela (RN-06). Registrada a diretriz para as telas de mapa (RN-07). | A casca anterior era genérica: barra superior repetindo o título em corpo 13, menu branco encostado em miolo branco, grade de linhas sem hierarquia e nenhum número à vista — quem abria o sistema não sabia por onde começar o dia. O menu, abaixo de 900px, ficava escondido atrás de um hambúrguer. |
