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
ponto azul final** ("Ambulantes.") e **subtítulo**. É o `.rt-page-head`, que
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

### RN-07 — Telas de MAPA usam o padrão imersivo (construído em 02/09/2026)

As duas telas de mapa (**Mapa ao Vivo** e **Mapa de Calor**) **não** usam a casca
da RN-01 no miolo: elas usam o padrão **imersivo** do mockup aprovado, que é outro
registro para outro trabalho. As regras de cada uma estão em
[`fiscalizacao/mapa-ao-vivo.md`](fiscalizacao/mapa-ao-vivo.md) e
[`fiscalizacao/mapa-de-calor.md`](fiscalizacao/mapa-de-calor.md); o que é **da
casca** está aqui.

- **o mapa é o fundo**, sangrando de borda a borda — não um cartão dentro do
  miolo. Quem abre um mapa está olhando a cidade, não uma tela com um mapa dentro;
- **o menu PERMANECE.** O mockup punha a navegação numa pílula flutuante no topo,
  no lugar do painel lateral. Foi a única peça dele que não veio: o imersivo é
  sobre o **conteúdo**, e trocar a casca em duas telas faria o Chefe de Setor perder o
  menu justamente onde ele mais precisa pular para "Cadastro de Operação" — e
  criaria uma segunda navegação para manter. O que o modo imersivo faz na casca
  são três coisas, e só: o miolo perde o preenchimento, a página perde a rolagem
  (quem rola é o mapa) e o cluster de tema/avisos clareia, porque em texto escuro
  ele desapareceria na cidade à noite;
- **painéis de vidro** (fundo translúcido escuro, borda de 1px azul, desfoque
  atrás) para os blocos de leitura — "a cidade agora", foco do dia, últimos
  registros — e para o cartão de detalhe do ponto selecionado;
- **pontos pulsando**, com o anel expandindo: laranja para o que está fora do
  esperado, azul para o que é rotina. A mesma gramática do splash de boas-vindas.
  **Só dois tipos pulsam** — pulso em tudo não destaca nada;
- **manchas de calor** radiais para concentração, sem número em cima delas: o
  número fica no painel, a mancha diz onde olhar.

A tela declara o modo por **propriedade de layout**, junto da trilha:

```ts
MinhaTelaDeMapa.layout = { imersivo: true, breadcrumbs: [...] };
```

**A paleta do vidro é FIXA nos dois temas** (tokens `--sm-mapa-*`), pelo mesmo
motivo do menu (RN-01): é moldura sobre a cidade à noite. E as imagens do mapa são
**escurecidas por filtro** em vez de vir de outro provedor — a mesma receita que o
aplicativo do fiscal usa no tema escuro, o que evita uma segunda origem de rede.

⚠️ **Por baixo das imagens fica o navy com a malha de ruas**, e isso não é
enfeite: é o que a tela mostra enquanto os blocos de imagem chegam, e o que ela
mostra **se eles não chegarem** (rede da Prefeitura, proxy, servidor fora do ar).
A tela degrada para o desenho aprovado, não para um retângulo vazio. Tela de mapa
nova deve manter isso.

### RN-08 — Esconder do menu é tirar o ATALHO, nunca desligar a tela

Um item pode declarar `oculto` em `config/retaguarda_menu.php`. O efeito é um só: o
item sai do menu. **Continuam vivas** a rota (a tela abre pelo endereço), a tela em
si e a permissão no Modo Gerente — inclusive a concessão que o Chefe de Setor já tenha
feito. Seção que fica sem nenhum item visível desaparece da barra junto.

**Por que não apagar:** esconder apagando transforma "voltar atrás" em "refazer", e
faz a concessão de acesso já dada evaporar sem ninguém perceber. Com o flag, trazer
de volta é remover uma linha.

Em uso hoje: as **seis telas de Parametrização** (decisão do dono, 27/08/2026) —
prontas, mas fora do menu por ora.

### RN-09 — Tela que ainda não existe ABRE e diz o que vai ser

O plano do sistema anda na frente das telas. Enquanto isso aparecia como item de
menu sem destino e cartão "Em construção" sem link, quem clicava não recebia
resposta e a espera não era explicada em lugar nenhum.

Agora cada tela do caminho que ainda não chegou tem **endereço, permissão e uma
tela de verdade**: cabeçalho editorial normal (a pessoa caiu no lugar certo) e um
corpo que diz, em uma linha, o que a tela vai ser, **em que fase chega** e o que
vai permitir fazer. Duas variantes, e a diferença é conteúdo, não enfeite:

