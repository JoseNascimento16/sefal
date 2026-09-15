# Integração com o e-Salvador — estudo do contrato

> Fonte: *Documentação API eSalvador — Completa* (API REST v1.0, SEMGE, consultada em 28/04/2026).
> Configuração: [`config/esalvador.php`](../../config/esalvador.php) · chaves no `.env`.

## O achado que governa todo o resto

**A API não tem denúncia.** Ela tem **processo**, **requerente** e **trâmite**.

O e-Salvador é o sistema de processo administrativo eletrônico da Prefeitura, feito
pela SEMGE para "criação, tramitação e arquivamento de documentos oficiais". O que
chega à SEMOP como denúncia de ambulante é um **processo** como qualquer outro,
distinguido apenas pela **classificação**: `grupo → assunto → subassunto`.

Três consequências práticas, e nenhuma é detalhe de implementação:

1. **A integração não "busca denúncias": ela lê a caixa de entrada de uma unidade
   e filtra pela classificação.** Quais ids são nossos é pergunta para a SEMOP —
   estão em `ESALVADOR_GRUPOS` / `ESALVADOR_ASSUNTOS` / `ESALVADOR_SUBASSUNTOS`,
   vazios até alguém confirmar. Chutar id de catálogo alheio importa processo de
   outro setor, e o erro só aparece quando um fiscal for à rua errada.

2. **O relato não vem em campo próprio.** Vem em `descricao`, **HTML**, dentro do
   último trâmite. É texto livre escrito por gente.

3. **O ENDEREÇO DO FATO não existe na API.** Em lugar nenhum. Há endereço do
   *requerente* (`cep`, `logradouro`, `numero`, `bairro`, `cidade`, `uf`) — que é
   onde mora quem reclamou, não onde está o ambulante. Onde o fato acontece está
   dentro do texto da `descricao`, junto com tudo o mais.

O item 3 é o mais caro para nós: a varredura de repetições da pré-triagem pesa
**bairro, logradouro, número e distância** — nada disso chega estruturado. Ver
"O que isso cobra da pré-triagem", no fim.

---

## Autenticação

`POST https://apiesalvador.salvador.ba.gov.br/api/login`

```json
{ "nome": "…", "password": "…", "token": "…" }
```

Resposta: `access_token` (JWT), `token_type: Bearer`, `expires_in: 3600`.
Depois, `Authorization: Bearer <token>` em toda chamada.

As três credenciais vêm de lugares **diferentes**:

| Credencial | De onde vem | Expira? |
|---|---|---|
| `nome` | equipe do e-Salvador (SEMGE) | não |
| `password` (secret key) | equipe do e-Salvador (SEMGE) | não |
| `token` | gerado por uma **pessoa** no e-Salvador: Ajuda → Integração Token | **não**, e **não fica guardado lá** — perdeu, gera outro e o antigo morre na hora |

> ⚠️ **O IP da nossa aplicação é cadastrado junto com as credenciais.** A
> documentação menciona isso uma única vez, e o que ela diz é só isto:
>
> > "As credenciais de acesso nome e password, **bem como o endereço de IP da
> > aplicação que vai acessar a API**, devem ser previamente configuradas junto à
> > equipe de desenvolvimento do eSalvador - SEMGE."
>
> **O que a documentação NÃO diz:** se uma chamada vinda de IP não cadastrado é
> recusada, nem com qual código. Pode ser allowlist com bloqueio efetivo, pode ser
> só registro cadastral do provisionamento. Não assuma nenhum dos dois — **teste**
> assim que houver credencial, e anote aqui o que de fato aconteceu.
>
> A distinção importa na prática: se for bloqueio, o IP de saída da nossa
> aplicação tem de ser estável e conhecido (no OKD, o IP de egress do cluster;
> em máquina de dev, o da VPN da Prefeitura), e isso muda a conversa com a SEMGE.

O JWT dura 1 hora; guardamos por 3300s (`ESALVADOR_TOKEN_VALIDO_POR`) para nunca
apresentar um token que expira no meio do caminho.

---

## Como uma denúncia chegaria até nós

Nenhum endpoint entrega tudo. São três chamadas por processo:

### 1. `PUT /seleciona-caixa` — escolher de quem é a caixa

```json
{ "unidade": "5514" }
```

A API trabalha com **uma caixa por vez**. É estado do lado deles, então a seleção
precede a leitura em toda rodada — não dá para presumir que continua onde ficou.

### 2. `GET /caixa-processos?…` — o que está na caixa

Resposta paginada (`current_page`, `data[]`, `per_page` até 100):

