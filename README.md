# SEMOP — Fiscalização de Permissionários

Sistema da SEMOP (Prefeitura de Salvador) para fiscalização de **permissionários** (comerciantes ambulantes de rua). App Laravel único com a **Retaguarda** (gestão, Inertia/React) e, adiante, o **PWA do Fiscal** (SPA offline-first para uso em rua).

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 13 (PHP 8.5) |
| Auth | Fortify |
| Ponte back↔front | Inertia.js v3 (sem API REST separada na Retaguarda) |
| Frontend | React 19 + TypeScript + Tailwind CSS 4 |
| Roteamento client | Wayfinder (`@/actions`, `@/routes` gerados) |
| Testes | Pest |
| Banco | Oracle (produção, schema próprio prefixo `LRV_`) · SQLite (dev) |

## Como rodar (dev, Windows nativo)

```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

Depois, em dois terminais:

```bash
php artisan serve   # http://localhost:8000
npm run dev         # Vite HMR
```

## Rodando os testes

```bash
npm run build            # obrigatório antes da 1ª execução dos testes
php artisan test --compact
```

**O `npm run build` é pré-requisito da suíte.** Os testes renderizam as páginas Inertia de verdade, e a build é quem gera (a) o **manifest do Vite** em `public/build/` e (b) os arquivos de rota do **Wayfinder** em `resources/js/{actions,routes,wayfinder}` — os três caminhos são gitignorados, então num clone novo eles simplesmente não existem. Sem a build, a suíte quebra com **`ViteManifestNotFoundException: Vite manifest not found at: public/build/manifest.json`**. Repita a build sempre que apagar `public/build/` ou puxar mudanças no front.

Gate local completo antes de cada push (não há runner no GitLab):

```bash
vendor/bin/pint --dirty
npx tsc --noEmit
php artisan test --compact
```

## Seletor de banco (`DB_DRIVER`)

O `.env` tem a chave **`DB_DRIVER`** para escolher onde o app roda: `oracle` (Oracle real, exige `oci8` + Instant Client), `sqlite` (arquivo `database/fiscalizacao_dev.sqlite`, para quem está sem acesso ao Oracle) ou `auto` — o padrão, que usa o Oracle quando há `oci8` **e** `DB_HOST` preenchido, e cai no SQLite caso contrário. O mesmo código serve os dois.

**Máquina sem `oci8` roda normalmente**: fica em SQLite e o `composer install` não exige a extensão, porque o `composer.json` declara `config.platform.ext-oci8` — o Composer assume a extensão só na resolução de dependências. Em tempo de execução ela é necessária apenas se a conexão `oracle` for de fato usada.

## Material interno

Instruções de IA, skills, specs, planos e documentação interna **não vivem aqui** — este repositório é entregue ao cliente. Esse material fica na branch **`ferramental`**.
