#!/bin/sh
# Teste das thumbnails servidas pelo hub (docs/seguranca.md: A4).
# Sobe um nginx descartável, sem rede, com a configuração real do bitman-web e fixtures.
# Uso: sh tests/infra/hub-media.sh   (requer apenas docker)
set -eu

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
NAME="bitman-test-hub-$$"
NAME_OK="0123456789abcdef0123456789abcdef"
SPA=$(mktemp -d)
MEDIA=$(mktemp -d)
FAILURES=0

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; rm -rf "$SPA" "$MEDIA"; }
trap cleanup EXIT

printf '<!doctype html><title>spa</title>' > "$SPA/index.html"
mkdir -p "$MEDIA/thumbnails"
printf 'RIFF....WEBP' > "$MEDIA/thumbnails/$NAME_OK.webp"
printf 'segredo' > "$MEDIA/thumbnails/$NAME_OK.png"
printf 'segredo' > "$MEDIA/outro.txt"
chmod -R a+rX "$SPA" "$MEDIA"

# "app" só precisa resolver para o nginx subir (o php-fpm não é usado aqui).
docker run -d --name "$NAME" --network none --add-host app:127.0.0.1 \
  -e GAMES_ORIGIN=https://games.test \
  -v "$ROOT/docker/web/templates:/etc/nginx/templates:ro" \
  -v "$SPA:/srv/spa:ro" \
  -v "$MEDIA:/srv/media:ro" \
  nginx:1.29-alpine >/dev/null

i=0; until docker exec "$NAME" wget -q -O /dev/null http://127.0.0.1/health 2>/dev/null; do
  i=$((i+1)); [ "$i" -gt 30 ] && { echo "nginx não subiu"; docker logs "$NAME"; exit 1; }; sleep 0.5
done

status() { docker exec "$NAME" sh -c "wget -S -q -O /dev/null 'http://127.0.0.1$1' 2>&1 | awk '/^  HTTP\\//{c=\$2} END{print c}'"; }
headers() { docker exec "$NAME" sh -c "wget -S -q -O /dev/null 'http://127.0.0.1$1' 2>&1"; }

expect_status() {
  got=$(status "$2")
  if [ "$got" = "$1" ]; then echo "ok   $1 $2"; else echo "FAIL esperado $1, obtido '$got' em $2"; FAILURES=$((FAILURES+1)); fi
}
expect_header() {
  if headers "$1" | grep -qiF "$2"; then echo "ok   $1 contém [$2]"; else echo "FAIL $1 sem [$2]"; FAILURES=$((FAILURES+1)); fi
}

P="/media/thumbnails/$NAME_OK.webp"
expect_status 200 "$P"
expect_header "$P" "Content-Type: image/webp"   # tipo fixo: nunca adivinhado pelo conteúdo
expect_header "$P" "X-Content-Type-Options: nosniff"
expect_header "$P" "Content-Security-Policy: default-src 'none'"
expect_status 404 "/media/thumbnails/$NAME_OK.png"   # só .webp com o nome gerado pelo backend
expect_status 404 "/media/thumbnails/ABC.webp"
expect_status 404 "/media/outro.txt"
expect_status 404 "/media/thumbnails/"               # sem listagem
expect_status 404 "/media/"

[ "$FAILURES" -eq 0 ] && echo "hub-media: todos os testes passaram" || { echo "hub-media: $FAILURES falha(s)"; exit 1; }
