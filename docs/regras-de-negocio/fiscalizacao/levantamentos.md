# Levantamentos — o censo de ambulantes por rua

**Onde fica:** Menu → Fiscalização → Levantamentos (`/retaguarda/levantamentos`).
**Quem usa:** administrador; Chefe de Setor e líder de equipe, em **consulta** (slug `levantamentos`).

O levantamento é o censo que o setor fazia em planilha (o cliente entregou um exemplar: Rua Barão
de Mauá, Arenoso, equipes A1 e C1): numa rua, quem trabalha em cada ponto, a atividade e a foto do
equipamento. Quem levanta é o **fiscal, no aplicativo** (ver
[aplicativo-do-fiscal.md](aplicativo-do-fiscal.md), RN-19); esta tela é onde a Retaguarda lê e
exporta. Origem: protótipo do aplicativo (02/09/2026), mantido pelo dono em 13/10/2026 ("pode manter
e fazer no retaguarda"). Não há HU escrita.

---

## Regras vigentes

### RN-01 — Só consulta

A tela não inclui, não edita e não exclui: o levantamento é o registro do que a equipe encontrou na
rua, no dia. Por isso a concessão do Chefe de Setor e do líder é `apenas_leitura`.

### RN-02 — O líder vê só o que as equipes dele levantaram

O recorte é o das outras listas: o líder vê os levantamentos de que alguma equipe dele participou. A
**foto** segue a mesma régua — trocar o número no endereço não abre a foto de outra equipe (403).

### RN-03 — A busca acha a rua ou o ambulante

A busca casa rua, bairro, protocolo, equipes, encarregados, quem levantou **e o nome de cada
ambulante levantado** — "onde a Maria do acarajé foi encontrada?" é pergunta que a tela responde.
Facetas: `hoje` e `sem foto` (levantamento com alguma linha sem foto).

### RN-04 — A ficha é a planilha da rua

Abrir um levantamento mostra o cabeçalho (protocolo, rua e bairro, equipes, encarregados, quem
levantou, quando) e a planilha: item, nome, atividade, referência e foto. Clicar numa linha mostra
CPF/RG, contato, coordenada e a foto (ver e baixar). A planilha exporta sozinha, em PDF, XLSX ou
Word, com todas as colunas — é ela que substitui a planilha manual.

### RN-05 — Os encarregados são os do cadastro

Os encarregados do cabeçalho saem das equipes escolhidas, no servidor, no momento em que o
levantamento é gravado — não do que o aparelho mandou.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 13/10/2026 | José Nascimento | Levantamentos | Nasce a tela de consulta (RN-01 a RN-05), com rota de foto própria e concessão por migration (`2026_10_13_110000_concede_a_tela_de_levantamentos`). | Pedido do dono: manter o levantamento do aplicativo e criar a tela na Retaguarda. |
