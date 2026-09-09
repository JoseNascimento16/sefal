# Cadastro de Operação

**Onde fica:** Menu → Fiscalização → Cadastro de Operação (`/retaguarda/operacoes`).
**Quem usa:** Chefe de Setor (cadastra as da área dele), Coordenador (**consulta**, sem cadastrar) e
administrador. **O fiscal não entra** (ver RN-07).

> ⚠️ **É PROTÓTIPO.** Não há tabela nem gravação: a lista de partida é
> `config/prototipo_operacoes.php` e o que a pessoa cria, altera ou exclui vive na **sessão** de quem
> navega. A tela diz isso de forma visível — protótipo que se disfarça de sistema pronto vira decisão
> tomada por engano.

**"A operação é evento; a equipe é organização."** A área e a equipe são a estrutura **permanente**
com que a SEMOP divide a cidade ([Áreas e Equipes](../estrutura/areas-e-equipes.md)); a operação é o
trabalho com **começo, fim e foco** que se monta em cima dela — Operação Verão na orla, Volta às
Aulas no entorno das escolas, a rotina semanal do Centro.

---

## Regras vigentes

### RN-01 — É o MESMO catálogo que o direcionamento das Denúncias consome

Esta é a regra que decide a tela, e a razão de ela ter sido construída **em cima** da lista que já
existia em vez de **ao lado** dela.

A operação já era consumida antes desta tela existir: no direcionamento das
[Denúncias](denuncias.md), o Chefe de Setor **anexa** a denúncia a uma operação já planejada em vez
de mandar uma ida isolada. Se o cadastro tivesse a lista dele:

- no dia seguinte o direcionamento ofereceria uma operação que o cadastro **não conhece**;
- e recusaria a que o cadastro **acabou de criar**;
- e nada acusaria — as duas telas abrem, cada uma com a sua verdade.

Então o catálogo é **um**: `App\Support\Prototipo\OperacoesFicticias`. As operações **saíram** de
`config/prototipo_denuncias.php` para `config/prototipo_operacoes.php`, e
`DenunciasFicticias::operacoes()` passou a **delegar** — não tem lista própria. Um teste-lei prova as
duas metades: o que o direcionamento oferece é **subconjunto** do cadastro, e a operação criada aqui
aparece lá na mesma sessão. Sem a segunda metade, duas listas alimentadas pela mesma configuração
passariam no teste e divergiriam na primeira gravação.

**O direcionamento também CRIA operação** (o caso de não haver trabalho planejado ainda para aquela
região). Ela nasce **em andamento** e começando **hoje**: quem abre operação no meio de um
direcionamento está mandando a equipe agora, e nascer "planejada" a deixaria fora do trabalho que
motivou a criação. O campo de período em **texto livre** daquele formulário ("até o fim de março"),
que não cabe em data, é preservado como **observação** em vez de jogado fora — foi o que a chefia
escreveu, e alguém vai transformá-lo em data aqui, com o calendário à mão.

### RN-02 — O que uma operação declara

| Campo | Obrigatório | O que é |
|---|---|---|
| **nome** | sim (mín. 5) | como a equipe reconhece a operação em rua, e o que a denúncia grava ao ser anexada |
| **área** | **sim** | quem vê a operação e quem a executa (ver RN-03) |
| região | não | o alcance em texto — "Orla de Itapuã a Boca do Rio" |
| **equipes** | não | **lista** de códigos de equipe (ver abaixo) |
| bairros | não | os bairros varridos **dentro** da área. Vazio significa **a área inteira**, e não "nenhum" |
| **início** | **sim** | data |
| fim | não | data. Em branco = **rotina permanente**, sem data de encerramento |
| **situação** | **sim** | planejada / em andamento / **encerrada** (ver RN-05) |
| foco | não | o que a equipe vai procurar |
| observação | não | horário, reforço de equipe, quem pediu a operação |

**Equipes é LISTA, e não uma equipe só:** operação grande junta equipe de mais de uma área — a
Noturna reforçando a orla no verão é o caso real. Singular, a chefia teria de inventar uma segunda
operação só para registrar a segunda equipe.

