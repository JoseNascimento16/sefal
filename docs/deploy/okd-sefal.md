# SEFAL no OKD do cliente (COGEL) — o que colar, na ordem

Receita **deste projeto**. A estrutura e as armadilhas do ambiente estão na skill
`deploy-gitlab-okd` e no runbook `docs/deploy/okd-deploy.md` do ferramental do CODECON — aqui está só
o que muda para o SEFAL, com os valores já preenchidos.

Namespace: **`sefal-homolog`**. Já está preenchido em tudo abaixo — é colar.

Sobre o **host público**: não precisa dele para começar, e não precisa esperar o WAF (ver o passo 5).

| Peça | Valor do SEFAL |
|---|---|
| Origem do código (dev) | GitLab da Fábrica — `jose.nascimento/fiscalizacao-permissionarios`, branch `homolog` |
| Repositório que o OKD builda | `repositoriosemit.salvador.ba.gov.br/jose.nascimento/sefal`, branch `homolog` |
| Dockerfile | `dockerfile_redhat` (base do cliente + oci8, **sem LibreOffice**) |
| Imagem base | a **mesma do CODECON**, em `codecon-homolog/php:latest` — puxada de lá (passo 0) |
| Banco | Oracle, schema próprio `LRV_` do SEFAL (usuário `USR_SEFAL`) |
| Uploads a persistir | `storage/app/public` (fotos de fiscalização) e `storage/app/private` |

> ⚠️ **Duas diferenças em relação ao CODECON, e as duas custam tempo se passarem batidas:**
> 1. A origem é o **GitLab da Fábrica**, não o GitHub — o espelho (`.gitlab-ci.yml`, na raiz da
>    branch `homolog`) clona de lá.
> 2. O SEFAL guarda foto em **dois** diretórios: `storage/app/public` (o que a tela mostra) e
>    `storage/app/private` (a foto que a auditoria de segurança mandou tirar da web). **Um PVC só
>    montado na raiz do `storage` publicaria o privado pelo Apache** — por isso são dois volumes.

---

## 0. Autorizar o `sefal-homolog` a puxar a imagem do `codecon-homolog`

A base é a do CODECON e vive no outro namespace. Cruzar namespace é permitido, mas precisa de
autorização explícita — **e ela é criada no namespace de ORIGEM** (`codecon-homolog`), não no do
SEFAL. Console → **`+` Import YAML** (com o projeto `codecon-homolog` selecionado):

```yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: sefal-homolog-puxa-a-base
  namespace: codecon-homolog
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: ClusterRole
  name: system:image-puller
subjects:
  # Quem builda a imagem do SEFAL...
  - kind: ServiceAccount
    name: builder
    namespace: sefal-homolog
  # ...e quem roda o pod (o runtime também resolve a camada base).
  - kind: ServiceAccount
    name: default
    namespace: sefal-homolog
```

Sem isto o build morre no `FROM` com **`unauthorized`** — e o erro tem cara de "imagem não existe",
não de "falta permissão". É o primeiro lugar a olhar se o build falhar na primeira linha.

## 1. Secret de pull (o build clonar o GitLab do cliente)

Console → **`+` Import YAML**. O token é um **Deploy Token** do projeto no GitLab do cliente, com
`read_repository`.

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: gitlab-interno-pull
  namespace: sefal-homolog
type: kubernetes.io/basic-auth
stringData:
  username: "<usuario-do-deploy-token>"
  password: "<senha-do-deploy-token>"
```

## 2. ImageStream + BuildConfig

```yaml
apiVersion: image.openshift.io/v1
kind: ImageStream
metadata: { name: sefal, namespace: sefal-homolog }
---
apiVersion: build.openshift.io/v1
kind: BuildConfig
metadata: { name: sefal, namespace: sefal-homolog }
spec:
  source:
    type: Git
    git:
      uri: "https://repositoriosemit.salvador.ba.gov.br/jose.nascimento/sefal.git"
      ref: homolog
    contextDir: /
    sourceSecret: { name: gitlab-interno-pull }
  strategy:
    type: Docker
    dockerStrategy: { dockerfilePath: dockerfile_redhat }
  output:
    to: { kind: ImageStreamTag, name: "sefal:latest" }
  triggers:
    - { type: ConfigChange }
    # O segredo vai INLINE, e não por `secretReference`: assim não há dois
    # Secrets extras para criar antes. ⚠️ O runbook do CODECON usa
    # `secretReference` e NUNCA manda criar esses Secrets — no CODECON eles já
    # existiam, então a falha passou. Sem os gatilhos abaixo o rodapé "Webhooks"
    # do BuildConfig NEM APARECE, e é dele que sai a URL para colar no GitLab.
    - { type: Generic, generic: { secret: "<valor-aleatorio>" } }
    - { type: GitLab,  gitlab:  { secret: "<valor-aleatorio>" } }
  # O `npm run build` come RAM; se o build morrer por OOM, descomente:
  # resources: { limits: { memory: 4Gi } }
