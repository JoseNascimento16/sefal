# Reforma dos papéis e das três frentes de entrada

> Origem: conversa do dono com coordenadores, chefe de setor e líderes de equipe
> (22/09/2026). Decisões confirmadas na mesma data: líder de equipe = encarregado
> do documento das áreas, um por equipe; a pré-triagem continua (repetição ainda
> chega); o setor `coordenador` sai da Retaguarda.

## O que muda de entendimento

| Quem | Onde trabalha | Papel no SEFAL |
|---|---|---|
| Coordenadores | **e-Salvador**, só lá | **nenhum** — direcionam de lá para a caixa do SEFAL (unidade 5382) |
| Chefe de setor | Retaguarda | **1 pessoa**, vê tudo: pré-tria, encaminha aos líderes, fecha, responde ao e-Salvador |
| Líderes de equipe | Retaguarda (+ Fala Salvador) | recorte = **a própria equipe**: direcionam aos fiscais, recebem o retorno, conversam com o chefe |
| Fiscais | PWA | fiscalizam |

### As três frentes

1. **e-Salvador** — coordenador direciona (lá) → caixa do SEFAL → *pré-triagem* →
   Caixa do chefe → encaminha ao líder → líder direciona aos fiscais → retorno
   → líder ↔ chefe até concluir → **chefe responde ao e-Salvador** (trâmite no
   processo; hoje é escrita proibida — fica registrado localmente e desligado).
2. **Fala Salvador** (substitui "Salvador Digital") — sem API por enquanto.
   Cadastro **manual, pelo líder**, só para registro; ele continua respondendo no
   Fala Salvador. O formulário específico vem depois (o dono tem fotos/documentos).
3. **Avulsa** — chefe recebe ligação/e-mail de superior → registra → encaminha ao
   líder → retorno → **chefe cria processo no e-Salvador** (escrita; fica como
   ato registrado localmente e desligado).

## O que colide com o que existe

| Hoje | Passa a ser |
|---|---|
| setor `coordenador` tria; a Caixa/Pré-Triagem é "a mesa dele" | setor removido; Caixa/Pré-Triagem são do **chefe de setor** |
| `chefe-de-setor` é **um por área** (`areas.chefe_de_setor_id`), recortado por área | **um só**, sem recorte — vê tudo |
| `Equipe.encarregado` é **texto** | vira **usuário** (`equipes.lider_id`) com setor `lider-de-equipe`; recorte por equipe |
| `PapelNaArea` (áreas do chefe) | `Papel` (equipes do líder; chefe e admin sem recorte) |
| estados `Encaminhada à área` / `Direcionada à equipe` | `Encaminhada ao líder` (chefe → líder) / `Direcionada aos fiscais` (líder → fiscais) |
| trâmite `PAPEL_COORDENADOR` | `PAPEL_CHEFE_DE_SETOR` para o que era do coordenador; novo `PAPEL_LIDER` |
| canal `salvador-digital` (integração) | canal `fala-salvador` (**balcão**, manual, pelo líder) |
| canais `nova-licenca` / `oficio` | + canal `avulsa` (ligação/e-mail de superior, registrado pelo chefe) |
| `DecisaoDaChefia::devolverAoCoordenador` | `devolverAoChefe` (líder → chefe; volta a `Recebida`) |
| Denúncias: etapas `triagem` (coordenador) / `direcionamento` (chefe) | `encaminhamento` (chefe) / `direcionamento` (líder) |

## Ordem de execução (cada passo é um commit verde)

1. **Papéis** — setor `lider-de-equipe`; migration que remove `coordenador`
   (catálogo, vínculos, matriz — mesmo padrão da migration de 04/09);
   `equipes.lider_id`; `EstruturaSeeder` cria a conta do líder a partir do
   encarregado (matrícula `lider-<equipe>` em minúsculo; nome do documento);
   `Papel` substitui `PapelNaArea` nos 6 pontos; `PrepararDemonstracao` com as
   contas novas (`chefe`, `lider-c2`…); menu/permissões; testes.
2. **Estados e trâmites** — renomear estados (migration de texto em `demandas`
   e `demanda_tramites`); `TriagemDeDemandas` / `DecisaoDaChefia` com os papéis
   certos; Caixa e Denúncias com as etapas novas; textos das telas.
3. **Canais** — `fala-salvador` no lugar de `salvador-digital` (constante,
   config, rota, página, seeders, testes); canal `avulsa`; quem cadastra o quê.
4. **Resposta ao e-Salvador** — ato do chefe sobre demanda concluída de
   integração: registra o trâmite local; a chamada à API fica atrás de
   `ESALVADOR_LIGADA=false` (PEND).
5. **Docs** — fluxo em `docs/regras-de-negocio/`, `PENDENCIAS.md`,
   `acompanhamento_requisitos.php`.
6. **Demo** — snapshot regerado com as contas novas; Render.

## Decisões tomadas por mim (avisar se discordar)

- **A área continua existindo** como território (bairros → equipe sugerida) e
  como agrupador das equipes. Só deixa de ter chefe próprio. A coluna
  `chefe_de_setor_id` fica, sem uso — apagar coluna é DDL no Oracle e não há
  ganho.
- **O líder é um usuário por equipe, e a conta nasce da estrutura** (como já
  acontece com os fiscais). Matrícula `lider-<código da equipe>` até o cliente
  informar as reais — matrícula identifica gente e será trocada sem mexer no
  vínculo.
- **Renomeio os estados no banco** em vez de reinterpretar os nomes antigos:
  "Encaminhada à área" mentiria numa tela em que o chefe escolhe um líder.
- **Avulsa é canal, não fiscalização solta.** O que hoje se chama
  "fiscalização avulsa" (o fiscal registra algo que encontrou em rua) continua
  existindo e é outra coisa — origem `avulsa` da fiscalização, não da demanda.
