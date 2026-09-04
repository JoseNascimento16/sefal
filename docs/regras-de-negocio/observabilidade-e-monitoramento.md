# Observabilidade e Monitoramento

**Onde fica:** Menu → Sistema → **Logs** (`/retaguarda/logs`) e Menu → Sistema → **Monitoramento**
(`/retaguarda/monitoramento`).
**Quem usa:** Logs, só o administrador; Monitoramento, administrador e Chefe de Setor.

São **duas telas com o mesmo propósito e tempos diferentes**:

| | **Logs** | **Monitoramento** |
|---|---|---|
| Responde | "o que quebrou, e para quem?" | "o sistema está de pé?" |
| Olha para | o **passado** — falhas que já aconteceram | o **presente** — condições mínimas de funcionamento |
| Quando se abre | depois que alguém relatou um erro | antes de publicar, e sempre que algo parecer estranho |

As duas nascem do mesmo problema: **falha silenciosa**. O erro que ninguém viu acontecer e a
parametrização que ninguém sabe que sumiu custam o mesmo — dias de trabalho errado até alguém
relacionar as coisas.

---

## Regras vigentes — Logs

### RN-01 — Toda exceção capturada vira uma linha consultável

Tudo que o sistema reporta como erro é gravado (tipo, mensagem, rastro, endereço, verbo, quem
estava logado e quando). O framework não reporta 404, 403, 419 e erro de validação — essas são
conversas normais com o usuário, não falhas.

### RN-02 — O código que o usuário vê é o MESMO que o suporte procura

Cada requisição recebe um **código curto** (`REQ-xxxxxx`). Ele aparece na página de erro como
"Código deste erro", volta no cabeçalho `X-Request-Id` da resposta e fica gravado na ocorrência.
É esse encontro que transforma "deu erro às 14h" numa linha específica: a pessoa dita o código, quem
atende encontra a ocorrência.

O código é **hexadecimal** de propósito: ele viaja em endereço e em campo de busca, e o WAF da
Prefeitura barra qualquer texto com cara de injeção de SQL — um `--` sorteado por acaso faria a
consulta voltar disfarçada de erro de CORS.

### RN-03 — Registrar o erro JAMAIS derruba o pedido

A gravação é best-effort: se ela falhar (o caso real é o **próprio banco fora do ar**), o erro cai
no arquivo de log e a requisição segue para a página amigável. Um segundo erro por cima do primeiro
custaria ao usuário até a explicação do que houve.

A reserva também é protegida: com o banco fora **e** o arquivo de log inacessível (disco cheio,
diretório sem permissão), não há terceira opção — e deixar a falha escapar dali derrubaria o
`report()` e, com ele, a própria página amigável. O silêncio nesse ponto é a decisão certa.

Há trava de reentrada: se gravar a ocorrência disparasse outra exceção reportável, o registro
chamaria a si mesmo em laço.

### RN-04 — Mensagem e rastro têm corte declarado

Mensagem em 2.000 caracteres, rastro em 5.000. Erro de banco traz o SQL inteiro na mensagem e uma
recursão profunda gera dezenas de milhares de caracteres de rastro — sem corte, **gravar o erro
vira o próximo erro**, e some justamente o registro que interessava. O rastro guarda o **começo**,
que é onde está a origem; o fundo é sempre o framework.

### RN-05 — A ocorrência não guarda segredo: caminho sem consulta, e nunca argumentos

Esta tabela é lida por qualquer administrador **e sai em PDF pela exportação**. Ela não pode virar
um depósito de credenciais, e duas decisões garantem isso por construção:

**(a) grava-se o CAMINHO, nunca o endereço completo.** O que viaja depois do `?` é escolha de quem
escreveu a tela — hoje uma data, amanhã um e-mail, um documento ou um termo de busca. Guardar só o
caminho tira essa decisão do caminho do erro: ninguém consegue, sem perceber, mandar segredo para
cá. A coluna se chama **`caminho`**, e não `url`, justamente por isso: o nome do campo tem de dizer
o que ele guarda, senão o próximo dev grava o endereço inteiro achando que cumpre o contrato.

Além disso, o **último trecho de caminhos sensíveis entra mascarado**
(`reset-password/[token]`, `email/verify/[id]/[assinatura]`): esses segredos viajam no próprio
caminho, e quem tem o token de redefinição troca a senha da conta alheia sem saber a antiga.

A lista de caminhos sensíveis é escrita à mão, e lista escrita à mão envelhece calada — aqui,
envelhecer significa gravar credencial. Por isso ela **não é conferida contra si mesma**: o teste
percorre as **rotas reais** do sistema, pega toda aquela cujo endereço tem `{token}`, `{hash}` ou
`{signature}`, e exige que o caminho gravado não carregue o valor. Rota nova de segredo reprova a
suíte até entrar na lista.

