# Retorno de Campo — a fila do Chefe de Setor

**Onde fica:** Menu → Fiscalização → Retorno de Campo (`/retaguarda/retorno-de-campo`).
**Quem usa:** Chefe de Setor (é a fila dele), Coordenador (acompanha, sem decidir) e administrador.
**O fiscal não entra** (ver RN-08).

> ⚠️ **É PROTÓTIPO.** Não há tabela nem gravação: os registros que vieram de denúncia são
> **derivados** do trâmite dela, as fiscalizações **avulsas** vêm de
> `config/prototipo_registros_de_campo.php`, e as decisões vivem na **sessão** de quem navega. A tela
> diz isso de forma visível — protótipo que se disfarça de sistema pronto vira decisão tomada por
> engano.

Todo registro de fiscalização **concluído** volta para o Chefe de Setor da área. Sem esta tela o
trabalho da equipe termina no aplicativo do fiscal e ninguém do outro lado é obrigado a ler: o
desfecho existiria no sistema e a decisão que ele pede — voltar ao ponto, encerrar — ficaria sem
dono.

---

## Regras vigentes

### RN-01 — Não é a Caixa de Entrada, e a tela diz isso

| Tela | Onde fica na cadeia | Quem age | O que chega |
|---|---|---|---|
| [Caixa de Entrada](caixa-de-entrada.md) | começo | Coordenador | o que chegou em **papel** ao balcão, digitado à mão |
| [Denúncias](denuncias.md) | começo | Coordenador → Chefe de Setor | o que as ouvidorias entregam por **integração** |
| **Retorno de Campo** | **fim** | **Chefe de Setor** | o que a equipe **concluiu em rua** |

São as duas pontas do mesmo trabalho, com papéis, dados e decisões diferentes. O aviso fica **em
cima da tela**, e não numa coluna da grade, porque é a natureza da tela inteira: **aqui ninguém
registra fiscalização** — quem registra é o fiscal, em rua, pelo aplicativo. Não há rota de
inclusão, e um botão de cadastrar aqui criaria um segundo dono para o ato que dá sentido à fila.

### RN-02 — A fila DERIVA do que já existe; só a fiscalização avulsa é dado próprio

"Registro de fiscalização concluído" nasce de dois lugares:

1. **de uma denúncia dirigida** — e essa vistoria já está descrita, passo a passo, no trâmite da
   própria denúncia (desfecho, relato, fotos, coordenada, documento lavrado, considerações e
   recomendações). A fila a **deriva** do **último** passo do trâmite que declarou desfecho;
2. **de operação planejada, ronda da equipe ou pedido de outro órgão** — sem denúncia atrás. Essas
   não existiam em lugar nenhum, e são as únicas que moram em arquivo de dados próprio.

**Por que derivar:** copiar a vistoria para uma segunda lista daria dois donos à **mesma** vistoria,
e um dia o trâmite diria "regularizado no local" enquanto a fila continuaria dizendo "notificado" —
com a demonstração mostrando as duas telas se contradizendo. É a lei da fonte única aplicada.

**"Último" e não "primeiro"** porque a vistoria pode ter mais de um desfecho ao longo da vida do
registro (notificado, depois regularizado): o que voltou para a chefia é onde a coisa parou.

**A metade avulsa importa:** boa parte do trabalho da equipe não vem de reclamação de cidadão, e uma
fila que só mostrasse o que veio de denúncia desenharia um setor que só reage.

### RN-03 — A RECOMENDAÇÃO do fiscal tem coluna própria

O desfecho diz **como** a vistoria terminou; a recomendação diz o que quem esteve no ponto está
**pedindo**. É por ela que a chefia direciona — então ela tem **coluna na grade**, não uma linha no
detalhe: quem precisa varrer trinta retornos com o olho não abre trinta detalhes.

As **considerações** (texto livre do fiscal) ficam no detalhe da linha, junto das recomendações
repetidas em destaque: o atalho se lê de relance, o texto se lê com atenção. O contrato dos dois
campos com o aplicativo do fiscal está em [Denúncias, RN-17b](denuncias.md).

Registro **sem** recomendação diz isso ("o fiscal não recomendou nada") em vez de deixar a célula
vazia — célula vazia parece dado que não carregou.

### RN-04 — Cada linha traz o essencial para decidir

Quando (com **há N dias na fila**, contado no **servidor** — no navegador dependeria do relógio e do
fuso de quem abre a tela), o **ponto** (endereço, bairro e área), a **equipe e o fiscal** que
assinou, o **desfecho**, o **documento** lavrado quando houve (tipo e número, em selo), a
**recomendação** e o **estado** da fila.