```json
{
  "rn": "1",
  "numero_ano": "1207/2022",
  "grupo_assunto_subassunto": "SISTEMA ESALVADOR/DESENVOLVIMENTO DE SOFTWARE/TESTE DE SOFTWARE",
  "procedencia": "SEMGE/ESAL/DEV II",
  "instruido_por": "DAIANA SANTOS SANTANA",
  "status": "Não Lido",
  "prioridade": "NORMAL",
  "data_atualizacao": "2022-08-18 10:10:42"
}
```

**Repare no que NÃO está aí:** relato, requerente, endereço, documento. A caixa é
uma lista de ponteiros — serve para saber *o que existe*, não *o que é*.

`status` filtra por não lido (`0`) / lido (`1`); `prioridade` distingue urgente.
Consulta por período aceita no máximo **90 dias** e exige `data_inicio` **e**
`data_fim` juntas.

### 3. `GET /consulta-ultimo-tramite/{numero}/{ano}` — o conteúdo

```json
{
  "numero_processo": "33270",
  "ano": "2020",
  "assunto": "479",
  "grupo": "15",
  "descricao": "<p>…o relato, em HTML…</p>",
  "unidade_origem": "5513",
  "unidade": "5604",
  "natureza": "1",
  "tipo_requerente": "3",
  "requerentes": [ { "documento": "…", "nome": "CARLOS AUGUSTO DE ARAUJO ALVES" } ]
}
```

É **aqui** que estão o relato e quem reclamou. `GET /consulta/{numero}/{ano}`
complementa com `identificador`, `numero_hash`, datas e `status_confidencial`.

### Ainda: anexos e histórico

- `GET /visualiza/{numero}/{ano}` → PDF do processo em base64;
- `GET /download-etcm/{numero}/{ano}` → pacote em base64;
- `GET /historico-tramitacao/{numero}/{ano}` → todos os trâmites, com unidades e datas.

---

## Como o resultado da fiscalização VOLTA

Esta é a metade que fecha o ciclo — e que faz a pré-triagem valer a pena:

| Ato | Endpoint |
|---|---|
| Responder o que a fiscalização apurou | `POST /criar-tramite` (`descricao` + `nome_arquivo[]`/`arquivo[]` em base64) |
| Empurrar o processo adiante | `PUT /tramita-processo` |
| Encerrar | `POST /arquiva-processo` |

**É por aqui que "uma fiscalização responde a dez denúncias" se cumpre:** o
registro que foi a campo produz UM resultado, e a integração escreve o mesmo
trâmite em cada um dos processos agregados. Cada cidadão recebe a resposta no
protocolo dele, sem ninguém redigir dez vezes.

---

## Mapa de campos — e-Salvador → `demandas`

| Nosso campo | De onde vem | Observação |
|---|---|---|
| `canal` | fixo `e-salvador` | — |
| `entrada` | fixo `integracao` | — |
| `numero_origem` | `numero_ano` (ou `codigo`, o hash) | é a **chave de idempotência**: a tabela já tem unique `(canal, numero_origem)`, então reler a caixa não duplica |
| `recebida_em` | `data_atualizacao` / `data_criacao` | — |
| `assunto` | `assunto_descricao` (ou `grupo_assunto_subassunto`) | é a classificação DELES, não um título escrito por alguém |
| `relato` | `descricao` do último trâmite | **HTML** — precisa virar texto antes de ir para a tela e para a varredura |
| `requerente` / `documento` | `requerentes[0].nome` / `.documento` | ⚠️ processo pode ter **vários** requerentes |
| `anonima` | derivado: sem requerente | — |
| `logradouro`, `numero`, `bairro`, `latitude`, `longitude` | **não existem na API** | ver abaixo |
| `estabelecimento`, `denunciado` | **não existem na API** | idem |

---

## O que isso cobra da pré-triagem

A varredura de repetições ([`AnalisadorPorRegra`](../../app/Support/Agrupamento/AnalisadorPorRegra.php))
pesa, hoje: bairro (pré-requisito), logradouro, número próximo, distância em
metros, nome do estabelecimento, documento do denunciado, palavras do assunto.

**Da integração chegam apenas as palavras do assunto e o texto do relato.** Sem
bairro, a regra nem começa — bairro é pré-requisito, não peso.

Três caminhos, e eles não são excludentes:

1. **Extrair do texto.** Bairro e logradouro costumam estar escritos na
   `descricao`; bairro dá para casar contra a lista real de
   [`config/geografia.php`](../../config/geografia.php), e logradouro contra o
   texto. É heurística, e por isso o resultado tem de aparecer como **sugestão**
   para o coordenador confirmar — nunca como dado certo.
