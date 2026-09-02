# Parametrização — as listas que o sistema oferece para escolher

**Onde fica:** Menu → Parametrização → *(Tipos de Infração · Atividades do Ambulante · Unidades de
Medida · Tipos de Operação · Origens de Operação · Motivos de Recusa)*
(`/retaguarda/parametrizacao/…`).
**Quem usa:** administrador e gestor. O fiscal **consome** estas listas em rua, pelo aplicativo — não
as edita.

São seis telas do mesmo feitio, mais um conjunto de **parâmetros numéricos** que o sistema lê por
código. Sozinha, nenhuma delas é interessante; juntas, elas são o vocabulário com que a fiscalização
descreve o que encontrou. Lista vazia ou desatualizada trava o trabalho em rua **em silêncio**: o
fiscal abre o formulário e não tem o que escolher.

| Lista | Para que serve | Campo próprio |
|---|---|---|
| Tipos de Infração | O que o fiscal enquadra ao autuar | Descrição (apoio à escolha, opcional) |
| Atividades do Ambulante | O ramo autorizado na permissão | — |
| Unidades de Medida | Como se conta a mercadoria em apreensão/vistoria | Sigla (obrigatória) |
| Tipos de Operação | O feitio do trabalho em campo | — |
| Origens de Operação | De onde veio a ordem de fiscalizar | — |
| Motivos de Recusa | Por que o gestor devolveu um cadastro feito em campo | — |

---

## Regras vigentes

### RN-01 — Aposentar um valor é **inativar**, não excluir

Valor de lista aparece em registro histórico (uma fiscalização de dois anos atrás aponta para um tipo
de infração). Por isso o caminho normal de tirar um valor de circulação é **desmarcar "Ativo"**: ele
some do que se pode escolher hoje e continua legível no que já foi gravado. Quem responde "o que pode
ser escolhido agora?" é o `ativos()` do model — e é ele que as telas que oferecem a lista consultam.

A **exclusão** existe para o outro caso: o valor cadastrado errado, que ninguém chegou a usar. A tela
diz isso na hora de confirmar, em vez de deixar a pessoa descobrir depois.

### RN-02 — Nome é obrigatório e não se repete na mesma lista

A conferência ignora **caixa, acento e espaços das pontas**: "Feira livre", "feira livre",
"  FEIRA LIVRE " e "Área não autorizada" × "área nao autorizada" são o mesmo valor para quem escolhe.
Duas linhas fariam o valor aparecer duas vezes no formulário do fiscal, e os registros históricos se
dividiriam entre as duas sem ninguém perceber. A busca da tela já é acento-insensível: a gravação
segue a mesma régua.

A comparação é feita em **PHP** (`App\Support\Texto::chave`), nunca em SQL. `LOWER()` do SQLite
rebaixa só ASCII e o do Oracle depende do idioma da instância: a mesma conferência responderia coisas
diferentes em desenvolvimento e em produção. O índice único da coluna continua sendo **rede de
segurança**, não validação — quando ele é quem recusa, o que a pessoa vê é uma tela em silêncio.

Alterar um registro **não esbarra no próprio nome** — senão salvar só para inativar seria recusado.

O nome vai gravado **sem os espaços das pontas**: espaço na ponta não é conteúdo, é o que faz "Feira "
conviver com "Feira".

### RN-02-b — Uma palavra só para a situação: **Ativo**

O atributo tem um nome, e é o mesmo em toda parte: o campo do formulário se chama **Ativo**, a coluna
da grade se chama **Situação** e mostra **Ativo/Inativo**, e a busca reconhece **"ativos"/"inativos"**.
Três palavras para o mesmo atributo ("Em uso" no formulário, "Ativo" na grade, "ativos" na busca)
fazem parecer três coisas diferentes — e quem procura de volta procura pela palavra que leu.

### RN-03 — O campo próprio de cada lista é declarado pelo servidor

A descrição do tipo de infração e a sigla da unidade de medida vêm de **uma** declaração, no
servidor: é ela que monta a validação **e** o que a tela desenha. Se o formulário declarasse os seus
campos por conta própria, um dia a tela pediria algo que o servidor não valida — ou o contrário.

A sigla é **obrigatória**: é ela que sai no documento impresso em rua, onde não cabe o nome por
extenso.

### RN-04 — Uma permissão para as seis, com o nome da seção

As seis telas moram sob o mesmo primeiro trecho do caminho (`/retaguarda/parametrizacao/…`), que é de
onde o controle de acesso deduz a tela: a permissão é **uma só**, para o conjunto, e aparece no Modo
Gerente como **"Parametrização"**. Separar a permissão de "motivos de recusa" da de "tipos de
operação" seria uma decisão que ninguém precisa tomar, e seis linhas a mais na matriz para todo mundo
ler.