O detalhe da linha acrescenta o registro, a **origem da ida ao ponto**, a área com o nome da chefia,
a situação em que a denúncia de origem ficou, a coordenada **sempre com a precisão** (um ponto ruim é
pior que um ponto ausente disfarçado de bom), as considerações e — quando houve — a decisão já
tomada.

**O documento aparece por tipo e número, não por inteiro.** A leitura do papel é do trâmite da
denúncia, e a Retaguarda não emite documento de campo ([Denúncias, RN-18](denuncias.md)). Aqui a fila
precisa dizer **que** houve papel, para a chefia saber que há prazo correndo — e o detalhe aponta
onde está o percurso completo.

### RN-05 — Os três estados da fila

| Estado | O que significa |
|---|---|
| **Aguardando leitura** | voltou do campo e espera o Chefe de Setor — é a fila propriamente dita |
| **Ciente** | a chefia leu e o que era dela está encerrado |
| **Nova vistoria determinada** | a chefia devolveu o ponto à equipe, com justificativa |

O contador de dias parados **zera** ao sair de "Aguardando leitura": contar dias de fila do que já
saiu dela seria cobrar um atraso que não existe.

### RN-06 — As duas decisões da chefia, em lote e uma a uma

**Dar ciência** — o retorno sai da fila. A observação é **opcional** de propósito: o ato de ler já é
a informação, e exigir texto para dar ciência de seis registros de uma vez faria a chefia escrever
seis frases vazias, o que estraga justamente o campo em que ela escreveria algo quando tem algo a
dizer.

**Determinar nova vistoria** — o ponto volta para a equipe. A justificativa é **obrigatória**, com
tamanho mínimo, e a exigência mora **no servidor**: mandar a equipe de volta gasta o trabalho dela
outra vez, e "voltar lá" não conta a ela o que deve procurar desta vez. Esconder o campo na tela não
impede ninguém de mandar a requisição sem ele. A confirmação passa por `ModalConfirm`, dizendo
quantos pontos voltam e que isso custa outra ida.

O **lote é o caso normal**: a equipe volta da rua com seis pontos vistoriados, e a chefia lê os seis
de uma vez. Um caminho para o lote e outro para o registro isolado seriam a mesma regra com dois
donos, e um dia só um deles ganharia a validação nova.

**"Selecionar todos" alcança o recorte filtrado**, não só a página à vista — e a seleção **some** com
o recorte: trocar de aba ou filtrar deixando marcado o que saiu da tela faria a decisão em lote
alcançar o que a pessoa não está vendo.

### RN-07 — O recorte é por ÁREA, e é do SERVIDOR — com DUAS recusas

**Quem é recortado:** o Chefe de Setor, e só ele. A lista dele traz apenas os registros das equipes
das áreas que ele responde.

**Quem não é:** o **administrador** (é o dono do sistema) e o **Coordenador** — quem tria precisa
saber o que aconteceu com o que encaminhou, e não se acompanha o que não se vê. Um Chefe de Setor que
também seja Coordenador **não** é recortado: o papel que amplia ganha, a mesma regra da união de
setores na matriz de permissões.

**O recorte é feito no servidor**, e não na tela: filtro de front esconde, não protege, e a fila
inteira teria viajado até o navegador de quem não deve vê-la — com o relato do fiscal, a coordenada e
o número do documento dentro.

E esconder da lista **não é fronteira**. Quem souber montar a requisição alcançaria registro de outra
área, e o **lote** é o caminho fácil porque manda uma lista de identificadores. Então há **duas**
conferências no servidor, e nenhuma substitui a outra:

1. **quem decide** — a leitura do retorno é ato da **chefia da área**. O Coordenador acompanha e não
   decide: dar-lhe a decisão criaria um segundo dono para o direcionamento;
2. **de quem é o registro** — conferido contra a área **gravada** em cada registro e o vínculo do
   usuário, as duas coisas que o corpo da requisição não controla.

As duas recusam **dizendo o motivo**, com `flash.erro` e `back()` — nunca em silêncio, nunca com tela
de erro seca: quem clicou perdeu a seleção, não a explicação. A recusa por área **nomeia** os
registros de fora e avisa que **nada foi alterado**.

**Lote misto é recusado por inteiro.** Um identificador da própria área junto de um de fora não
aplica "a parte válida": aplicar metade deixaria a fronteira valendo pela metade, e quem montou a
requisição sairia com metade do que pediu.

