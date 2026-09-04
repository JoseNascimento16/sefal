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
| `coordenador` | Coordenador | Recebe o que chega de fora e faz a **triagem**: lê a denúncia das ouvidorias, registra o que vem em papel na Caixa de Entrada e **encaminha à área**. Vê o universo, porque não se tria o que não se vê. |
| `chefe-de-setor` | Chefe de Setor | Responde por uma **área** de fiscalização: **direciona** o que foi encaminhado a ela (equipe ou operação), **recebe de volta** o que a equipe concluiu em campo e valida o cadastro que nasceu em rua. Vê **só a área dele**. |
| `fiscal` | Fiscal | Trabalha em rua, pelo aplicativo. Na Retaguarda ele **consulta** o que é do trabalho dele e não grava nada. |

Uma pessoa pertence a **N setores** (`user_setores`), e acumular papéis **soma**:
quem é Coordenador e Chefe de Setor exerce as duas etapas e **não** é recortado
por área — o papel que amplia ganha, a mesma regra da união de concessões na
matriz.

### RN-02 — `Coordenador` ≠ administrador do sistema

O Coordenador faz o trabalho de coordenação da entrada de demanda; **não** é quem
administra o sistema. Quem distribui acesso é o `administrador`, e só ele.

### RN-03 — `Chefe de Setor` ≠ `encarregado`

São duas pessoas diferentes, e confundi-las é fácil: o **encarregado** chefia a
equipe **em rua** (vem do documento de áreas do cliente); o **Chefe de Setor**
responde pela área **dentro do sistema**. Ver
[Áreas e Equipes](estrutura/areas-e-equipes.md) RN-01b.

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
`administrativo1` **depois** da renomeação dos papéis. A pessoa que responde pela
Área 5 é a mesma antes e depois de o cargo mudar de nome, e trocar a matrícula
quebraria o vínculo com a área em `config/prototipo_estrutura.php`, que casa pelo
`login`.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 04/09/2026 | José Nascimento | Papéis e setores | Nasce o doc, e com ele a renomeação dos dois papéis de retaguarda: `administrativo` → **`coordenador` / Coordenador** e `gestor` → **`chefe-de-setor` / Chefe de Setor**. A troca alcança o slug (catálogo, matriz e semente), os textos de todas as telas, os docs de regra e os testes, com migration renomeando as linhas já gravadas (RN-04). Matrículas de demonstração e log de permissões ficam como estão (RN-05, RN-06). | Decisão do dono. "Gestor" e "administrativo" são as mesmas palavras que o sistema usa para falar de gestão e de ato administrativo, então o papel ficava sem nome próprio — e na SEMOP quem responde por uma área é o **Chefe de Setor**, e quem tria o que chega dos canais é o **Coordenador**. Sem um doc dono do vocabulário, o nome de cada papel tinha tantos donos quantas telas o citavam. |