- **mapa** — painel navy com a malha de ruas, para as telas de mapa: elas se
  explicam melhor mostrando o que vão mostrar;
- **cartão** — aviso sóbrio para as telas de lista. Desenhar um mapa decorativo
  numa tela de lista prometeria a coisa errada.

Em uso hoje (`TelasEmPreparacaoController`): **Cadastro de Operação** e
**Fiscalizações** (Fase 2). O **Mapa ao Vivo** e o **Mapa de Calor** saíram do
catálogo em 02/09/2026, quando passaram a existir — é a troca que o ⚠️ abaixo
prevê, e ela não tocou o menu.

⚠️ Com a saída dos dois, **nenhuma entrada usa a variante `mapa` hoje**. Ela fica
porque é ela que a próxima tela em preparação com cara de mapa vai querer; se
ficar sem uso por muito tempo, o certo é apagá-la junto com o `.prep-cidade` do
CSS, e não deixá-la envelhecendo aqui.

⚠️ Isto é **andaime**. Quando a tela real nascer, ela toma o `slug` e a rota, e a
entrada sai do catálogo do controller — o nome de rota já segue o padrão das telas
de verdade (`retaguarda.<slug>.index`) justamente para essa troca não tocar no menu.

**A concessão inicial das quatro telas do caminho** segue o critério das telas
prontas (e continua valendo para as duas de mapa, agora que elas existem): o fiscal
consulta o que é do trabalho dele — o que registrou em campo (Fiscalizações) e onde
a cidade está agora (Mapa ao Vivo) — e não entra no que é de gestão (planejar
operação, analisar concentração). Ele não grava nada pela Retaguarda: grava em rua,
pelo aplicativo.

### RN-10 — O tema padrão é o CLARO

Quem nunca escolheu abre o sistema no **claro**. O escuro é escolha, e quem a fizer
(ou pedir "do aparelho") tem a escolha respeitada — a opção não saiu do lugar.

Era `system`, e o efeito era este: quem tem o sistema operacional no escuro — o
padrão de fábrica em boa parte dos aparelhos — abria a Retaguarda em navy sem nunca
ter pedido. Aqui o dia de trabalho é claro.

⚠️ O padrão é decidido em **quatro** lugares, e os quatro precisam concordar: o
`HandleAppearance` (cookie), as duas leituras do `app.blade.php` (a classe do
`<html>` e o script de pré-pintura) e o `PADRAO` do `use-appearance.tsx`. Se um
discordar, a página nasce de um tema e passa para o outro na hidratação — o lampejo
que a pré-pintura existe para evitar. Há teste-lei sobre isso.

A ausência de escolha **não é gravada** como escolha: antes se escrevia `system` no
armazenamento na primeira visita, o que congelava o padrão para sempre naquele
navegador. Sem essa escrita, mudar o padrão alcança quem nunca decidiu.

**A tela de acesso e o splash de boas-vindas não seguem o tema** — são escuros por
desenho, nos dois. O painel da fotografia e o splash são a moldura da identidade,
como o menu (RN-01); o formulário de acesso, esse sim, acompanha o tema.

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
| 02/09/2026 | José Nascimento | Casca da Retaguarda · telas de mapa | A RN-07 sai de "decidido, ainda não construído" para **construído**: as duas telas de mapa nascem no padrão imersivo, declarado por propriedade de layout (`imersivo: true`). Registrada a única divergência do mockup — o **menu permanece** — e o que o modo imersivo faz na casca (miolo sem preenchimento, página sem rolagem, cluster clareado). Registrados também a paleta fixa `--sm-mapa-*`, o escurecimento das imagens por filtro e a malha de ruas por baixo como degradação. A RN-09 perde as duas do catálogo de telas em preparação. | O desenho já estava aprovado em mockup e as duas telas existiam como andaime prometendo a Fase 3. O cenário da reunião de 02/09 (estrutura Área › Equipe › bairros) deu a elas o conteúdo que faltava: é o que transforma um mapa bonito em decisão de operação. |
| 27/08/2026 | José Nascimento | Casca da Retaguarda | As seis telas de Parametrização saem do menu por flag, sem nada ser desligado (RN-08); as quatro telas do caminho da fiscalização entram no menu como tela que abre e explica a espera (RN-09); o tema padrão passa de "do aparelho" para CLARO (RN-10). | O menu mostrava telas de manutenção interna e não mostrava o caminho do trabalho — quem abria o sistema não via que fiscalização, operação e mapa fazem parte dele. E o padrão "do aparelho" fazia quem tem o sistema operacional no escuro abrir a Retaguarda em navy sem nunca ter pedido. |
