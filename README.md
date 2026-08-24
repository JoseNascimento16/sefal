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

## Seletor de banco (`DB_DRIVER`)

O `.env` terá a chave **`DB_DRIVER`** para escolher onde o app roda: `oracle` (Oracle real, exige `oci8` + Instant Client) ou `sqlite` (arquivo `database/database.sqlite`, para quem está sem acesso ao Oracle). O mesmo código serve os dois. _Chega na Task 2 da Fase 0._

## Material interno

Instruções de IA, skills, specs, planos e documentação interna **não vivem aqui** — este repositório é entregue ao cliente. Esse material fica na branch **`ferramental`**.