**Ao anexar denúncia à operação, a denúncia fica com a PRIMEIRA equipe dela.** A denúncia tem uma
equipe só, porque quem vai ao ponto responde por ele; a de reforço entra no **trabalho**, não na
**responsabilidade**.

**Fim em branco é decisão, não campo esquecido.** "Rotina Centro" é permanente: inventar um fim faria
a tela mostrar prazo onde não há, e a operação apareceria encerrando num dia que ninguém decidiu. A
etiqueta do período diz isso — *"a partir de 12/03/2026"*.

### RN-03 — A ÁREA é obrigatória, porque é ela que decide quem vê e quem trabalha

Operação sem área é operação **de ninguém**: não aparece para chefe algum e não tem equipe a quem
cobrar. A área tem de existir na estrutura de fiscalização — área inventada deixaria a operação órfã.

### RN-04 — Fim antes do início não passa, e a recusa diz o efeito

Período invertido não é detalhe de formulário: a operação **apareceria encerrada antes de começar**, e
a conta de "quanto tempo ela durou" sairia negativa em todo relatório que a somar.

**Um dia só passa.** A interdição de um evento começa e termina no mesmo dia, e exigir fim
**posterior** obrigaria a chefia a mentir a data para conseguir salvar.

A conferência é do **servidor**; a tela a adianta (o campo de fim tem mínimo, e a mensagem aparece
antes do envio) porque é melhor descobrir antes de mandar — mas esconder o botão não impede ninguém
de mandar a requisição.

### RN-05 — Operação ENCERRADA não recebe denúncia nova

Ela **sai da escolha** do direcionamento e **continua** no cadastro, consultável: o histórico é a
régua da operação do ano que vem.

As **duas** metades existem, e a segunda é a que importa: esconder da lista não é fronteira. Quem
souber montar a requisição manda o nome de uma encerrada, e a denúncia entraria num trabalho que
ninguém vai mais executar — **desaparecendo da fila sem nunca chegar a campo**. É a pior falha
possível aqui, porque não parece falha nenhuma. O servidor recusa **dizendo o nome da operação e que
ela está encerrada**, e oferecendo as saídas: escolher uma em andamento, abrir uma nova ali mesmo, ou
direcionar à equipe.

⚠️ A recusa vai por **aviso flutuante** (`flash.erro`), e não por erro de campo: a tela das denúncias
não renderiza o saco de erros de validação, e recusa que não aparece é o **bloqueio em silêncio** que
a lei do projeto proíbe.

**Encerrar não é excluir.** A tela oferece as duas coisas e a confirmação da exclusão diz qual
preferir: se a operação já aconteceu, encerre-a.

### RN-06 — O NOME é único

É por ele que a equipe reconhece a operação em rua, e é ele que a denúncia grava na linha ao ser
anexada. Duas com o mesmo nome fazem a anexação apontar para **qualquer uma das duas**, e ninguém
sabe qual. A régua vale nos **dois** caminhos de nascimento de operação — este cadastro e a criação a
partir do direcionamento —, senão o nome duplicado entraria pelo outro.

### RN-07 — O recorte é por ÁREA, e é do SERVIDOR — com recusa NOMINAL

| Quem | O que vê | O que faz |
|---|---|---|
| **Chefe de Setor** | as operações da **área dele** | cadastra, altera e exclui as dela |
| **Coordenador** | o **universo** | **consulta** — ele tria a entrada e precisa saber a que operação encaminhar a demanda |
| **administrador** | o universo | tudo (é o dono do sistema) |
| **Fiscal** | não entra | planejar operação é ato de gestão; ele recebe o trabalho já dirigido, pelo aplicativo |

O recorte é feito **no servidor**: filtro de front esconde, não protege — e a operação carrega o
**foco** e a **observação** da gestão, que viajariam dentro do registro até o navegador de quem não
deve vê-los.

E esconder da lista **não é fronteira**. A gravação sobre operação de outra área é recusada
**nominalmente**, dizendo por quais áreas a pessoa responde. Na **alteração**, as **duas** áreas são
conferidas — a de onde a operação está e a para onde ela iria: sem a primeira, bastaria mover a
operação alheia para a própria área para poder alterá-la; sem a segunda, dava para empurrar a própria
operação para a área de outro.

