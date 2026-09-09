# Fiscalizações — a fila do Chefe de Setor e o acervo

**Onde fica:** Menu → Fiscalização → Fiscalizações (`/retaguarda/fiscalizacoes`).
**Quem usa:** Chefe de Setor (a fila é dele), Coordenador (acompanha, sem decidir), administrador e
o Fiscal em **apenas leitura** (ver RN-08).

> ### Esta tela era DUAS, e foi unificada em 09/09/2026
>
> Havia "**Retorno de Campo**", construída, com a fila da chefia; e "**Fiscalizações**", um andaime
> que **anunciava** a consulta por ambulante, área e período sem entregá-la. Duas telas sobre o
> **mesmo registro** — a fiscalização concluída — que a lei da fonte única já condenava: no dia em
> que uma ganhasse regra nova, a outra continuaria mostrando o mundo de antes. E, antes disso, o
> custo diário era outro: o gestor pulava de menu para juntar as duas metades da mesma informação.
>
> O dono decidiu unificar. Ficaram **duas abas** — "A decidir" (a fila) e "Acervo" (a consulta) —,
> este documento passou a ser o único da entrega, e o endereço antigo **redireciona** para o novo
> (RN-12).

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
| **Fiscalizações** | **fim** | **Chefe de Setor** | o que a equipe **concluiu em rua** |

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

**O registro guarda a CHAVE do atalho; esta tela mostra a redação EXPLÍCITA.** O aplicativo do fiscal
grava `retorno`, `sgci`, `passagem`… (é a chave que o relatório soma), e o catálogo que a traduz vem do
servidor. A redação curta é a da pílula no celular; aqui quem decide precisa da frase inteira —
"Sugerir retorno da equipe" não diz **quando** voltar, e "Voltar ao ponto no vencimento do prazo" diz.
Isso vale nos três lugares em que a recomendação aparece: a **coluna** da grade, o **destaque** do
detalhe e a **exportação** (o arquivo é lido por quem decide, não pelo aparelho — uma célula com
`sgci` não é resposta para ninguém). A **busca** também casa contra a frase, e não contra a chave: quem
procura por *operação* tem de achar o registro em que o fiscal pediu operação.

**Chave que o catálogo não conhece aparece CRUA** — nunca desaparece. Recomendação que evapora em
silêncio é pior que recomendação feia: a chefia decidiria sem saber que o fiscal pediu alguma coisa, e
a chave crua na tela é o sintoma visível de que os dois catálogos (aqui e o do aplicativo) andaram
separados.

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

### RN-08 — O fiscal CONSULTA, e não decide

**Concessão inicial:** administrador, Coordenador, Chefe de Setor e **Fiscal em apenas leitura**.

O fiscal entra por decisão do dono (09/09/2026), e a decisão vem com uma **ressalva registrada em
voz alta**: *o fiscal é usuário do **aplicativo**; o acesso dele à Retaguarda é **improvável** e
existe por **completude**, não por fluxo.* Ele trabalha em rua, pelo PWA — e é lá que o trabalho
dele chega e é registrado.

O que **não** mudou é o que importa: quem escreveu o retorno foi ele, e dar-lhe a **decisão**
permitiria **dar ciência do próprio trabalho**, apagando a conferência que a fila existe para
provocar. Duas coisas garantem isso, e nenhuma substitui a outra:

- a **tela não lhe oferece** a seleção nem a janela de decisão (a resposta `decide` vem do
  servidor, não de uma segunda conta feita no navegador);
- o **servidor recusa** o ato. Na prática há duas guardas em série: a de **ação** do Modo Gerente
  barra primeiro, porque a concessão dele é "apenas leitura"; a de **papel** (RN-07) barraria em
  seguida. Esconder botão é conforto — a fronteira é a recusa.

⚠️ **O que ele vê é o acervo INTEIRO, e não "o que ele mesmo registrou".** O recorte por área é do
Chefe de Setor (RN-07); entre a **conta** do fiscal e os registros que ela **assinou** não existe
vínculo hoje — o registro guarda o **nome** de quem assinou, a estrutura de áreas guarda a
**matrícula** do fiscal na equipe, e nada liga os dois. Casar por nome seria adivinhar, e adivinhar
em fronteira de dados é pior que não ter fronteira: cria a impressão de que existe uma. Fica como
**PEND-021**, e a frase da tela diz o que ela faz — "você consulta o que a fiscalização registrou".

### RN-09 — A busca é o filtro único; a aba é que troca a fonte