```

Depois: **Builds → BuildConfigs → sefal → Actions → Start build** e acompanhe o log. Tem de terminar
`Complete`.

⚠️ **Se falhar já na primeira linha** (`unauthorized` ou "imagem não encontrada" no `FROM`), é o
RoleBinding do passo 0 que não está lá — o `sefal-homolog` não tem direito de puxar a base do
`codecon-homolog`. Não é a imagem que falta.

## 3. Secret `sefal-env` (o `.env` inteiro)

**Workloads → Secrets → Create → Key/value secret**, nome `sefal-env`, chave **`.env`**, valor = o
`.env` completo de homologação. O essencial do SEFAL:

```
APP_NAME=SEFAL
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:<gere com php artisan key:generate --show>
APP_URL=https://sefal-sefal-homolog.apps.<dominio-do-cluster>   # o host que a Route mostrar
APP_LOCALE=pt_BR

# Oracle de homologação (o seletor do projeto: oracle | sqlite | auto)
DB_DRIVER=oracle
DB_CONNECTION=oracle
DB_HOST=<host do Oracle>
DB_PORT=1521
DB_DATABASE=SEFAL
DB_USERNAME=USR_SEFAL
DB_PASSWORD=<senha>

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Bloqueio de acesso por setor LIGADO (em dev roda em `log`)
PERMISSAO_ENFORCE=block
```

⚠️ **Editar este secret depois NÃO recarrega o pod** (é montado por `subPath`) → **Actions → Restart
rollout** no `sefal` **e** no `sefal-scheduler`.

## 4. PVCs dos uploads (dois, e o motivo importa)

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata: { name: sefal-uploads-publicos, namespace: sefal-homolog }
spec:
  accessModes: [ReadWriteOnce]
  resources: { requests: { storage: 5Gi } }
---
apiVersion: v1
kind: PersistentVolumeClaim
metadata: { name: sefal-uploads-privados, namespace: sefal-homolog }
spec:
  accessModes: [ReadWriteOnce]
  resources: { requests: { storage: 5Gi } }
```

⚠️ **`ReadWriteOnce`, e não RWX — medido no cluster (10/09/2026).** A classe de armazenamento deste
cluster é **`thin-csi`** (bloco do vSphere), que **não atende `ReadWriteMany`**: o PVC fica `Pending`
para sempre, sem `PersistentVolume`, e o pod do app nunca agenda. O sintoma engana duas vezes — a
Route responde *"Application is not available"* (parece app quebrado) e o pod fica `Pending`
**sem nenhum evento** (parece cluster sem recursos). O que denuncia: o **scheduler sobe normalmente**,
porque ele não monta volume nenhum. Se os dois pods estão parados, é agendamento; se só o do app,
é volume.

`accessModes` é **imutável** — para trocar, apague os PVCs e recrie.

RWO serve porque o app roda com **1 réplica**: os dois volumes ficam no mesmo nó, com o mesmo pod. No
dia em que precisar de 2 réplicas, RWX volta a ser necessário e aí é pedido à infraestrutura do
cliente.

E sem PVC nenhum **a foto que o fiscal tirou desaparece no próximo deploy** — o disco do pod é
efêmero.

## 5. Deployment + Service + Route + Scheduler

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: sefal
  namespace: sefal-homolog
  labels: { app: sefal }
  annotations:
    image.openshift.io/triggers: |-
      [{"from":{"kind":"ImageStreamTag","name":"sefal:latest","namespace":"sefal-homolog"},"fieldPath":"spec.template.spec.containers[?(@.name==\"sefal\")].image"}]
