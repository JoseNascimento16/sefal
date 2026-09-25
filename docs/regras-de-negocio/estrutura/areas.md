# Áreas — as áreas e os bairros de cada uma

**Onde fica:** Menu → Sistema → Áreas (`/retaguarda/areas`).
**Quem usa:** administrador e Chefe de Setor (slug `areas`).

Pedido do dono em 25/09/2026: "cadastro de Área com possibilidade de definição
dos bairros de cada área, selecionáveis via chips igual na tela de operações".
Junto com [Equipes](equipes.md), substitui no menu a antiga [Áreas e
Equipes](areas-e-equipes.md), que segue pelo endereço.

---

## Regras vigentes

### RN-01 — O que uma área tem

Nome, região, o que ela cobre (**bairros**, **corredores** ou a **cidade
inteira**), turno, se está ativa e os **bairros**.

### RN-02 — Os bairros em chips

Os chips oferecem todo bairro já conhecido (de qualquer área), com busca; bairro
que ainda não existe entra digitado. Bairro em mais de uma área é caso normal
(divisa). Ao salvar, sai o bairro desmarcado e entra o marcado; o que fica
**mantém a coordenada** que o mapa usa, e o novo herda a coordenada de outra área
que já tenha o bairro.

### RN-03 — Para que servem os bairros

É pelo bairro do endereço que a demanda recebe a **sugestão de equipe**; é deles
que a operação **marca os bairros sozinha** ao escolher a área; e é por eles que
sai o **recorte do líder** nos mapas.

### RN-04 — Área em uso não se exclui

Área com equipe, demanda ou operação não é excluída (a recusa diz o motivo):
desmarca-se **Ativa**.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/09/2026 | José Nascimento | Áreas | Nasce a tela (RN-01 a RN-04), com a migration `2026_09_25_140000_concede_a_tela_de_areas`. | Pedido do dono. |
