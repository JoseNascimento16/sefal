# Cadastro de Ambulante — a identidade de quem é fiscalizado

**Onde fica:** Menu → Fiscalização → **Ambulantes** (`/retaguarda/ambulantes`).
**Quem usa:** administrador, Chefe de Setor e **fiscal**. É a tela-núcleo da fiscalização: sem um
ambulante cadastrado, não há a quem ligar uma vistoria.

O desenho desta tela responde a uma realidade concreta da rua, e não a um cadastro de escritório: **o
alvo muitas vezes não tem documento à mão**, não tem endereço fixo e às vezes não quer dizer o nome.
Uma tela que exigisse CPF simplesmente faria o cadastro em campo não acontecer — e a pessoa
continuaria trabalhando sem constar de lugar nenhum.

> **A entidade é o AMBULANTE, e ser permissionário é um ATRIBUTO dele** (decisão da área de negócio
> em 02/09/2026, que fechou a PEND-010). A fiscalização de rua encontra quem tem permissão da SEMOP e
> quem não tem — e quem não tem é a maior parte do trabalho educativo. Enquanto a tela se chamava
> "Cadastro de Permissionário", ela mentia sobre metade dos registros, e não havia como responder
> "quantos têm permissão?". O SGCI é a base de quem TEM; este cadastro é de quem a fiscalização
> encontra na calçada, com permissão ou sem.

---

## Regras vigentes

### RN-01 — Documento é **opcional**, e a identidade prática é foto + apelido

`documento` (CPF **ou** CNPJ) é opcional em **todo caminho de gravação**: formulário, e amanhã a fila
do aplicativo. Obrigatórios são três campos apenas — **nome**, **atividade autorizada** e
**situação**.

Quem identifica a pessoa em rua é a **foto** e o **apelido** (o nome de guerra pelo qual ela é
conhecida no ponto). Por isso a listagem mostra o retrato ao lado do nome e, **quando não há foto,
mostra as iniciais** — nunca um quadrado em branco: o fiscal precisa reconhecer alguém, e vazio não
reconhece ninguém.

### RN-01-b — **Ser permissionário é atributo**, e o número da permissão é o que o sustenta

`permissionario` (sim/não) responde "tem permissão da SEMOP?". É o campo que dá sentido a
`numero_permissao` e `validade_permissao` — antes eles ficavam pendurados em todo cadastro, inclusive
no de quem nunca teve permissão nenhuma. É também o que o vínculo futuro com o **SGCI** vai
preencher.

Um cadastro novo nasce **sem permissão**: é a resposta honesta para o que ninguém conferiu, e é
também o caso mais comum na rua. Afirmar permissão que ninguém viu é pior do que não afirmar nada.

**Marcado, o nº da permissão passa a ser obrigatório.** Sem essa amarra, marcar a opção seria só um
clique, e o cadastro passaria a dizer "tem permissão" sem nada que se pudesse conferir depois. O
formulário só mostra número e validade quando a resposta é sim — exibi-los sempre era o que fazia
todo cadastro parecer permissionário.

**A validade continua OPCIONAL, e isso é decisão consciente.** Em rua o papel está desbotado, rasgado
ou simplesmente não está com a pessoa; exigir a data faria alguém inventar uma — e data inventada é
pior que data ausente, porque a busca por *"permissão vencida"* passaria a acusar com base nela.
Ausente, ela não acusa ninguém (a faceta trata nulo como "não vencida", nunca como "vencida").

**Desmarcar LIMPA número e validade**, na tela e no servidor. Guardar a permissão de quem o cadastro
diz não ter deixaria a base afirmando duas coisas contrárias — e a busca acusaria alguém por um papel
que o próprio sistema diz que não existe.

⚠️ **A situação é INDEPENDENTE disto.** `Regular` / `Irregular` / `Cadastrado em campo` responde outra
pergunta: um ambulante **sem permissão pode estar regular** (ponto autorizado por outra via) e um
**permissionário pode estar irregular** (fora do que a permissão dele autoriza). Deduzir uma da outra
apagaria as duas informações — por isso são dois campos, duas colunas na grade e dois filtros no
relatório.

