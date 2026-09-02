#!/bin/sh
# Entrypoint da imagem de demonstração (Render). Serve o Laravel via `php artisan serve`
# na porta que o Render injeta em $PORT. Sem cache de config/rotas de propósito: o
# ambiente é efêmero e menos cache = menos surpresa no boot.
set -e

cd /var/www/html

# .env mínimo só para o artisan não reclamar — as variáveis reais vêm do ambiente do
# Render e têm precedência. Nenhuma credencial aqui.
if [ ! -f .env ]; then
    touch .env
fi

php artisan config:clear || true

# public/storage → storage/app/public (não vai na imagem; recriado a cada boot).
php artisan storage:link || true

# Aplica migrations pendentes a cada boot: o filesystem do Render é EFÊMERO (o banco volta
# sempre ao sefal_demo.sqlite versionado), então rodar migrate aqui garante que o schema
# publicado reflita o código mesmo que o snapshot commitado esteja atrás.
# Fail-open: se falhar, a demo ainda sobe e o erro aparece nos Logs (LOG_CHANNEL=stderr).
php artisan migrate --force || echo "AVISO: 'migrate' falhou no boot — ver erro acima nos Logs."

echo "SEFAL demo iniciando em 0.0.0.0:${PORT:-10000} (APP_ENV=${APP_ENV:-?}, DB_DRIVER=${DB_DRIVER:-?})"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
