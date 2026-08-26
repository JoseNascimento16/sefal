# Cadastro de Permissionário — a identidade de quem é fiscalizado

**Onde fica:** Menu → Fiscalização → **Permissionários** (`/retaguarda/permissionarios`).
**Quem usa:** administrador, gestor e **fiscal**. É a tela-núcleo da fiscalização: sem um
permissionário cadastrado, não há a quem ligar uma vistoria.

O desenho desta tela responde a uma realidade concreta da rua, e não a um cadastro de escritório: **o
alvo muitas vezes não tem documento à mão**, não tem endereço fixo e às vezes não quer dizer o nome.
Uma tela que exigisse CPF simplesmente faria o cadastro em campo não acontecer — e a pessoa
continuaria trabalhando sem constar de lugar nenhum.

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

**A tela de validação dessa fila (aprovar / mesclar duplicado / recusar com motivo) é de entrega
futura.** Hoje a situação é um valor que o gestor troca à mão nesta própria tela, e a busca sabe
achar quem está esperando ("cadastrado em campo", "quarentena").

### RN-04 — O código do cadastro nasce sozinho, pelo gerador de protocolo

`codigo` = `PER` + data + sequencial do dia (`App\Support\Protocolo`), nunca digitado. O gerador
recebe o model e a coluna: assim, se a linha do contador do dia não existir (banco restaurado, carga
anterior), o número **continua de onde o que já está gravado parou**, em vez de recomeçar em 001 e
colidir.

### RN-05 — A atividade apontada tem de estar **em uso**, no cadastro novo

Atividade inexistente é recusada sempre. Atividade **inativada** é recusada no cadastro novo e
**aceita no cadastro que já a apontava**: inativar tira o valor das escolhas de hoje, não reescreve o
passado — senão quem entrasse para corrigir um telefone seria obrigado a trocar o ramo, mudando um
dado que ninguém pediu para mudar. No formulário, a inativada aparece marcada como *(fora de uso)*
apenas no registro que a usa.

### RN-06 — Excluir a atividade em uso é **recusado na Parametrização**

Uma atividade apontada por qualquer permissionário **não pode ser excluída**: a tela de
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

Excluir o cadastro leva o arquivo junto, e o arquivo é apagado **antes** da linha: na ordem inversa,
uma falha deixaria a foto órfã sem ninguém para reencontrá-la.

### RN-08 — Busca inteligente: uma barra só

O campo único interpreta a frase em facetas do domínio + termos livres, sem acento: `regular`,
`irregular`, `cadastrado em campo` / `quarentena`, `sem documento`, `com documento`, `permissão
vencida` — e **o nome de qualquer atividade da parametrização**, que vira faceta em tempo de
execução (a lista é do gestor; escrita na tela, envelheceria no primeiro ramo renomeado). Como
faceta, "bebidas" filtra pelo **ramo**, e não casa por acaso com um apelido que tenha a palavra.

O que sobra casa contra nome, apelido, código, nº da permissão, atividade e situação — e
**também contra o documento sem máscara**, para quem digita `123.456.789-09` achar quem está gravado
como `12345678909`.

Não há chips nem filtros paralelos: é a lei de busca do projeto.

### RN-09 — A listagem exporta o **recorte visível**

PDF / XLSX / DOCX pelo ponto único de exportação, com o que o filtro e a busca deixaram na tela —
nunca o universo, nunca só a página atual. Datas saem em **dd/mm/aaaa** e o documento sai
**formatado**; nada de id, caminho de arquivo ou forma ISO no arquivo.

### RN-10 — Quem entra: o pacote da semente, e o refinamento no Modo Gerente

A tela é controlada pela permissão **`permissionarios`** (primeiro trecho do caminho), semeada para
**administrador, gestor e fiscal**. O fiscal está na lista porque chegar à calçada sem saber quem
está cadastrado é trabalhar às cegas.

⚠️ A semeadura da matriz **não tem granularidade**: ela concede o **pacote** da tela (vê, opera,
inclui, exclui) a cada setor declarado. A intenção para o fiscal é **consulta** — e isso é ajustado
no **Modo Gerente**, marcando "Só consulta" no cruzamento *Fiscal × Permissionários*, o que derruba
operar, incluir e excluir. Não é decisão de código: quem distribui acesso é o gestor, na tela.

---

## Fora de escopo (por ora)

- **Validação do cadastro em quarentena** (aprovar, mesclar duplicado, recusar com motivo). Chega
  junto com a fila que a alimenta — o aplicativo do fiscal. Enquanto isso, a situação é editável à
  mão (RN-03).
- **Prontuário / trilha de movimentação.** O histórico geoespacial nasce das fiscalizações, que
  ainda não existem.
- **Bloqueio de exclusão do permissionário por vínculo.** Hoje nada aponta para ele, então excluir é
  livre (com a confirmação que a tela pede). Quando a cadeia de fiscalização existir, a recusa entra
  no `destroy()` desta tela — como a Parametrização faz com a atividade (RN-06).
- **`client_id`.** A coluna já existe (é o que fará o reenvio da fila do aplicativo reconhecer o
  cadastro que já subiu, em vez de criar um segundo), mas **nenhum caminho de hoje a preenche**: o
  cadastro pela Retaguarda nasce com ela vazia.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/08/2026 | José Nascimento | Cadastro de Permissionário | Criação da tela com listagem, inclusão, alteração e exclusão; documento opcional validado e normalizado quando informado; foto com allowlist de anexos e limpeza do arquivo anterior; situação com quarentena; código pelo gerador de protocolo; busca inteligente e exportação do recorte visível. | É a identidade de quem é fiscalizado: sem ela não há a quem ligar uma vistoria, e o cadastro precisa caber na realidade da rua, onde não há documento à mão. |
| 25/08/2026 | José Nascimento | Parametrização → Atividades do Ambulante | Exclusão passa a ser recusada quando algum permissionário aponta a atividade, dizendo quantos são e mandando inativar. | Excluir deixaria os cadastros apontando para o nada, e quem responderia seria a chave estrangeira do banco — com um erro cru na cara de quem está na tela. |