### RN-02 — Documento informado é validado, **normalizado** e único

Informado, o documento é validado como CPF (11 dígitos) ou CNPJ (14 posições, **alfanumérico** desde
2026 — letras nas 12 primeiras). É guardado na **forma canônica** (só `[0-9A-Z]`, maiúsculo,
sem máscara), e a unicidade é conferida sobre esse valor: sem isso, `123.456.789-09` e
`12345678909` entrariam como duas pessoas diferentes, e a busca por documento acharia só uma delas.

A normalização mora no **model**, não no formulário: é a coluna que tem o índice único, e um segundo
caminho de gravação que esquecesse de normalizar quebraria a garantia sem ninguém perceber.

Alterar um cadastro **não esbarra no próprio documento** — senão salvar só para corrigir o apelido
seria recusado.

⚠️ `preg_replace('/\D/', '')` é **proibido** em qualquer campo que possa conter CNPJ: apaga as letras
e corrompe o dado em silêncio.

### RN-03 — Três situações, e uma delas é **quarentena**

`Regular` · `Irregular` · `Cadastrado em campo`. A terceira é quarentena: o cadastro nasceu em rua,
com o que a pessoa disse, e **espera conferência**. É o valor padrão da coluna — o que chega sem
situação declarada nunca entra como regular.

**A quarentena não é oferecida na INCLUSÃO pela Retaguarda**, e o servidor recusa (não é a tela
escondendo a opção): `Cadastrado em campo` significa "isto nasceu na rua, sem conferência", e um
cadastro feito de mesa — com o Chefe de Setor lendo o documento na tela — não nasce assim. Permiti-lo
sujaria com registros dispensáveis justamente a fila que dá sentido à quarentena. A inclusão de mesa
**propõe `Regular`**, que é o caso comum de quem cadastra com o documento em mão.

Na **alteração** as três valem: devolver à fila um cadastro que se mostrou duvidoso é exatamente o
que o Chefe de Setor precisa poder fazer.

**A tela de validação dessa fila (aprovar / mesclar duplicado / recusar com motivo) é de entrega
futura.** Hoje a situação é um valor que o Chefe de Setor troca à mão nesta própria tela, e a busca sabe
achar quem está esperando ("cadastrado em campo", "quarentena").

### RN-04 — O código do cadastro nasce sozinho, pelo gerador de protocolo

`codigo` = `AMB` + data + sequencial do dia (`App\Support\Protocolo`), nunca digitado. O gerador
recebe o model e a coluna: assim, se a linha do contador do dia não existir (banco restaurado, carga
anterior), o número **continua de onde o que já está gravado parou**, em vez de recomeçar em 001 e
colidir.

⚠️ **Os cadastros anteriores a 02/09/2026 continuam com o prefixo `PER`, e ficam assim.** O código é
identidade **visível** — ele é lido no documento entregue em rua, citado em ofício e usado para achar
o cadastro. Reescrevê-lo em massa mudaria o número de registros que já circularam no papel, para
ganhar coerência estética. Os dois prefixos convivem, e a busca acha os dois: o contador é por
prefixo + data, então `AMB` começa a própria sequência sem colidir com nada.

### RN-05 — A atividade apontada tem de estar **em uso**, no cadastro novo

Atividade inexistente é recusada sempre. Atividade **inativada** é recusada no cadastro novo e
**aceita no cadastro que já a apontava**: inativar tira o valor das escolhas de hoje, não reescreve o
passado — senão quem entrasse para corrigir um telefone seria obrigado a trocar o ramo, mudando um
dado que ninguém pediu para mudar. No formulário, a inativada aparece marcada como *(fora de uso)*
apenas no registro que a usa.

### RN-06 — Excluir a atividade em uso é **recusado na Parametrização**

