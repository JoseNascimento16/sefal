# Mapa ao Vivo — a cidade agora, para o gestor

**Onde fica:** Retaguarda → menu **Fiscalização → Mapa ao Vivo** (`/retaguarda/mapa`).
**Quem usa:** administrador, gestor e o **fiscal**, em leitura — saber onde a cidade
está agora é do trabalho de rua.

> ⚠️ **É PROTÓTIPO.** Não há tabela, model, migration nem tempo real. Pessoas,
> horários e situações são **inventados**; as **coordenadas de Salvador** e a
> **área/equipe de cada bairro**, não. O que se aprova aqui é a FORMA.

---

## Regras vigentes

### RN-01 — A tela é do GESTOR, e a pergunta dela é uma só

O aplicativo do fiscal mostra a calçada em que ele está. Esta mostra a **cidade**,
em escala de operação, e responde: **para onde eu mando gente hoje?**

Isso decide o que entra e o que não entra. Entram: concentração, cobertura por
equipe, quem está na rua, o que passou do prazo. Não entram: o formulário de
registro, a lavratura de documento, o passo a passo de uma abordagem — tudo isso
é do aplicativo, e a Retaguarda não grava fiscalização.

### RN-02 — O desenho é o IMERSIVO (RN-07 do desenho da Retaguarda)

O mapa é o **fundo**, sangrando de borda a borda; a leitura flutua sobre a cidade
em **painéis de vidro** (fundo translúcido escuro, borda de 1px azul, desfoque
atrás). O **menu permanece**: o imersivo é sobre o conteúdo, não sobre a casca.

A paleta do vidro é **fixa nos dois temas** (tokens `--sm-mapa-*`), pelo mesmo
motivo do menu: é moldura sobre a cidade à noite. As imagens do mapa são
**escurecidas por filtro**, não por outro provedor — a mesma receita que o
aplicativo do fiscal usa no tema escuro.

Por baixo das imagens fica o **navy com a malha de ruas** do resto do sistema.
Não é enfeite: é o que a tela mostra enquanto os blocos de imagem chegam, e o que
ela mostra **se eles não chegarem** (rede da Prefeitura, proxy, servidor de
imagens fora do ar). A tela degrada para o desenho aprovado, e não para um
retângulo vazio.

### RN-03 — Só duas coisas PULSAM, e o retorno vencido já vem escrito

Cinco tipos de ponto, cada um com sua cor: **regular**, **irregular**, **retorno
vencido**, **entrou no período** e **fiscal em campo**. Pulsam **dois**: o retorno
vencido (fora do esperado) e o que acabou de entrar (rotina viva). Pulso em tudo
não destaca nada.

O ponto com **retorno vencido** carrega o **"há N dias" colado no pino**, sem
precisar de clique: é a informação que faz o gestor agir, e informação que exige
clique é informação que ninguém vê.

⚠️ **Os fiscais aparecem sempre**, inclusive com um filtro de situação ligado:
"quem está na rua agora" não é uma situação de ponto, e esconder a equipe atrás
desse filtro faria o gestor mandar reforço para onde já tem gente.

### RN-04 — Filtrar pela equipe NOTURNA é filtrar por turno

O filtro de equipe seleciona o **bloco de bairros** daquela área — exceto na
**Noturna**, cujo recorte é o **turno**: nela o filtro seleciona o que foi
registrado **à noite, em qualquer bairro**.

**Por quê:** a Noturna cobre Salvador inteira e não tem bloco de bairros.
Comparar o código da equipe devolveria uma cidade vazia — leitura exatamente
invertida. É a mesma decisão dos três recortes da tela de Áreas e Equipes.

### RN-05 — Os números saem da lista que o mapa desenha, e o recorte vai DITO

"A cidade agora", o foco do dia e os últimos registros são **agregações dos
mesmos pontos desenhados** (RN-06 do desenho da Retaguarda) — não de uma segunda
consulta. Por isso mudam junto com o filtro: filtrar a Equipe C1 e ver o número da
cidade inteira seria responder outra pergunta.

