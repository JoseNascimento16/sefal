# Áreas e Equipes

**Onde fica:** Menu → Estrutura → Áreas e Equipes (`/retaguarda/areas-e-equipes`).
**Quem usa:** administrador e gestor. **O fiscal não entra** (ver RN-07).

> ## ⚠️ ESTA TELA É UM PROTÓTIPO
>
> Ela existe para o dono **olhar a forma e aprovar a hierarquia** antes de a estrutura virar tabela,
> migration e cadastro de produção. **Nada é gravado em banco:** a estrutura de partida é a
> transcrição do documento do cliente em
> [`config/prototipo_estrutura.php`](../../../config/prototipo_estrutura.php) e o que a pessoa mexe
> vive na **sessão** dela
> ([`App\Support\Prototipo\EstruturaFicticia`](../../../app/Support/Prototipo/EstruturaFicticia.php)).
>
> **O que é REAL:** as 8 áreas, os nomes, o código de cada equipe, o encarregado e o bloco de
> bairros — 151 bairros distintos mais os 3 corredores da Itinerante —, transcritos do documento
> **"ÁREAS DAS EQUIPES ATUALIZADA - 17/04/2026"**.
> **O que é INVENTADO:** a lista de fiscais de cada equipe (o documento nomeia só o encarregado) e o
> turno declarado de cada área.
>
> **Origem:** reunião com o cliente de 02/09/2026. Sem HU escrita — a linha em
> `config/acompanhamento_requisitos.php` nasce `hu_status => 'nao'` declarando isso.

**"A operação é evento; a equipe é organização."** Esta é a divisão **permanente** com que a SEMOP
cobre a cidade, e é dela que sai a derivação bairro → equipe que a
[Caixa de Entrada](../fiscalizacao/caixa-de-entrada.md) usa para sugerir o destino de cada demanda.

---

## Regras vigentes

### RN-01 — A hierarquia é Área › Equipe › encarregado › fiscais › bloco de bairros

Uma área tem **uma** equipe, a equipe tem **um** encarregado e **N** fiscais, e a área cobre um
bloco de bairros.

| Área | Região | Equipe | Encarregado |
|---|---|---|---|
| Área 1 | Centro | C2 | José Roberto |
| Área 2 | Itapagipe | A1 | Marco Gonçalves |
| Área 3 | Brotas | A2 | Nonato Silva |
| Área 4 | Liberdade | B2 | Andréa Rocha |
| Área 5 | Boca do Rio | C1 | César Amaral |
| Área 6 | Pau da Lima | B1 | José Antonio |
| Itinerante | Avenida Sete | I1 | Roberto Moraes |
| Noturna | Toda Salvador | N1 | Alcione Brandão |

### RN-02 — São TRÊS recortes, não um

| Recorte | Quem é | O que a tela mostra |
|---|---|---|
| `bairros` | as seis áreas numeradas | o bloco de bairros, em fichas |
| `corredores` | Itinerante | os eixos de grande circulação (Avenida Sete de Setembro, Comércio, Avenida Joana Angélica) — não um bloco fechado de bairros |
| `cidade` | Noturna | **todos os bairros**; o recorte dela é o **TURNO**, não a geografia |

Tratar as oito como iguais faria a Noturna aparecer com **"0 bairros"** — a leitura exatamente
invertida: ela cobre todos. Por isso a área declara o recorte, e a tela desenha cada uma pelo que
ela é: a Noturna não tem bloco a manter, e a tela diz isso em vez de mostrar uma lista vazia.

### RN-03 — Bairro em mais de uma área é AVISO, nunca erro

**Mussurunga**, **Patamares** e **Jardim das Margaridas** pertencem à Área 5 e à Área 6.
**Comércio** é bairro da Área 1 e corredor da Itinerante.

O vínculo bairro↔equipe **não é 1:1**. A tela mostra isso como aviso informativo — a contagem no
cabeçalho, a marca no cartão da área e a ficha do bairro em laranja dentro do bloco —, e diz o que
acontece nesse caso: a Caixa de Entrada **sugere** uma equipe e o administrativo **confirma**.

Marcar como pendência mandaria o gestor "corrigir" um dado que está certo.

