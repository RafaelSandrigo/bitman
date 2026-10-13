#!/bin/sh
# Jogo de exemplo (examples/snake) segue o protocolo e a CSP da origem de jogos
# (docs/protocolo-do-jogo.md, docs/seguranca.md J2): três arquivos regulares, UTF-8, até 1 MiB,
# nada inline, nada externo, sem rede nem storage, e o envelope do postMessage.
set -eu
cd "$(dirname "$0")/../.."

DIR=examples/snake
fail=0
check() { # check <descrição> <comando...>
    desc=$1; shift
    if "$@" >/dev/null 2>&1; then :; else echo "example-game: FALHOU: $desc"; fail=1; fi
}
absent() { # absent <descrição> <regex> <arquivo>
    if grep -E -q -- "$2" "$3"; then echo "example-game: FALHOU: $1 ($3)"; grep -E -n -- "$2" "$3" | head -3; fail=1; fi
}

for f in index.html style.css game.js; do
    check "$f existe e é arquivo regular" test -f "$DIR/$f"
    check "$f não é symlink" test ! -L "$DIR/$f"
    # O modo que vale é o do Git (o repositório publicado): blob regular 100644, nunca 100755/120000.
    mode=$(git ls-files -s -- "$DIR/$f" | cut -d' ' -f1)
    check "$f está no Git como blob regular 100644 (modo: ${mode:-não rastreado})" test "$mode" = 100644
    check "$f tem até 1 MiB" test "$(wc -c < "$DIR/$f")" -le 1048576
    check "$f é UTF-8 válido" iconv -f UTF-8 -t UTF-8 "$DIR/$f"
done

# CSP: script-src/style-src só da origem de jogos, sem 'unsafe-inline'.
absent 'script inline' '<script(>|[^>]*>[^<[:space:]])' "$DIR/index.html"
absent 'handler inline (on*=)' '[[:space:]]on[a-z]+=' "$DIR/index.html"
absent 'atributo style inline' '[[:space:]]style=' "$DIR/index.html"
absent 'bloco <style> inline' '<style' "$DIR/index.html"
for f in index.html style.css game.js; do
    absent 'recurso externo' '(https?:)?//[a-z0-9.-]+\.[a-z]{2,}' "$DIR/$f"
done
absent '@import no CSS' '@import' "$DIR/style.css"

# Sem rede, storage, cookies nem código dinâmico.
absent 'API proibida' '(fetch|XMLHttpRequest|WebSocket|EventSource|localStorage|sessionStorage|indexedDB|document\.cookie|eval|new Function|importScripts|navigator\.sendBeacon)' "$DIR/game.js"

# Envelope do protocolo e um único game-over.
check 'envelope source/version do protocolo' grep -q "source: 'bitman', version: 1" "$DIR/game.js"
check 'envia game-over com score inteiro' grep -q "sendToHub('game-over', { score: Math.floor(score) })" "$DIR/game.js"
check 'só fala com window.parent' grep -q 'window.parent.postMessage' "$DIR/game.js"
check 'index.html carrega game.js e style.css relativos' sh -c "grep -q '<script src=\"game.js\"></script>' $DIR/index.html && grep -q 'href=\"style.css\"' $DIR/index.html"

if [ "$fail" -ne 0 ]; then exit 1; fi
echo "example-game: protocolo e CSP ok"
