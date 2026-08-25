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
| **Só consulta** (`apenas_leitura`) | Abre para olhar, sem incluir nem excluir. |
| **Inclui** (`incluir`) | Pode criar registro novo. |
| **Exclui** (`excluir`) | Pode excluir registro. |

A lógica é **positiva**: a marca presente concede; a ausência nega. Não existe
regra de "negar explicitamente" — negar é não conceder.

### RN-02 — "Vê" é pré-requisito; "Só consulta" derruba escrita

Ao gravar, a linha é normalizada: sem **Vê**, todas as demais caem; com **Só
consulta**, *Inclui* e *Exclui* caem. Assim não existe linha que diga, ao mesmo
tempo, que o setor só olha e que ele pode apagar.

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

### RN-09 — A concessão inicial vem do menu; depois, quem manda é a matriz

`config/retaguarda_menu.php` declara, por tela, os setores que a usam. Essa
lista é a **semente** da matriz, aplicada uma vez pelo seeder (idempotente e não
destrutivo: rodar de novo cria só o que falta e nunca desfaz decisão de
gerente). Depois disso, mudar a lista não muda nada — quem concede e quem tira é
esta tela.

### RN-10 — Toda alteração de permissão deixa rastro

Quem salvou, quando e em qual tela ficam registrados, e o **nome** de quem
alterou vai gravado no registro — não só a chave da conta, que pode ser
renomeada ou desligada. A tela mostra as últimas alterações.

### RN-11 — O bloqueio tem três estágios

`PERMISSAO_ENFORCE` ∈ `off` | `log` | `block`:

- `off` — não confere nada;
- `log` — confere e **registra** quem seria barrado, sem barrar;
- `block` — barra de verdade.

O padrão hoje é **`log`**. Ligar o bloqueio junto com o nascimento do catálogo
seria estrear a fechadura antes de saber quantas portas a casa tem: cada tela das
próximas entregas entra na matriz, e um esquecimento de concessão viraria gente
sem acesso ao próprio trabalho. A tela avisa, em voz alta, quando o modo não é
`block` — senão quem configura acha que tirou um acesso e não tirou.

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
