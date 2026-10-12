#!/bin/sh
# Teste do isolamento de rede da git-sandbox (docs/seguranca.md G3, ADR 0003).
# A sandbox só alcança a internet pelo egress-proxy, e só os hosts da allowlist.
# Requer a stack de pé (make up). No CI só sobem sandbox e proxy: as checagens de app/queue pulam.
set -eu
cd "$(dirname "$0")/../.."
FAILURES=0

run() { docker compose exec -T git-sandbox sh -c "$1" >/dev/null 2>&1; }
ok()   { echo "ok   $1"; }
fail() { echo "FAIL $1"; FAILURES=$((FAILURES+1)); }

# Permitido: host da allowlist via proxy (repositório público deste projeto).
if run "git ls-remote https://github.com/RafaelSandrigo/bitman.git HEAD"; then ok "github.com via proxy"; else fail "github.com via proxy deveria funcionar"; fi

# Negado: host fora da allowlist.
if run "git ls-remote https://example.com/repo.git"; then fail "example.com deveria ser bloqueado"; else ok "example.com bloqueado"; fi

# Negado: saída direta sem o proxy (rede interna sem rota para a internet).
if run "HTTPS_PROXY= HTTP_PROXY= git ls-remote https://github.com/RafaelSandrigo/bitman.git HEAD"; then fail "saída direta deveria falhar"; else ok "sem saída direta para a internet"; fi

# Negado: serviços internos do projeto (banco) não são alcançáveis pela sandbox.
if run "php -r 'exit(@fsockopen(\"postgres\", 5432, \$e, \$s, 3) ? 0 : 1);'"; then fail "sandbox alcançou o postgres"; else ok "postgres inalcançável"; fi

# Negado: o proxy não abre CONNECT para portas diferentes de 443.
if run "php -r '\$f=@fsockopen(\"egress-proxy\",8888,\$e,\$s,3); fwrite(\$f,\"CONNECT github.com:22 HTTP/1.1\r\nHost: github.com:22\r\n\r\n\"); \$l=fgets(\$f); exit(str_contains((string)\$l,\" 200 \") ? 0 : 1);'"; then fail "proxy permitiu CONNECT na porta 22"; else ok "CONNECT só na 443"; fi

# G8: sem o token certo, a sandbox não busca nada (e sem token configurado, recusa todos).
fetch_status() { docker compose exec -T git-sandbox php -r '$c=stream_context_create(["http"=>["method"=>"POST","header"=>"Content-Type: application/json\r\nAuthorization: Bearer '"$1"'","content"=>"{}","ignore_errors"=>true]]); @file_get_contents("http://127.0.0.1:8080/fetch",false,$c); echo explode(" ",$http_response_header[0] ?? "x 0")[1];'; }
if [ "$(fetch_status token-errado)" = "401" ]; then ok "token errado recebe 401"; else fail "token errado deveria receber 401"; fi
if [ "$(fetch_status '')" = "401" ]; then ok "sem token recebe 401"; else fail "sem token deveria receber 401"; fi

# Só o worker da fila (rede sandbox) fala com a sandbox; o php-fpm não.
if docker compose ps --status running --services | grep -qx queue; then
    if docker compose exec -T app php -r 'exit(@fsockopen("git-sandbox", 8080, $e, $s, 3) ? 0 : 1);' >/dev/null 2>&1; then fail "app alcançou a git-sandbox"; else ok "app não alcança a git-sandbox"; fi
    if docker compose exec -T queue php -r 'exit(@fsockopen("git-sandbox", 8080, $e, $s, 3) ? 0 : 1);' >/dev/null 2>&1; then ok "queue alcança a git-sandbox"; else fail "queue deveria alcançar a git-sandbox"; fi
else
    echo "skip app/queue não estão de pé (CI)"
fi

# Hardening do container.
if run "touch /srv/sandbox/x"; then fail "rootfs deveria ser somente leitura"; else ok "rootfs somente leitura"; fi
if [ "$(docker compose exec -T git-sandbox id -u | tr -d '\r')" = "0" ]; then fail "sandbox roda como root"; else ok "usuário não-root"; fi

[ "$FAILURES" -eq 0 ] && echo "sandbox-egress: todos os testes passaram" || { echo "sandbox-egress: $FAILURES falha(s)"; exit 1; }
