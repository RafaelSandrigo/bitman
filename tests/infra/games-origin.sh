#!/bin/sh
# Teste da origem de jogos (docs/seguranca.md: J1, J2, J3, J6, C1, C2).
# Sobe um nginx descartável, sem rede, com a configuração real e uma versão-fixture.
# Uso: sh tests/infra/games-origin.sh   (requer apenas docker)
set -eu

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
NAME="bitman-test-games-$$"
UUID="0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f"
FIXTURE=$(mktemp -d)
FAILURES=0

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; rm -rf "$FIXTURE"; }
trap cleanup EXIT

mkdir -p "$FIXTURE/$UUID"
printf '<!doctype html><title>t</title>' > "$FIXTURE/$UUID/index.html"
printf 'body{}' > "$FIXTURE/$UUID/style.css"
printf 'void 0;' > "$FIXTURE/$UUID/game.js"
printf 'segredo' > "$FIXTURE/$UUID/README.md"
ln -s /etc/passwd "$FIXTURE/$UUID/passwd" 2>/dev/null || true
# Os artefatos publicados precisam ser legíveis pelo worker do nginx (0755/0644).
chmod 755 "$FIXTURE" "$FIXTURE/$UUID" && chmod 644 "$FIXTURE/$UUID"/*.html "$FIXTURE/$UUID"/*.css "$FIXTURE/$UUID"/*.js "$FIXTURE/$UUID"/*.md

docker run -d --name "$NAME" --network none \
  -e GAMES_ORIGIN=https://games.test -e HUB_ORIGIN=https://hub.test \
  -v "$ROOT/docker/games/templates:/etc/nginx/templates:ro" \
  -v "$FIXTURE:/srv/games:ro" \
  nginx:1.29-alpine >/dev/null

i=0; until docker exec "$NAME" wget -q -O /dev/null http://127.0.0.1/health 2>/dev/null; do
  i=$((i+1)); [ "$i" -gt 30 ] && { echo "nginx não subiu"; docker logs "$NAME"; exit 1; }; sleep 0.5
done

# status <path> -> código HTTP; headers <path> -> cabeçalhos da resposta
status() { docker exec "$NAME" sh -c "wget -S -q -O /dev/null 'http://127.0.0.1$1' 2>&1 | awk '/^  HTTP\\//{c=\$2} END{print c}'"; }
headers() { docker exec "$NAME" sh -c "wget -S -q -O /dev/null 'http://127.0.0.1$1' 2>&1"; }

expect_status() {
  got=$(status "$2")
  if [ "$got" = "$1" ]; then echo "ok   $1 $2"; else echo "FAIL esperado $1, obtido '$got' em $2"; FAILURES=$((FAILURES+1)); fi
}
expect_rejected() {
  got=$(status "$1")
  case "$got" in 400|403|404) echo "ok   $got $1";; *) echo "FAIL esperado 400/403/404, obtido '$got' em $1"; FAILURES=$((FAILURES+1));; esac
}
expect_header() {
  if headers "$1" | grep -qiF "$2"; then echo "ok   $1 contém [$2]"; else echo "FAIL $1 sem [$2]"; FAILURES=$((FAILURES+1)); fi
}

for f in index.html style.css game.js; do expect_status 200 "/$UUID/$f"; done
expect_status 404 "/$UUID/README.md"          # arquivos fora da allowlist nunca são servidos
expect_status 404 "/$UUID/passwd"             # symlink
expect_status 404 "/$UUID/"                   # sem listagem
expect_rejected "/$UUID/../../etc/passwd"      # traversal (o nginx responde 400)
expect_status 404 "/naoeuuid/index.html"
expect_status 404 "/"

P="/$UUID/index.html"
expect_header "$P" "sandbox allow-scripts"                 # J1: origem opaca mesmo fora do iframe
expect_header "$P" "connect-src 'none'"                    # J2: sem rede
expect_header "$P" "default-src 'none'"
expect_header "$P" "script-src https://games.test"
expect_header "$P" "frame-ancestors https://hub.test"
expect_header "$P" "Cache-Control: no-store, no-transform" # C1, C2
expect_header "$P" "X-Content-Type-Options: nosniff"
expect_header "$P" "X-Robots-Tag: noindex"
expect_header "/$UUID/game.js" "Content-Type: application/javascript"
expect_header "/$UUID/game.js" "connect-src 'none'"
if headers "$P" | grep -qi "Set-Cookie"; then echo "FAIL origem de jogos emitiu cookie"; FAILURES=$((FAILURES+1)); else echo "ok   sem Set-Cookie"; fi

[ "$FAILURES" -eq 0 ] && echo "games-origin: todos os testes passaram" || { echo "games-origin: $FAILURES falha(s)"; exit 1; }