A **busca inteligente** é a barra única, acento-insensível, com exemplos clicáveis. Facetas do
domínio: *com documento* / *sem documento*, *de denúncia* / *avulsa* / *ronda* / *operação*, *com
recomendação*. O texto restante casa contra ponto, bairro, equipe, fiscal, área, desfecho, estado, as
considerações e as recomendações — quem procura pelo que o fiscal escreveu tem de achar.

Os **números do topo** são o resumo da mesma lista e, clicados, escrevem a faceta na busca: atalho
sem criar um segundo filtro. **Não há chip de filtro paralelo.**

**Facetas do acervo**, além das da fila: *não identificado* / *sem alvo*, *com foto*, *prazo
vencido*, *prazo correndo*, *nos últimos 7 dias*, *nos últimos 30 dias*. É assim que a consulta
**por período** acontece — pela frase, e não por um par de seletores de data ao lado da barra, que
seria justamente o segundo filtro que este padrão existe para evitar. E o texto livre casa também
contra o **alvo** e o **número do documento**: "consultar por ambulante" é a pergunta que o acervo
existe para responder.

A **aba** é outra coisa: ela troca a **fonte** dos dados (ver RN-12). Por isso ela entra no
**contexto da exportação** — e por isso cada aba exporta as **colunas dela**: a fila leva o que
decide (recomendação, considerações), o acervo leva o que **prova** (alvo, provas, prazo).

### RN-10 — Exportação do recorte visível

PDF, XLSX e DOCX pelo ponto **único** (`POST /retaguarda/exportar-listagem`), com o recorte que
filtro, busca e aba deixaram na tela — nunca o universo, nunca só a página. O contexto impresso diz a
aba, as áreas do recorte e a busca digitada. As datas saem em **dd/mm/aaaa**: o arquivo é lido fora do
sistema, onde ninguém traduz ISO.

### RN-11 — Reiniciar a demonstração

Existe porque é protótipo: quem está mostrando o sistema precisa poder recomeçar a cena. O botão só
aparece **depois** de a sessão ter decidido algo. No sistema real esta rota não existe — ciência dada
não se desfaz.

### RN-12 — As DUAS abas: o que cada uma responde, e por que não há uma terceira

| Aba | A pergunta que ela responde | O que ela é |
|---|---|---|
| **A decidir** (padrão) | *o que eu tenho para fazer agora?* | a **fila**: o que voltou da rua e espera a leitura da chefia. Tela de **trabalho** — seleção, comando flutuante, janela de decisão |
| **Acervo** | *o que foi feito naquele ponto?* | a **consulta**, sem ação: tudo o que passou por aqui, inclusive o já lido e o devolvido à equipe |

A fila é o acervo **com o corte do estado** — a fonte é **uma só**. Não são duas consultas: se
fossem, um dia mostrariam desfechos diferentes para a mesma vistoria.

**O acervo carrega o que a fila não precisa** (e é o que transforma consulta em prova): **quem foi
encontrado** no ponto e o equipamento, as **fotos**, o **ponto de GPS com a precisão**, o
**documento** que saiu na hora e o **prazo de retorno** (RN-13). Alvo **nulo** é caso previsto, e
não dado faltando — "nada encontrado no local" é desfecho legítimo, e a foto do ponto vazio é a
prova da ida; a tela escreve *"não identificado"*, e não um travessão.

**Por que a seleção não existe no acervo:** ele é leitura. Caixinha ali prometeria uma ação que a
aba não tem — e a decisão sobre um retorno já lido não existe.

**Por que NÃO há uma terceira aba.** "Por operação" e "por prazo vencido" foram consideradas e
recusadas: não são **conjuntos** diferentes, são **recortes** do acervo, e a busca já os entrega
("operação", "prazo vencido"). Aba que só filtra o mesmo conjunto seria um segundo filtro
concorrendo com a barra, contra o padrão de busca do projeto (RN-09). A régua para uma aba nova
fica escrita: **ela só se justifica se responder a uma pergunta que estas duas não respondem.**

**O endereço antigo redireciona.** `/retaguarda/retorno-de-campo` responde **301** para
`/retaguarda/fiscalizacoes`. Quem trabalhava na tela tem o endereço no favorito, no e-mail de aviso,
na conversa de ontem — devolver "não encontrado" transformaria uma melhoria em falha, e a pessoa
concluiria que **perdeu a fila dela**. Só **GET** redireciona: link salvo é GET, e um POST sob um
caminho que já não é tela de ninguém seria mutação que a guarda de ações não consegue atribuir a
tela nenhuma. A concessão do slug antigo foi **migrada** (só `UPDATE`/`DELETE` de texto, sem DDL):
quem tinha `retorno-de-campo` e não tinha `fiscalizacoes` — o Coordenador — teria perdido o acesso
**em silêncio**.

