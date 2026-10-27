# Aplicativo do Fiscal — a fila dirigida e a vistoria em campo

**Onde fica:** `/app` (casca própria, fora da Retaguarda; ver
[`routes/web.php`](../../../routes/web.php) e
[`resources/views/pwa.blade.php`](../../../resources/views/pwa.blade.php)).
**Quem usa:** o fiscal de campo, no celular. **A Retaguarda não abre estas telas**, e o fiscal não
abre as da Retaguarda.

> ## 🔌 O APLICATIVO FALA COM O SERVIDOR (desde 13/10/2026)
>
> Login, fila, números de documento e o envio do registro são **reais**: o aplicativo conversa com a
> Retaguarda pelas rotas `/app/api/*` (mesma origem e mesma sessão), e a vistoria despachada entra
> no banco esperando a leitura do líder. Desde a mesma data, também o **mapa** (ambulantes na última
> posição), o **mapa de calor**, as **operações** e o **levantamento de ambulantes** vêm do servidor
> (RN-17 a RN-19). A faixa "Em implantação · homologação" fica visível até a versão final.
>
> **Origem:** cenário da reunião com o cliente (02/09/2026) e decisões do dono. Não há HU escrita: a
> linha em [`config/acompanhamento_requisitos.php`](../../../config/acompanhamento_requisitos.php)
> nasce `hu_status => 'nao'` declarando essa origem.

O trabalho do fiscal tem **duas entradas**, e o aplicativo mostra as duas: o que ele descobre andando
a rua (**avulso**, pelo mapa) e o que chega **dirigido** — a denúncia que a ouvidoria entregou ao
SEFAL, que o **Coordenador** triou e que o **Chefe de Setor** da área direcionou à equipe dele.

> ### 🗣️ VOCABULÁRIO DO DOMÍNIO (decisão do dono, 04/09/2026)
>
> Quem antes se chamava **gestor** é o **Chefe de Setor**; o **administrativo** da triagem é o
> **Coordenador**. A troca vale em tudo que o usuário lê, nos dois lados (aqui e na Retaguarda). É
> troca de **papel**, não de pessoa: matrícula identifica gente, então os logins (`gestor1`,
> `administrativo1`) continuam os mesmos.

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
[`dados-demandas.ts`](../../../resources/js/pwa/dados-demandas.ts)). O servidor já só entrega ao
aplicativo as situações de campo (`CampoDoFiscal::fila`); a lista do aplicativo é a segunda
conferência, não a única.

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
denúncia voltou ao **Chefe de Setor** da área para a próxima medida — a equipe não tem ato pendente.

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
- **Retorno com a situação mantida** → prazo vencido e ponto igual: a denúncia sobe ao Chefe de Setor
  da área (é o `Retorno vencido` do outro lado).

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

O número vem de uma **reserva feita pelo servidor para o fiscal** (`numeros_reservados`): a cada
conversa com o servidor, o aparelho recebe até **3 números livres de cada tipo**, que ele consome
mesmo sem sinal — é o que faz o documento nascer numerado no meio da rua. O servidor nunca entrega o
mesmo número a dois fiscais, e só aceita o documento que volta com um número **reservado para quem o
envia** (a recusa diz isso e manda atualizar com sinal). A numeração começa **depois** dos números já
usados (Notificação 194906, Auto de Apreensão 160052, ou depois do maior já lavrado). Sem número no
estoque, o aplicativo não lavra e diz por quê.

### RN-09 — Datas em `dd/mm/aaaa`, vindas do servidor

Recebimento, prazo e cada passo do trâmite vêm do servidor, as mesmas datas que a Retaguarda mostra,
sempre em `dd/mm/aaaa`. A hora que o aparelho registra sai em UTC e o servidor a grava no fuso de
Salvador (RN-16).

### RN-10 — Fonte única: a fila VEM do módulo de Denúncias

A fila do aplicativo é a das demandas do banco, entregue pelo servidor no **kit** do turno
(`GET /app/api/kit`): as que estão na mão das equipes de quem entrou (*Direcionada aos fiscais*,
*Em operação*, *Em campo*, *Aguardando regularização*, *Retorno vencido*) e as que elas concluíram nos
últimos 30 dias. O vocabulário é o da Retaguarda, sem tradução: o canal é a chave de
`Demanda::CANAIS` e a situação a de `Demanda::SITUACOES`.

