# syntax=docker/dockerfile:1
#
# SEFAL — Dockerfile do ambiente de DEMONSTRAÇÃO no Render (free tier).
# ⚠️ EXCLUSIVO do Render. O deploy de verdade (OKD do cliente) usa /dockerfile_redhat.
#
# Imagem enxuta, só com SQLite: sem oci8 / Instant Client → o AppServiceProvider cai no
# SQLite versionado (database/sefal_demo.sqlite) porque DB_DRIVER=sqlite vem do ambiente.
# Serve com `php artisan serve` num único container, escutando em $PORT (o Render injeta).
#
# Build context = raiz do repo (dockerContext: . no render.yaml); ignore-file específico:
# render/Dockerfile.dockerignore (o BuildKit usa este no lugar do /.dockerignore).
#
# Credenciais NUNCA entram na imagem: .env é excluído no ignore-file e as variáveis vêm
# do painel do Render.

############################################################
# Stage base — PHP CLI + extensões do Laravel (sem oci8)
############################################################
FROM php:8.4-cli-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libonig-dev libzip-dev libsqlite3-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite mbstring bcmath intl zip opcache gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html


############################################################
# Stage build — vendor (Composer) + assets (Vite/React)
#   PHP + Composer + Node juntos porque o plugin Wayfinder roda
#   `php artisan wayfinder:generate` durante o `npm run build`.
############################################################
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 22 (mesma glibc bookworm)
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# Dependências PHP de produção. O yajra/laravel-oci8 está em "require" e o projeto declara
# config.platform.ext-oci8, então o composer instala sem a extensão real presente.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --no-progress

# Dependências Node a partir do lockfile (binários Linux do rollup/oxide/lightningcss).
# ignore-scripts=true vem do .npmrc do projeto (trava de supply-chain) — mantido.
COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .

# Boot mínimo do artisan no build + geração de assets. O .env de build é descartável e
# NÃO vai para a imagem final (o stage app não copia .env).
RUN cp .env.example .env \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && composer dump-autoload --optimize --no-dev --no-scripts \
    && php artisan package:discover --no-ansi \
    && php artisan key:generate --force --no-interaction --no-ansi \
    && php artisan wayfinder:generate --with-form --no-ansi \
    && npm run build \
    && rm -f .env


############################################################
# Stage app — imagem final de runtime
############################################################
FROM base AS app

RUN mv "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini"
RUN { \
        echo 'upload_max_filesize = 20M'; \
        echo 'post_max_size = 24M'; \
        echo 'memory_limit = 256M'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.validate_timestamps = 0'; \
    } > "${PHP_INI_DIR}/conf.d/zz-sefal.ini"

# Código. database/sefal_demo.sqlite É incluído de propósito → é o banco da demonstração.
COPY --chown=www-data:www-data . .

COPY --from=build --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=build --chown=www-data:www-data /var/www/html/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R ug+rwX storage bootstrap/cache database

COPY render/entrypoint.sh /usr/local/bin/render-entrypoint
RUN chmod +x /usr/local/bin/render-entrypoint

ENV PORT=10000
EXPOSE 10000
CMD ["render-entrypoint"]