E justamente porque mudam, **o recorte é escrito em palavras** logo abaixo dos
números ("Equipe C1 · Área 5 (Boca do Rio) · 24 pontos conhecidos no recorte").
Sem isso alguém lê "7 retornos vencidos" achando que é a cidade toda.

### RN-06 — O FOCO DO DIA conta só o irregular

A região de maior incidência é apurada sobre os registros **irregulares** do
período, e não sobre todos.

**Por quê:** um bairro com vinte registros todos regulares está **bem
fiscalizado**, não em crise. Mandar operação para lá seria gastar equipe onde o
trabalho já foi feito.

O painel do foco leva ao **Cadastro de Operação** — quem cria a operação é aquela
tela.

### RN-07 — Clicar no ponto abre um cartão de LEITURA, com ações que levam a outro lugar

O cartão traz o resumo (quem, atividade, situação, permissão, local, equipe,
última visita) e duas ações: **Ver prontuário** e **Encaminhar fiscalização**. As
duas **navegam**; nenhuma grava nada aqui.

Quando o bairro do ponto é coberto por **mais de uma equipe**, o cartão diz isso
em palavras e acrescenta que **não é duplicidade a corrigir** — o vínculo
bairro↔equipe não é 1:1, e quem confirma é gente. É a mesma conversa que a Caixa
de Entrada tem com o administrativo.

### RN-08 — A tela declara o INSTANTE que está mostrando

Não há tempo real. O subtítulo diz "A cidade em 02/09/2026 14:32", e é honesto:
uma tela chamada "ao vivo" que mostra um instante parado, sem dizer qual, mente
por omissão.

---

## Fonte dos dados (enquanto é protótipo)

| O que | Onde |
|---|---|
| Pontos, registros do dia, fiscais em campo | `App\Support\Prototipo\MapasFicticios::aoVivo()` |
| Área, equipe e encarregado de cada bairro | `App\Support\Prototipo\EstruturaFicticia` — **a mesma fonte** da tela de Áreas e Equipes e da sugestão da Caixa de Entrada |
| Coordenadas dos bairros | `MapasFicticios::BAIRROS` — aproximadas ao centro do bairro, **reais** |
| Tipos, cores e as agregações | `resources/js/dados-prototipo/mapas.ts` |
| Imagens do mapa | OpenStreetMap, o mesmo provedor do aplicativo do fiscal |

O sorteio dos pontos tem **semente fixa**: o dono recarrega e vê a mesma cidade, e
as duas telas de mapa concordam entre si. Com sorteio livre, o "foco do dia"
mudaria de bairro a cada recarga — e a primeira conclusão de quem vê isso é que o
sistema está errado.

---

## Fora de escopo (por ora)

- **Tempo real** (posição do fiscal atualizando sozinha, registro entrando na
  tela sem recarregar). Precisa de canal ao vivo e de decisão sobre a frequência
  com que o aplicativo reporta posição.
- **Polígono da área desenhado sobre o mapa.** Hoje a área aparece como
  informação do ponto, não como contorno: não temos o desenho geográfico dos
  blocos de bairros, só a lista de nomes.
- **Agrupar pinos** quando a cidade está muito povoada (o clustering).
- **Busca por texto no mapa.** A busca inteligente é a lei das telas de listagem;
  em mapa, o recorte é geográfico e os chips fazem o trabalho.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 02/09/2026 | José Nascimento | Mapa ao Vivo | Nasce a tela, como **protótipo**, e sai do catálogo de telas em preparação. Primeira aplicação do padrão **imersivo** (RN-07 do desenho da Retaguarda): mapa como fundo, painéis de vidro, pinos pulsando com o "há N dias" do retorno vencido, filtros de gestor por equipe/situação/período, foco do dia e cartão de detalhe do ponto. | A tela existia como andaime ("chega na Fase 3"). O desenho imersivo já estava aprovado em mockup e o cenário da reunião de 02/09 deu a ela o conteúdo que faltava — a estrutura Área › Equipe › bairros, que é o que transforma um mapa bonito em decisão de operação. Entregue como protótipo para o dono aprovar a forma antes de existir tabela e tempo real. |
