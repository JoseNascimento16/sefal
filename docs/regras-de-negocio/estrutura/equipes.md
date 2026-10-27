# Equipes — quem está em cada equipe

**Onde fica:** Menu → Sistema → Equipes (`/retaguarda/equipes`).
**Quem usa:** administrador e Chefe de Setor (slug `equipes`).

Pedido do dono em 25/09/2026: "preciso de um cadastro de equipes". [Áreas e
Equipes](areas-e-equipes.md) desenha a divisão da cidade (área, bloco de bairros,
turno) e trata a área e a equipe dela como uma coisa só; esta tela diz **quem**
está em cada equipe.

---

## Regras vigentes

### RN-01 — O que uma equipe tem

Código (C2, A1, I1… — único, em maiúsculas), nome (opcional; sem ele, "Equipe
&lt;código&gt;"), **área**, **turno**, **líder** e **fiscais**, e se está ativa.

### RN-02 — O líder é uma conta do setor Líder de Equipe

Só contas **ativas** do setor Líder de Equipe podem ser líder — o setor se dá em
Usuários. O líder É o encarregado (RN-03 de [papéis](../papeis-e-setores.md)):
escolhido o líder, o nome dele passa a ser o encarregado da equipe. É pela equipe
que o líder lidera que sai o **recorte** dele em todas as telas (Caixa de
Entrada, Fiscalizações, arquivos). Uma pessoa pode liderar mais de uma equipe.

### RN-03 — Fiscais são contas do setor Fiscal

Só contas **ativas** do setor Fiscal entram como fiscais. Quem já estava na equipe
e mudou de setor continua aceito ao salvar outro campo — senão a equipe travaria.

### RN-04 — Equipe com histórico não se exclui

A equipe que já recebeu demanda, foi a campo, abriu Fiscalização ou entrou em
operação não é excluída (a recusa diz o motivo): desmarca-se **Ativa**. A sem
histórico sai do cadastro com o vínculo dos fiscais.

### RN-05 — Busca e exportação

Busca por código, área, líder, fiscal ou turno; reconhece "sem líder", "sem
fiscal", "ativas" e "inativas". A exportação traz o turno e os nomes dos fiscais.

---

## Changelog

| Data | Autor | Tela | Alteração | Motivo |
|---|---|---|---|---|
| 25/09/2026 | José Nascimento | Equipes | Nasce a tela (RN-01 a RN-05), com a migration `2026_09_25_110000_concede_a_tela_de_equipes` que dá a tela ao Chefe de Setor nos bancos existentes. | Pedido do dono. |