**Chefe de Setor sem área vinculada** é recusado dizendo isso, e não deixado passar: ele exerce a
decisão e não tem área sobre a qual decidir. Recusar é o que faz alguém corrigir o cadastro; deixar
passar daria a ele a fila inteira do setor.

### RN-08 — O fiscal não entra

Quem escreveu o retorno foi ele. Dar-lhe a fila permitiria **dar ciência do próprio trabalho**, o que
apaga a conferência que a fila existe para provocar. **Concessão inicial:** administrador,
Coordenador e Chefe de Setor.

### RN-09 — A busca é o filtro único; a aba é que troca a fonte

A **busca inteligente** é a barra única, acento-insensível, com exemplos clicáveis. Facetas do
domínio: *com documento* / *sem documento*, *de denúncia* / *avulsa* / *ronda* / *operação*, *com
recomendação*. O texto restante casa contra ponto, bairro, equipe, fiscal, área, desfecho, estado, as
considerações e as recomendações — quem procura pelo que o fiscal escreveu tem de achar.

Os **números do topo** são o resumo da mesma lista e, clicados, escrevem a faceta na busca: atalho
sem criar um segundo filtro. **Não há chip de filtro paralelo.**

A **aba** é outra coisa: ela troca a **fonte** dos dados — "A ler" é a fila propriamente dita,
"Todos" é o histórico do que a área devolveu. Por isso ela entra no **contexto da exportação**.

### RN-10 — Exportação do recorte visível

PDF, XLSX e DOCX pelo ponto **único** (`POST /retaguarda/exportar-listagem`), com o recorte que
filtro, busca e aba deixaram na tela — nunca o universo, nunca só a página. O contexto impresso diz a
aba, as áreas do recorte e a busca digitada. As datas saem em **dd/mm/aaaa**: o arquivo é lido fora do
sistema, onde ninguém traduz ISO.

### RN-11 — Reiniciar a demonstração

Existe porque é protótipo: quem está mostrando o sistema precisa poder recomeçar a cena. O botão só
aparece **depois** de a sessão ter decidido algo. No sistema real esta rota não existe — ciência dada
não se desfaz.

---

## Pendências que isto abre

| ID | O que falta | O que destrava |
|---|---|---|
| **PEND-013** | A **fiscalização como tabela**. Hoje ela só existe dentro do trâmite da denúncia e no arquivo das avulsas. | Aprovação da forma pelo dono + o contrato do que o aplicativo do fiscal envia. |
| **PEND-014** | O **efeito real de "nova vistoria"** no aplicativo do fiscal. Hoje ela só muda o estado da fila; no sistema real o ponto tem de reaparecer no aparelho da equipe. | O contrato da fila de trabalho do aplicativo. |
| **PEND-015** | O **prazo de leitura** que torna um retorno atrasado. A tela já conta os dias parados, mas ninguém definiu a partir de quantos ele cobra. | Decisão da área de negócio. |
| **PEND-016** | O **catálogo de recomendações** existe duas vezes enquanto os dois lados são protótipo (aqui e no aplicativo do fiscal). | Virar produção: a lista passa a ser lista de escolha do servidor, consumida pelo aplicativo. |
| — | A **modelagem definitiva do vínculo chefia↔área** (em produção é tabela usuário↔área, não arquivo de configuração). | A mesma pendência já registrada em [Áreas e Equipes](../estrutura/areas-e-equipes.md). |

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 04/09/2026 | José Nascimento | Retorno de Campo | Nasce a tela, como **protótipo**: a fila do Chefe de Setor com todo registro de fiscalização concluído da área dele, derivado do trâmite das denúncias mais as fiscalizações avulsas (RN-02); recomendação do fiscal em coluna própria (RN-03); três estados e as duas decisões da chefia, em lote, com justificativa obrigatória para mandar a equipe voltar (RN-05, RN-06); recorte por área feito no servidor, com as duas recusas explicadas (RN-07); busca inteligente, aba que troca a fonte e exportação do recorte visível (RN-09, RN-10). | Decisão do dono de 04/09/2026: "todo registro de fiscalização concluído cai/volta para a caixa de entrada do Chefe de Setor". Sem a tela, o trabalho da equipe terminava no aplicativo do fiscal e ninguém do outro lado era obrigado a ler — o desfecho existia no sistema e a decisão que ele pede ficava sem dono. Entregue como protótipo para o dono aprovar a forma antes de a fiscalização existir como tabela. |
