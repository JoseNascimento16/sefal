# Aplicativo do Fiscal — a fila dirigida e a vistoria em campo

**Onde fica:** `/app` (casca própria, fora da Retaguarda; ver
[`routes/web.php`](../../../routes/web.php) e
[`resources/views/pwa.blade.php`](../../../resources/views/pwa.blade.php)).
**Quem usa:** o fiscal de campo, no celular. **A Retaguarda não abre estas telas**, e o fiscal não
abre as da Retaguarda.

> ## ⚠️ ESTE MÓDULO É UM PROTÓTIPO
>
> Ele existe para o dono percorrer o trabalho do fiscal e aprovar a forma antes de virar API,
> banco offline e sincronização. **Não há servidor no meio:** os dados de partida são escritos em
> TypeScript ([`resources/js/pwa/`](../../../resources/js/pwa/)) e o que o fiscal registra vive na
> memória da aba. A faixa "Protótipo · dados fictícios" fica visível em todas as telas — protótipo
> que se disfarça de sistema pronto vira decisão tomada por engano.
>
> **Origem:** cenário da reunião com o cliente (02/09/2026) e decisões do dono. Não há HU escrita: a
> linha em [`config/acompanhamento_requisitos.php`](../../../config/acompanhamento_requisitos.php)
> nasce `hu_status => 'nao'` declarando essa origem.

O trabalho do fiscal tem **duas entradas**, e o aplicativo mostra as duas: o que ele descobre andando
a rua (**avulso**, pelo mapa) e o que chega **dirigido** — a denúncia que a ouvidoria entregou ao
SEFAL, que o administrativo triou e que o gestor da área direcionou à equipe dele.

---

## Regras vigentes

### RN-01 — A fila é da EQUIPE, e a equipe vem de quem entrou

A denúncia não chega para o fiscal: chega para a **equipe** da área onde fica o endereço, e qualquer
um dela pode atender. Por isso a matrícula digitada na porta decide a identidade e, com ela, a
equipe, a fila e o contorno da área no mapa
([`sessao.ts`](../../../resources/js/pwa/sessao.ts) + [`dados-equipes.ts`](../../../resources/js/pwa/dados-equipes.ts)).
Trocar de fiscal troca a fila; matrícula desconhecida cai no fiscal genérico da **Equipe C1 · Área 5**,
que é o aparelho da demonstração.

O cabeçalho das telas diz **de quem é a fila** ("Equipe C1 · Área 5 — Boca do Rio"). Sem esse rótulo,
o fiscal não entende por que aparece na lista um endereço que não é o dele.

### RN-02 — O fiscal NUNCA vê denúncia em triagem

O que chega ao aplicativo é o que já está **na mão da equipe**: `Direcionada à equipe`, `Em operação`,
`Em campo`, `Aguardando regularização`, `Retorno vencido` e `Concluída`. Denúncia `Recebida`,
`Encaminhada à área`, `Devolvida` ou `Arquivada` **não aparece** — dar ao fiscal o que ainda está em
triagem seria deixá-lo escolher o próprio trabalho, e é exatamente por isso que a Retaguarda não lhe
concede as telas de Denúncias.

A régua é uma lista só (`SITUACOES_NA_MAO_DA_EQUIPE`, em
[`dados-demandas.ts`](../../../resources/js/pwa/dados-demandas.ts)), e o dado do protótipo **guarda**
as denúncias de triagem de propósito: é o que permite a fila provar o recorte em vez de só afirmá-lo.

### RN-03 — A fila é dividida pelo ATO DEVIDO, não numa lista corrida

Três blocos, nesta ordem: **A vistoriar** (o que pede ida ao local, ou o fim de uma vistoria já
aberta), **Aguardando regularização** (notificação lavrada, prazo correndo) e **Já encerradas**.
Dentro de cada bloco, a ordem é por **prazo** — vencido primeiro.

Numa lista corrida, a vistoria de hoje ficaria ao lado de uma denúncia concluída na semana passada, e
o número do topo contaria como trabalho pendente o que já foi feito. Por isso o contador de abertura
("N denúncias a vistoriar") conta **só** o primeiro bloco, e o prazo de regularização aparece em selo
próprio.

### RN-04 — A situação diz DE QUEM É A BOLA

Cada situação vem com a frase que explica o que ela cobra e de quem (`SITUACOES_EXPLICADAS`).
`Aguardando regularização` e `Retorno vencido` parecem a mesma coisa e são opostas: na primeira o
prazo corre e a bola está com o **notificado**; na segunda o prazo venceu com a situação mantida e a
denúncia voltou ao **gestor da área** para a próxima medida — a equipe não tem ato pendente.