**Chefe de Setor sem área vinculada** é recusado dizendo isso, e não deixado passar: ele exerce o
cadastro e não tem área sobre a qual cadastrar. Recusar é o que faz alguém corrigir o vínculo.

Quem **apenas consulta** também é recusado com o motivo: *montar operação é do Chefe de Setor da
área*. A tela não lhe oferece o formulário (a resposta `cadastra` vem do servidor), e o servidor
recusa o ato — as duas coisas, porque esconder botão é conforto.

⚠️ Isto é **papel**, e não permissão de tela. A permissão (slug `operacoes`, no Modo Gerente) diz
**quem entra**; isto diz **de quem é o cadastro**. As duas conferências existem, e nenhuma substitui
a outra.

### RN-08 — A busca é o filtro único

A **busca inteligente** é a barra única, acento-insensível, com exemplos clicáveis. Facetas do
domínio: *em andamento*, *planejadas*, *encerradas*, *permanentes* (as sem data de fim). O texto
restante casa contra nome, área, região, foco, observação, situação, equipes e bairros. Os **números
do topo** são o resumo da mesma lista e, clicados, escrevem a faceta na busca. **Não há chip de
filtro paralelo.**

### RN-09 — Exportação do recorte visível

PDF, XLSX e DOCX pelo ponto **único** (`POST /retaguarda/exportar-listagem`), com o recorte que a
busca e o recorte por área deixaram na tela — nunca o universo, nunca só a página. O contexto
impresso diz as áreas e a busca digitada. As datas saem em **dd/mm/aaaa**: o arquivo é lido fora do
sistema, onde ninguém traduz ISO. Bairro nenhum listado sai como **"a área inteira"**, e não em
branco: é uma decisão, não dado faltando.

### RN-10 — Reiniciar a demonstração

Existe porque é protótipo: quem está mostrando o sistema precisa poder recomeçar a cena. O botão só
aparece **depois** de a sessão ter mexido no catálogo. No sistema real a operação é cadastro, e
cadastro não se reinicia.

---

## Pendências que isto abre

| ID | O que falta | O que destrava |
|---|---|---|
| **PEND-018** | A **operação como tabela**, com o vínculo real área/equipes e o histórico de quem a criou e encerrou. | Aprovação da forma pelo dono. |
| **PEND-019** | O **vínculo entre a operação e os registros de campo que ela produziu**. Hoje a fiscalização avulsa aponta para a operação por **texto**, no campo referência — então ninguém consegue somar "o que a Operação Verão produziu". | A fiscalização como tabela ([Fiscalizações](fiscalizacoes.md), PEND-013) + a operação como tabela. |
| **PEND-020** | O **encerramento com resultado consolidado**, que a spec previa ("encerrar a operação com o resultado") e ninguém definiu: quais números, e o que acontece com o que ficou em aberto. | Decisão da área de negócio. |
| — | A **modelagem definitiva do vínculo chefia↔área** (em produção é tabela usuário↔área, não arquivo de configuração). | A mesma pendência já registrada em [Áreas e Equipes](../estrutura/areas-e-equipes.md). |

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 09/09/2026 | José Nascimento | Cadastro de Operação | Nasce a tela, como **protótipo**, no lugar do andaime que anunciava a Fase 2: listagem com busca inteligente e exportação, formulário com nome, área, região, bairros, período, foco, equipes, situação e observação (RN-02). O catálogo de operações **saiu** de `prototipo_denuncias.php` para `prototipo_operacoes.php` e passou a ser **fonte única**, com o direcionamento das Denúncias delegando a ele (RN-01). Regras com o motivo escrito na recusa: área obrigatória (RN-03), fim não antes do início (RN-04), encerrada não recebe denúncia nova (RN-05), nome único (RN-06). Recorte por área feito no servidor, com recusa nominal e as duas áreas conferidas na alteração (RN-07). | Decisão do dono de 09/09/2026: *"faça também já o cadastro de operação"*. O direcionamento das denúncias já anexava demanda a operação, e o catálogo **não tinha tela que o mantivesse** — a operação só nascia de dentro de um direcionamento, e nada permitia planejá-la, corrigi-la ou encerrá-la. Entregue como protótipo para o dono aprovar a forma antes de a operação existir como tabela. |