**(b) o rastro é montado quadro a quadro, sem os argumentos das chamadas.** O PHP guarda os
argumentos escalares de cada quadro quando `zend.exception_ignore_args` está **desligado** — e
desligado é o default fora do `php.ini-production`. Numa falha durante o login, a senha digitada
entraria no rastro em texto claro e apareceria no detalhe da tela. O sistema não confia na
configuração do servidor: ele constrói o rastro que grava, e argumento nenhum passa por lá, seja
qual for o `php.ini` da máquina.

> **Pré-requisito de ambiente (dev Windows e qualquer máquina fora da imagem publicada):**
> mantenha `zend.exception_ignore_args = On` no seu `php.ini`. A construção acima protege o que vai
> para o **banco**; a ini protege o que o **próprio PHP e o framework** escrevem no arquivo de log,
> que não passa pelo nosso código. Na imagem publicada ela já vem ligada
> (`dockerfile_redhat`, bloco `60-fiscalizacao-uploads.ini`).

### RN-06 — A tela é SÓ LEITURA

Não há como editar nem apagar uma ocorrência. Log de erro é a prova do que aconteceu, e ninguém
apaga o que não incomoda: uma tela de exclusão apagaria a única trilha de um defeito de produção.
O teste reprova qualquer mutação que nasça sob o caminho da tela.

### RN-07 — O rastro não vem na listagem

A listagem traz as colunas curtas; o rastro é buscado quando alguém **abre uma ocorrência**. É
campo longo (CLOB no Oracle) e custa uma ida ao banco por linha: no sistema irmão, trazê-lo na lista
derrubou esta mesma tela por tempo esgotado com setenta registros — a ferramenta de diagnóstico caiu
no dia em que foi preciso diagnosticar.

### RN-08 — O período é a JANELA dos dados; a busca recorta o que veio

A tela abre nos **últimos 7 dias** e carrega no máximo **500 ocorrências**, das mais recentes para
as mais antigas. O período decide o que o servidor traz; a **busca inteligente** (barra única,
acento-insensível, com as expressões `hoje` e `sem usuário`) recorta o que chegou.

Quando o período tem mais do que o teto, a tela **diz isso em voz alta**. Sem o aviso, quem vê 500
linhas conclui que o surto acabou, quando ele só transbordou.

### RN-09 — Ocorrência sem usuário é AUSÊNCIA de usuário

Erro em requisição não autenticada nasce sem dono, e a tela mostra "sem usuário" — nunca um usuário
inventado. O sistema procura o dono em **todos os guards configurados**, e não numa lista escrita à
mão: quando o guard do aplicativo do fiscal nascer, o erro vindo da rua já nasce com dono.

### RN-10 — A listagem exporta o recorte visível

Como toda listagem da Retaguarda (PDF/XLSX/DOCX), com as datas em BR e o período impresso no
documento.

---

## Regras vigentes — Monitoramento

### RN-11 — A promessa da tela: tudo verde = fluxos operacionais

Cada item é uma **condição mínima** para algum fluxo funcionar. Quando alguém faz uma alteração
destrutiva, a tela acusa **o que deixou de funcionar** e leva **para onde se corrige**.

### RN-12 — Critério de admissão: só entra o que quebra fluxo EM SILÊNCIO

- ✅ entra: não existe conta de administrador ativa → ninguém distribui acesso, e o sistema fica sem
  dono sem nunca dizer isso a ninguém.
- ❌ não entra: cadastro **descritivo** (a cor de um selo, uma lista de bairros). A ausência
  incomoda, não quebra.

Uma tela com sessenta itens verdes não se lê — e o dia em que um ficar vermelho, ninguém repara.
O que foi **avaliado e descartado** fica escrito no próprio catálogo, com o motivo, para a próxima
revisão não reexplorar tudo.

### RN-13 — Todo item diz para onde ir

Ou a **rota da tela** onde se corrige, ou uma **instrução** quando a correção não tem tela
(ambiente, comando, administrador). Alarme sem porta não compila: o construtor recusa. E toda rota
apontada precisa existir — link quebrado aqui manda o usuário ao nada, no pior momento possível.

### RN-14 — Verificação que estoura vira item vermelho legível, nunca erro 500

Esta tela é o instrumento de diagnóstico: ela precisa **abrir justamente quando as coisas estão
quebradas**. A mensagem crua do erro fica de fora (erro de banco carrega SQL, host e porta); o erro
completo vai para os Logs, e a tela diz onde procurá-lo.

### RN-15 — Rede e disco nunca na abertura da tela

As verificações baratas (banco e configuração) rodam ao abrir. As **profundas** — escrita real no
armazenamento, conversa com serviço externo — só pelo botão "Testar a fundo", e o resultado
substitui o estado do item na tela. Indisponibilidade momentânea é **atenção**, não falha: rede
oscila; parametrização não.

### RN-16 — Verde também informa; severidade honesta

O detalhe do item verde diz **o que foi conferido** ("2 contas de administrador ativas"). E
`falha` é fluxo quebrado; `atenção` é degradado ou arriscado, mas andando. Se tudo for falha, nada é.

### RN-17 — A tela saudável é uma coluna de cards fechados

Um card retrátil por módulo: fechado quando tudo passa (uma linha com `n/n ✓`), **aberto sozinho
quando há pendência**, com as falhas no topo. Ninguém precisa procurar o que está errado.

