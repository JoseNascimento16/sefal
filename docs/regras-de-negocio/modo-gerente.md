# Modo Gerente — controle de acesso por setor

**Onde fica:** Menu → Sistema → Modo Gerente — abre como **painel sobre a tela
atual** (ver RN-13), alimentado por `/retaguarda/modo-gerente`.
**Quem usa:** administrador.

O Modo Gerente é onde se decide **quem entra em qual tela**. A unidade de decisão
é o **setor** (perfil de acesso), não a pessoa: quem trabalha muda de função, e
manter permissão por pessoa faria cada admissão virar uma rodada de conferência.

---

## Regras vigentes

### RN-01 — A permissão é `setor × tela × ação`

Cada tela controlável tem, para cada setor, cinco marcas:

| Ação | O que concede |
|---|---|
| **Vê** (`visivel`) | A tela aparece no menu e abre. |
| **Opera** (`habilitado`) | Pode usar as ações da tela e alterar o que já existe. |
| **Só consulta** (`apenas_leitura`) | Abre para olhar: sem operar, sem incluir e sem excluir. |
| **Inclui** (`incluir`) | Pode criar registro novo. |
| **Exclui** (`excluir`) | Pode excluir registro. |

A lógica é **positiva**: a marca presente concede; a ausência nega. Não existe
regra de "negar explicitamente" — negar é não conceder.

### RN-02 — "Vê" é pré-requisito; "Só consulta" derruba TUDO que grava

Ao gravar, a linha é normalizada: sem **Vê**, todas as demais caem; com **Só
consulta**, caem **Opera, Inclui e Exclui** — as três. Assim não existe linha que
diga, ao mesmo tempo, que o setor só olha e que ele pode alterar.

*Opera* está incluído de propósito: enquanto ele sobrevivia ao lado de *Só
consulta*, o setor "só consulta" ainda gravava por `PUT`/`PATCH` — a coluna
prometia "abre para olhar" e o servidor deixava alterar.

**A tela reflete a mesma regra, nos DOIS sentidos.** Marcar *Só consulta*
desmarca e indisponibiliza as três na hora; desmarcar *Vê* desmarca e
indisponibiliza as quatro. Sem a segunda metade, quem tirava o *Vê* continuava
vendo *Inclui* e *Exclui* marcados, salvava, e saía convencido de ter concedido
as duas ao setor — quando o que foi gravado nega tudo. Estado enganoso é pior que
recusa: a recusa a pessoa vê.

### RN-03 — Quem tem vários setores soma o que cada um concede

A permissão efetiva é a **união (OR)** das permissões dos setores da pessoa.
Fosse interseção, acumular setores *tiraria* acesso — o contrário do que
acumular papéis significa para quem trabalha.

Caso especial: se algum setor concedeu *Inclui* ou *Exclui*, o **Só consulta**
dos outros deixa de valer para essa pessoa — a união já lhe deu poder de gravar.

### RN-04 — O administrador é desvio, não concessão

Quem é administrador (marca na conta **ou** vínculo com o setor
`administrador`) pode tudo, sem depender de linha nenhuma. A matriz mostra o
administrador marcado e **travado**, e o servidor ignora qualquer tentativa de
gravar linha para ele.

**Por quê:** linha de tabela alguém desmarca por engano — e o primeiro efeito de
desmarcar a do administrador seria ninguém mais conseguir abrir esta tela para
remarcar.

### RN-05 — O menu e as guardas leem a MESMA regra

O que aparece no menu é exatamente o que a pessoa pode abrir. Esconder o item é
conforto, nunca fronteira: quem digita o endereço não passa pelo menu, então a
conferência acontece no servidor — na leitura (`GET /retaguarda/{tela}/...`,
inclusive sub-rotas) e nas mutações.

### RN-06 — Ninguém é barrado em silêncio

- **Leitura negada** → a pessoa vai para a **tela inicial** com o recado
  "Você não tem acesso a essa tela." Nunca uma tela de erro seca.
- **Mutação negada** → volta para a tela anterior com "Você não tem permissão
  para esta ação.", preservando o que estava preenchido.

### RN-07 — Telas fora do controle, por decisão

Duas coisas **nunca** dependem da matriz:

1. **A tela inicial.** É o destino da própria negativa de acesso — controlá-la
   fecharia um loop de redirecionamento, e o navegador morreria sem explicar
   nada.
2. **A área da própria conta** (dados e senha). Não é decisão de gestor:
   colocá-la na matriz permitiria trancar alguém fora da própria conta e deixá-lo
   sem como recuperar a senha.

### RN-08 — Tela nova nasce controlada

A tela a que uma mutação pertence é deduzida do **caminho**
(`POST /retaguarda/vistorias` pertence à tela `vistorias`), e a ação, da
convenção de nomes de rota (`.store` inclui, `DELETE`/`.destroy` exclui, o resto
opera). Rota nova, portanto, já chega protegida — sem ninguém declarar nada.

Quem foge da convenção é declarado em `config/permissao_acoes.php`, **com o
motivo escrito ao lado**; a cobertura no gate reprova mutação que não seja
derivável nem declarada.

E porque a tela é deduzida do caminho, **mutação fora do prefixo `/retaguarda`
não pertence a tela nenhuma** — nasceria fora do alcance do controle. O gate
mantém uma lista fechada do que legitimamente fica fora (autenticação, o
redirecionamento da raiz, a rota de disco local do framework) e reprova qualquer
rota de escrita nova fora dela — inclusive entrada morta na própria lista, que
seria a brecha seguinte.

### RN-09 — A concessão inicial vem do menu; depois, quem manda é a matriz

`config/retaguarda_menu.php` declara, por tela, os setores que a usam. Essa
lista é a **semente** da matriz, aplicada uma vez pelo seeder (idempotente e não
destrutivo: rodar de novo cria só o que falta e nunca desfaz decisão de
gerente). Depois disso, mudar a lista não muda nada — quem concede e quem tira é
esta tela.

O normal é o **pacote inteiro** (vê, opera, inclui e exclui): é o que "este setor
usa esta tela" quer dizer. A declaração aceita **ajuste por setor** para o caso em
que não é isso — `'fiscal' => ['incluir' => false, 'excluir' => false]`. Foi
preciso justamente no cadastro de ambulante: o fiscal precisa **consultar**
quem está cadastrado (chegar na calçada sem saber é trabalhar às cegas) e não
cria nem apaga por lá — ele cadastra em rua, pelo aplicativo, e o que nasce em
rua entra em quarentena para o gestor conferir. Cadastro criado de mesa por ele
passaria ao largo dessa conferência; excluir cadastro é ato de gestão.

### RN-10 — Toda alteração de permissão deixa rastro do que MUDOU

Quem salvou, quando, em qual tela — e **o que mudou, por setor**:

```
Tela "Vistorias": fiscal: +visivel, +habilitado | gestor: -excluir
```

O **nome** de quem alterou vai gravado no registro, não só a chave da conta (que
pode ser renomeada ou desligada). Setor sem mudança é **omitido**, e gravação que
não alterou nada diz isso ("nada mudou"): rastro que repete o estado inteiro a
cada salvamento some no próprio volume, e a pergunta que se faz depois de um
incidente é "quem abriu qual porta, para quem?".

Quando o detalhe não cabe no registro, o corte é por **setor inteiro** e o que
sobrou é contado (`(+3 setores)`) — meia mudança gravada seria pior que uma
contagem honesta.