### RN-05 — O desfecho sai de uma lista FECHADA, a mesma da Retaguarda

Ao encerrar a vistoria o fiscal escolhe **um** dos seis desfechos do catálogo:
`Regularizado no local`, `Nada encontrado no local`, `Notificação Preliminar emitida`,
`Regularizado após notificação`, `Retorno com a situação mantida`, `Auto de Apreensão lavrado`.

Não é texto livre porque é o que o relatório soma — "quantas denúncias se resolveram sem documento" é
a pergunta que mede se a fiscalização está sendo educativa — e porque é o desfecho que **fecha o passo
do trâmite** na Retaguarda. Listas diferentes nos dois lados fariam o campo dizer uma coisa e a
Retaguarda outra.

**Cada ato oferece o seu recorte da mesma lista:** a primeira vistoria oferece os quatro que ela pode
produzir; o **retorno** oferece os dois que só ele produz. A primeira vistoria não pode terminar em
"regularizado após notificação" (não havia notificação), e o retorno não volta a "nada encontrado" —
ele existe para dizer se o prazo foi cumprido.

**A leitura "regular / irregular"** (a cor do pino no mapa e o selo da lista de registros) é
**derivada** do desfecho (`LOCAL_APOS_O_DESFECHO`), nunca escolhida ao lado dele: perguntar as duas
coisas deixaria o fiscal marcar como regular um ponto que levou Auto de Apreensão.

### RN-06 — O retorno existe no aplicativo, e é onde o ciclo fecha

Denúncia em `Aguardando regularização` mostra o **documento** lavrado (número, prazo do impresso e
vencimento em `dd/mm/aaaa`) e oferece **Registrar retorno**. A tela é a mesma do registro — foto,
coordenada, relato —, com o título, o crachá e os desfechos trocados: é disso que o retorno precisa.

- **Regularizado após notificação** → o prazo foi cumprido e a denúncia se encerra.
- **Retorno com a situação mantida** → prazo vencido e ponto igual: a denúncia sobe ao gestor da área
  (é o `Retorno vencido` do outro lado).

Sem esse caminho, o fiscal lavrava a notificação e não tinha por onde fechar o ciclo — o prazo corria
para ninguém.

### RN-07 — Só o desfecho é obrigatório

A fiscalização de ambulante é, antes de tudo, **educativa**: o fiscal chega, pede para desarmar, o
ambulante sai. Não há identificação, não há documento, muitas vezes não há nem nome. Então o registro
não exige nada além da **decisão**: coordenada, hora e região já vêm capturadas, e foto, relato,
ocorrência e vínculo com um ambulante conhecido são opcionais.

A coordenada é **do aparelho** mesmo na demanda dirigida: o endereço do processo erra, o GPS não. A
precisão aparece em chip (±N m) porque ponto ruim é pior que ponto ausente disfarçado de bom.

### RN-08 — O documento de campo é lavrado aqui, com número do bloco reservado

Os dois documentos são a **Notificação Preliminar** (dá prazo para sanar) e o **Auto de Apreensão**
(recolhe material, com guarda no SEGUB). Quem lavra é o fiscal, em rua; a Retaguarda apenas **lê** o
que foi lavrado.

O número vem de uma **faixa reservada no aparelho**, consumida mesmo sem sinal — é o que faz o
documento nascer numerado no meio da rua. A faixa começa **depois** dos números que o módulo de
Denúncias já mostra nos casos semeados (Notificações 194901–194905, Auto de Apreensão 160051): dois
papéis diferentes com o mesmo número é exatamente o que a reserva existe para impedir.

### RN-09 — Datas em `dd/mm/aaaa`, e no dado tudo é RELATIVO

Nenhuma data é escrita à mão no dado do protótipo: o recebimento é `agora − N horas`, o prazo do canal
é `agora + N dias` e cada passo do trâmite é `recebimento + N horas` — a **mesma aritmética** do outro
lado. É isso que faz as duas telas mostrarem a mesma data em qualquer dia que a demonstração
aconteça. Data fixa envelhece: uma semana depois a fila apareceria inteira vencida, e o dono leria
isso como comportamento do sistema.

### RN-10 — Fonte única: o aplicativo ESPELHA o módulo de Denúncias

