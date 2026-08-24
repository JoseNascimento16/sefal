# Ambiente de desenvolvimento — Windows nativo

O desenvolvimento roda **direto no Windows** (sem Docker, sem WSL): PHP na máquina, `php artisan
serve` e o Vite ao lado. O banco é escolhido por uma chave do `.env` — Oracle real ou SQLite local —
e **o mesmo código serve os dois**.

O passo a passo do dia a dia (clonar, instalar, subir, rodar os testes) está no **`README.md`**. Este
documento cobre o que ele não cobre: o **Oracle no Windows** e a **escolha do banco**.

## 1. PHP e a extensão `oci8`

A máquina que vai falar com o **Oracle** precisa de três peças, nesta ordem:

1. **PHP oficial para Windows** (thread-safe, x64) em `C:\php`, no `PATH`.
2. A extensão **`oci8`** compatível com essa versão do PHP (DLL em `C:\php\ext`, habilitada no
   `php.ini`).
3. O **Oracle Instant Client** (Basic), com a pasta dele também no `PATH` — é ele quem fala TNS.

Confira com `php -m` (tem de listar `oci8`) e `php -r "var_dump(function_exists('oci_connect'));"`.

> **Não pesquise versões do zero.** O casamento entre versão do PHP, versão do `oci8` e versão do
> Instant Client é a única parte difícil, e ela **já foi resolvida e validada** no projeto CODECON,
> da mesma fábrica e do mesmo cliente: o guia `docs/ambiente/oci8-windows.md` daquele repositório traz
> as URLs, as versões exatas e o script de setup. Siga-o e volte para cá.

**Sem `oci8` o projeto roda normalmente** — fica em SQLite. Inclusive o `composer install` não exige a
extensão, porque o `composer.json` declara `config.platform.ext-oci8`: o Composer assume a extensão só
na resolução de dependências; em execução ela é necessária apenas se a conexão `oracle` for usada de
fato.

## 2. Seletor de banco (`DB_DRIVER`)

| `DB_DRIVER` | O que acontece | Quando usar |
|---|---|---|
| `oracle` | força o Oracle (exige `oci8` + Instant Client + rede e credenciais) | trabalho contra o banco real |
| `sqlite` | força `database/fiscalizacao_dev.sqlite` | sem `oci8`, sem VPN, offline |
| `auto` | **padrão**: Oracle quando houver `oci8` **e** `DB_HOST` preenchido; senão SQLite | máquina que alterna |

Quem decide é o `AppServiceProvider` (`configurarConexaoDeBanco`). Dois detalhes que evitam susto:

- **A suíte de testes não obedece a esta chave.** Quem manda nela é o `phpunit.xml`, que fixa SQLite
  em `:memory:` e `DB_DRIVER=sqlite` com `force`. Assim, um dev com `DB_DRIVER=oracle` no `.env` **não**
  cria nem derruba tabela no Oracle ao rodar os testes.
- **Num clone novo o arquivo do SQLite não existe** (é gitignorado, regenerável). O provider cria o
  arquivo vazio para o `migrate` poder rodar.

As tabelas próprias do sistema usam o prefixo **`LRV_`** (`DB_PREFIX`) — o Oracle é um banco
compartilhado, e o prefixo é o que separa o que é nosso.

## 3. Rodando

Dois terminais: `php artisan serve` (http://localhost:8000) e `npm run dev` (Vite com HMR). O Laravel
detecta o `public/hot` e passa a servir os assets do Vite.

**O `npm run build` é pré-requisito da suíte de testes** — ele gera o manifest do Vite e os arquivos de
rota do Wayfinder, os dois gitignorados. Detalhe no README.

## 4. Antes de cada push

**Não há runner de CI** (o `.gitlab-ci.yml` existe, mas está dormente — ver `docs/deploy/okd.md`).
O gate é local, e é obrigatório:

```bash
vendor/bin/pint --dirty
npx tsc --noEmit
php artisan test --compact
```

E ative os hooks de git **uma vez por clone** (`git config core.hooksPath .githooks`): eles barram
push direto em `main`/`develop`/`homolog` e arquivo que não pertence a este repositório.
