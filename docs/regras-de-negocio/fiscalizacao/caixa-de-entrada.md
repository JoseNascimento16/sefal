# Caixa de Entrada — a mesa do Chefe de Setor

**Onde fica:** Menu → Fiscalização → Caixa de Entrada (`/retaguarda/caixa-de-entrada`).
**Quem usa:** o Chefe de Setor (a Caixa é a mesa dele) e o administrador. **O líder e o fiscal não entram** (ver RN-08).

> ## 🔁 22/09/2026 — os papéis mudaram; leia [Papéis e setores](../papeis-e-setores.md) antes deste doc
>
> Depois de ouvir coordenadores, Chefe de Setor e líderes, o dono redesenhou quem faz o quê:
> os **coordenadores não usam o SEFAL** (trabalham no e-Salvador e mandam para a caixa do setor);
> o **Chefe de Setor é UM SÓ**, recebe tudo na Caixa de Entrada e **encaminha à EQUIPE** — quem
> recebe é o **líder da equipe** (o encarregado do documento de 17/04/2026, agora com conta); o
> líder **direciona aos fiscais** e recebe o retorno; o chefe responde ao canal. O setor
> `coordenador` foi removido. Onde este doc diz "Coordenador", leia **Chefe de Setor**; onde diz
> "Chefe de Setor da área", leia **líder da equipe**; `Encaminhada à área` virou
> **`Encaminhada ao líder`** e `Direcionada à equipe` virou **`Direcionada aos fiscais`**.

> ## ⚠️ ESTA TELA É UM PROTÓTIPO
>
> Ela existe para o dono **olhar a forma e aprovar o fluxo** antes de o módulo virar tabela,
> migration e regra de produção. **Nada é gravado em banco:** as demandas de partida vêm de
> [`config/prototipo_caixa_entrada.php`](../../../config/prototipo_caixa_entrada.php) e o que a
> pessoa registra, encaminha ou devolve vive na **sessão** dela
> ([`App\Support\Prototipo\CaixaDeEntradaFicticia`](../../../app/Support/Prototipo/CaixaDeEntradaFicticia.php)).
>
> A tela **diz isso em cima**, com o selo "PROTÓTIPO · DADOS FICTÍCIOS": protótipo que se disfarça
> de sistema pronto vira decisão tomada por engano — alguém vê a grade cheia, conclui que o módulo
> está no ar e planeja em cima disso.
>
> **Origem:** reunião com o cliente de 02/09/2026 (`docs/cenario-2026-09-02-reuniao-cliente.md`,
> na branch `ferramental`). Não há HU escrita: a linha em `config/acompanhamento_requisitos.php`
> nasce `hu_status => 'nao'` declarando essa origem.

O sistema não é uma ilha: **ele recebe demanda de fora**. Hoje ela chega em **papel** — o
e-Salvador e o Fala Salvador entregam documento impresso ao Chefe de Setor, e o pedido
de nova licença chega como processo. Esta tela é a porta por onde isso entra, e de onde sai
**trabalho dirigido** para as equipes de campo.

---

## Regras vigentes

### RN-01 — O cadastro manual é requisito, não gambiarra

O Chefe de Setor **digita** o que chegou em papel: origem, número do documento de origem, data de
recebimento, requerente, endereço, bairro, assunto, descrição e o arquivo digitalizado.

A adaptação para API (e-Salvador e 156) vem depois, e **o cadastro manual permanece**: papel não
desaparece por decreto, e uma tela que só soubesse receber API deixaria o setor sem como registrar
o que chega na mão dele.

### RN-02 — Quatro origens, lista fechada

`e-Salvador`, `Fala Salvador`, `Nova licença` e `Ofício`. É de onde o documento veio, não texto
livre — e é essa distinção que um dia separa o que chega por integração do que é interno.

O catálogo vem do **servidor**, que é quem valida a escolha. Escrito também na tela, um dia os dois
discordariam e a tela ofereceria uma opção que o servidor recusa.

### RN-03 — Denúncia pode ser ANÔNIMA, e ser anônima é escolha explícita