Os catálogos fechados (situações, desfechos, canais e chaves de recomendação) ainda têm cópia no
TypeScript, e
[`tests/Feature/PwaFilaDeDenunciasTest.php`](../../../tests/Feature/PwaFilaDeDenunciasTest.php)
reprova a cópia que divergir das constantes do servidor.

### RN-11 — A amostra é majoritariamente EDUCATIVA

A maioria dos casos termina **sem documento**. Os dois documentos existem para quando a orientação
não resolve, e apreensão é **guarda**, não destruição. Isso é regra da amostra, não observação: uma
demonstração em que todo caso de campo termina em papel desenharia um sistema punitivo que não é o do
cliente — e há teste que reprova a amostra que perder essa proporção.

### RN-12 — O registro é DESPACHADO ao Chefe de Setor, e leva as considerações finais

O registro não termina no aparelho: **todo registro concluído vai ao LÍDER da equipe** — a vistoria
entra na Fiscalização da demanda (ou abre uma, quando é avulsa) em *Aguardando leitura*, e o líder
decide se a equipe volta ao ponto ou se o caso sobe ao Chefe de Setor. _(Revisão de 13/10/2026: antes
o destino declarado era a caixa do Chefe de Setor; o fluxo real, desde 22/09, passa pelo líder.)_ O botão que fecha a vistoria chama-se, por isso, **Despachar registro**
(ou **Despachar retorno**) — chamá-lo de "concluir" prometia um fim que não é o dele —, e a tela
seguinte é a **Conclusão do registro**, que declara o destino com área ("Caixa de entrada do Chefe de
Setor · Área 5 — Boca do Rio") e fecha com o botão **Concluir registro**.

Nessa tela o fiscal acrescenta as **considerações finais**, de duas formas que **convivem**:

- **atalhos de recomendação** — lista fechada, um toque cada, com a redação **curta** (é a que cabe
  numa pílula de celular): `Sugerir retorno da equipe`, `Reprogramar a vistoria`,
  `Sugerir operação na área`, `Manter na passagem semanal`, `Encaminhar ao Chefe de Setor`,
  `Conferir cadastro no SGCI`, `Bens recolhidos ao SEGUB`, `Encaminhar ao SEAB`,
  `Encaminhar a outro órgão`, `Pedir apoio da guarda municipal`, `Sem providência adicional`
  (`RECOMENDACOES`, em [`dados-demandas.ts`](../../../resources/js/pwa/dados-demandas.ts));
- **texto livre**, para o porquê que o atalho não conta.

As duas são **opcionais** — a maioria das abordagens se explica pelo desfecho —, mas são **a parte
importante da entrega**: é por elas que o Chefe de Setor e o Coordenador entendem a recomendação de
quem esteve no ponto e sabem para onde direcionar. Por isso elas **viajam com o registro**, e a tela
de detalhe do registro no aplicativo as mostra em leitura depois do despacho.

**Contrato com o outro lado** (o servidor recusa chave fora do catálogo, e um teste compara as listas):
`consideracoes` = texto livre; `recomendacoes` = as **chaves** dos atalhos escolhidos, como já ocorre
com `ocorrencias` e com os `motivos` do impresso. Chave e não texto porque recomendação o outro lado
precisa **somar** ("quantos registros pediram operação na área?"), e texto livre não se soma. Do lado
da Retaguarda, elas entram no **histórico do trâmite** da denúncia.

**Uma chave, duas redações — e cada uma no lado que a usa.** O catálogo nasceu duas vezes, em frentes
paralelas: aqui com chave e rótulo curto, e na Retaguarda como frases inteiras, com outra redação e
sem o SEAB. Resultado: o aplicativo mandava `["seab"]` e o outro lado esperava uma frase. Unificado
por decisão do dono (04/09/2026 — *"unifique usando a redação curta no app e a explícita no
retaguarda"*):

| Redação | Onde é lida | Exemplo (`retorno`) |
|---|---|---|
| curta (aqui) | A **pílula do aparelho** — de pé na calçada não cabe frase. | Sugerir retorno da equipe |
| explícita (Retaguarda) | O que quem **decide** lê, no trâmite e na fila de Retorno de Campo. | Voltar ao ponto no vencimento do prazo |

O aplicativo **nunca** mostra a redação explícita: ela não cabe, e a decisão do dono é essa. O que
atravessa é sempre a **chave** — assim a redação de um lado não mexe no dado do outro, e é a chave que
o relatório soma.

⚠️ **Chave renomeada, ou atalho novo só de um lado, quebra o de-para em silêncio.** Nada estoura; a
Retaguarda apenas mostra a chave crua onde deveria estar a recomendação (de propósito: recomendação
que evapora é pior que recomendação feia). E **nenhum teste alcança os dois lados** — este catálogo e
o `config/prototipo_denuncias.php` da Retaguarda vivem em **branches diferentes**
(`feature/pwa-prototipo` e `feature/prototipo-administrativo`), que não se veem. Cada lado só prova o
que ele próprio declara: aqui, que o catálogo bate com a régua escrita à mão e que nenhum registro
semeado usa chave fora dele (`tests/Feature/PwaFilaDeDenunciasTest.php`). A conferência entre as duas
listas é **à mão**, no mesmo passo. Quando o aplicativo falar com o servidor, o catálogo passa a vir
de lá e esta cópia morre.

Consequências na tela de **Envio**: a fila diz para onde vai ("Na fila para o Chefe de Setor · Área
5") e o registro **ainda aberto** — despachado, mas sem a conclusão confirmada — **não** entra na
fila: fica em bloco próprio ("Falta concluir"), com o caminho de volta. A caixa do Chefe de Setor
recebe registro concluído; meio-registro seria trabalho que ninguém pediu.

### RN-13 — Sem documento, só conclui o desfecho que não lavra documento

**Concluir sem documento lavrado é possível apenas quando o desfecho é `Regularizado no local` ou
`Nada encontrado no local`.** Com `Notificação Preliminar emitida` ou `Auto de Apreensão lavrado`, a
conclusão é **impedida**: a Retaguarda ficaria com um passo que anuncia um documento que não existe, e
o notificado sem a via que faz o prazo correr.

A régua é o **`DOCUMENTO_DO_DESFECHO`**, através de `impedimentoParaConcluir` — uma função só. Não há
segunda lista de "desfechos que concluem": duas listas para a mesma decisão divergem no primeiro
ajuste. Como consequência natural, o **retorno** segue concluindo sem papel (os dois desfechos dele
não lavram nada).

**O impedimento nunca é silencioso** (lei do projeto): a tela mostra, *antes* de o dedo chegar ao
botão, o motivo em linguagem clara ("O desfecho 'Notificação Preliminar emitida' lavra a Notificação
Preliminar, e ela ainda não foi lavrada neste registro") e o que fazer ("Lavre a Notificação
Preliminar para concluir — ou volte e troque o desfecho, se nada foi lavrado no local"), com o botão
que **abre o formulário do documento que falta**. O botão "Concluir registro" fica barrado enquanto
isso, e não há `alert` em lugar nenhum.

Quem escolhe um desses desfechos já é avisado no **passo 6 do registro**: "o registro só conclui com
ele lavrado".

### RN-14 — "Voltar" volta para a tela anterior

O botão da conclusão é **Voltar** (era "Voltar ao mapa") e usa o **histórico**: quem chegou da lista
de registros volta à lista; quem chegou do despacho volta ao registro. O mapa continua sendo o destino
de segurança de quem abriu o aplicativo direto num endereço colado, sem histórico para onde voltar.
Levar sempre ao mapa mandava o fiscal para longe de onde ele estava.

---

### RN-15 — Entrar é com a conta da Retaguarda, e quem não está em equipe não entra

A porta pede **matrícula e senha** — a mesma conta da Retaguarda, conferida pelo servidor com as
mesmas recusas faladas (senha errada, senha ainda não definida, conta inativa). Quem não está em
**nenhuma equipe** (como fiscal ou como líder) é recusado com o motivo: sem equipe não há fila. A
sessão fica lembrada no aparelho; sair é pelo "Encerrar plantão".

### RN-16 — O registro despachado sobe ao servidor, e o que não subiu fica no aparelho

Ao despachar, o aplicativo envia o registro inteiro — desfecho, relato, ocorrências, GPS e precisão,
fotos, considerações, recomendações e o documento lavrado (com as chaves dos catálogos e a via
impressa). O registro nasce guardado na **fila do aparelho** (IndexedDB, com as fotos) e só sai dela
quando o servidor confirma; fechar o aplicativo ou ficar sem sinal não perde nada. Voltou o sinal, a
fila sobe sozinha; "Sincronizar agora", na aba Envio, faz o mesmo e atualiza a fila da equipe e os
números. O envio repetido do mesmo registro não duplica a vistoria (`client_id`). Recusa do servidor
aparece ao lado do registro, com o motivo.

Na fila de demandas, a que já foi vistoriada e despachada sai de "A vistoriar" e vai para **"Com o
líder"**, sem ato do fiscal — se o líder mandar voltar, ela volta para "A vistoriar".

### RN-17 — O mapa mostra os ambulantes do cadastro, na última posição conhecida

Os pinos são os ambulantes com posição registrada, cada um no último ponto conhecido, com as últimas
5 idas da fiscalização no histórico e o retorno vencido calculado pela mesma regra da Retaguarda
(`MapaDaCidade::retornoVencidoHaDias`). O registro feito **a partir de um pino** leva o ambulante
identificado (`ambulante_id`) e **move o pino** para onde o fiscal esteve — trocar o nome no
registro desfaz a identificação. O ícone do pino sai da categoria da atividade.

### RN-18 — O calor é das vistorias com irregularidade; as operações são as da equipe

O mapa de calor recebe as vistorias dos últimos 90 dias com GPS em que havia **irregularidade** no
ponto: só "Nada encontrado no local" fica de fora ("Regularizado no local" teve ambulante irregular,
resolvido na hora). A tela recorta 7, 30 ou 90 dias. As operações do início são as **abertas** que
chegam ao fiscal — as das equipes dele e as em que ele foi posto à parte.

### RN-19 — O levantamento de ambulantes é gravado no servidor

O censo por rua (cabeçalho da força-tarefa + uma linha por ambulante, numerada 001, 002…) é
concluído no botão **Concluir levantamento** e vai ao servidor com a foto do equipamento e o ponto de
GPS de cada linha (`levantamentos` / `levantamento_linhas`, protocolo `LEV`). As **equipes** do
cabeçalho são as do cadastro, e os **encarregados** que assinam saem delas no servidor — o aparelho
só mostra. Sem sinal, o levantamento fica na fila do aparelho e sobe com a sincronização; recusado
pelo servidor, volta à tela com o motivo. O levantamento **não** entra no mapa de calor (é censo, não
incidência). Os atalhos "Preencher exemplo" e "Simular foto" do protótipo saíram.

### RN-20 — Encerrar o plantão avisa o que ficou no aparelho

O aparelho é compartilhado. Se há registro esperando sinal ou registro aberto, "Encerrar plantão e
sair" não sai direto: diz quantos estão só no aparelho, que eles só sobem quando o mesmo fiscal entrar
de novo, e oferece **Enviar agora**, **Sair assim mesmo** ou **Continuar no plantão**. As ocorrências
marcadas no registro são chaves do catálogo do servidor (`ocorrencias_do_fiscal`); chave fora dele é
recusada no envio.

### RN-21 — A via sai na térmica pelo diálogo de impressão do aparelho

Como no CODECON (mesma impressora de 80 mm): o cupom é montado em HTML (brasão binarizado embutido,
sans-serif em negrito, margem zero com recuo simétrico) e entregue ao diálogo de impressão do Android;
a impressora Bluetooth é alcançada pelo serviço de impressão dela, pareado no aparelho. Sem
impressora, o mesmo diálogo salva em PDF. Falha ao abrir a impressão aparece na tela.

### RN-22 — O relato pode ser ditado

O botão **Ditar** usa o reconhecimento de voz do Chrome, em português: cada frase reconhecida entra no
fim do relato, sem apagar o que já foi escrito. Precisa de sinal e da permissão do microfone; sem um
deles, o botão diz o motivo.

### RN-23 — O contorno da área vem do servidor

O contorno do bloco (ou os corredores) que o mapa do aplicativo desenha é o que a Retaguarda guarda
na tela de Áreas; o aplicativo não traz mais desenho próprio.

### RN-24 — A coordenada do registro é a MEDIDA pelo aparelho, ou o registro diz por que não há

- O registro grava a posição medida pelo GPS do aparelho — também na vistoria dirigida e na aberta por
  um pino do mapa. O aparelho segue medindo enquanto a tela está aberta e guarda a leitura de menor
  margem; tocar no selo da precisão mede de novo. Margem acima de 30 m aparece como alerta.
- Enquanto a primeira medição não chega, o botão de despacho espera ("Medindo a posição…").
- Sem GPS (permissão negada, sem sinal, sem resposta em 25 s ou navegador sem GPS), o registro vai
  **sem coordenada** e com o **motivo**; a Retaguarda mostra "Registrado sem GPS: <motivo>" na vistoria
  e no trâmite da denúncia. **Nenhum ponto é inventado**: antes, o aplicativo mandava o centro do
  bairro, que entrava no mapa, no calor e no pino do ambulante como posição medida.
- O servidor recusa registro sem coordenada e sem motivo, e coordenada pela metade. Registro sem
  coordenada não move o pino do ambulante. Os motivos são os de `prototipo_denuncias.motivos_sem_gps`,
  e a lista do aplicativo é conferida contra ela por teste.
- O mapa do aplicativo só desenha o fiscal onde o GPS o mediu; sem GPS, não desenha.

### RN-25 — No registro avulso, o endereço é o que o fiscal escreve

Sem processo e sem pino, o fiscal escreve o endereço ou ponto de referência; sem texto, vai
"Proximidades de <bairro>". O bairro vem do GPS (o do catálogo mais perto) ou o fiscal escolhe —
sem GPS e sem bairro escolhido, o despacho espera. Antes, o aplicativo completava com uma de onze ruas
fixas do protótipo.

### RN-26 — A operação abre tela própria, com as denúncias anexadas

Tocar numa operação da tela inicial abre a operação: tipo, período, foco, todos os bairros, número de
fiscais, quem a abriu, a observação e as **denúncias que o Chefe de Setor anexou a ela** (cada uma com o
mesmo cartão da fila, e o botão de fiscalizar). A ligação é pela chave da operação, não pelo nome. Sem
denúncia anexada, a tela diz que a ronda segue pelos bairros com registro avulso. "Ver no mapa" leva ao
primeiro bairro, que era o que o cartão fazia antes. Sem operação aberta, a tela inicial diz isso em vez
de deixar a seção vazia.

## Como demonstrar

O aplicativo abre em `/app` e entra com a conta da Retaguarda de um fiscal que esteja numa equipe
(RN-15). Na demonstração (Render), a conta é `fiscal1`, da equipe A1; o líder dela é `lider1`, que lê
na Retaguarda o que o fiscal despachou. As contas da demonstração ficam no comando
`sefal:preparar-demonstracao`.

**Roteiro curto:**
1. Entre como `fiscal1`. A tela inicial mostra a fila da equipe, os retornos vencidos e as operações
   abertas.
2. Em **Minhas demandas**, abra uma demanda e toque em **Fiscalizar**. A coordenada vem do GPS do
   aparelho; sem ele, a tela diz o motivo e o registro segue com a marca (RN-24). Escolha o desfecho
   e despache. Com "Notificação Preliminar emitida", o documento usa um número reservado ao fiscal
   (RN-08) e a via sai pela impressão do aparelho (RN-21).
3. Na **Conclusão do registro**, escreva as considerações e conclua. O registro sobe ao servidor, ou
   fica no aparelho até o sinal voltar (RN-16).
4. Na Retaguarda, entre como `lider1` e abra a Fiscalização em **Fiscalização › Fiscalizações**:
   relato, ocorrências, fotos, coordenada (ou a marca de "sem GPS") e retorno combinado estão lá.
5. De volta ao aplicativo, toque numa operação da tela inicial para ver as denúncias anexadas a ela
   (RN-26).

---

## Pendências

- **Ficha do SGCI na demanda.** Não há integração com o SGCI; a ficha do permissionário não aparece.
- **Abrir sem sinal.** Sem versão instalável (decisão do dono, 13/10/2026), o aplicativo precisa de
  sinal para abrir. O que o fiscal fizer fica no aparelho e sobe quando o sinal volta.
- **Impressão e ditado no aparelho.** A via sai pelo diálogo de impressão do Android e chega à
  térmica pelo serviço de impressão pareado; o ditado precisa de sinal e da permissão do microfone.
  Os dois foram verificados só no navegador, sem aparelho real.
- **Redação dos impressos duplicada** (`dados-documentos.ts` ↔ `config/prototipo_documentos_campo.php`).
  Conferidas em 03/09/2026: motivos, penalidades, prazos, fundamentação, SEGUB e destinações estão
  iguais. A que fica é a do servidor.
- **A lista de atalhos de recomendação é proposta**, exceto "Encaminhar ao SEAB", que veio do dono.
  Os outros seis (SEGUB, SGCI, guarda municipal, operação na área, retorno da equipe…) precisam de
  confirmação — inclusive se falta algum destino que o fiscal recomenda com frequência.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 27/10/2026 | José Nascimento | Aplicativo do Fiscal e Retaguarda (Caixa, Denúncias, Fiscalizações, Operações, Áreas e Equipes, Ambulantes, mapas) | Sobras do protótipo removidas: a estrutura e os fiscais inventados saem do aplicativo; o selo "PROTÓTIPO · DADOS FICTÍCIOS" vira "DEMONSTRAÇÃO · DADOS DE EXEMPLO" e só aparece no ambiente de demonstração; Ambulantes diz que a carga do SGCI ainda não existe; textos que citavam o protótipo reescritos; seções "Como demonstrar" e "Pendências" deste doc atualizadas. | Pedido do dono: o aviso de dados fictícios era falso em homologação, onde os registros são os do banco. |
| 14/10/2026 | José Nascimento | Aplicativo do Fiscal (registro, mapa) e Retaguarda (vistoria, trâmite) | Coordenada sempre medida, com recaptura e alerta de margem; sem GPS, o registro vai sem coordenada e com o motivo, mostrado na Retaguarda (RN-24). Endereço do avulso escrito pelo fiscal (RN-25). Operação com tela própria e as denúncias anexadas (RN-26). | O aplicativo mandava o centro do bairro e uma rua fixa do protótipo como se fossem dados medidos. |
| 13/10/2026 | José Nascimento | Aplicativo do Fiscal (via, registro, mapa) | Impressão real da via na térmica 80 mm pelo mesmo caminho do CODECON (RN-21); ditado do relato pelo reconhecimento de voz (RN-22); contorno da área vindo do servidor (RN-23). A porta do aplicativo passa a dizer o motivo da recusa da conta. | Pedido do dono. |
| 13/10/2026 | José Nascimento | Aplicativo do Fiscal (perfil, envio) | Encerrar plantão avisa o que ficou só no aparelho (RN-20); ocorrências validadas pelo catálogo do servidor e mostradas ao líder na Retaguarda. | Aparelho compartilhado: sair em silêncio deixava registro parado sem o fiscal saber. |
| 13/10/2026 | José Nascimento | Aplicativo do Fiscal (mapa, calor, início, levantamento) | **Etapa 3: os dados de campo deixam de ser fictícios.** Pinos de ambulante do cadastro na última posição, com histórico e retorno vencido; registro a partir do pino liga a vistoria ao ambulante e move o pino (RN-17). Calor das vistorias com irregularidade e operações da equipe (RN-18). Levantamento gravado no servidor com foto e GPS por linha, encarregados do cadastro, fila no aparelho (RN-19). Saem as sementes de ambulantes, calor, operações e registros, e os atalhos de exemplo. Migration `2026_10_13_100000_levantamento_de_ambulantes`. | Pedido do dono (13/10/2026): seguir para as próximas etapas, sem versão instalável. |
| 13/10/2026 | José Nascimento | Aplicativo do Fiscal (todas) | **O aplicativo passa a falar com o servidor.** Login real (RN-15); fila da equipe vinda do banco, no vocabulário da Retaguarda (canais e situações sem tradução); o despacho grava a vistoria esperando o LÍDER, ligada à Fiscalização e ao trâmite da demanda (RN-12, RN-16); números de NP/AA reservados pelo servidor por fiscal (RN-08); fila do aparelho com as fotos, reenvio quando o sinal volta; bloco "Com o líder" na fila. As 22 demandas fictícias saíram do aplicativo. Rotas `/app/api/*` protegidas por `AplicativoDoFiscal`; migration `2026_10_13_090000_aplicativo_do_fiscal_fala_com_o_servidor`. | Pedido do dono (13/10/2026): ajustar o PWA para se comunicar de verdade com a Retaguarda. |
| 04/09/2026 | José Nascimento | Aplicativo do Fiscal (despacho) | **O catálogo de recomendações passa a ser o mesmo da Retaguarda, unificado pela CHAVE.** A lista sai de 7 para 11 atalhos (entram `reprogramar`, `passagem`, `chefia` e `outro-orgao`), todos com a redação **curta** — a explícita, que quem decide lê, mora do lado da Retaguarda, na mesma chave. Nenhuma chave antiga saiu, então os registros já semeados continuam válidos. | O catálogo tinha nascido em duas versões incompatíveis: o aplicativo gravava chave e a Retaguarda esperava a frase inteira, com outra redação e sem o `seab` — que foi justamente o exemplo dado pelo dono. Assim a recomendação chegaria do outro lado como coisa que ele não sabe ler nem somar. Decisão do dono: redação curta no aplicativo, explícita na Retaguarda, chave como contrato. |
| 03/09/2026 | José Nascimento | Aplicativo do Fiscal (fila, detalhe, registro) | **O aplicativo passa a falar a língua do módulo de Denúncias.** (1) A fila deixa de espelhar a Caixa de Entrada (`CXE-NNNN`, situações `Aguardando triagem`/`Encaminhada`) e passa a espelhar `config/prototipo_denuncias.php`: protocolo `DEN-NNNN`, os dois canais das ouvidorias e o catálogo fechado de 10 situações. (2) A régua do que chega ao campo passa a ser explícita — o fiscal não vê denúncia em triagem — e a fila é dividida pelo ato devido (a vistoriar, aguardando regularização, encerradas). (3) O registro passa a terminar num **desfecho** da lista fechada de seis, com a leitura "regular/irregular" derivada dele, e nasce o **retorno** para a denúncia com prazo de notificação correndo. (4) A amostra traz as denúncias que a Retaguarda semeou para as equipes, com o mesmo protocolo, requerente, endereço, prazo, registro de campo e documento — inclusive a NP 194903 da DEN-0029, que também está no turno do aparelho. (5) A faixa de números reservados no aparelho passa a começar depois dos documentos já semeados do outro lado. | Decisão do dono (03/09/2026): alinhar o protótipo do aplicativo ao vocabulário novo para os dois lados contarem a MESMA história na demonstração, **sem** implementar sincronização — "só vale a pena avançar para as outras etapas quando tivermos requisitos e fluxos mais concretos". |
| 04/09/2026 | José Nascimento | Aplicativo do Fiscal (registro, conclusão, envio) | **O registro passa a ser DESPACHADO, com considerações finais, e não conclui sem o documento que o desfecho manda lavrar.** (1) Vocabulário novo do domínio: *gestor* → **Chefe de Setor** e *administrativo* → **Coordenador** em tudo que o fiscal lê (matrículas não mudam). (2) O botão que fecha a vistoria virou **Despachar registro**; a tela seguinte virou **Conclusão do registro**, com o destino declarado (caixa de entrada do Chefe de Setor da área da equipe) e o botão **Concluir registro** no lugar do antigo "+ Novo registro". (3) Nasceram as **considerações finais**: atalhos de recomendação (lista fechada) e texto livre, que convivem, viajam com o registro nos campos `consideracoes`/`recomendacoes` e aparecem em leitura quando o registro é reaberto. (4) **RN-13**: concluir sem documento só com "Regularizado no local" ou "Nada encontrado no local"; nos outros dois o impedimento é explícito, com motivo, o que fazer e o formulário a um toque. (5) **Voltar** passou a voltar para a tela anterior. (6) A fila de envio passou a dizer o destino, e registro ainda aberto saiu da fila para o bloco "Falta concluir". | Decisão do dono (04/09/2026): o registro de campo é uma entrega a alguém — o Chefe de Setor —, e é pela recomendação do fiscal que esse alguém decide o encaminhamento; o impedimento evita denúncia com desfecho que promete papel inexistente. |