Uma atividade apontada por qualquer ambulante **não pode ser excluída**: a tela de
*Parametrização → Atividades do Ambulante* recusa dizendo **quantos** cadastros dependem dela e
manda desmarcar **"Em uso"**. Sem essa recusa, quem respondia era a chave estrangeira do banco — com
um erro cru de integridade, que para quem está na tela é o sistema quebrando sem motivo.

Atividade que **ninguém** aponta continua excluível: a guarda não pode virar "nunca mais se exclui
atividade", porque o valor cadastrado errado tem de sair.

### RN-07 — A foto é anexo controlado, e trocar/remover **apaga o arquivo**

Só **JPG ou PNG**, até **5 MB**, passando pela allowlist de anexos do projeto (`ArquivoSeguro`), que
barra executável renomeado, extensão perigosa em qualquer posição do nome e nome que o WAF
reprovaria na URL de download.

Três casos, e confundi-los é o erro clássico:

| O que o formulário manda | O que acontece |
|---|---|
| Arquivo novo | Grava o novo e **apaga o anterior** (senão o disco vira depósito de órfãos) |
| "Remover a foto atual" marcado | Apaga o arquivo e limpa o campo |
| **Nada** | **Nada** — é o caso de quem entrou só para corrigir o telefone |

Tratar "campo ausente" como remoção apagaria a foto de quem nunca pediu isso — e a foto é a
identidade de campo (RN-01).

**A ordem é: gravar primeiro, apagar depois.** O arquivo antigo só é removido depois de a gravação
dar certo, e a foto de um cadastro excluído só depois de a linha sair. Entre os dois estragos
possíveis quando algo falha no meio — **arquivo sobrando no disco** ou **cadastro vivo apontando para
arquivo que não existe** —, o primeiro é lixo e o segundo é perda da identidade de campo. Por isso a
prioridade é essa, e não a inversa.

Na **inclusão** a ordem é inevitavelmente a inversa (é o arquivo que preenche a coluna), então ali
vale o cuidado espelhado: se a gravação da linha falhar, o arquivo recém-guardado **vai junto** —
senão a imagem fica no disco sem nada apontando para ela, e nada a recolhe depois.

### RN-07-b — A foto sai por **rota autenticada**, nunca por URL de disco público

O arquivo mora em disco **privado** e só é servido por `GET /retaguarda/ambulantes/{id}/foto`,
que passa pela guarda de leitura como qualquer outra tela: **quem não abre o cadastro não vê o
retrato de quem está nele**. Cadastro sem foto, ou com o arquivo sumido do disco, responde **404** —
a tela já trata a ausência mostrando as iniciais (RN-01), e uma resposta vazia com código 200 faria
o navegador desenhar imagem quebrada.

O motivo é o dado, não a mecânica: é o **retrato de um cidadão fiscalizado**, exibido ao lado do
CPF/CNPJ dele. No disco público o arquivo é servido direto pelo servidor web, **fora das guardas** —
quem tivesse a URL (histórico de estação compartilhada, log de proxy, cabeçalho de referência, print
encaminhado) abriria a imagem sem estar autenticado. Nome de arquivo difícil de adivinhar reduz a
chance de tropeçar nele; **não é controle de acesso**.

A foto nova é guardada na pasta `ambulantes/` desse disco; as anteriores continuam onde estão
(`permissionarios/`) e continuam abrindo, porque **é a coluna do cadastro que guarda o caminho** —
não há convenção deduzida em tempo de execução para quebrar. Nenhum arquivo precisou ser movido.

**Consequência no deploy:** a pasta privada é tão volátil quanto a pública — o que não estiver em
volume/PVC some a cada subida de imagem, **sem erro nenhum**, e só aparece quando um Chefe de Setor abre um
cadastro antigo e não vê o retrato. Por isso a persistência declarada em `docker-compose.homolog.yml`
e em `docs/deploy/okd.md` cobre a pasta do disco onde a foto de fato mora, e essa amarração é
verificada por teste — a decisão tem três donos (controller, compose, doc do OKD) e, sem asserção,
um dia eles divergem em silêncio.

### RN-08 — Busca inteligente: uma barra só

