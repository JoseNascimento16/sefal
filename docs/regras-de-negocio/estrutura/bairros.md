# Bairros — o catálogo de bairros da cidade

**Onde fica:** Menu → Sistema → Bairros (`/retaguarda/bairros`).
**Quem usa:** administrador e Chefe de Setor (slug `bairros`).

Pedido do dono em 25/09/2026: "crie também a tela Bairros para cadastro de
bairros, para termos um controle melhor. Bairro com Área não se exclui."

---

## Regras vigentes

### RN-01 — O nome é único sem acento

"Imbuí" e "Imbui" são o mesmo bairro (`Area::chaveDeBairro`, que usa a mesma
conversão em qualquer sistema operacional). O cadastro recusa o repetido dizendo
qual já existe.

### RN-02 — A coordenada é o ponto no mapa

Latitude e longitude dentro de Salvador (a faixa barra coordenada trocada ou
digitada errada). Sem coordenada, o bairro existe nas listas mas o mapa de calor
não o desenha.

### RN-03 — Renomear alcança áreas e operações

As áreas e as operações citam o bairro pelo nome: renomear aqui renomeia lá (e a
coordenada nova vale nas áreas). A demanda antiga guarda o nome com que chegou.

### RN-04 — Bairro em área não se exclui

A recusa diz em que áreas ele está. O caminho é tirá-lo da área (em
[Áreas](areas.md)) ou desmarcar **Ativo** — inativo, ele deixa de ser oferecido no
cadastro de Áreas.

### RN-05 — Áreas e catálogo andam juntos

O cadastro de Áreas oferece os bairros ativos daqui; o bairro novo acrescentado
numa área entra no catálogo. O catálogo nasceu com os bairros que as áreas já
citavam (migration `2026_09_25_170000_cadastro_de_bairros`), e a semeadura da
estrutura o completa.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/09/2026 | José Nascimento | Bairros | Nasce o catálogo e a tela (RN-01 a RN-05). A chave do bairro passa a usar `Str::ascii`: no Windows, o `iconv` trocava "í" por "'i" e separava bairros iguais. | Pedido do dono. |
