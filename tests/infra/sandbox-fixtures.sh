#!/bin/sh
# Harness da git-sandbox com repositórios-fixture maliciosos (docs/seguranca.md G1–G7).
# Container descartável da imagem real, SEM rede e com o mesmo endurecimento do compose.
# Compara os manifestos com o golden que o Pest usa (backend/tests/Fixtures/sandbox/manifests.json).
#
#   sh tests/infra/sandbox-fixtures.sh            # verifica
#   sh tests/infra/sandbox-fixtures.sh --update   # regrava o golden (revise o diff!)
set -eu
cd "$(dirname "$0")/../.."

GOLDEN=backend/tests/Fixtures/sandbox/manifests.json
OUT=$(mktemp)
trap 'rm -f "$OUT"' EXIT

docker build -q -t bitman-git-sandbox docker/git-sandbox >/dev/null

docker run --rm --network none --read-only --tmpfs /tmp:size=256m,mode=1777 \
    --cap-drop ALL --security-opt no-new-privileges --pids-limit 128 --memory 512m \
    -v "$PWD/tests/sandbox:/fixtures:ro" \
    bitman-git-sandbox sh -c 'sh /fixtures/build-fixtures.sh && php /fixtures/run.php' > "$OUT"

if [ "${1:-}" = "--update" ]; then
    mkdir -p "$(dirname "$GOLDEN")"
    cp "$OUT" "$GOLDEN"
    echo "sandbox-fixtures: golden regravado em $GOLDEN"
elif cmp -s "$OUT" "$GOLDEN"; then
    echo "sandbox-fixtures: manifestos iguais ao golden"
else
    diff "$GOLDEN" "$OUT" | head -40
    echo "sandbox-fixtures: manifestos diferentes do golden (rode com --update e revise o diff)"
    exit 1
fi
