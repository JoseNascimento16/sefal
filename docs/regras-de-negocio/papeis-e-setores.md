# Papéis e setores — quem é quem na Retaguarda

**Onde fica:** não é tela. É o catálogo em [`config/retaguarda.php`](../../config/retaguarda.php)
→ `setores`, semeado na tabela `setores` pelo `SetoresSeeder` e usado pelo
[Modo Gerente](modo-gerente.md) como unidade de decisão da matriz de permissões.
**Quem usa:** todo mundo, indiretamente — é o papel que decide o que cada pessoa
vê e pode.

Este doc existe porque o vocabulário dos papéis é **fonte única**: ele aparece no
catálogo, na matriz, no menu, nos textos de dezenas de telas e nos docs de regra.
Sem um dono, cada tela passaria a chamar o mesmo papel por um nome diferente — e
foi exatamente o que a renomeação de 04/09/2026 teve de desfazer.

---

## Regras vigentes

### RN-01 — Quatro papéis, e cada um responde por uma parte do trabalho

| Slug | Nome | O que ele é |
|---|---|---|
| `administrador` | Administrador | Enxerga e administra tudo. Exerce, quando precisa, o trabalho dos outros três — é ele que demonstra o fluxo inteiro e cobre a ausência de alguém. |
| `chefe-de-setor` | Chefe de Setor | **Um só.** A Caixa de Entrada é a mesa dele: recebe tudo o que chega (e-Salvador, Fala Salvador, avulsas), faz a **pré-triagem** (repetições), **encaminha à EQUIPE** — quem recebe é o líder dela — e **devolve/arquiva** o que não é do setor. Recebe de volta o retorno das equipes e **responde ao canal**. Vê o **universo**, sem recorte. |
| `lider-de-equipe` | Líder de equipe | É o **encarregado** do documento de áreas (17/04/2026) **com conta**: uma pessoa por equipe (`equipes.lider_id`). Recebe o que o chefe encaminhou à equipe dele, **direciona aos fiscais** (ou anexa a uma operação) e **recebe de volta** o que a equipe concluiu em campo. Vê **só a(s) equipe(s) que lidera**; as operações, pela área dela(s). |
| `fiscal` | Fiscal | Trabalha em rua, pelo aplicativo. Na Retaguarda ele **consulta** o que é do trabalho dele e não grava nada. |

Uma pessoa pertence a **N setores** (`user_setores`), e acumular papéis **soma**:
quem é Chefe de Setor e líder exerce as duas etapas e **não** é recortado —
o papel que amplia ganha, a mesma regra da união de concessões na matriz.

**Não há `coordenador`.** Os coordenadores trabalham no **e-Salvador** e direcionam
para a caixa do setor (unidade 5382); nunca entram aqui. O setor foi removido em
22/09/2026 (catálogo, vínculos e matriz) pela migration
`2026_09_22_090000_lider_de_equipe_e_fim_do_coordenador`.

### RN-02 — `Chefe de Setor` ≠ administrador do sistema

O Chefe de Setor coordena a entrada e a saída do trabalho do setor; **não** é quem
administra o sistema. Quem distribui acesso é o `administrador`, e só ele.

### RN-03 — Líder de equipe É o encarregado — a mesma pessoa, com conta

Até 22/09/2026 este doc separava "encarregado (rua)" de "Chefe de Setor da área
(sistema)". O dono desfez a separação: quem recebe a demanda dentro do sistema é
o **mesmo** encarregado que chefia a equipe em rua — o **líder de equipe**. A conta
dele nasce no `EstruturaSeeder` com matrícula `lider-<código da equipe>` (ex.:
`lider-c1`) e o vínculo é `equipes.lider_id`. A **área** continua existindo como
**território** (bairros → sugestão de equipe; recorte das operações); o que sumiu
foi o vínculo *pessoa ↔ área* (`areas.chefe_de_setor_id` ficou sem uso). Ver
[Áreas e Equipes](estrutura/areas-e-equipes.md).

### RN-04 — O slug é chave, não rótulo — renomear papel é migration

O slug do setor é a chave por onde três coisas se encontram:

- o catálogo (`setores.slug`);
- o vínculo da pessoa (`user_setores.setor_id`, que aponta para a linha do catálogo);
- a matriz de permissões (`permissoes_setor.setor`, que guarda o slug como **texto**).

Trocar a lista da config sem tocar no banco produz o pior resultado possível, e em
silêncio: o `SetoresSeeder` (`updateOrCreate` por slug) **criaria** dois setores
novos e deixaria os antigos como lixo, com as pessoas vinculadas ao lixo e sem
acesso a nada; e o `PermissoesSetorSeeder` (`firstOrCreate`, de propósito, para
não desfazer o que se decidiu na tela) criaria linhas **novas** com as concessões
de fábrica, abandonando as que foram ajustadas à mão.