O campo único interpreta a frase em facetas do domínio + termos livres, sem acento: `regular`,
`irregular`, `cadastrado em campo` / `quarentena`, `sem documento`, `com documento`,
**`permissionários`** / **`sem permissão`**, `permissão vencida` — e **o nome de qualquer atividade da
parametrização**, que vira faceta em tempo de execução (a lista é do Chefe de Setor; escrita na tela,
envelheceria no primeiro ramo renomeado). Como faceta, "bebidas" filtra pelo **ramo**, e não casa por
acaso com um apelido que tenha a palavra.

As três facetas de permissão dividem a mesma palavra, então a ordem entre elas é regra e não detalhe:
`permissão vencida` é reconhecida **antes** de `sem permissão`, que vem **antes** de `permissionários`
— declarada ao contrário, a mais genérica engoliria as outras duas e "sem permissão" filtraria
justamente quem tem.

O que sobra casa contra nome, apelido, código, nº da permissão, atividade e situação — e
**também contra o documento sem máscara**, para quem digita `123.456.789-09` achar quem está gravado
como `12345678909`.

No documento o casamento é pelo **começo**, não por trecho no meio: é assim que a pessoa digita (lê da
esquerda para a direita e para quando achou). Casando no meio, `529982` encontrava `77852998224` — e é
assim que se abre o prontuário de quem não se procurava.

A conferência é **termo a termo**: cada palavra digitada vale se casar no texto **ou** no documento, e
todas precisam casar. É o que faz a consulta **mista** funcionar — `acaraje 12345678909` acha a pessoa
cujo apelido tem "acarajé" **e** cujo documento é aquele. Exigir que todos os termos caíssem do mesmo
lado (todos no texto, ou todos no documento) não acharia ninguém.

Não há chips nem filtros paralelos: é a lei de busca do projeto.

### RN-09 — A listagem exporta o **recorte visível**

PDF / XLSX / DOCX pelo ponto único de exportação, com o que o filtro e a busca deixaram na tela —
nunca o universo, nunca só a página atual. Datas saem em **dd/mm/aaaa** e o documento sai
**formatado**; nada de id, caminho de arquivo ou forma ISO no arquivo.

### RN-10 — Quem entra: o fiscal CONSULTA; criar e apagar é da gestão

A tela é controlada pela permissão **`ambulantes`** (primeiro trecho do caminho), semeada para
**administrador, Chefe de Setor e fiscal**. O fiscal está na lista porque chegar à calçada sem saber quem está
cadastrado é trabalhar às cegas.

Mas ele entra para **consultar**, e só: a semente concede a ele **"Só consulta"**, que derruba
*opera*, *inclui* e *exclui* de uma vez. O motivo é o desenho do fluxo, não desconfiança — o fiscal
cadastra em **rua**, pelo aplicativo, e o que nasce em rua entra em **quarentena** até o Chefe de Setor
conferir (RN-03). Cadastro criado de mesa por ele passaria ao largo dessa conferência; apagar
cadastro é ato de gestão, porque leva embora a identidade a que uma fiscalização se liga.

⚠️ **Por que "Só consulta", e não apenas *inclui* e *exclui* desligados.** É a diferença que decide
se a quarentena existe de verdade. Com *opera* ligado o fiscal **alterava** o cadastro — e a
**situação é campo do mesmo formulário** (RN-03), então ele tirava da fila o registro que ele mesmo
acabara de criar em rua, e a conferência do Chefe de Setor simplesmente não acontecia, sem nada no sistema
registrando que foi pulada. "Pode alterar" e "pode validar" são a mesma coisa aqui.

O Chefe de Setor mantém o pacote inteiro: validar e corrigir cadastro de campo é o trabalho dele.

Isto é a **concessão inicial**. Alargar ou apertar depois é ato do Chefe de Setor no Modo Gerente.

