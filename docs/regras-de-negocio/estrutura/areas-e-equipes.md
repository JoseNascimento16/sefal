# Áreas e Equipes

**Onde fica:** Menu → Estrutura → Áreas e Equipes (`/retaguarda/areas-e-equipes`).
**Quem usa:** administrador e Chefe de Setor. **O fiscal não entra** (ver RN-07).

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

### RN-01b — Encarregado e CHEFE DE SETOR são duas pessoas diferentes

O **encarregado** chefia a equipe **em rua** (vem do documento do cliente). O **Chefe de Setor da área**
responde por ela **dentro do sistema**: é ele que recebe a denúncia encaminhada à área e decide se
ela vai a uma equipe ou entra numa operação (ver [Denúncias](../fiscalizacao/denuncias.md), RN-06).

A tela mostra os dois com o papel escrito no rótulo — "Encarregado (campo)" e "Chefe de Setor da área
(sistema)" —, porque é fácil confundi-los, e confundi-los faz mandar denúncia para quem está na
calçada em vez de para quem decide.

| Área | Chefe de Setor da área | Conta de demonstração |
|---|---|---|
| Área 1 · Centro | Marta Nogueira Prado | `gestor2` |
| Área 2 · Itapagipe | Djalma Sousa Vieira | — |
| Área 3 · Brotas | Verônica Lins Barreto | `gestor3` |
| Área 4 · Liberdade | Ivanildo Costa Pinheiro | — |
| Área 5 · Boca do Rio | Lourdes Figueiredo Sales | `gestor1` |
| Área 6 · Pau da Lima | Otacílio Ramos Cunha | — |
| Itinerante · Avenida Sete | Bruna Cavalcanti Reis | — |
| Noturna · Toda Salvador | Aristides Moreno Fagundes | — |

Área **sem** Chefe de Setor registrado aparece com o aviso de que a denúncia encaminhada a ela fica sem quem
a receba — é aviso, não bloqueio: o cadastro do Chefe de Setor é de fora desta tela.

⚠️ **PROTÓTIPO — a modelagem definitiva deste vínculo é da fase de produção.** Hoje ele mora em
`config/prototipo_estrutura.php`, junto da área, e liga pela **matrícula**. Em produção o vínculo é
entre **usuário e área**, em tabela: uma pessoa pode responder por mais de uma área, Chefe de Setor entra e
sai, e isso é fato datado — não linha de arquivo de configuração. O código já trata **lista** de
áreas por Chefe de Setor, justamente para a modelagem real não obrigar a reescrever quem lê.

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
acontece nesse caso: a Caixa de Entrada **sugere** uma equipe e o coordenador **confirma**.

Marcar como pendência mandaria o Chefe de Setor "corrigir" um dado que está certo.

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
`administrador` e `chefe-de-setor`, semeada em
[`config/retaguarda_menu.php`](../../../config/retaguarda_menu.php); depois disso quem concede e
quem tira é o Modo Gerente.

### RN-08 — Uma fonte, três consumidores

A estrutura é lida pela **própria tela**, pela **Caixa de Entrada** (que dela deriva a equipe
sugerida) e pelas telas de **Denúncias** (que dela derivam a área sugerida pelo bairro, o Chefe de Setor de
cada área e o recorte do que cada Chefe de Setor vê) — as três pelo mesmo `EstruturaFicticia`. Duplicar a
lista faria a sugestão discordar do cadastro no primeiro ajuste: a tela mostraria um bloco e a
triagem sugeriria outra equipe.

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
- **Cadastrar o Chefe de Setor pela tela.** O nome do Chefe de Setor é mostrado (RN-01b) e não é editável aqui: o
  vínculo definitivo é usuário↔área, e cadastrá-lo por este formulário criaria um cadastro de
  pessoa que não é o cadastro de usuário do sistema — dois donos para a mesma identidade.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 02/09/2026 | José Nascimento | Áreas e Equipes | A área ganha **gestor** (RN-01b): quem responde por ela dentro do sistema, distinto do encarregado de campo, com o papel escrito no rótulo dos dois, aviso na área sem gestor e o nome entrando na busca. | O dono decidiu que o **gestor é de uma área** e que só lhe interessa o que for direcionado a ela — então a área precisa saber quem a responde, e é desta estrutura que as telas de Denúncias derivam o recorte de cada gestor e o nome que o triador vê antes de encaminhar. |
| 02/09/2026 | José Nascimento | Áreas e Equipes | Nasce a tela, como **protótipo**, com a estrutura real do documento de 17/04/2026: 8 áreas em cartões, a ficha de cada equipe, os fiscais, o bloco de bairros em fichas com inclusão e remoção, os três recortes (bairros / corredores / cidade por turno) e o aviso de bairro em mais de uma área. | Decisão da reunião com o cliente de 02/09/2026: Área › Equipe › bloco de bairros é estrutura permanente (a operação é evento; a equipe é organização), e é dela que sai a equipe sugerida para cada demanda da Caixa de Entrada. |
| 04/09/2026 | José Nascimento | Áreas e Equipes | Os papéis passam a se chamar **Coordenador** (era `administrativo`) e **Chefe de Setor** (era `gestor`) — slug inclusive, com migration renomeando catálogo e matriz. Ver [Papéis e setores](../papeis-e-setores.md). | O vínculo pessoa↔área é o do Chefe de Setor: a chave do dado de protótipo virou `chefe_de_setor` e o rótulo da tela acompanhou. |