O mesmo bairro repetido **dentro** do bloco de uma área só **não** conta como compartilhado: isso é
digitação, não organização — a contagem é feita sobre os bairros únicos de cada área.

### RN-04 — A ordem dos bairros é acento-insensível

A lista é longa (153 entradas) e existe para ser varrida com o olho. Ordenada por byte, "Águas
Claras" cai depois de "Vitória" e "São Caetano" depois de "Sussuarana" — a lista fica impossível de
usar justamente onde ela é maior. A ordem sai da **chave sem acento** (`App\Support\Texto::chave`),
a mesma régua da busca.

### RN-05 — Acrescentar bairro OFERECE o que já existe

O campo de inclusão traz a lista de todos os bairros conhecidos. É o que evita o mesmo bairro
entrando com duas grafias e virando dois bairros — dos quais só um receberia demanda.

Bairro já presente no bloco não entra de novo (a comparação é pela chave sem acento).

### RN-06 — Excluir área diz o que a exclusão custa

A confirmação nomeia a equipe, o encarregado e a quantidade de bairros do bloco, e avisa o efeito
real: **as demandas desses bairros deixam de ter equipe sugerida na Caixa de Entrada**. Confirmação
que só pergunta "tem certeza?" não informa nada.

### RN-07 — O fiscal não entra nesta tela

Desenhar a divisão da cidade e nomear encarregado é ato de **gestão**. A concessão inicial é
`administrador` e `gestor`, semeada em
[`config/retaguarda_menu.php`](../../../config/retaguarda_menu.php); depois disso quem concede e
quem tira é o Modo Gerente.

### RN-08 — Uma fonte, dois consumidores

A estrutura é lida pela **própria tela** e pela **Caixa de Entrada** (que dela deriva a equipe
sugerida) — as duas pelo mesmo `EstruturaFicticia`. Duplicar a lista faria a sugestão discordar do
cadastro no primeiro ajuste: a tela mostraria um bloco e a triagem sugeriria outra equipe.

### RN-09 — A listagem exporta o recorte visível

`<BotaoExportar>` pelo ponto único `POST /retaguarda/exportar-listagem`, em PDF, XLSX e DOCX, com o
**bloco de bairros inteiro** em coluna própria (é o que faz o documento valer como referência de
campo). Folha deitada por padrão: nove colunas em pé ficariam ilegíveis.

### RN-10 — "Reiniciar demonstração" existe porque é protótipo

O botão devolve a estrutura ao documento de 17/04/2026, e só aparece depois de a sessão mexer em
algo. **No sistema real esta rota não existe:** cadastro não se reinicia.

---

## Fora de escopo (por ora)

- **Alocar fiscal a uma equipe pela tela.** A lista de fiscais é de exemplo; ligar usuário real a
  equipe depende de o cliente informar o quadro (o documento nomeia só o encarregado).
- **Polígono da área no mapa.** Hoje a área é definida por uma lista de bairros, não por geometria.
  Quando houver polígono, a mesma regra de ponto-em-polígono dos dois lados (Turf.js e PHP) passa a
  valer aqui.
- **Regra de desempate do bairro compartilhado** (PEND-022). Hoje a sugestão é a **primeira** área
  que cobre o bairro e as outras aparecem como alternativa; se o cliente definir um critério
  (proximidade, carga da equipe), ele entra aqui.
- **Histórico de alteração da estrutura.** Quando a estrutura virar tabela, mudar a área de um
  bairro passa a ser fato datado — as demandas antigas continuam apontando para a equipe que as
  atendeu.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 02/09/2026 | José Nascimento | Áreas e Equipes | Nasce a tela, como **protótipo**, com a estrutura real do documento de 17/04/2026: 8 áreas em cartões, a ficha de cada equipe, os fiscais, o bloco de bairros em fichas com inclusão e remoção, os três recortes (bairros / corredores / cidade por turno) e o aviso de bairro em mais de uma área. | Decisão da reunião com o cliente de 02/09/2026: Área › Equipe › bloco de bairros é estrutura permanente (a operação é evento; a equipe é organização), e é dela que sai a equipe sugerida para cada demanda da Caixa de Entrada. |