spec:
  replicas: 1
  # `Recreate`, e não o RollingUpdate padrão: os volumes são RWO (ver passo 4) e
  # só aceitam UM pod por vez. Com rolling, o pod novo espera um volume que o
  # antigo ainda segura, e o antigo não morre até o novo ficar pronto — impasse
  # eterno, com "Multi-Attach error" nos eventos e o pod preso em
  # ContainerCreating. Derrubar antes de subir custa alguns segundos de
  # indisponibilidade e resolve.
  strategy:
    type: Recreate
  selector: { matchLabels: { app: sefal } }
  template:
    metadata: { labels: { app: sefal } }
    spec:
      volumes:
        - name: sefal-env
          secret: { secretName: sefal-env, defaultMode: 420 }
        - name: uploads-publicos
          persistentVolumeClaim: { claimName: sefal-uploads-publicos }
        - name: uploads-privados
          persistentVolumeClaim: { claimName: sefal-uploads-privados }
      containers:
        - name: sefal
          image: image-registry.openshift-image-registry.svc:5000/sefal-homolog/sefal:latest
          ports: [{ containerPort: 8080 }]
          env:
            # SEM isto o Apache serve a raiz do repositório e o `.env` fica exposto na web.
            - { name: DOCUMENTROOT, value: /public }
          volumeMounts:
            - { name: sefal-env, mountPath: /opt/app-root/src/.env, subPath: .env, readOnly: true }
            - { name: uploads-publicos, mountPath: /opt/app-root/src/storage/app/public }
            - { name: uploads-privados, mountPath: /opt/app-root/src/storage/app/private }
---
apiVersion: v1
kind: Service
metadata: { name: sefal, namespace: sefal-homolog }
spec:
  selector: { app: sefal }
  ports: [{ name: 8080-tcp, port: 8080, targetPort: 8080 }]
---
apiVersion: route.openshift.io/v1
kind: Route
metadata: { name: sefal, namespace: sefal-homolog }
spec:
  # SEM `host`: o OKD gera um sozinho no domínio curinga do cluster
  # (sefal-sefal-homolog.apps.<dominio-do-cluster>), que já responde para quem
  # está na VPN. É com ele que se testa hoje.
  #
  # O host PÚBLICO (…salvador.ba.gov.br) depende de DNS + WAF, que é pedido à
  # infraestrutura do cliente quando o sistema for aberto para fora — aí se
  # acrescenta o `host:` aqui (ou uma segunda Route) e o TLS `edge` continua o
  # mesmo. Não é pré-requisito de nada agora.
  to: { kind: Service, name: sefal }
  port: { targetPort: 8080-tcp }
  tls: { termination: edge, insecureEdgeTerminationPolicy: Redirect }
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: sefal-scheduler
  namespace: sefal-homolog
  labels: { app: sefal-scheduler }
  annotations:
    image.openshift.io/triggers: |-
      [{"from":{"kind":"ImageStreamTag","name":"sefal:latest","namespace":"sefal-homolog"},"fieldPath":"spec.template.spec.containers[?(@.name==\"scheduler\")].image"}]
spec:
  replicas: 1
  selector: { matchLabels: { app: sefal-scheduler } }
  template:
    metadata: { labels: { app: sefal-scheduler } }
    spec:
      volumes:
        - name: sefal-env
          secret: { secretName: sefal-env, defaultMode: 420 }
      containers:
        - name: scheduler
          image: image-registry.openshift-image-registry.svc:5000/sefal-homolog/sefal:latest
          args: ["php", "artisan", "schedule:work"]
          volumeMounts:
            - { name: sefal-env, mountPath: /opt/app-root/src/.env, subPath: .env, readOnly: true }