É a realidade do Fala Salvador e do e-Salvador: muita denúncia chega sem quem a fez. Então o requerente é
opcional — **mas só quando a demanda é marcada como anônima**. Sem a marca, o nome é obrigatório.

A marca existe justamente para que "anônima" nunca seja o resultado de um campo esquecido: na
grade, a demanda anônima aparece dita como tal, nunca como um espaço em branco.

### RN-04 — O BAIRRO define a equipe; o sistema sugere, o Chefe de Setor confirma

A equipe responsável é derivada do bairro, pela estrutura Área › Equipe
([Áreas e Equipes](../estrutura/areas-e-equipes.md)). Ao escolher o bairro, a tela mostra a
sugestão em palavras — *"Bairro Mussurunga → sugerida Equipe C1 · Área 5 (Boca do Rio)"* — com o
encarregado, e deixa trocar.

**A sugestão nunca decide sozinha.** Um bairro pode pertencer a **duas áreas** (Mussurunga,
Patamares, Jardim das Margaridas), e aí as duas respostas estão igualmente certas: nesse caso a
tela avisa quais são as outras áreas que cobrem o bairro e pede a confirmação de quem conhece o
ponto. Escolher uma em silêncio esconderia a decisão de quem tem de tomá-la.

Trocar o bairro **refaz** a sugestão de equipe: mantida, ela continuaria apontando para a área do
bairro anterior.

### RN-05 — Duas saídas, e as duas registram a demanda

| Saída | O que acontece |
|---|---|
| **Registrar e encaminhar** | A demanda entra na caixa como `Encaminhada` à equipe escolhida e vira **fiscalização dirigida**: aparece no aplicativo dos fiscais daquela equipe. Pode levar uma orientação ("vistoriar depois das 21h"). |
| **Registrar e devolver/arquivar** | A demanda entra na caixa como `Devolvida` (volta ao remetente) ou `Arquivada`, com **motivo** de lista e **justificativa** por escrito. |

**Recusar não é deixar de registrar.** A demanda entra na caixa de qualquer maneira — inclusive
quando não é atendida. É o que permite responder depois "o que foi feito com aquele documento?".

Por isso as duas saídas são **um único** ponto de gravação (`POST /retaguarda/caixa-de-entrada`):
dois endpoints obrigariam a repetir a validação do documento nos dois, e um dia só um deles teria a
regra nova.

### RN-06 — Devolver ou arquivar exige justificativa POR ESCRITO

É ato administrativo. O **motivo** é de lista (para o relatório poder somar por motivo) e a
**justificativa** é texto livre obrigatório, com mínimo de 15 caracteres — o motivo genérico não
conta o caso a quem abrir a demanda meses depois.

A conferência é do **servidor**, não só do formulário: esconder o campo na tela não impede ninguém
de mandar a requisição sem ele.

Encaminhar uma demanda que havia sido devolvida **limpa** o motivo e a justificativa anteriores: ela
voltou ao fluxo, e deixar o texto antigo pendurado faria a tela mostrar "encaminhada" com a
justificativa de quem a devolveu.

### RN-07 — Toda decisão deixa linha no TRÂMITE

Cada demanda guarda o percurso: `Recebida` → `Triada e encaminhada` → (`Em campo`) → e, quando é o
caso, `Devolvida` / `Arquivada`. Cada linha traz **quem, quando e o quê**.

Sem o rastro, "devolvida" é uma palavra sem autor. O passo `Em campo` aparece dito como **próximo
passo**, e não como fato: o protótipo não simula a vistoria, e prometer o que não aconteceu é pior
que não mostrar nada.

### RN-08 — O fiscal não entra nesta tela

Triar o que chega, encaminhar e devolver com justificativa é **ato administrativo**. A demanda
encaminhada chega ao fiscal pelo aplicativo, **já dirigida**.

