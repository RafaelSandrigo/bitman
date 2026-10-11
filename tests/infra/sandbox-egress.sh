#!/bin/sh
# Teste do isolamento de rede da git-sandbox (docs/seguranca.md G3, ADR 0003).
# A sandbox só alcança a internet pelo egress-proxy, e só os hosts da allowlist.
# Requer a stack de pé (make up).
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

# Hardening do container.
if run "touch /srv/sandbox/x"; then fail "rootfs deveria ser somente leitura"; else ok "rootfs somente leitura"; fi
if [ "$(docker compose exec -T git-sandbox id -u | tr -d '\r')" = "0" ]; then fail "sandbox roda como root"; else ok "usuário não-root"; fi

[ "$FAILURES" -eq 0 ] && echo "sandbox-egress: todos os testes passaram" || { echo "sandbox-egress: $FAILURES falha(s)"; exit 1; }