```

`replicas: 1` no scheduler não é economia: duas réplicas rodariam **cada tarefa agendada em dobro**.

## 6. Migrate — é MANUAL, e no SEFAL isso pesa

O DevOps do cliente tirou o migrate do deploy de propósito; **não recrie initContainer**. Depois de
cada deploy que traga migração, no terminal do pod (**Workloads → Pods → sefal-… → Terminal**):

```
php artisan migrate --force
php artisan storage:link
php artisan db:seed --class=SetoresSeeder --force
php artisan db:seed --class=PermissoesSetorSeeder --force
```

⚠️ **A semente de permissões é obrigatória no primeiro deploy**: sem ela a matriz nasce vazia,
**nenhuma tela abre para ninguém** (nem para o administrador, que é desvio no código e não linha na
matriz) — e o sintoma parece "sistema quebrado", não "falta semear".

O primeiro usuário: `php artisan fp:criar-usuario-dev --login=<matricula> --setor=administrador
--senha=<senha> --force` e trocar a senha no primeiro acesso.

## 7. Webhook (build automático a cada push)

**Builds → BuildConfigs → sefal → rodapé Webhooks → Copy URL with Secret** (tipo *Generic*) e
cadastre no GitLab do cliente em **Settings → Webhooks**: *Push events* marcado, **SSL verification
desligada**.

## 8. O espelho (o passo que faz o resto existir)

O `.gitlab-ci.yml` está na raiz da branch `homolog`. No projeto do **cliente**:

1. **Settings → CI/CD → Variables** (todas *Masked*): `FABRICA_TOKEN` (leitura na Fábrica),
   `GITLAB_USER` e `GITLAB_TOKEN` (**Project Access Token**, Maintainer + `write_repository` —
   Deploy Token dessa instância **não** tem escrita).
2. **Settings → Repository → Default branch → `homolog`** (sem isso a home mostra "empty" e a CI não
   acha o `.gitlab-ci.yml`).
3. **Build → Pipeline schedules** → agendamento (ex.: a cada hora) e um *Run pipeline* manual para
   testar.

O bootstrap (primeiro push, da máquina do dev, que é a única que alcança os dois lados) está no
`README` desta pasta — resumo:

```
git remote add cliente https://repositoriosemit.salvador.ba.gov.br/jose.nascimento/sefal.git
git config http.postBuffer 524288000
git push cliente homolog:refs/heads/homolog
```

Push grande atrás do proxy do cliente **fica em silêncio por minutos** processando no servidor — não
interrompa.

## Armadilhas do ESPELHO (medidas em 10/09/2026, na primeira montagem)

| Sintoma | Causa | Correção |
|---|---|---|
| `remote: You are not allowed to download code from this project` + **403** no `git clone` | token da Fábrica criado com papel **Guest** | recriar com **Reporter** ou acima, escopo `read_repository`. ⚠️ Esse 403 tem a MESMA cara de credencial ausente — antes de mexer na pipeline, teste o clone na sua máquina com o token: é o teste decisivo, e separa "token errado" de "CI errada" |
| Pipeline **verde** e o OKD **não builda** | o espelho não empurrou nada: os dois lados já estavam no mesmo commit, então não houve push nem evento | é o comportamento certo. Para provar a corrente, faça um commit de verdade na `homolog` da Fábrica e rode de novo |
| Variável de CI chega vazia (e o clone dá 403) | variável marcada **Protected** e a branch não é protegida no GitLab do cliente | desmarcar *Protected*, ou proteger a branch `homolog` |

⚠️ **A ordem importa e é fácil errar:** o espelho faz **force-push** da `homolog` da Fábrica sobre a do
cliente. Se a da Fábrica estiver atrás (na primeira montagem ela estava em `Initial commit`), rodar a
pipeline **apaga** o que está no cliente — os arquivos de deploy inclusive. Antes do primeiro
disparo, confirme que as duas apontam para o mesmo commit:

```
git rev-parse origin/homolog cliente/homolog   # os dois valores têm de ser iguais
```

## Armadilhas herdadas do ambiente (as que mais custam)

| Sintoma | Causa | Correção |
|---|---|---|
| Nada do cliente abre | VPN da COGEL desconectada | conectar antes de qualquer diagnóstico |
| App expõe código/`.env` | falta `DOCUMENTROOT=/public` | env no Deployment |
| Editei o secret e nada mudou | `subPath` não recarrega | Restart rollout (app **e** scheduler) |
| Tela nova com `ORA-00904/00942` | migração pendente | `migrate --force` no pod |
| Build fica `pending` | job sem a tag do runner | `tags: [smed]` |
| Push do espelho dá 403 | Deploy Token sem `write_repository` | Project Access Token |
| Build não dispara no push | webhook ausente ou com SSL verification ligada | recadastrar |
| Rodapé **Webhooks** não aparece no BuildConfig | falta gatilho `Generic`/`GitLab` nos `triggers` | acrescentar (passo 2) — é dele que sai a URL |
| Erro que parece CORS na tela | **WAF** barrando assinatura de SQLi na URL | ver a skill `url-segura-waf` |
| Foto desaparece após deploy | falta PVC | os dois PVCs do passo 4 |
| PVC `Pending` sem `PersistentVolume`; pod do app `Pending` **sem eventos**, mas o scheduler roda | `accessModes: ReadWriteMany` numa classe que só faz RWO (`thin-csi`) | apagar e recriar os PVCs com `ReadWriteOnce` |
| Pod novo preso em `ContainerCreating` com **"Multi-Attach error for volume"** | RollingUpdate + volume RWO: o novo espera o volume que o antigo segura | `strategy: { type: Recreate }` no Deployment |
| Tela EM BRANCO com o fundo pintado, e os `href` dos assets saindo em `http://` | proxy encerra o TLS (Route `edge`) e o app não confia nos cabeçalhos de encaminhamento | `trustProxies(at: '*')` no `bootstrap/app.php` (já está na linha de desenvolvimento) |
