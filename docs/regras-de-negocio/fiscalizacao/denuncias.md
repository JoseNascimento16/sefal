# Denúncias das ouvidorias (e-Salvador e Fala Salvador)

**Onde fica:** Menu → **Denúncias** (item que expande) → **e-Salvador**
(`/retaguarda/denuncias/e-salvador`) e **Fala Salvador** (`/retaguarda/denuncias/fala-salvador`).
**Quem usa:** administrativo, gestor e administrador. **O fiscal não entra** (ver RN-11).

> ## ⚠️ ESTE MÓDULO É UM PROTÓTIPO
>
> Ele existe para o dono **olhar a forma e aprovar o fluxo** antes de virar tabela, migration e
> contrato de API. **A integração não existe ainda e nada é gravado em banco:** as denúncias de
> partida vêm de [`config/prototipo_denuncias.php`](../../../config/prototipo_denuncias.php) e o que
> a pessoa tria, encaminha, direciona ou devolve vive na **sessão** dela
> ([`App\Support\Prototipo\DenunciasFicticias`](../../../app/Support/Prototipo/DenunciasFicticias.php)).
>
> As telas **dizem isso em cima**, com o selo "PROTÓTIPO · DADOS FICTÍCIOS": protótipo que se
> disfarça de sistema pronto vira decisão tomada por engano — alguém vê a grade cheia, conclui que a
> integração está no ar e planeja em cima disso.
>
> **Origem:** pedido do dono de 02/09/2026, a partir do cenário da reunião com o cliente
> (`docs/cenario-2026-09-02-reuniao-cliente.md`, na branch `ferramental`). Não há HU escrita: as
> linhas em `config/acompanhamento_requisitos.php` nascem `hu_status => 'nao'` declarando essa
> origem.

As ouvidorias da Prefeitura recebem a denúncia do cidadão e a **entregam ao SEFAL**. Este módulo é
onde ela chega, é analisada e vira trabalho dirigido de campo — e o caminho tem **duas etapas com
dois donos**: o administrativo tria, o gestor da área direciona.

---

## Regras vigentes

### RN-01 — A denúncia chega por INTEGRAÇÃO; ninguém a digita aqui

As duas telas **não têm botão de cadastrar**, e isso é a regra, não uma falta. A denúncia é entregue
pelo canal de origem, e cada uma carrega, visível:

- o **número que o canal lhe deu** (`ESL-…` no e-Salvador, `156-…` no Fala Salvador), em coluna
  própria da grade;
- a **hora em que a integração a entregou**, no lugar de uma "data de cadastro";
- uma **primeira linha de trâmite assinada pela integração**, e não por pessoa — é o que prova que o
  dado veio de fora.

> **Isto separa este módulo da [Caixa de Entrada](caixa-de-entrada.md).** Lá o administrativo
> **digita** o papel que chegou ao balcão (e o cadastro manual é requisito: papel não desaparece por
> decreto). Aqui não há papel nem digitação. Ter os dois no mesmo lugar apagaria a distinção que
> decide como o setor trabalha — e é por isso que Denúncias é seção própria do menu, e não um item
> dentro de Fiscalização.

⚠️ **A integração NÃO EXISTE ainda** — nem o contrato dela (**PEND-021** é a irmã disto, para o
SGCI). O que o protótipo mostra é o formato do dado recebido e o que o setor faz com ele.

### RN-02 — Dois canais, e eles não são o mesmo formulário

| | e-Salvador | Fala Salvador (Disque 156) |
|---|---|---|
| Como o cidadão abre | portal na internet, **autenticado** | ligação telefônica |
| Requerente | **sempre identificado** (nome, CPF, e-mail, telefone) | **pode ser anônimo** |
| Relato | escrito pelo próprio cidadão | **transcrição** do que o atendente ouviu |
| Endereço | estruturado (logradouro, número, referência) | como deu para apurar na ligação |
| Anexos | **sim** (foto, documento) | **não** — ninguém anexa foto por telefone |
| Categoria | assunto escolhido pelo cidadão | categoria escolhida pelo **atendente** |

O que cada canal carrega vem do **servidor**
([`config/prototipo_denuncias.php`](../../../config/prototipo_denuncias.php) → `canais`), e a tela
mostra o campo **só onde o canal o entrega**. Um formulário único pediria CPF de denúncia anônima e
ofereceria anexo a quem ligou — e, pior, faria o sistema mentir sobre a qualidade do endereço, que é
justamente o que decide se dá para mandar equipe ao local.

### RN-03 — Denúncia anônima é caso previsto, não cadastro incompleto