As denúncias do aplicativo são as **mesmas** de `config/prototipo_denuncias.php` (branch
`feature/prototipo-administrativo`): mesmo protocolo `DEN-NNNN`, mesmo número do canal de origem,
mesmo requerente, assunto, endereço, bairro, prazo, situação, área, equipe, operação — e, quando a
denúncia já andou, o mesmo registro de campo e o mesmo documento. Abrir DEN-00NN na Retaguarda e achar
a mesma denúncia na fila do fiscal é o que prova o fluxo de ponta a ponta.

⚠️ **É uma segunda cópia, e duas cópias sempre divergem.** Ela existe porque o aplicativo roda sem
servidor. Enquanto for assim, **mexer num lado exige mexer no outro** — e o de-para foi deixado
mecânico, campo a campo, na cabeça de
[`dados-demandas.ts`](../../../resources/js/pwa/dados-demandas.ts). O mesmo vale para a estrutura de
áreas e equipes ([`dados-equipes.ts`](../../../resources/js/pwa/dados-equipes.ts) ↔
`config/prototipo_estrutura.php`) e para a redação dos impressos
([`dados-documentos.ts`](../../../resources/js/pwa/dados-documentos.ts) ↔
`config/prototipo_documentos_campo.php`) — nesta última, **a cópia que fica é a do servidor**: a
redação de um formulário legal não pode divergir entre a Retaguarda e o aplicativo.

O que o gate consegue guardar disso está em
[`tests/Feature/PwaFilaDeDenunciasTest.php`](../../../tests/Feature/PwaFilaDeDenunciasTest.php):
catálogo de situações e de desfechos idêntico ao da Retaguarda, repartição dos desfechos entre
vistoria e retorno, nenhuma denúncia na amostra que o outro lado não semeou, as seis formas de trâmite
que o roteiro percorre, a faixa de números sem colisão e a amostra ainda educativa.

### RN-11 — A amostra é majoritariamente EDUCATIVA

A maioria dos casos termina **sem documento**. Os dois documentos existem para quando a orientação
não resolve, e apreensão é **guarda**, não destruição. Isso é regra da amostra, não observação: uma
demonstração em que todo caso de campo termina em papel desenharia um sistema punitivo que não é o do
cliente — e há teste que reprova a amostra que perder essa proporção.

---

## Como demonstrar (protótipo)

Entrada em `/app`. Não há senha a conferir: a **matrícula** decide a identidade e a equipe.

| Matrícula | Quem entra | Fila |
|---|---|---|
| `fiscal` (ou `F-2500`) | César Amaral, encarregado | **Equipe C1 · Área 5 — Boca do Rio** |
| `F-2504` … `F-2571` | os fiscais da C1 | a mesma da C1 |
| `F-2000` (ou `F-2041`) | José Roberto / Adriana, Equipe C2 | **Área 1 — Centro** |
| `F-2900` | Alcione Brandão, Equipe N1 | **Noturna** |
| qualquer outra | fiscal genérico | Equipe C1 · Área 5 |

**As denúncias que aparecem nos dois lados**, por equipe:

| Equipe | Denúncia | Situação | O que ela demonstra no aplicativo |
|---|---|---|---|
| **C1 · Área 5** | **DEN-0011** | Em operação | a vistoriar, chegando por **operação** (Operação Verão — Orla) |
| **C1 · Área 5** | **DEN-0029** | Aguardando regularização | **prazo correndo** (NP 194903, 05 dias) → oferece **Registrar retorno** |
| **C1 · Área 5** | **DEN-0030** | Retorno vencido | retorno frustrado (NP 194902, notificado **recusou assinar**) → subiu ao gestor |
| **C2 · Área 1** | **DEN-0010** | Direcionada à equipe | vistoria dirigida avulsa, prazo vencendo |
| **C2 · Área 1** | **DEN-0012** | Em campo | **vistoria aberta**: relato, fotos e coordenada, sem desfecho → "Continuar" |
| **C2 · Área 1** | **DEN-0013** | Concluída | **Auto de Apreensão** (160051), bens no SEGUB |
| **C2 · Área 1** | **DEN-0024** | Em operação | anexada à Rotina Centro |
| **C2 · Área 1** | **DEN-0033** | Concluída | o caminho comum: **regularizado no local**, sem documento |
| **N1 · Noturna** | **DEN-0025**, **DEN-0038** | Direcionada / Concluída | a **troca de equipe** — trabalho de outra área que caiu na Noturna |