### RN-18 — Conta de administrador: ATIVA, não apenas cadastrada

O check conta só quem consegue entrar. Conta desligada não entra no sistema, e contá-la faria o
check garantir que há quem administre quando não há mais ninguém. Quando há administrador
desligado, a mensagem diz quantos — é a pista da correção.

### RN-19 — As listas de escolha OBRIGATÓRIAS são vigiadas; as demais, não

Inativar o último registro de uma lista obrigatória não avisa ninguém — e é
exatamente a alteração destrutiva silenciosa que esta tela existe para pegar.
Duas entram, com **severidades diferentes de propósito** (RN-16):

- **Atividade do ambulante** → `falha`. O cadastro de ambulante exige a
  atividade autorizada e recusa a inativada: sem nenhuma em uso, ninguém é
  cadastrado — e é do cadastro que a fiscalização parte.
- **Tipo de infração** → `atenção`. Nada está parado hoje (o enquadramento em rua
  é de entrega futura), mas lista vazia aqui é problema que se descobre na
  calçada, longe da mesa.

**Unidade de medida, tipo de operação, origem de operação e motivo de recusa ficam
FORA por enquanto**: nenhuma tem consumidor nesta entrega, e check de fluxo que
não existe é verde permanente (RN-12). Cada uma entra **junto com a tela que a
consumir** — a regra do catálogo, e há teste-lei que reprova a entrada
antecipada.

### RN-20 — A tela de erro oferece a saída de QUEM está lendo

A página de erro (404, 500, 503) não oferece as duas portas para todo mundo: quem
está autenticado recebe "Ir para o início"; o visitante, "Entrar no sistema".
Oferecer o caminho de entrada a quem já está dentro é convidá-lo a se deslogar —
ação sem sentido para o estado dele, e no pior momento: ele errou um endereço e
quer voltar ao trabalho.

A conferência de "está autenticado?" é protegida: a sessão deste sistema mora no
**banco**, e esta página precisa desenhar justamente quando o banco não responde.
Na dúvida, trata como visitante.

---

## O que os testes travam

Os testes-lei do monitoramento valem para **todo** check, inclusive os que ainda vão nascer (eles
iteram o catálogo): id único, rota existente, nunca estourar, saída sempre renderizável, e o
**flip** — verde → provoca a quebra → vermelho. Um check que nunca fica vermelho é tautológico, e dá
a sensação de sistema saudável justamente quando ele não está.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/08/2026 | José Nascimento | Logs | A coluna do endereço passa a se chamar **`caminho`** (banco, model e tela com o mesmo nome), a máscara do link de confirmação de e-mail passa a apontar o caminho real do Fortify (`email/verify/{id}/{hash}`), e a lista de caminhos sensíveis passa a ser conferida contra as **rotas reais** do sistema. | O nome `url` prometia o endereço inteiro numa coluna que guarda só o caminho; o padrão antigo (`verify-email/*/*`) nunca casaria com rota nenhuma; e a lista escrita à mão envelheceria calada, gravando credencial de rota nova. |
| 25/08/2026 | José Nascimento | Logs | A ocorrência passa a guardar o **caminho** (sem a consulta, com os trechos sensíveis mascarados) em vez do endereço completo, e o **rastro é montado sem os argumentos** das chamadas. | O endereço completo levava o token de redefinição de senha e o e-mail para uma tabela que qualquer administrador lê e exporta; o rastro do PHP levava os argumentos — a senha digitada no login em texto claro. |
| 25/08/2026 | José Nascimento | Logs / Monitoramento | Criação do registro central de exceções (com código de requisição compartilhado com a página de erro), da tela de consulta só-leitura e do motor de verificações com os dois primeiros checks (conta de administrador ativa e armazenamento gravável). | O sistema não tinha como responder "o que aconteceu com esse usuário" sem entrar no servidor, e nada avisava quando uma condição mínima de funcionamento deixava de valer. |
| 26/08/2026 | José Nascimento | Monitoramento | Módulo novo **"Parametrização da fiscalização"** com dois checks: atividade do ambulante em uso (falha) e tipo de infração em uso (atenção) — RN-19. As outras quatro listas ficam fora, com teste-lei que reprova a entrada antecipada. | O cadastro de permissionário não salva sem atividade autorizada: inativar a última parava o fluxo em silêncio, com o painel continuando verde. É o caso que o critério de admissão descreve. |
| 26/08/2026 | José Nascimento | Monitoramento | O selo da verificação profunda deixa de ser um parêntese grudado no título ("gravável(tem teste real)") e o resumo geral passa a dizer **"Sistema em operação"**. | Vocabulário de dentro de casa vazando para a tela, e "sistema operacional" em português é primeiro o SO da máquina — num painel de infraestrutura, a leitura errada é provável. |
| 26/08/2026 | José Nascimento | Telas de erro | A saída oferecida passa a depender de quem está lendo (RN-20). | "Entrar no sistema" para quem já está dentro é convite a se deslogar. |