### RN-13 — O PRAZO de retorno de quem foi notificado

Só a **Notificação Preliminar** tem prazo de retorno: ela dá um tempo para o notificado regularizar,
e **alguém tem de voltar no vencimento** — sem retorno, ela fica no papel. O Auto de Apreensão não
pede volta ao ponto (o que ele tem é prazo de guarda do material, que é assunto do depósito).

O prazo é a **única informação da fila que continua correndo depois da ciência**, e é por isso que
ele mora no **acervo**, e não só na fila: o retorno sai da fila da chefia e o prazo não para.

**A conta é do servidor.** No navegador ela dependeria do relógio e do fuso da máquina de quem abre
a tela, e "vence amanhã" viraria "venceu ontem". O que viaja é a data de vencimento, o número de
dias **com sinal** (negativo = vencido) e quem foi notificado — um número com sinal, e não dois
campos ("dias" e "vencido") que um dia discordariam. **Prazo não declarado não é inventado:** prazo
chutado é pior que prazo ausente, porque alguém volta ao ponto no dia errado.

### RN-14 — O contador no item de menu

O item do menu traz o **número da fila**, em tom de alerta. É o gatilho de trabalho de quem decide:
sem ele, a chefia só descobre que tem sete retornos parados quando abre a tela.

Duas regras que o número obedece, e as duas por um motivo prático:

- ele é **recortado pela mesma área** (RN-07). Um contador que somasse o universo mostraria "12" a
  quem abre a tela e encontra 3, e a diferença pareceria **registro perdido**;
- ele conta **só para quem decide**. Para o Coordenador — que acompanha — e para o Fiscal, o número
  seria cobrança sobre trabalho que não é deles. **Zero não vira selo:** alerta em zero chama
  atenção para dizer que não há nada.

---

## Pendências que isto abre

