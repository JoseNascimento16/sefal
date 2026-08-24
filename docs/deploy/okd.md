# Deploy no OKD do cliente — receita preparada

> ## ⛔ ESTADO: NÃO ATIVADO (24/08/2026)
>
> Não existe ambiente de homologação deste sistema: **sem namespace no cluster, sem banco Oracle
> provisionado e sem domínio**. Nada aqui está construído ou testado neste projeto — é a receita
> transplantada de um sistema que **já roda neste mesmo cluster** (mesma stack: Laravel + Inertia +
> Oracle), pronta para o dia em que o cliente liberar o ambiente.
>
> Enquanto isso: o `dockerfile_redhat` na raiz **nunca foi construído**, o `.gitlab-ci.yml` está
> dormente (sem runner) e o gate real é local.

## 1. O desenho, em uma figura

```
   GitLab de desenvolvimento                 Rede interna do cliente
   (fonte da verdade, branch homolog)
        │
        │  (1) ESPELHO — modelo PULL: um runner de DENTRO clona o repositório
        │      de fora e força-empurra a branch para o GitLab interno
        ▼
   GitLab interno do cliente  ──(2) WEBHOOK de push──►  OKD
                                                         BuildConfig (Docker, dockerfile_redhat)
                                                           → ImageStream
                                                           → Deployment (S2I: Apache + PHP, porta 8080)
                                                           → Service → Route → WAF do cliente
```

Duas pontes, porque a rede interna do cliente **não alcança a internet** e a internet **não alcança
a rede dele**:

1. **Espelho** — é PULL: quem inicia é o runner de dentro, que alcança o repositório de fora.
   O GitLab interno é **espelho descartável** (o force-push reescreve a branch): nunca edite código
   lá, some no próximo tique.
2. **Build** — disparado por **webhook** do GitLab interno para a API do OKD. Funciona porque o
   *servidor* do GitLab alcança a API do cluster (`:6443`); um *runner* de CI não alcança.

## 2. Peças a criar no cluster (ordem)

| # | Objeto | Ponto que morde |
|---|---|---|
| 1 | **Namespace** | vem com Pod Security `restricted` → a imagem roda como UID 1001 |
| 2 | **Secret de clone** (`kubernetes.io/basic-auth`) | com a annotation `build.openshift.io/source-secret-match-uri-1` o OKD usa o secret sozinho ao clonar aquele GitLab |
| 3 | **ImageStream + BuildConfig** | `strategy: Docker` com `dockerfilePath: dockerfile_redhat`; o `npm run build` come RAM (considere `resources.limits.memory: 4Gi`) |
| 4 | **Secret do `.env`** (chave `.env`, o arquivo inteiro) | montado por `subPath` no pod |
| 5 | **PVC** em `storage/app/public` | `ReadWriteMany`; **sem ele todo upload do usuário desaparece a cada deploy** |
| 6 | **Deployment + Service + Route** | ver as três envs obrigatórias abaixo |

**As três coisas que mais quebram no Deployment:**

- `DOCUMENTROOT=/public` — sem isso o Apache serve a raiz do projeto e **o `.env` e o código ficam
  expostos na web**.
- Annotation `image.openshift.io/triggers` apontando para `<app>:latest` — sem ela a build nova
  termina e o pod continua com a imagem antiga.
- Volume do `.env` montado com `subPath` **e** o PVC em `/opt/app-root/src/storage/app/public`.

**Route:** `tls: edge` (o HTTPS termina no router; o pod recebe HTTP na 8080). O Laravel confia no
proxy via `trustProxies` + `APP_URL=https://...`, senão os links de e-mail saem em `http://`.

## 3. O `.env` do ambiente (o que não pode estar errado)

| Chave | Valor | Por quê |
|---|---|---|
| `APP_DEBUG` | **`false`** | com `true`, qualquer erro mostra stack trace **com as credenciais** |
| `APP_URL` | `https://<host público>` | só a origem; alimenta link de e-mail e reset de senha |
| `APP_KEY` | o **mesmo** já em uso | trocar torna ilegível todo dado criptografado no banco |
| `DB_DRIVER` | `oracle` | seletor de banco do app (ver `docs/ambiente/dev-windows.md`) |
| `DB_HOST` / `DB_PORT` / `DB_SERVICE_NAME` / `DB_USERNAME` / `DB_PASSWORD` | do ambiente | exige liberação de rede OKD → Oracle |
| `DB_PREFIX` | `LRV_` | as tabelas próprias do sistema |

> ⚠️ **Editou o secret do `.env`?** Ele é montado por `subPath` e **não recarrega sozinho**. Faça
> **Restart rollout** no Deployment — todas as vezes.

## 4. `migrate` é MANUAL — regra de equipe

**O deploy não roda `migrate`.** No cluster deste cliente o initContainer de migrate foi removido de
propósito pelo DevOps — **não recrie**. Então, a cada versão que traz migration:

```
php artisan migrate:status      # no terminal do pod
php artisan migrate --force     # se listar Pending
```

Sem isso, a tela que usa a coluna nova quebra com `ORA-00904`/`ORA-00942`. Por isso: **todo MR com
migration avisa o dono** de forma explícita.

## 5. Erros reais desse cluster (economizam horas)

| Sintoma | Causa | Correção |
|---|---|---|
| A web mostra o código / o `.env` | falta `DOCUMENTROOT=/public` | env no Deployment |
| Editei o `.env` e nada mudou | secret por `subPath` não recarrega | Restart rollout |
| Tela nova dá `ORA-00904`/`00942` | migration pendente | `migrate --force` no pod |
| Build falha no `curl` do LibreOffice / Oracle | egress da build bloqueado para o host | testar egress com um pod sonda; pedir liberação |
| Build falha em `liberation-fonts` | pacote não existe no UBI | não instalar: o LibreOffice já embute as fontes |
| Upload grande estoura só aqui | o pod herdou os limites default do PHP (2M/8M) | limites gravados em `/etc/php.d` pelo `dockerfile_redhat` |
| Build nova não vira deploy | falta a annotation de trigger | `image.openshift.io/triggers` |
| PVC fica `Pending` | storageClass errada ou sem RWX | conferir a classe com o DevOps |
| "Erro de CORS" que só acontece com alguns valores | **WAF** do cliente barrando assinatura de SQL injection na URL (`--`, aspas, `<`, `>`) | não pôr texto livre nem valor opaco na query string |

## 6. Quando este arquivo mudar

- **Uploads:** os limites do PHP vivem no `dockerfile_redhat` (versionados). Não use
  `oc set env PHP_*` como solução definitiva — se perde na recriação do Deployment.
- **Carimbo de versão:** quando a Retaguarda exibir a versão no rodapé, grave um `version.txt` no
  build (`date` no `dockerfile_redhat`) e leia o arquivo. Não dependa de
  `getenv('OPENSHIFT_BUILD_COMMIT')`: essa env não chega ao contexto web do S2I.
- **Produção:** namespace e branch próprios, outro `.env`, `APP_ENV=production` e — se houver 2+
  réplicas — sessão compartilhada (não `file`). O agendador, quando existir, fica sempre em 1 réplica.
- **Rota alternativa** (servidor Docker do cliente, sem OKD): `docker-compose.homolog.yml`, também
  não ativo, com o que falta anotado no topo do arquivo.