Concedida na semente a **administrador** e **gestor**; daí em diante quem manda é a matriz.

### RN-05 — As listas nascem preenchidas

O sistema já sobe com valores realistas de fiscalização de ambulantes (5 tipos de infração, 5
atividades, 5 unidades, 4 tipos de operação, 5 origens, 4 motivos de recusa). Não é dado de
demonstração: é o mínimo para a operação começar.

A semeadura é **idempotente e não destrutiva** — rodar de novo cria só o que falta e nunca desfaz o
que o gestor ajustou (inativação, descrição reescrita, sigla corrigida).

### RN-06 — Busca inteligente e exportação, como em qualquer listagem

Campo único que entende a situação em palavras (`ativos`, `inativos`, "em uso", "fora de uso") e casa
o resto do texto, sem acento, contra o nome e os campos próprios da lista. A exportação (PDF / Excel /
Word) sai pelo ponto único do projeto e entrega o **recorte visível**, com a situação já em palavras.

### RN-07 — Os parâmetros da fiscalização são lidos por código, e não têm tela **nesta entrega**

`prazo_notificacao_dias = 10` (dias corridos para o ambulante atender a uma notificação) já
existe e é lido por `ParametroFiscalizacao::inteiro()`, sempre com um valor de referência em mão —
parâmetro ausente não pode derrubar um fluxo de rua.

A tela de edição fica para quando a **cadeia de fiscalização** existir (`PEND-011`): botão que muda um
número sem fluxo que o leia não muda nada, e ninguém saberia dizer o efeito de alterá-lo.

### RN-08 — **Atividade em uso não pode ser excluída** (a primeira lista com vínculo)

O cadastro de ambulante guarda a **atividade autorizada**. Excluir uma atividade apontada por
algum cadastro deixaria esses registros apontando para o nada — e quem responderia seria a chave
estrangeira do banco, com um erro cru de integridade que, para quem está na tela, é o sistema
quebrando sem motivo.

Então a recusa acontece **antes**, na tela de onde a pessoa clicou, dizendo **quantos** ambulantes
dependem da atividade e mandando fazer o que a RN-01 já ensina: **desmarcar "Em uso"**. Atividade que
ninguém aponta continua excluível — a guarda não pode virar "nunca mais se exclui atividade".

---

## Fora de escopo (por ora)

- **Bloqueio de exclusão por vínculo nas outras cinco listas.** Só **Atividades do Ambulante** é
  apontada por registro de operação hoje (pelo cadastro de ambulante), e ela já barra — ver a
  RN-08 acima. As outras cinco ganham a mesma recusa quando a cadeia de fiscalização existir; até lá,
  seria código morto tratando de um caso impossível.
- **Verificação no Monitoramento de Parametrizações.** "Lista de escolha vazia" só quebra um fluxo
  quando existe fluxo que a consome; a verificação nasce junto com ele, para poder dizer o que
  exatamente parou.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/08/2026 | José Nascimento | Parametrização (6 telas) | Criação das seis listas de escolha com cadastro, alteração, inativação e exclusão; semeadura inicial com valores de fiscalização de ambulantes; tabela de parâmetros da fiscalização (`prazo_notificacao_dias`) lida por código, sem tela. | O vocabulário da fiscalização precisa existir antes dos fluxos que o usam — e precisa ser mantido pelo gestor, não por release. |
| 25/08/2026 | José Nascimento | Parametrização → Atividades do Ambulante | Exclusão passa a ser recusada quando algum permissionário aponta a atividade (RN-08), dizendo quantos são e mandando inativar. | O cadastro de permissionário criou o primeiro vínculo: sem a recusa em tela, quem respondia era a chave estrangeira do banco. |
| 26/08/2026 | José Nascimento | Parametrização (6 telas) | A conferência de nome repetido passa a ignorar **acento** e a rodar em PHP, fora do SQL (RN-02); o campo de situação passa a se chamar **Ativo** em todas as superfícies (RN-02-b); o texto de exclusão deixa de concordar no masculino fixo. | Nome repetido com acento passava pela validação (o `LOWER` do SQLite só rebaixa ASCII) e morria no índice único: **erro 500 com a tela em silêncio**, sem dizer nada a quem estava salvando. E gêmeos visuais na lista do fiscal partem o relatório ao meio. As três palavras para "situação" e o "ele/usado" fixo saíam errados nas listas de nome feminino. |
