#!/bin/sh
# Imagens de produção (docs/implantacao.md): geradas de um commit, com o código embutido.
# Constrói as imagens com os mesmos Dockerfiles do "make release" e confere:
#   - PHP: sem dependências de dev, sem composer, sem .env nem testes, opcache sem revalidar
#     timestamps, código de dono root (o php-fpm e a fila, como www-data, não o alteram).
#   - bitman-web: SPA compilada, sem source maps, com a configuração do nginx embutida.
#   - bitman-games: configuração do nginx (CSP sandbox, J1–J3) embutida, sem bind mount.
#
#   sh tests/infra/release-images.sh          # árvore de trabalho (arquivos versionados e novos não ignorados)
#   sh tests/infra/release-images.sh <ref>    # um commit, como o make release
set -eu
cd "$(dirname "$0")/../.."

TAG=release-test
SRC=$(mktemp -d)
trap 'rm -rf "$SRC"' EXIT
if [ -n "${1:-}" ]; then
    git archive "$1" | tar -x -C "$SRC"
else
    # Antes do commit: o que o git versionaria (sem vendor, node_modules, .env etc.).
    git ls-files -z --cached --others --exclude-standard | tar --null --ignore-failed-read -T - -cf - 2>/dev/null | tar -x -C "$SRC"
fi

docker build -q --target prod --build-arg CONFIG_DIR=docker/php \
    -f "$SRC/docker/php/Dockerfile" -t "bitman-php:$TAG" "$SRC" >/dev/null
docker build -q --build-arg VITE_GAMES_ORIGIN=https://games.test \
    -f "$SRC/docker/web/Dockerfile" -t "bitman-web:$TAG" "$SRC" >/dev/null
docker build -q -f "$SRC/docker/games/Dockerfile" -t "bitman-games:$TAG" "$SRC" >/dev/null

failures=0
ok() { echo "  ✓ $1"; }
fail() { echo "  ✗ $1"; failures=$((failures + 1)); }
check() { if docker run --rm --network none --entrypoint sh "$1" -c "$2" >/dev/null 2>&1; then ok "$3"; else fail "$3"; fi; }

echo "bitman-php"
check "bitman-php:$TAG" 'php artisan --version' "o Laravel inicia sem .env"
check "bitman-php:$TAG" 'test ! -d vendor/pestphp && test ! -d vendor/laravel/boost && test ! -d vendor/larastan' "sem dependências de dev (Pest, Boost, Larastan)"
check "bitman-php:$TAG" '! command -v composer' "sem o binário do composer"
check "bitman-php:$TAG" 'test ! -e .env && test ! -d tests && test ! -d node_modules' "sem .env, testes ou node_modules"
check "bitman-php:$TAG" 'php -i | grep -q "opcache.validate_timestamps => Off"' "opcache não revalida timestamps"
check "bitman-php:$TAG" 'test "$(stat -c %U app artisan vendor | sort -u)" = root' "código e vendor com dono root"
check "bitman-php:$TAG" 'test "$(stat -c %U storage bootstrap/cache | sort -u)" = www-data' "storage e bootstrap/cache graváveis por www-data"
check "bitman-php:$TAG" 'php -i | grep -q "session.upload_progress.enabled => Off"' "configuração do php.ini do projeto aplicada"
if docker run --rm --network none "bitman-php:$TAG" php -v >/dev/null 2>&1; then ok "o entrypoint gera os caches e executa o comando"; else fail "o entrypoint gera os caches e executa o comando"; fi

echo "bitman-web"
check "bitman-web:$TAG" 'test -f /srv/spa/index.html' "SPA compilada em /srv/spa"
check "bitman-web:$TAG" 'test -z "$(find /srv/spa -name "*.map")"' "sem source maps"
check "bitman-web:$TAG" 'grep -q "https://games.test" /srv/spa/assets/*.js' "origem de jogos embutida no build"
check "bitman-web:$TAG" 'test -f /etc/nginx/templates/default.conf.template' "configuração do nginx embutida"

echo "bitman-games"
check "bitman-games:$TAG" 'test -f /etc/nginx/templates/default.conf.template && grep -q "sandbox allow-scripts" /etc/nginx/templates/game-headers.inc.template' "configuração com CSP sandbox embutida"

docker image rm -f "bitman-php:$TAG" "bitman-web:$TAG" "bitman-games:$TAG" >/dev/null

if [ "$failures" -gt 0 ]; then
    echo "release-images: $failures verificação(ões) falharam"
    exit 1
fi
echo "release-images: ok"