**A tela obedece à matriz, e não só o servidor.** O que a pessoa pode fazer chega junto com a tela
(prop `acoes`, vinda do mesmo serviço que as guardas consultam), e os botões que ela não tem
simplesmente não aparecem. Sem isso o fiscal via "Incluir", preenchia o formulário inteiro e só
então era recusado. Esconder botão é **conforto, nunca fronteira** — quem barra continua sendo a
guarda, no servidor. Fora do modo que barra, a tela oferece tudo: senão esconderia o que o servidor
aceita, e o registro do rollout nunca existiria.

### RN-11 — Nome e apelido são nomes de cadastro, não texto livre qualquer

Aceitam letras (com acento), números (há apelido com número), espaço e a pontuação de nome de
cadastro — ponto, apóstrofo, hífen, **vírgula e E comercial** (`Ana D'Ávila`, `Maria-José`,
`J. Carlos`, `Silva & Filhos Ltda`, `José da Silva, ME`). Recusam markup (`<img …>`), aspas duplas,
ponto e vírgula, barras, caractere invisível e **dois hífens seguidos**.

A vírgula e o `&` estão na lista porque o documento aceita **CNPJ** (RN-02), e portanto há
ambulante **pessoa jurídica**: razão social é escrita com eles o tempo todo. Recusá-los
obrigava quem cadastrava a alterar a razão social para o formulário aceitar — e aí o nome deixava de
bater com o documento que ele representa. Nenhum dos dois é assinatura de SQL para o WAF nem abre
marcação HTML, então admiti-los não afrouxa o que a regra existe para barrar.

Não é purismo: o valor GRAVADO sai por outras portas além da tela — relatório, planilha, documento,
nome de arquivo. E `--` é a assinatura que o WAF da Prefeitura barra na URL: gravaria sem reclamar e
depois faria a requisição que o carregasse voltar disfarçada de erro de CORS. A recusa **diz o que é
aceito**, para quem digitou o nome da pessoa saber o que corrigir.

As **iniciais** do retrato (o que aparece quando não há foto) são montadas só com letra e número, pela
mesma razão em segunda camada: com pontuação no começo do nome, saíam iniciais como "Z<", que não
identificam ninguém.

---

## Fora de escopo (por ora)

- **Validação do cadastro em quarentena** (aprovar, mesclar duplicado, recusar com motivo). Chega
  junto com a fila que a alimenta — o aplicativo do fiscal. Enquanto isso, a situação é editável à
  mão (RN-03).
- **Prontuário / trilha de movimentação.** O histórico geoespacial nasce das fiscalizações, que
  ainda não existem.
- **Bloqueio de exclusão do ambulante por vínculo.** Hoje nada aponta para ele, então excluir é
  livre (com a confirmação que a tela pede). Quando a cadeia de fiscalização existir, a recusa entra
  no `destroy()` desta tela — como a Parametrização faz com a atividade (RN-06).
- **`client_id`.** A coluna já existe (é o que fará o reenvio da fila do aplicativo reconhecer o
  cadastro que já subiu, em vez de criar um segundo), mas **nenhum caminho de hoje a preenche**: o
  cadastro pela Retaguarda nasce com ela vazia.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 02/09/2026 | José Nascimento | Cadastro de Ambulante | **A entidade passa de Permissionário a Ambulante em todo o sistema** — tabela, model, controller, rota/slug (`ambulantes`), permissão da tela, relatório, menu, títulos e textos —, e ganha o atributo **é permissionário da SEMOP** (RN-01-b): número da permissão passa a ser exigido de quem é marcado, a validade segue opcional, desmarcar limpa os dois, a grade e o relatório distinguem os dois públicos e a busca ganha as facetas "permissionários" e "sem permissão". O prefixo do código passa a ser `AMB`; os cadastros antigos continuam `PER` (RN-04). | A fiscalização de rua encontra quem tem permissão e quem não tem, e quem não tem é a maior parte do trabalho educativo: chamar a base de "permissionários" obrigava a mentir sobre metade dos registros e não deixava responder quantos de fato têm permissão. O número e a validade ficavam pendurados em quem nunca teve permissão nenhuma, e a marcação sem número seria uma afirmação que ninguém consegue conferir depois. |
