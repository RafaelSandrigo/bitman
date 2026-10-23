#!/bin/sh
# Os caches do Laravel dependem do .env, que só existe em tempo de execução (env_file):
# por isso são gerados ao subir o container, e não no build da imagem.
# Sem "optimize": ele inclui view:cache, e o projeto não tem resources/views (API + SPA).
set -eu
php artisan config:cache --no-interaction >/dev/null
php artisan route:cache --no-interaction >/dev/null
php artisan event:cache --no-interaction >/dev/null
exec "$@"