Também estão na amostra, e são de outras equipes: DEN-0027 e DEN-0037 (B2), DEN-0028 e DEN-0036 (I1),
DEN-0031 e DEN-0032 (A2), DEN-0034 (A1), DEN-0035 (B1, com a segunda notificação em prazo).

**Roteiro curto:** entre como `fiscal`; a tela inicial abre com a chamada da fila. Em **Minhas
demandas**, abra **DEN-0011** e registre um desfecho (repare que a lista é fechada, e que
"Notificação Preliminar emitida" avisa que o documento vem em seguida). Volte e abra **DEN-0029**: o
documento com prazo está à vista, e o botão é **Registrar retorno** — escolha "Regularizado após
notificação" ou "Retorno com a situação mantida". Abra **DEN-0030** para ver o caso que já subiu ao
gestor (sem ato para a equipe). Depois entre como `F-2000`: a fila é outra, e o que era da C1 não
aparece — o rodapé da lista diz quantas denúncias estão com equipes diferentes.

---

## Pendências que isto abre

- **Sincronização de verdade.** Não há endpoint, banco offline (IndexedDB), service worker nem fila de
  envio: a tela de sincronização é encenação. Enquanto isso não existir, a fila e a estrutura são
  segunda cópia do que a Retaguarda tem (RN-10).
- **A situação da denúncia não muda no aplicativo.** O que o fiscal registra vira registro do turno
  (com selo na denúncia: "Neste aparelho: <desfecho>"), mas a situação semeada continua a mesma — sem
  servidor, mudá-la só no aparelho criaria uma verdade que o outro lado não conhece.
- **A Caixa de Entrada saiu da fila do aplicativo.** A amostra anterior espelhava
  `config/prototipo_caixa_entrada.php` (o que chega em **papel**: nova licença, ofício, protocolo
  `CXE-NNNN`). A fila agora é a das **denúncias das ouvidorias**. Falta a decisão do dono: a Caixa
  também dirige trabalho ao fiscal? Se sim, ela volta como **segunda fonte**, com o vocabulário dela.
- **Nenhuma denúncia da Área 5 nas formas "Direcionada à equipe", "Em campo" e "Concluída".** A
  Retaguarda não semeou esses casos para a C1, e a amostra do aplicativo não os inventa (protocolo que
  não existe do outro lado quebra a demonstração). Hoje o roteiro cobre essas três formas entrando
  como fiscal da **C2**. Se o dono quiser as seis formas na fila do aparelho da demonstração, o
  caminho é semear os casos **na Retaguarda** e espelhá-los aqui.
- **Redação dos impressos duplicada** (`dados-documentos.ts` ↔ `config/prototipo_documentos_campo.php`).
  Conferidas em 03/09/2026: motivos, penalidades, prazos, fundamentação, SEGUB e destinações estão
  iguais. A que fica, quando os protótipos se encontrarem, é a do servidor.
- **Numeração dos blocos** é fictícia (faixa na memória da aba). No sistema ela sai de estoque
  reservado por aparelho, com reconciliação quando a resposta se perde.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 03/09/2026 | José Nascimento | Aplicativo do Fiscal (fila, detalhe, registro) | **O aplicativo passa a falar a língua do módulo de Denúncias.** (1) A fila deixa de espelhar a Caixa de Entrada (`CXE-NNNN`, situações `Aguardando triagem`/`Encaminhada`) e passa a espelhar `config/prototipo_denuncias.php`: protocolo `DEN-NNNN`, os dois canais das ouvidorias e o catálogo fechado de 10 situações. (2) A régua do que chega ao campo passa a ser explícita — o fiscal não vê denúncia em triagem — e a fila é dividida pelo ato devido (a vistoriar, aguardando regularização, encerradas). (3) O registro passa a terminar num **desfecho** da lista fechada de seis, com a leitura "regular/irregular" derivada dele, e nasce o **retorno** para a denúncia com prazo de notificação correndo. (4) A amostra traz as denúncias que a Retaguarda semeou para as equipes, com o mesmo protocolo, requerente, endereço, prazo, registro de campo e documento — inclusive a NP 194903 da DEN-0029, que também está no turno do aparelho. (5) A faixa de números reservados no aparelho passa a começar depois dos documentos já semeados do outro lado. | Decisão do dono (03/09/2026): alinhar o protótipo do aplicativo ao vocabulário novo para os dois lados contarem a MESMA história na demonstração, **sem** implementar sincronização — "só vale a pena avançar para as outras etapas quando tivermos requisitos e fluxos mais concretos". |