2. **Deixar o coordenador completar na pré-triagem.** É a etapa em que ele já
   está lendo o relato inteiro; pedir que confirme bairro e endereço ali é o
   momento de menor atrito e maior acerto. **Isto ainda não existe na tela** e é
   provavelmente a próxima coisa a construir.
3. **Juntar à mão.** Já existe: seleção na fila → "Juntar num caso só". É a saída
   para o que nenhuma regra alcança, e passa a ser o caminho PRINCIPAL enquanto
   os endereços não estiverem estruturados.

> A conclusão desconfortável, e que é melhor dizer agora: **enquanto a integração
> não entregar endereço, a varredura automática vai achar pouco.** O valor da
> pré-triagem nos primeiros dias virá do olho do coordenador com a junção manual,
> não da regra. A regra ganha força na medida em que o endereço for preenchido.

---

---

## Reconhecimento contra a API REAL — 15/09/2026

> Feito **somente com GET** (mais o `POST /login`, que é autenticação e não cria
> nem altera recurso). Nenhum `PUT`, nenhum `DELETE`, nenhum POST de escrita — em
> particular **não** se chamou `PUT /seleciona-caixa`. É API de **produção**, e
> não existe ambiente de homologação.

### O que ficou provado

**1. A autenticação funciona daqui.** `POST /login` → **HTTP 200**, com
`access_token` de 371 caracteres. Isso encerra a dúvida do IP: do ponto de rede
desta máquina (VPN da Prefeitura), a credencial passa. Continua sem resposta o
que acontece a partir de um IP diferente — o do egress do OKD, por exemplo.

**2. A documentação tem um erro de grafia.** O endpoint publicado como
`/subsassuntos/{id}` responde **404**. O real é **`/subassuntos/{id}`** (sem o
primeiro "s"), que responde 200.

**3. Para nós a classificação tem DOIS níveis, não três.** Todos os assuntos de
interesse responderam com **zero subassuntos**. `ESALVADOR_SUBASSUNTOS` fica
vazio por não haver o que pôr.

**4. ⚠️ O CONTEÚDO só é legível para processo que está NA CAIXA DO USUÁRIO.**

`GET /consulta-ultimo-tramite/{n}/{ano}` — que é onde moram o relato e os
requerentes — respondeu, para todo processo testado:

```json
{"error":"Unauthorized","msg":"O processo não está na caixa do usuário."}
```

Enquanto `GET /consulta/{n}/{ano}` (metadados: classificação, unidades, datas,
`codigo`, `identificador`) responde 200 para qualquer processo.

E `GET /caixa-processos`, chamado sem `seleciona-caixa`, devolveu uma caixa que
**não é a da SEMOP**: veio com processos de PGMS/SECOB, SEFAZ/DRM, grupos
ADMINISTRATIVO FISCAL e POLITICAS PUBLICAS. Ou seja, o usuário da nossa
credencial está hoje apontado para outra caixa.

**Consequência direta: sem apontar a caixa para a unidade da SEFAL, a integração
lê metadados e não lê denúncia nenhuma.** E apontar é `PUT /seleciona-caixa` —
escrita, proibida nesta fase. Ver "O que precisa ser resolvido", abaixo.

### Os ids, descobertos e confirmados com dado real

| O quê | Id | Nome no catálogo deles |
|---|---|---|
| **Órgão** | **5369** | SEMOP — Secretaria Municipal de Ordem Pública |
| **Unidade — a nossa** | **5382** | **SEFAL — Setor de Fiscalização de Atividades em Logradouros Públicos** |
| Unidade — onde o público entrega | 5423 | SEATE — Setor de Atendimento ao Público |
| Unidade — licenciamento | 5381 | SEALP — Setor de Autorização para o Exercício de Atividades em Logradouros Públicos |
| Unidade — apreensão | 5383 | SEABE — Setor de Apreensão de Bens em Logradouros Públicos |
| Unidade — guarda do apreendido | 5379 | SEGUB — Setor de Guarda de Bens Apreendidos |
| **Grupo** | **10** | ORDEM PUBLICA |
| Grupo (ouvidoria da cidade) | 11 | OUVIDORIA |

Assuntos do grupo 10 que nos interessam:

| Id | Assunto | O que parece ser |
|---|---|---|
| **215** | COMERCIO INFORMAL E ESPACO PUBLICO - **FISCALIZACAO** | **a denúncia** — é o que mais chega à caixa da SEFAL |
| 222 | COMERCIO INFORMAL E ESPACO PUBLICO - CADASTRO | cadastro de ambulante |
| 216 | COMERCIO INFORMAL E ESPACO PUBLICO - LICENCA AMBULANTE | o canal "nova licença" |
| 217 / 218 / 219 / 221 | LICENCA BAIANA DE ACARAJE / KIT PRAIA / USO DO SOLO / OUTRAS | outras licenças |
| 223 | COMERCIO INFORMAL E ESPACO PUBLICO - OUTROS | resto |
| 232 | FISCALIZACAO | genérico, e de vários órgãos — **não** é só nosso |

E, no grupo 11: 235 DENUNCIA, 234 CENTRAL DE ATENDIMENTO - 156, 237 FALA
SALVADOR.

### O volume real (últimos 89 dias, consultado em 15/09/2026)

| Recorte | Processos |
|---|---|
| grupo 10 · assunto 215 (todos os órgãos) | **53** |
| grupo 10 · assunto 216 — licença ambulante | 66 |
| grupo 10 · assunto 232 — fiscalização genérica | 26 |
| grupo 11 · assunto 235 — denúncia (cidade inteira) | 254 |
| **unidade 5382 — a caixa da SEFAL** | **38** |
| unidade 5381 — SEALP | 5 |
| unidade 5423 — SEATE | 868 |

Duas leituras que importam:

- **a caixa da SEFAL recebe ~38 processos por trimestre**, quase todos
  `COMERCIO INFORMAL E ESPACO PUBLICO - FISCALIZACAO`. É um volume de dezenas por
  trimestre, não de milhares — o que muda a expectativa sobre a pré-triagem: ela
  vai tratar poucos casos por dia, e o ganho está em não mandar equipe duas vezes,
  não em volume;
- **`OUVIDORIA / DENUNCIA` (235) NÃO é nosso**: os processos que apareceram são
  de SMED/OUV (Educação). É a ouvidoria da cidade toda. Filtrar por esse assunto
  importaria denúncia de escola.

### O identificador tem estrutura

`"identificador": "215.5423.207495/2026"` = `{assunto}.{unidade_origem}.{numero}/{ano}`.

Serve de conferência barata: dá para validar a classificação sem uma segunda
chamada.

### O que precisa ser resolvido antes de ligar

| Bloqueio | Natureza | Com quem |
|---|---|---|
| **A caixa do nosso usuário aponta para outra unidade** | precisa de `PUT /seleciona-caixa` (escrita) **ou** de a SEMGE/SEMOP apontá-la do lado deles | SEMOP + SEMGE |
| Confirmar que **215** é o assunto da denúncia de ambulante | conferir um caso real com quem opera | SEMOP |
| Saber se o endereço do fato vem no texto | só dá para ver lendo um relato — e ler depende do item 1 | SEMOP |
| Se o IP bloqueia, e qual será o IP de saída em produção | daqui passa; do OKD, desconhecido | SEMGE |

> A ordem importa: **enquanto a caixa não apontar para a SEFAL, nada do conteúdo
> é legível**, e a pergunta sobre o endereço — que é a que decide o desenho da
> pré-triagem — continua sem resposta.

## Pendências antes de ligar

| O que falta | Com quem |
|---|---|
| Cadastro do **IP** da aplicação (e saber se ele bloqueia) | equipe do e-Salvador (SEMGE) |
| `nome` + `password` (secret key) | equipe do e-Salvador (SEMGE) |
| `token` de integração gerado por um servidor | SEMOP, dentro do e-Salvador |
| Id da **unidade** (a caixa) e do **órgão** da SEMOP | SEMOP / `GET /orgaos`, `GET /unidades/{orgao}` |
| Quais **grupo/assunto/subassunto** são denúncia de ambulante | SEMOP / `GET /grupos`, `GET /assuntos/{grupo}` |
| Confirmar se o endereço do fato vem mesmo só no texto | SEMOP — ver um caso real |

Enquanto isso, `ESALVADOR_LIGADA=false`: nada sai daqui para a rede.

## Códigos de resposta

`200` ok · `201` criado · `400` dados ausentes ou errados · `401` não autenticado
· `403` uso indevido do recurso · `404` não encontrado · `500` erro deles.

> A documentação não relaciona nenhum desses códigos ao IP não cadastrado — a
> lista é genérica. Quando o primeiro teste real acontecer, registre aqui o
> código e a mensagem que vierem: é a única forma de o próximo a depurar não
> refazer a adivinhação.