| ID | O que falta | O que destrava |
|---|---|---|
| **PEND-013** | A **fiscalização como tabela**. Hoje ela só existe dentro do trâmite da denúncia e no arquivo das avulsas. | Aprovação da forma pelo dono + o contrato do que o aplicativo do fiscal envia. |
| **PEND-014** | O **efeito real de "nova vistoria"** no aplicativo do fiscal. Hoje ela só muda o estado da fila; no sistema real o ponto tem de reaparecer no aparelho da equipe. | O contrato da fila de trabalho do aplicativo. |
| **PEND-015** | O **prazo de leitura** que torna um retorno atrasado. A tela já conta os dias parados, mas ninguém definiu a partir de quantos ele cobra. | Decisão da área de negócio. |
| **PEND-016** | O **catálogo de recomendações** existe duas vezes enquanto os dois lados são protótipo (aqui e no aplicativo do fiscal). As duas cópias vivem em **branches diferentes** (`feature/prototipo-administrativo` e `feature/pwa-prototipo`), que não se veem — então **nenhum teste consegue compará-las**: chave nova, chave renomeada ou redação mexida entra nos dois lados no mesmo passo, à mão. Cada lado tem teste-lei do que ele **próprio** declara (aqui: toda chave com as duas redações; lá: nenhum registro semeado fora do catálogo). | Virar produção: a lista passa a ser lista de escolha do servidor, consumida pelo aplicativo — e aí o `curto` que este catálogo já guarda passa a ser o rótulo que o aparelho baixa. |
| **PEND-021** | O **recorte do FISCAL**: hoje ele consulta o acervo inteiro, porque não há vínculo entre a conta dele e os registros que ela assinou. Baixa urgência declarada pelo dono — o fiscal é usuário do aplicativo, e o acesso à Retaguarda existe por completude. | A fiscalização como tabela (**PEND-013**), com o **autor** do registro sendo o usuário, e não um nome de texto. |
| **PEND-017** | As **fotos de verdade**. O acervo mostra o **nome do arquivo** e a contagem, porque o protótipo não guarda imagem — e miniatura falsa prometeria o que a tela não entrega. | O contrato de anexo do aplicativo do fiscal + o armazenamento (a foto é retrato de cidadão fiscalizado: a entrega passa pelo caminho da tela, como já acontece com a do cadastro de ambulante). |
| — | A **modelagem definitiva do vínculo chefia↔área** (em produção é tabela usuário↔área, não arquivo de configuração). | A mesma pendência já registrada em [Áreas e Equipes](../estrutura/areas-e-equipes.md). |

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 09/09/2026 | José Nascimento | Fiscalizações | **As duas grades ficaram enxutas** (padrão [`docs/padroes/listagem-clean.md`](../../padroes/listagem-clean.md)). _A decidir_ passou de 6 colunas com sub-linha para **Concluída · Ponto · [Área] · Desfecho · Recomendação do fiscal** — `Estado` saiu porque a aba já filtra por ele (era a mesma palavra em toda linha), e equipe, fiscal, bairro, documento, hora da conclusão e dias na fila desceram para a ficha. _Acervo_ passou de 8 para **Concluída · Ponto · Quem foi encontrado · Desfecho · Prazo de retorno**. A coluna de **Área** só existe para quem responde por mais de uma. A recomendação aparece como a **primeira** frase mais "+N", com a lista inteira na dica e na ficha. **A exportação continua completa** e ganhou recomendação e considerações também no acervo. | Ordem do dono (09/09/2026): _"as listagens estão muito poluídas, muita informação quebrando linha de forma irregular… deixe a informação detalhada para quando o usuário clicar"_. A régua e o porquê de cada item ficam em [`docs/padroes/listagem-clean.md`](../../padroes/listagem-clean.md) — este doc aponta para lá em vez de repetir a régua. |
| 09/09/2026 | José Nascimento | Fiscalizações | **As duas telas viraram uma, com duas abas.** "Retorno de Campo" passou a ser a aba **A decidir** e o andaime "Fiscalizações" foi substituído pela aba **Acervo**, que entrega o que ele anunciava: consulta por ambulante, área e período, com alvo, fotos, GPS, documento e **prazo de retorno** (RN-12, RN-13). O endereço antigo **redireciona** (301) e a concessão do slug `retorno-de-campo` foi migrada para `fiscalizacoes`. O **Fiscal** passou a entrar em **apenas leitura**, sem poder decidir (RN-08). O item de menu ganhou o **contador da fila**, recortado por área e só para quem decide (RN-14). O recorte por área passou a vir de uma **fonte única** (`PapelNaArea`), que antes era código copiado entre esta tela e Denúncias. | Duas telas sobre o **mesmo registro** — a fiscalização concluída — obrigavam o gestor a pular de menu para juntar as duas metades da mesma informação, e a lei da fonte única já dizia onde isso ia parar: uma ganharia regra nova e a outra continuaria mostrando o mundo de antes. Decisão do dono: *"pode unificar em Fiscalizações com aba a decidir, acervo e outras se necessário"*. A entrada do fiscal é decisão dele na mesma conversa, com a ressalva de que **o fiscal é usuário do aplicativo** — o acesso à Retaguarda existe por completude, não por fluxo. |
| 04/09/2026 | José Nascimento | Retorno de Campo | A recomendação do fiscal passa a chegar como **chave** e a ser mostrada na **redação explícita** do catálogo do servidor (RN-03) — na coluna, no destaque do detalhe, na exportação e na busca. Chave desconhecida aparece **crua**, em vez de desaparecer. | O catálogo estava divergente entre a Retaguarda (que esperava a frase inteira) e o aplicativo do fiscal (que grava chave): o despacho chegaria aqui com recomendação que a tela não sabe ler. Decisão do dono: unificar por chave, com a redação curta no aplicativo e a explícita na Retaguarda. |
| 04/09/2026 | José Nascimento | Retorno de Campo | Nasce a tela, como **protótipo**: a fila do Chefe de Setor com todo registro de fiscalização concluído da área dele, derivado do trâmite das denúncias mais as fiscalizações avulsas (RN-02); recomendação do fiscal em coluna própria (RN-03); três estados e as duas decisões da chefia, em lote, com justificativa obrigatória para mandar a equipe voltar (RN-05, RN-06); recorte por área feito no servidor, com as duas recusas explicadas (RN-07); busca inteligente, aba que troca a fonte e exportação do recorte visível (RN-09, RN-10). | Decisão do dono de 04/09/2026: "todo registro de fiscalização concluído cai/volta para a caixa de entrada do Chefe de Setor". Sem a tela, o trabalho da equipe terminava no aplicativo do fiscal e ninguém do outro lado era obrigado a ler — o desfecho existia no sistema e a decisão que ele pede ficava sem dono. Entregue como protótipo para o dono aprovar a forma antes de a fiscalização existir como tabela. |