Por isso a renomeação vem com migration
(`2026_09_04_090000_renomeia_papeis_para_coordenador_e_chefe_de_setor`): ela
renomeia a **linha existente** no catálogo e na matriz, e o vínculo de cada conta
acompanha sem ser tocado, porque é por `setor_id`.

### RN-05 — O log de permissões NÃO é reescrito

`permissoes_log` registra **atos** — "em tal dia, tal pessoa mudou tal coisa" —, e
naquele dia o papel se chamava `gestor`. Reescrever registro de auditoria para
ficar coerente com o vocabulário de hoje é adulterar a auditoria. O mesmo vale
para as linhas de changelog dos docs de regra.

### RN-06 — Matrícula identifica gente, não cargo

As contas de demonstração continuam sendo `gestor1`, `gestor2`, `gestor3` e
`administrativo1` **depois** da renomeação dos papéis. Desde 22/09/2026 os
`gestorN` são **Chefes de Setor** (a demonstração tem três para mostrar a mesa
sem recorte) e os líderes são `lider-<equipe>` (`lider-a1`, `lider-c1`…), com a
senha inicial igual à matrícula.

### RN-08 — Quatro frentes de entrada, e quem registra cada uma

| Canal | Como chega | Quem registra | Onde |
|---|---|---|---|
| **e-Salvador** (denúncia e licença) | integração (API em reconhecimento; o papel, enquanto isso) | Chefe de Setor | Caixa de Entrada › e-Salvador |
| **Fala Salvador** (156) | **sem API** — só os líderes o acessam | **líder de equipe** | Caixa de Entrada › Fala Salvador (nasce na mesa dele) |
| **e-Protocolo** | atendimento presencial na sede da SEFAL, sem API | Chefe de Setor | Caixa de Entrada › e-Protocolo |
| **Avulsa** | ligação ou e-mail de superior ao chefe | Chefe de Setor | Caixa de Entrada › Avulsas |

A chave é `registro` em `config/demandas.php` (`chefe` ou `lider`): é ela que decide o que a
Caixa oferece e em que tela o formulário aparece. O "Salvador Digital" virou Fala Salvador em
23/09/2026 (`2026_09_23_090000_canal_fala_salvador`).

### RN-07 — Os estados dizem PARA QUEM a demanda foi

`Encaminhada ao líder` (o chefe escolheu a equipe; está na mesa do líder dela) e
`Direcionada aos fiscais` (o líder mandou a equipe ao ponto). Eram `Encaminhada à
área` e `Direcionada à equipe`; a migration
`2026_09_22_090100_renomeia_estados_do_encaminhamento` reescreveu o texto gravado
em `demandas.situacao` e `demanda_tramites.situacao` — o fato registrado é o
mesmo, o vocabulário é o que a tela usa — e preencheu `equipe_id` do que estava
"encaminhado à área" com a equipe daquela área, senão nenhum líder o veria.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 04/09/2026 | José Nascimento | Papéis e setores | Nasce o doc, e com ele a renomeação dos dois papéis de retaguarda: `administrativo` → **`coordenador` / Coordenador** e `gestor` → **`chefe-de-setor` / Chefe de Setor**. A troca alcança o slug (catálogo, matriz e semente), os textos de todas as telas, os docs de regra e os testes, com migration renomeando as linhas já gravadas (RN-04). Matrículas de demonstração e log de permissões ficam como estão (RN-05, RN-06). | Decisão do dono. "Gestor" e "administrativo" são as mesmas palavras que o sistema usa para falar de gestão e de ato administrativo, então o papel ficava sem nome próprio — e na SEMOP quem responde por uma área é o **Chefe de Setor**, e quem tria o que chega dos canais é o **Coordenador**. Sem um doc dono do vocabulário, o nome de cada papel tinha tantos donos quantas telas o citavam. |
| 22/09/2026 | José Nascimento | Papéis e setores | **Reforma dos papéis.** Sai o setor `coordenador` (catálogo, vínculos e matriz, por migration); o **Chefe de Setor passa a ser um só**, dono da Caixa de Entrada e da pré-triagem, sem recorte, e **encaminha à equipe**; nasce o **`lider-de-equipe`** — o encarregado do documento, com conta ligada por `equipes.lider_id` —, que direciona aos fiscais e recebe o retorno, recortado pela equipe. Estados renomeados: `Encaminhada ao líder` e `Direcionada aos fiscais` (RN-07). Helper único de papel: `App\Support\Papel` (substitui `PapelNaArea`). Os `gestorN` da demonstração viram Chefes de Setor; líderes nascem como `lider-<equipe>`. | Decisão do dono em 22/09/2026, depois de conversar com coordenadores, Chefe de Setor e líderes: os coordenadores trabalham no e-Salvador e nunca entrariam aqui; o setor tem um chefe, que recebe tudo e distribui aos líderes; e o encarregado da equipe é quem de fato recebe e devolve trabalho — mantê-lo como "outra pessoa" era ficção do protótipo. |