A seção **"Últimas alterações"** mostra esse texto numa coluna própria ("O que
mudou"), e não só *quando · quem · tela*: sem ela o registro respondia "alguém
mexeu nesta tela", que é quase nada — e era exatamente a pergunta que a seção
existe para responder que ficava sem resposta. A seção aparece **também vazia**,
com o recado de que ainda não houve alteração: numa instalação nova, sem ela
ninguém sabe que existe trilha de auditoria.

### RN-10-b — Alteração não salva é dita em voz alta

Cada tela tem o seu botão (um ajuste num canto não regrava a casa toda), e por
isso a tela precisa dizer **onde** há pendência: a seção alterada ganha o selo
*"alteração não salva"*, o botão fica indisponível quando não há nada a gravar, e
sair da tela com pendência **pergunta antes**. Sem isso, quem mexia em três
seções e salvava uma perdia as outras duas sem nunca saber. (No painel, "sair" é
**fechar**: a pergunta aparece igual, e também quando a pessoa navega para outra
tela com o painel aberto.)

### RN-11 — O bloqueio tem três estágios

`PERMISSAO_ENFORCE` ∈ `off` | `log` | `block`:

- `off` — não confere nada;
- `log` — confere e **registra** quem seria barrado, sem barrar;
- `block` — barra de verdade.

O padrão é **`block`** desde o fechamento da Fase 1. Ele nasceu `log`: ligar o
bloqueio junto com o nascimento do catálogo seria estrear a fechadura antes de
saber quantas portas a casa tem — cada tela das entregas seguintes entra na
matriz, e um esquecimento de concessão viraria gente sem acesso ao próprio
trabalho. O modo de observação registrou o que seria barrado, a matriz foi
conferida contra o catálogo real e a chave virou. A tela avisa, em voz alta,
quando o modo não é `block` — senão quem configura acha que tirou um acesso e não
tirou.

**O que isso exige de toda tela nova daqui em diante:** ela entra no catálogo
(declarando `slug` no menu) **e** nasce concedida a quem trabalha nela (a lista
`setores` do item, que o seeder da matriz aplica). Tela controlável sem concessão
não é tela "fechada por precaução": é tela que só o administrador abre — e o furo
passa batido justamente porque em desenvolvimento se testa logado como
administrador. `off` e `log` continuam existindo para diagnóstico pontual em
ambiente controlado; não são caminho de produção, e o gate reprova quem devolver
um deles ao padrão do código ou ao `.env.example`.

Em `log`, cada leitura e cada mutação que **seria** barrada gera um registro no
log da aplicação com tela, ação, rota, caminho e usuário. É esse registro que se
confere antes de virar a chave — quem passa legitimamente não gera nada, para o
rastro não afogar no próprio volume.

**O menu obedece o modo.** Fora do `block`, o item **continua aparecendo** para
quem ainda não tem a concessão. Sumir do menu seria a barrada em silêncio que o
rollout existe justamente para evitar: o item desapareceria sem recado, sem
registro, e a tela abriria pelo endereço de todo jeito. No `block`, o item some —
e aí a régua é a mesma do guarda, então menu e acesso nunca discordam.

### RN-12 — A tela que distribui acesso não espera o rollout

A própria tela do Modo Gerente é barrada **de verdade em qualquer modo**
(`retaguarda.permissao_sempre`). O rollout gradual existe para não *tirar* acesso
de ninguém por engano; aplicado à tela que *concede* acesso, ele daria acesso a
todos — qualquer pessoa autenticada poderia se conceder o resto.

A régua continua sendo a mesma (a matriz). O que não vale para ela é o adiamento
do bloqueio.

### RN-13 — O Modo Gerente é PAINEL sobre a tela atual, não página própria

O item do menu Sistema **não navega**: ele abre a matriz numa camada por cima do
que a pessoa está vendo, e fechá-la devolve a tela exatamente como estava. Quem
distribui acesso quase sempre está no meio de uma conferência ("por que esta
pessoa não vê isto?"), e trocar de página fazia perder o lugar — e a resposta.

Consequências, todas com a régua no mesmo servidor:

- a matriz é buscada pela **mesma rota** que a guarda de leitura protege, então
  quem não tem a permissão não recebe a matriz. Pedida em JSON, a negativa vem
  **com o motivo escrito** — seguir um redirecionamento devolveria a tela inicial
  em HTML, o painel não saberia ler aquilo e mostraria "falha ao carregar", que é
  a barrada em silêncio que a RN-06 proíbe;
- **gravar não fecha o painel** e não troca a tela de trás: a resposta volta para
  onde a pessoa estava, e a matriz é **relida do servidor** (que normaliza a
  linha ao gravar — mostrar o rascunho como salvo exibiria um estado que o banco
  recusou);
- **fechar com alteração pendente pergunta antes**, e sair do sistema com
  pendência também (RN-10-b);
- quem chega pelo **endereço antigo** (favorito, endereço digitado) é levado à
  tela inicial **com o painel abrindo lá** — nunca a uma página vazia ou a um
  "não encontrado".

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/08/2026 | José Nascimento | Modo Gerente | Criação do controle de acesso por setor: matriz `setor × tela × ação`, guarda de leitura, guarda de ação derivada do caminho, semeadura a partir do menu, rastro de alterações e a tela de administração. Enforcement em `log`, com a própria tela do Modo Gerente barrada em qualquer modo (RN-12). | A Retaguarda conferia apenas login: quem soubesse o endereço abria qualquer tela. |
| 25/08/2026 | José Nascimento | Modo Gerente | O menu passa a obedecer o modo de rollout: fora do `block`, o item continua à vista (RN-11). | Sumir do menu em modo de observação era barrada em silêncio — sem recado, sem registro, e com a URL funcionando. |
| 25/08/2026 | José Nascimento | Modo Gerente | "Só consulta" passa a derrubar também "Opera", na gravação e na tela (RN-02). | O setor "só consulta" ainda gravava por `PUT`/`PATCH`: a coluna prometia uma coisa e o servidor fazia outra. |
| 25/08/2026 | José Nascimento | Modo Gerente | O rastro passa a registrar o delta por setor, omitindo quem não mudou (RN-10). | "Permissões alteradas" não respondia "quem abriu qual porta, para quem?". |
| 26/08/2026 | José Nascimento | Modo Gerente | O padrão do enforcement passa de `log` para **`block`**: as guardas barram de verdade, e o `.env.example` acompanha (RN-11). | O rollout em observação cumpriu o que existia para cumprir — o catálogo da Fase 1 fechou e a matriz foi conferida contra ele. Manter `log` deixaria o controle de acesso ligado no papel e desligado na prática, o pior modo de falhar: nada quebra, nada aparece em tela. |
| 26/08/2026 | José Nascimento | Modo Gerente | A tela passa a derrubar Opera/Só consulta/Inclui/Exclui quando "Vê" é desmarcado (RN-02); o rastro passa a **exibir** o que mudou, em coluna própria, e a seção aparece também vazia (RN-10); seção com alteração pendente é sinalizada, o botão fica indisponível sem alteração e sair com pendência pergunta antes (RN-10-b); a semente aceita ajuste por setor, e o fiscal nasce sem Inclui/Exclui no cadastro de permissionário (RN-09). | A legenda prometia que sem "Vê" nada vale, e a tela deixava Inclui/Exclui marcados: o gestor saía convencido de ter concedido o que o servidor nega. O rastro gravava o delta desde o dia 25 e a tela não o mostrava — a auditoria respondia "alguém mexeu". E salvamento por seção sem aviso descartava, em silêncio, o que a pessoa acabou de marcar em outra. |
| 26/08/2026 | José Nascimento | Modo Gerente | A **semente** passa pela mesma normalização que a tela aplica ao gravar (RN-02/RN-09); toda tela da Retaguarda passa a **receber as ações da pessoa** junto com a página, e a esconder o que ela não tem; telas que dividem o mesmo slug aparecem na matriz com o nome da **seção**, decidido numa passada só (RN-09). | A semente montava a linha somando ajustes sobre o pacote completo, sem normalizar: declarar "Só consulta" gravava uma linha que se contradiz — "só consulta" marcado ao lado de Opera/Inclui/Exclui ligados. Como a resolução lê as colunas cruas, ela dava poder de gravar a quem a config diz que só olha, e a config parecia certa. As telas, por sua vez, decidiam o que oferecer por conta própria: ofereciam a ação que a guarda barra, e a pessoa só descobria depois de preencher o formulário. E o rótulo do slug compartilhado emergia da ordem de iteração — com duas telas em seções diferentes, resolvia pelo nome da última. |
| 27/08/2026 | José Nascimento | Modo Gerente | A matriz deixa de ser página e passa a abrir como **painel sobre a tela atual**, pelo item do menu Sistema (RN-13); a leitura pedida em JSON é negada com o motivo, em vez de redirecionada; gravar relê a matriz do servidor sem fechar o painel; o endereço antigo leva à tela inicial com o painel abrindo lá. | Quem distribui acesso está sempre no meio de uma conferência ("por que esta pessoa não vê isto?"), e trocar de página fazia perder o lugar — e a resposta. Manter a rota como fonte da matriz preserva a guarda de leitura no mesmo servidor: o container mudou, a régua não. |