No Fala Salvador a pessoa pode não querer se identificar (medo de represália é o motivo mais comum),
e a denúncia vale igual. Ela aparece como **"Anônimo"** — nunca como espaço em branco — e a busca a
alcança pela palavra `anônimas`.

O e-Salvador **não admite** anônima: quem abre está autenticado no portal. O canal declara isso, e a
tela não oferece o que aquele formato não tem.

### RN-04 — Endereço impreciso é informação, e fica marcado

Denúncia por telefone às vezes chega sem número e sem ponto de referência confiável ("a casa do
portão verde, subindo a ladeira"). A grade marca essas com o selo **"sem endereço"**, e o detalhe
diz o que falta.

Não é enfeite: é o dado que decide entre mandar equipe, pedir complemento ao canal ou arquivar por
impossibilidade de localizar (RN-07). Esconder isso faria o gestor deslocar equipe para um endereço
que não existe.

### RN-05 — ETAPA 1, triagem: o administrativo encaminha à ÁREA, derivada do bairro

A denúncia chega como **`Recebida`** e espera a triagem. O administrativo analisa e a encaminha à
**área** de fiscalização correspondente, que sai do **bairro** pela estrutura permanente
Área › Equipe › bloco de bairros (ver [Áreas e Equipes](../estrutura/areas-e-equipes.md)).

**A derivação SUGERE; quem confirma é gente.** A área sugerida vem preenchida e é **editável na
própria linha da grade**, porque um bairro pode pertencer a mais de uma área — Mussurunga, Patamares
e Jardim das Margaridas estão na Área 5 e na Área 6; o Comércio está na Área 1 e na Itinerante — e aí
**as duas respostas estão certas**. Nesses casos a linha ganha o selo **"bairro compartilhado"**, e
escolher uma em silêncio esconderia a decisão de quem tem de tomá-la.

A sugestão é **calculada na leitura**, nunca gravada junto da denúncia: a estrutura de áreas é
editável, e uma sugestão congelada continuaria apontando para a área de antes do ajuste.

**O triador vê PARA QUEM está encaminhando.** Cada opção de área traz o nome do **gestor** que vai
receber ("Área 5 — Lourdes Figueiredo Sales"), e o resumo do lote, antes de confirmar, lista área
**e** gestor. "Encaminhei para a Área 5" diz metade; a outra metade é a pessoa que passa a responder
por aquilo. Área **sem** gestor registrado gera aviso — a denúncia chegaria lá e ninguém seria
avisado.

### RN-05b — O gestor é gestor de UMA ÁREA, e só vê o que é dela

> "Pra ele só interessa o que for direcionado para a área dele" — decisão do dono, 02/09/2026.

O gestor tem **área vinculada** (ver [Áreas e Equipes](../estrutura/areas-e-equipes.md), RN-01b), e o
recorte vale nas **duas** pontas:

1. **a listagem traz só as denúncias da área dele** — as das outras áreas e as que ainda esperam a
   triagem (que ainda não têm área) não aparecem. O recorte é feito **no servidor**: filtro de front
   esconde, não protege, e a lista inteira teria viajado até o navegador de quem não deve vê-la;
2. **a ação sobre denúncia de outra área é RECUSADA**, com o motivo escrito e sem alterar nada. As
   duas coisas, e não uma: esconder da lista sem barrar a ação deixaria a fronteira valendo apenas
   para quem não sabe montar a requisição — e o **lote** é justamente o caminho fácil para isso,
   porque manda uma lista de identificadores.

A tela **explica o recorte** em vez de deixar a lista curta sem motivo ("Você está vendo só o que foi
encaminhado a Área 5 — Boca do Rio"), e o selo da etapa passa a nomear a área. Sem isso o gestor
contaria as denúncias, acharia o número baixo e concluiria que o canal está parado.

**Gestor sem área vinculada** é recusado dizendo isso, e a tela avisa na cara: ele exerce a etapa e
não tem de onde. Deixar passar daria a ele o sistema inteiro; lista vazia sem explicação pareceria
sistema quebrado.

**Quem NÃO é recortado:** o **administrador** (é o dono do sistema) e o **administrativo** — quem
tria precisa ver o universo, porque não se tria o que não se vê, e quem encaminhou precisa saber o
que aconteceu depois. Um gestor que também seja administrativo não é recortado: o papel que amplia
ganha, a mesma regra da união de setores na matriz de permissões.

### RN-06 — ETAPA 2, direcionamento: o gestor da área escolhe COMO o trabalho acontece

A denúncia **`Encaminhada à área`** espera o gestor daquela área, que tem **duas saídas**:

1. **direcionar à equipe** — vira vistoria dirigida e aparece no aplicativo dos fiscais da equipe;
2. **incluir numa operação** já planejada para a região, em vez de gerar uma ida isolada ao local. A
   equipe passa a ser a da operação. O gestor pode **abrir uma operação nova** dali mesmo, quando
   ainda não existe trabalho planejado para aquele lugar.

As duas são alternativas, não camadas: direcionar avulso desfaz o vínculo com operação, e anexar a
uma operação desfaz o direcionamento avulso — senão a tela mostraria dois responsáveis pelo mesmo
trabalho.

### RN-07 — Devolver ou arquivar é ato administrativo: motivo de lista MAIS justificativa escrita

A triagem também retira a denúncia do fluxo, e é assim que a **improcedente ou duplicada não chega
ao gestor**. Exige as três coisas:

- **motivo** de lista fechada (para o relatório poder somar por motivo);
- **justificativa por escrito**, com **mínimo de 15 caracteres**;
- **destino**: devolvida ao canal de origem, ou arquivada.

A validação está **no servidor**, não só no formulário: esconder o campo na tela não impede ninguém
de mandar a requisição sem ele. E a recusa diz **o porquê** do mínimo — "não procede" não conta o
caso a quem ler depois, nem ao cidadão que cobrar o canal.

A denúncia recusada **sai da área e da equipe**: ela não fica pendurada numa fila que ninguém mais
vai olhar.

### RN-08 — Trocar a equipe da área exige justificativa

O caminho normal é a equipe da própria área. Mandar para outra é decisão consciente — a **Noturna**,
por exemplo, quando o flagrante só é possível depois do fechamento — e aí a **justificativa passa a
ser obrigatória**.

A conferência é feita contra a área **gravada** na denúncia e a estrutura vigente, e não contra o que
a tela mandou: senão bastaria omitir a área no corpo da requisição para a exigência desaparecer.

### RN-09 — Os estados, e o trâmite de cada mudança

```
Recebida ─► Encaminhada à área ─► Direcionada à equipe ─► Em campo ─► Concluída
                              └─► Em operação ──────────┘
   └────────► Devolvida | Arquivada   (saídas da triagem, com justificativa)
```

`Em campo` e `Concluída` são o que vem **depois**, quando o aplicativo do fiscal estiver ligado a
estas telas; o protótipo mostra denúncias nesses estados para a vida inteira do registro ficar
visível, e escreve o próximo passo no trâmite como **próximo passo**, não como fato.

**Toda mudança acrescenta uma linha ao trâmite** — quem, quando, o quê e por quê. Sem o rastro,
"arquivada" é uma palavra sem autor.

### RN-10 — As duas etapas operam em LOTE e uma a uma, pelo mesmo caminho

A integração entrega várias denúncias de uma vez, então o lote é o caso **normal** do
administrativo, não a exceção:

- caixas de seleção na grade, com "selecionar todas" alcançando **o recorte filtrado**, não só a
  página;
- **"Encaminhar selecionadas"** mostra o resumo de **quantas vão para cada área** antes de confirmar,
  e recusa o lote se alguma ficou sem área escolhida — dizendo quantas são;
- as mesmas decisões existem no **detalhe** de uma denúncia, com um alvo só.

O botão do lote e o do detalhe chamam o **mesmo** endereço: dois caminhos seriam a mesma regra duas
vezes, e um dia só um deles ganharia a validação nova.

### RN-11 — Uma permissão para o módulo, num item de menu que EXPANDE; o fiscal não entra

As duas telas declaram o **mesmo slug** (`denuncias`) porque moram sob o mesmo primeiro trecho do
caminho (`/retaguarda/denuncias/…`), que é de onde as guardas deduzem a tela. A permissão é **uma**,
para o conjunto, e aparece no Modo Gerente com o nome da seção: quem cuida de denúncia cuida das duas
origens, e separar a permissão do e-Salvador da do Fala Salvador seria uma decisão que ninguém precisa
tomar.

No menu elas são os **filhos de um item "Denúncias" que expande** (decisão do dono, 02/09/2026,
depois de vê-las soltas no mesmo nível dos demais itens). A **pasta não decide acesso**: ela não
declara rota, slug nem setor — quem tem tela e permissão são os filhos, e ela aparece quando sobra ao
menos um filho visível. Se ela também declarasse setor, a mesma decisão teria dois donos.

**Concessão inicial:** administrativo, gestor e administrador — os papéis do fluxo. **O fiscal não
entra:** dar a ele estas telas permitiria escolher o próprio trabalho e arquivar o que não quisesse
atender. A denúncia chega a ele pelo aplicativo, **já dirigida**.

### RN-12 — A etapa de quem entrou vem do SETOR, e a tela diz qual é a sua

| Setor | Etapa |
|---|---|
| `administrativo` | **triagem** — o setor de retaguarda que recebe o que chega de fora (setor criado em 02/09/2026 por decisão do dono: não é o administrador do sistema) |
| `gestor` | **direcionamento**, restrito à área dele (RN-05b) |
| administrador do sistema | **as duas** — é ele que demonstra o fluxo inteiro e que cobre a ausência do outro |

A tela mostra um **selo "Sua etapa: …"** no cabeçalho — que para o gestor nomeia a **área**
("Sua etapa: direcionamento · Área 5 — Boca do Rio") — e oferece **só as abas da sua etapa**:
"A triar" para quem tria, "A direcionar" para quem direciona, "Todas" para acompanhar. Quem exerce as
duas vê as duas, na ordem do fluxo. O número **"a triar"** também só existe para quem tria: para o
gestor ele apareceria em zero, e zero ali leria como "não há nada a triar", que é falso.

A conferência acontece **no servidor**, e não só na tela: quem pedir a ação da etapa que não é a sua
é recusado **com o motivo escrito** (nunca em silêncio, nunca com tela de erro seca) e nada é
alterado. Esconder o botão é conforto; a fronteira é a recusa.

**O setor `administrativo` também é o dono da [Caixa de Entrada](caixa-de-entrada.md)** — registrar o
que chega em papel é a mesma função. Nada além dessas duas telas lhe foi concedido: cadastro,
operação, mapa e relatório são de gestão, e alargar a concessão é ato do gestor no Modo Gerente, não
decisão embutida na semente.

### RN-13 — A busca é o filtro único; a aba é que troca a fonte

Campo único e amplo, acento-insensível, com exemplos clicáveis. Ele interpreta a frase em **facetas**
do domínio — situação, área, equipe, `anônimas`, `sem endereço`, `com anexo`, `prazo vencido`,
`recebidas hoje` — mais os termos livres, que casam contra protocolo, número de origem, requerente,
contato, assunto, categoria, relato, endereço, bairro, área, equipe, operação, situação e motivo.

**Não há chip nem botão de filtro paralelo.** Os números do cabeçalho são o resumo da **mesma** lista
que a grade desenha e, clicados, trocam a aba ou escrevem a faceta na busca: atalho sem criar um
segundo dono para "o que está filtrado".

As facetas de área, equipe e situação nascem dos **catálogos que o servidor mandou** — os mesmos que
a validação aceita. Escritas na tela, um dia a busca reconheceria uma área que já não existe e
deixaria de reconhecer a que nasceu.

**A aba, sim, troca a FONTE** dos dados (cada uma é uma etapa do fluxo), e por isso ela entra no
contexto impresso da exportação — e a seleção é limpa ao trocar de aba, porque as linhas de uma não
existem na outra.

### RN-14 — Exportação do recorte visível

As duas telas têm exportação (PDF/XLSX/DOCX) pelo ponto único do sistema, entregando **o que o
filtro, a busca e a aba deixaram na tela** — nunca o universo, nunca só a página. Datas saem em
**dd/mm/aaaa** (com hora no recebimento): o documento é lido fora do sistema, onde ninguém traduz
ISO.

### RN-15 — Reiniciar a demonstração

Existe um botão **"Reiniciar demonstração"**, que aparece só depois de a sessão decidir algo. Ele
devolve as denúncias e as operações ao estado de partida — quem demonstra precisa poder recomeçar a
cena com os dois papéis.

**No sistema real esta ação não existe:** denúncia recebida não se desfaz.

---

## Como demonstrar (protótipo)

| Usuário | Senha | O que vê |
|---|---|---|
| `admin` | `prototipo123` | as **duas** etapas e **todas** as áreas — o fluxo inteiro |
| `administrativo1` | `adm123` | só a **triagem**, sobre o universo (Célia Andrade Portela) |
| `gestor1` | `gestor123` | só o **direcionamento**, e só da **Área 5 — Boca do Rio** (Lourdes Figueiredo Sales) |
| `gestor2` | `gestor123` | idem, **Área 1 — Centro** (Marta Nogueira Prado) |
| `gestor3` | `gestor123` | idem, **Área 3 — Brotas** (Verônica Lins Barreto) |
| `fiscal1` | `fiscal123` | **não entra** — é levado à tela inicial com o motivo na tela |

Roteiro: entre como `administrativo1`, selecione algumas denúncias em "A triar", confira as áreas
sugeridas na linha (procure uma com o selo *bairro compartilhado*) e repare no **nome do gestor** em
cada opção; clique em **Encaminhar selecionadas** e confira o resumo por área e gestor. Devolva outra
com justificativa — note que ele **não** tem a aba "A direcionar".

Depois entre como `gestor1`: a lista é só da Área 5, o selo nomeia a área e o aviso explica o
recorte. Mande um lote à equipe (tente uma equipe de fora da área para ver a justificativa passar a
ser obrigatória) e outro para a **Operação Verão — Orla**. Entre como `gestor2` para provar o
recorte: a lista é outra, e o que era do gestor1 não aparece.

---

## Pendências que isto abre

- **Contrato das integrações** do e-Salvador e do Fala Salvador (endpoints, autenticação, campos,
  como o canal informa complemento de endereço depois). Irmã da **PEND-021** (SGCI).
- **Prazo real de atendimento** de cada canal — o protótipo usa 10 dias para os dois.
- **Canal de devolução**: por onde o e-Salvador e o 156 aceitam o retorno com a justificativa
  (**PEND-025**).
- **Modelagem definitiva do vínculo gestor ↔ área.** A regra está decidida (RN-05b: o gestor é de uma
  área), mas no protótipo o vínculo mora em `config/prototipo_estrutura.php` e liga pela matrícula.
  Em produção ele é **tabela usuário↔área**: uma pessoa pode responder por mais de uma, gestor entra
  e sai, e a troca é fato datado. O código já trata **lista** de áreas por gestor, para a modelagem
  real não obrigar a reescrever quem lê. Falta também definir se existe **gestor geral** (que vê
  todas) e quem cobre a área cujo gestor está ausente.
- **Numeração definitiva** do protocolo interno: no protótipo é `DEN-NNNN` calculado; no sistema sai
  de `App\Support\Protocolo::proximo()`, a fonte única de numeração.
- **Ligação com a fiscalização de campo**: hoje `Em campo` e `Concluída` são estados semeados. Quando
  o aplicativo do fiscal receber a denúncia dirigida, o desfecho volta para o trâmite.
- **Derivação bairro → área com bairro compartilhado** e os casos Itinerante (corredor) e Noturna
  (turno) — a mesma **PEND-022** da Caixa de Entrada.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 02/09/2026 | José Nascimento | Denúncias (e-Salvador e Fala Salvador) | **Retorno do dono, três mudanças.** (1) As duas telas passam a ser **filhas de um item de menu "Denúncias" que expande**, e não itens soltos — estrutura genérica de pasta no config, com as três formas da casca resolvidas (RN-11). (2) O **gestor é de uma área**: vínculo gestor↔área na estrutura, listagem recortada pela área dele, ação sobre denúncia de outra área recusada no servidor, selo da etapa nomeando a área e o triador passando a ver o **nome do gestor** que vai receber (RN-05b e RN-05). (3) Nasce o setor **`administrativo`**, dono da triagem e também da Caixa de Entrada — a triagem deixa de ser do setor `administrador` (RN-12). | Respostas do dono às perguntas estruturais que o protótipo abriu: "pra ele só interessa o que for direcionado para a área dele" e "não é o admin do sistema, mas o admin pode fazer também". O submenu veio do print do dono, que mostrava os dois canais no mesmo nível dos demais itens do menu. |
| 02/09/2026 | José Nascimento | Denúncias (e-Salvador e Fala Salvador) | Nasce o módulo, como **protótipo**: duas telas de canal com a mesma mecânica, denúncias semeadas como se tivessem chegado por integração (com carimbo de recebimento e número de origem), fluxo de duas etapas com dois papéis — triagem encaminhando à área derivada do bairro e gestor direcionando à equipe ou a uma operação —, decisão em lote e individual, devolução/arquivamento com justificativa, trâmite por ato, busca inteligente e exportação. | Pedido do dono de 02/09/2026, a partir do cenário da reunião com o cliente: as ouvidorias da Prefeitura passarão a entregar denúncia ao SEFAL por API, e o setor precisa de onde triar, encaminhar à área e direcionar o trabalho — fluxo NOVO e paralelo ao da Caixa de Entrada, que continua sendo o que chega em papel. Entregue como protótipo para o dono aprovar a forma antes de virar tabela, migration e contrato de integração. |