| 25/08/2026 | José Nascimento | Cadastro de Permissionário | Criação da tela com listagem, inclusão, alteração e exclusão; documento opcional validado e normalizado quando informado; foto com allowlist de anexos e limpeza do arquivo anterior; situação com quarentena; código pelo gerador de protocolo; busca inteligente e exportação do recorte visível. | É a identidade de quem é fiscalizado: sem ela não há a quem ligar uma vistoria, e o cadastro precisa caber na realidade da rua, onde não há documento à mão. |
| 25/08/2026 | José Nascimento | Parametrização → Atividades do Ambulante | Exclusão passa a ser recusada quando algum permissionário aponta a atividade, dizendo quantos são e mandando inativar. | Excluir deixaria os cadastros apontando para o nada, e quem responderia seria a chave estrangeira do banco — com um erro cru na cara de quem está na tela. |
| 26/08/2026 | José Nascimento | Cadastro de Permissionário | Foto anterior passa a ser apagada só depois de a gravação dar certo (e a do excluído, depois de a linha sair); busca mista passa a casar termo a termo (texto ou documento); "Cadastrado em campo" sai das opções da inclusão pela Retaguarda, com recusa no servidor, e a inclusão propõe "Regular". | Apagar antes de gravar deixava o cadastro vivo apontando para arquivo inexistente — perda da identidade de campo. A busca não achava ninguém quando a frase misturava apelido e documento. E cadastro feito de mesa não é cadastro de rua: entrando em quarentena, sujava a fila de conferência. |
| 26/08/2026 | José Nascimento | Cadastro de Permissionário | Nome e apelido passam a aceitar apenas nome de gente (RN-11); a busca por documento passa a casar pelo **começo**, não por trecho no meio (RN-08); a semente do fiscal passa a nascer sem *inclui* e sem *exclui* (RN-10); a linha da grade passa a abrir o cadastro também pelo **teclado**, com a pista dita em tela. | Markup gravava no cadastro e saía em relatório e planilha (a renderização escapava, o armazenamento não), e `--` no nome é a assinatura que o WAF barra na URL. Documento casando no meio abre o prontuário da pessoa errada — no sistema irmão isso virou card de retorno da Qualidade. O fiscal cadastra em rua, em quarentena: criar de mesa passaria ao largo da conferência do gestor. E a única porta para o registro era invisível e só existia para quem usa mouse. |
| 26/08/2026 | José Nascimento | Cadastro de Permissionário | A concessão do fiscal passa de "sem *inclui* e sem *exclui*" para **"Só consulta"** (RN-10); a tela passa a receber do servidor o que a pessoa pode fazer e a esconder o que ela não tem (RN-10); a foto sai de disco público para **rota autenticada** em disco privado (RN-07-b); falha na gravação da inclusão passa a levar junto o arquivo recém-guardado (RN-07); nome e apelido passam a aceitar **vírgula e E comercial** (RN-11). | Com *opera* ligado, o fiscal alterava o cadastro — e a situação é campo do mesmo formulário, então ele tirava da quarentena o registro que ele mesmo criara em rua: a conferência do gestor deixava de acontecer sem nada registrar que foi pulada. A tela oferecia Incluir e Excluir a quem o servidor recusa, e a recusa só aparecia depois do formulário preenchido. A foto é retrato de cidadão fiscalizado, exibido ao lado do documento dele, e no disco público era servida fora das guardas. E o campo aceita CNPJ, mas a regra de nome recusava a pontuação de razão social — obrigando a adulterar o nome para o cadastro passar. |
| 26/08/2026 | José Nascimento | Cadastro de Permissionário | A provisão de persistência do deploy (volume do compose de homologação e PVC do OKD) passa a cobrir a pasta do disco **privado**, onde a foto mora desde a rota autenticada (RN-07-b). | A provisão continuou apontando só para a pasta pública depois de a foto mudar de disco: cada subida de imagem apagaria as fotos em silêncio, e a falta só apareceria quando alguém abrisse um cadastro antigo. |