Dar-lhe a caixa permitiria escolher o próprio trabalho e arquivar o que não quisesse atender — é a
mesma razão pela qual o cadastro nascido em rua entra em quarentena. A concessão inicial é
`administrador` e `chefe-de-setor`, semeada em [`config/retaguarda_menu.php`](../../../config/retaguarda_menu.php);
depois disso quem concede e quem tira é o Modo Gerente.

### RN-09 — A busca é o filtro ÚNICO

Uma barra só, ampla, acento-insensível, com exemplos clicáveis. Ela interpreta a frase em facetas do
domínio (situação, origem, anônima, prazo vencido, recebidas hoje) mais termos livres — *"denúncias
anônimas do Fala Salvador com prazo vencido"* funciona.

Os números do cabeçalho (na caixa · a triar · encaminhadas · retornadas) são o **resumo da mesma
lista** que a grade desenha, e clicados **escrevem a faceta na busca**. Não são um segundo filtro:
com dois donos, um dia a tela mostraria um recorte e o resumo contaria outro.

### RN-10 — Prazo, e o que vencido significa

O prazo é informado ou, em branco, calculado como **10 dias** a partir do recebimento (número de
protótipo — o prazo real de cada canal é pergunta aberta ao cliente).

Demanda **aguardando triagem** com prazo passado ganha a marca laranja na ponta da linha, o selo
"vencido" na coluna do prazo e um aviso acima da grade com a contagem. Demanda já encaminhada ou já
retornada não é acusada: o prazo era para a decisão do Chefe de Setor, e ela foi tomada.

### RN-11 — A listagem exporta o recorte visível

Como toda listagem da Retaguarda: `<BotaoExportar>` pelo ponto único
`POST /retaguarda/exportar-listagem`, em PDF, XLSX e DOCX. Sai o **recorte visível inteiro** (o que
a busca deixou), não a página atual nem o universo. As datas saem em **dd/mm/aaaa**.

### RN-12 — "Reiniciar demonstração" existe porque é protótipo

O botão devolve a caixa ao estado de partida, e só aparece depois de a sessão mexer em algo. **No
sistema real esta rota não existe:** caixa de entrada não se reinicia.

---

## Fora de escopo (por ora)

- **Integração com o e-Salvador e o 156** (PEND-021 é a irmã disto, para o SGCI). Enquanto não
  houver contrato, o registro é manual — e continuará existindo depois.
- **Numeração oficial do protocolo.** O protótipo gera `CXE-NNNN` por conta própria; no sistema real
  a numeração sai de `App\Support\Protocolo::proximo()`, que é a fonte única e vive no banco.
- **Upload real do documento digitalizado.** Hoje entra só o nome do arquivo. No sistema o campo
  será o envio do PDF, pela Rule `ArquivoSeguro` como os demais anexos.
- **Canal de devolução ao remetente.** O sistema registra a devolução; por qual canal ela chega de
  volta ao e-Salvador/156 é pergunta aberta ao cliente.
- **O desfecho da vistoria dentro do trâmite.** Depende de o módulo de Fiscalização estar ligado a
  esta caixa.
