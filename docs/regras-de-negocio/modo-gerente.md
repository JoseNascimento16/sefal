# Modo Gerente — controle de acesso por setor

**Onde fica:** Menu → Sistema → Modo Gerente (`/retaguarda/modo-gerente`).
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
prometia "abre para olhar" e o servidor deixava alterar. A tela reflete a mesma
regra: marcar *Só consulta* desmarca e indisponibiliza as três na hora, em vez de
aceitar o clique e recusar depois.

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
contagem honesta. A tela mostra as últimas alterações.

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

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/08/2026 | José Nascimento | Modo Gerente | Criação do controle de acesso por setor: matriz `setor × tela × ação`, guarda de leitura, guarda de ação derivada do caminho, semeadura a partir do menu, rastro de alterações e a tela de administração. Enforcement em `log`, com a própria tela do Modo Gerente barrada em qualquer modo (RN-12). | A Retaguarda conferia apenas login: quem soubesse o endereço abria qualquer tela. |
| 25/08/2026 | José Nascimento | Modo Gerente | O menu passa a obedecer o modo de rollout: fora do `block`, o item continua à vista (RN-11). | Sumir do menu em modo de observação era barrada em silêncio — sem recado, sem registro, e com a URL funcionando. |
| 25/08/2026 | José Nascimento | Modo Gerente | "Só consulta" passa a derrubar também "Opera", na gravação e na tela (RN-02). | O setor "só consulta" ainda gravava por `PUT`/`PATCH`: a coluna prometia uma coisa e o servidor fazia outra. |
| 25/08/2026 | José Nascimento | Modo Gerente | O rastro passa a registrar o delta por setor, omitindo quem não mudou (RN-10). | "Permissões alteradas" não respondia "quem abriu qual porta, para quem?". |
| 26/08/2026 | José Nascimento | Modo Gerente | O padrão do enforcement passa de `log` para **`block`**: as guardas barram de verdade, e o `.env.example` acompanha (RN-11). | O rollout em observação cumpriu o que existia para cumprir — o catálogo da Fase 1 fechou e a matriz foi conferida contra ele. Manter `log` deixaria o controle de acesso ligado no papel e desligado na prática, o pior modo de falhar: nada quebra, nada aparece em tela. |
