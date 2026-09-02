# Mapa de Calor — o registro de campo virando decisão de operação

**Onde fica:** Retaguarda → menu **Fiscalização → Mapa de Calor**
(`/retaguarda/mapa-de-calor`).
**Quem usa:** administrador e gestor. **O fiscal não entra** — concentração
histórica serve para **planejar**, e planejar é ato de gestão.

> ⚠️ **É PROTÓTIPO.** Não há tabela, model nem migration. A incidência é
> **inventada**; as **coordenadas** e a **estrutura de equipes**, não. O que se
> aprova aqui é a FORMA.

---

## Regras vigentes

### RN-01 — A LEITURA vem antes do mapa

A primeira coisa da tela é **uma frase**: *"Centro Histórico concentra 42% das
ocorrências dos últimos 30 dias — 3,1× a média da cidade, e subiu 38% contra os
30 dias anteriores."*

**Por quê:** quem abre esta tela entre duas reuniões não interpreta gradiente. A
mancha serve para **conferir** e para **achar o recorte**; a frase é o que sai da
tela na cabeça de quem olhou. Foi a peça mais elogiada da versão do aplicativo, e
em escala de cidade ela vale mais, não menos.

A comparação da frase é com a **média da cidade**, e não com o segundo colocado:
"3× a média" diz que aquilo é fora do normal; "o dobro do segundo" só diz que ele
é maior que o vizinho — o que todo primeiro colocado é.

### RN-02 — A variação é contra o PERÍODO ANTERIOR de igual tamanho

Cada linha do ranking traz a variação percentual contra os mesmos N dias
imediatamente anteriores (7 × 7, 30 × 30, 90 × 90).

**Por quê:** a pergunta da operação é *"isto está piorando desde a última vez que
eu olhei?"*. Comparar com a média do ano responderia outra coisa.

**Consequência de projeto:** a tela recebe **180 dias** de dados mesmo quando a
janela é de 90 — sem os 180, a coluna de variação seria invenção. E a troca de
janela é feita **no navegador**, sobre o mesmo conjunto: não é uma segunda
consulta, é o mesmo dado com outro corte, e a comparação fica garantidamente
coerente com a mancha desenhada.

Quando não havia nada no período anterior, a linha diz **"novo no período"** — e
não "+∞%". Variação de zero para algo não é percentual: é estreia.

### RN-03 — Subir é LARANJA, cair é VERDE

Aqui "mais" é mais **ocorrência de irregularidade**: a variação positiva é a **má
notícia**. Pintar o crescimento de verde inverteria a leitura de relance, que é
justamente o que a cor existe para dar.

### RN-04 — A mancha não leva número

A concentração é dita pela **cor** da mancha, e o número mora no **ranking**, ao
lado. Rótulo numérico em cima de mancha radial disputa espaço com a rua e é
ilegível em qualquer aproximação — e a mancha não precisa dele: ela diz **onde
olhar**.

### RN-05 — A RECOMENDAÇÃO diz o motivo, e nem sempre aponta o primeiro

A tela sugere onde fiscalizar. Se algum bairro entre os seis primeiros **subiu
30% ou mais** e não é o líder, é ele o sugerido: o líder já é conhecido e
provavelmente já tem operação de rotina; o que mudou é onde a decisão é nova.
Senão, o sugerido é o líder.

Nos dois casos o cartão **escreve qual dos dois motivos está mandando** —
recomendação sem motivo escrito é adivinhação com aparência de dado.

O botão **leva ao Cadastro de Operação**. Quem cria a operação é aquela tela: esta
não grava nada.

### RN-06 — Filtrar pela equipe NOTURNA é filtrar por turno

Igual ao Mapa ao Vivo (RN-04 de lá): o filtro de equipe seleciona o bloco de
bairros da área, exceto na **Noturna**, cujo recorte é o **turno** — nela ele
seleciona o que foi registrado à noite, em qualquer bairro. Comparar o código da
equipe devolveria uma cidade vazia, e ela cobre Salvador inteira.

### RN-07 — O ranking exporta, e sai o RECORTE VISÍVEL

O ranking é listagem, então tem exportação (PDF/XLSX/DOCX) pelo ponto único do
sistema. Sai o **ranking inteiro do recorte** — não as oito linhas visíveis do
painel — e o documento imprime o recorte em palavras: período, equipe, instante da
apuração e o aviso de que é protótipo.

---

## Fonte dos dados (enquanto é protótipo)

| O que | Onde |
|---|---|
| Pontos de incidência dos últimos 180 dias | `App\Support\Prototipo\MapasFicticios::calor()` |
| Área, equipe e encarregado de cada bairro | `App\Support\Prototipo\EstruturaFicticia` — **a mesma fonte** da tela de Áreas e Equipes |
| Coordenadas dos bairros e o peso da incidência | `MapasFicticios::BAIRROS` — coordenadas **reais**, peso de protótipo |
| Ranking, variação, frase de leitura e recomendação | `resources/js/dados-prototipo/mapas.ts` |
| Camada de calor e gradiente | `resources/js/lib/mapa.ts` — o **mesmo** plugin e o **mesmo** gradiente do aplicativo do fiscal |

O ponto viaja como **tupla** (`[bairro, lat, lng, dias, noturno]`), e não como
objeto: são ~700 pontos, e o nome do bairro, a área e o encarregado viajariam
repetidos setecentas vezes. A lista de bairros vem uma vez só.

O sorteio tem **semente fixa** — ver a mesma regra no doc do Mapa ao Vivo.

---

## Fora de escopo (por ora)

- **Comparar dois períodos lado a lado** (a mancha de agora sobre a de antes).
- **Recorte por atividade** ("onde a venda de bebida se concentra"). Precisa de
  decisão sobre quais atividades entram no relevo.
- **Exportar a MANCHA** como imagem para relatório. Hoje exporta-se o ranking.
- **Polígono da área** desenhado sobre o mapa — não temos o desenho geográfico
  dos blocos de bairros, só a lista de nomes.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 02/09/2026 | José Nascimento | Mapa de Calor | Nasce a tela, como **protótipo**, e sai do catálogo de telas em preparação. Padrão **imersivo** (RN-07 do desenho da Retaguarda), com leitura em uma frase, janela de 7/30/90 dias, recorte por equipe, ranking com variação contra o período anterior, recomendação de operação com motivo escrito e exportação do ranking. | A tela existia como andaime ("chega na Fase 3"). A versão do aplicativo do fiscal provou que a leitura em uma frase é o que faz a concentração virar decisão; em escala de cidade, com a estrutura Área › Equipe vinda da reunião de 02/09, ela passa a dizer também **de quem é a área** — que é a informação que faltava para a recomendação ser acionável. Entregue como protótipo para o dono aprovar a forma antes de existir registro real. |
