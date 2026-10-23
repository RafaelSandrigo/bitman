#!/bin/sh
# M11: E2E num navegador de verdade (Playwright/Chromium) contra uma stack descartável.
# Projeto Compose "bitman-e2e": nada de produção é tocado, e tudo é removido no fim (down -v).
#   sh tests/e2e/run.sh      (ou make test-e2e)
set -eu
cd "$(dirname "$0")"

C="docker compose -f compose.yml"
cleanup() { $C --profile runner down -v --remove-orphans >/dev/null 2>&1 || true; }
trap cleanup EXIT

$C build app bitman-web bitman-games
$C up -d --wait postgres redis
# Como no "make init": os diretórios dos volumes, com dono www-data.
$C run --rm --no-deps -T --entrypoint sh app -c 'mkdir -p /var/bitman/artifacts/served /var/bitman/artifacts/archive /var/bitman/quarantine && chown -R www-data:www-data /var/bitman/artifacts /var/bitman/quarantine && chmod 755 /var/bitman/artifacts /var/bitman/artifacts/served'
$C run --rm -T -u www-data app sh -c 'php artisan migrate --force --no-interaction >/dev/null && php artisan db:seed --class=E2eSeeder --force --no-interaction >/dev/null'
$C up -d --wait app bitman-web bitman-games
$C --profile runner run --rm -T runner