- **Prazo por canal.** Hoje é um número só, parametrizável no arquivo de dados do protótipo.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 09/09/2026 | José Nascimento | Caixa de Entrada | **A grade ficou enxuta** (padrão [`docs/padroes/listagem-clean.md`](../../padroes/listagem-clean.md)): de 9 colunas para **Protocolo · Recebida · Bairro · Situação · Prazo**. O **assunto** (texto livre) saiu da célula, e com ele origem, nº do documento de origem, requerente e equipe — os cinco já apareciam por inteiro na ficha do clique, e todos seguem no arquivo exportado. "Vencido" virou cor no texto, não um segundo chip. ⚠️ **Só a apresentação mudou:** o fluxo, os dados de `config/prototipo_caixa_entrada.php`, as ações e as abas ficaram como estavam. | Ordem do dono (09/09/2026): _"as listagens estão muito poluídas, muita informação quebrando linha de forma irregular… deixe a informação detalhada para quando o usuário clicar"_. A régua e o porquê de cada item ficam em [`docs/padroes/listagem-clean.md`](../../padroes/listagem-clean.md) — este doc aponta para lá em vez de repetir a régua. |
| 02/09/2026 | José Nascimento | Caixa de Entrada | Nasce o módulo, como **protótipo**: grade com busca inteligente e exportação, cadastro da demanda com as duas saídas (encaminhar / devolver-arquivar), sugestão de equipe pelo bairro com o caso do bairro compartilhado, e o trâmite de cada demanda. | Decisão da reunião com o cliente de 02/09/2026: o sistema recebe demanda de fora (e-Salvador, Fala Salvador, pedido de nova licença) e o administrativo precisa de onde registrar, triar e recusar com justificativa. Entregue como protótipo para o dono aprovar a forma antes de virar tabela e regra. |
| 04/09/2026 | José Nascimento | Caixa de Entrada | Os papéis passam a se chamar **Coordenador** (era `administrativo`) e **Chefe de Setor** (era `gestor`) — slug inclusive, com migration renomeando catálogo e matriz. Ver [Papéis e setores](../papeis-e-setores.md). | A tela é o trabalho do papel que tria: o rótulo dele aparece no texto de abertura, no aviso das duas saídas e na concessão da matriz. |
| 22/09/2026 | José Nascimento | Caixa de Entrada | A Caixa passa a ser a **mesa do Chefe de Setor** (um só, sem recorte): o destino do encaminhamento é a **EQUIPE**, obrigatória, e o passo registra o **líder** que a recebe (`Líder da equipe`); a área vem junto porque é a da equipe. Sai a saída "direcionar já à equipe" (era o chefe de área pulando etapa) — quem direciona aos fiscais é o líder, na tela de Denúncias. Pré-triagem continua aqui, como passo do chefe, sem recorte por área. | Reforma dos papéis de 22/09/2026 — ver [Papéis e setores](../papeis-e-setores.md). |
| 23/09/2026 | José Nascimento | Caixa de Entrada | O formulário oferece só os canais que o **chefe** registra (`registro = chefe` em `config/demandas.php`): e-Salvador (papel), Nova licença, Ofício e a nova **Avulsa** — ligação ou e-mail de superior pedindo uma ação (requerente identificado, com anexo, sem agrupamento). O **Fala Salvador saiu da Caixa**: é digitado pelo líder, na tela do canal. A busca reconhece "avulsa", "ligação" e "e-mail" como faceta de origem. | Decisão do dono em 22/09/2026 — três frentes de entrada. |
| 23/09/2026 | José Nascimento | Caixa de Entrada | **A avulsa concluída vira processo no e-Salvador (estrutura).** No detalhe da demanda avulsa concluída, o chefe registra a **abertura do processo** (resultado + nº do processo obrigatório, porque a integração não abre processo: ele abre à mão e o número prova) — `POST caixa-de-entrada/{demanda}/responder-ao-canal`, mesmo ato da resposta ao e-Salvador (`RetornoAoCanal`). | Decisão do dono em 22–23/09/2026. |
| 24/09/2026 | José Nascimento | Caixa de Entrada | **Aba Pré-Triagem escondida** (`MOSTRAR_PRE_TRIAGEM = false` na tela). Nada foi removido: servidor, varredura e agrupamento seguem; voltar a mostrar é trocar a constante. Enquanto escondida, o que está `Em pré-triagem` não aparece em aba nenhuma da Caixa. | Dono incerto sobre a necessidade da etapa. |
| 24/09/2026 | José Nascimento | Caixa de Entrada | Esta tela (a "mesa" do chefe, antes "Geral") **saiu do menu**: a Caixa de Entrada virou a pasta com as quatro caixas de canal — ver [Denúncias](denuncias.md), changelog de 24/09. Ela continua existindo **só para o chefe** (middleware `SoChefeDeSetor`; outro papel é mandado à caixa do e-Salvador com o motivo), porque guarda a pré-triagem escondida; sai quando o dono decidir sobre a pré-triagem. O canal **Ofício** ficou sem caixa nas quatro frentes: os registros antigos continuam no banco. | Decisão do dono em 24/09/2026. |
