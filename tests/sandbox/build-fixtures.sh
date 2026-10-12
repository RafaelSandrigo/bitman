#!/bin/sh
# Repositórios-fixture (bons e maliciosos) para o harness da git-sandbox (docs/seguranca.md G1–G7).
# Roda DENTRO da imagem da sandbox, sem rede, em /tmp (tmpfs). Autor e datas fixos: os SHAs são
# determinísticos, e o manifesto gerado pode ser comparado com o golden versionado.
set -eu

export GIT_AUTHOR_NAME=Fixture GIT_AUTHOR_EMAIL=fixture@bitman.test
export GIT_COMMITTER_NAME=Fixture GIT_COMMITTER_EMAIL=fixture@bitman.test
export GIT_AUTHOR_DATE='2026-01-01T00:00:00Z' GIT_COMMITTER_DATE='2026-01-01T00:00:00Z'
export HOME=/tmp GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null

ROOT=/tmp/fixtures
mkdir -p "$ROOT"

new() {
    mkdir -p "$ROOT/$1"
    cd "$ROOT/$1"
    git init -q -b main
    git config uploadpack.allowFilter true
    git config core.symlinks true
    git config core.fileMode true
}

base() {
    printf '<!doctype html>\n<html lang="pt-BR">\n<link rel="stylesheet" href="style.css">\n<script src="game.js" defer></script>\n</html>\n' > index.html
    printf 'body { margin: 0; background: #000; }\n' > style.css
    printf 'window.parent.postMessage({ type: "game-over", score: 42 }, "*");\n' > game.js
}

commit() {
    git add -A
    git commit -q -m fixture
}

# Monta um commit a partir de linhas do mktree ("<modo> <tipo> <objeto>\t<nome>"), sem passar
# pelo índice: é assim que um atacante cria árvores que o `git add` recusaria.
crafted() {
    tree=$(git mktree)
    head=$(git commit-tree "$tree" -m fixture)
    git update-ref refs/heads/main "$head"
}

# Jogo válido, com arquivos extras que devem ser só listados (ignorados), e uma tag.
new valid
base
printf '# Jogo\n' > README.md
mkdir assets && printf '\211PNG\r\n\032\n' > assets/logo.png
commit
git tag v1.0

# game.js é um symlink para fora do repositório.
new symlink
base
rm game.js && ln -s /etc/passwd game.js
commit

# Os obrigatórios estão certos, mas há um symlink em outro lugar.
new symlink-extra
base
ln -s ../../../etc docs
commit

# Submodule (entrada 160000 + .gitmodules).
new submodule
base
printf '[submodule "lib"]\n\tpath = lib\n\turl = https://github.com/ada/lib\n' > .gitmodules
git add -A
git update-index --add --cacheinfo 160000,1111111111111111111111111111111111111111,lib
git commit -q -m fixture

# game.js executável (100755).
new executable
base
chmod +x game.js
commit

# game.js de 2 MiB: acima do limite de blob, fica no servidor (filtro) e o tamanho volta nulo.
new huge
base
head -c 2097152 /dev/zero | tr '\0' 'a' > game.js
commit

# index.html binário: byte nulo e UTF-8 inválido.
new binary
base
printf '<html>\000\377\376</html>\n' > index.html
commit

# Nomes hostis (opção, barra invertida, quebra de linha, controle, UTF-8 inválido).
new hostile-names
base
printf 'x' > ./-rf
printf 'x' > 'a\b.js'
printf 'x' > "$(printf 'quebra\nde-linha.txt')"
printf 'x' > "$(printf 'controle\001.txt')"
printf 'x' > "$(printf 'invalido\377.txt')"
printf 'x' > 'acentuação.txt'
commit

# Árvores forjadas que o fsck do git recusa ao receber (G5).
new dotdot-tree
base
commit
blob=$(git rev-parse HEAD:game.js)
printf '100644 blob %s\t..\n100644 blob %s\tgame.js\n' "$blob" "$blob" | crafted

new dotgit-tree
base
commit
blob=$(git rev-parse HEAD:game.js)
printf '100644 blob %s\t.git\n100644 blob %s\tgame.js\n' "$blob" "$blob" | crafted

# 600 arquivos: passa pela sandbox, a política do Laravel (500) bloqueia.
new too-many
base
commit
blob=$(git rev-parse HEAD:game.js)
{ git ls-tree HEAD; i=1; while [ "$i" -le 597 ]; do printf '100644 blob %s\tf%04d.txt\n' "$blob" "$i"; i=$((i+1)); done; } | crafted

# Acima do teto da sandbox (10.000): a busca falha.
new way-too-many
base
commit
blob=$(git rev-parse HEAD:game.js)
{ git ls-tree HEAD; i=1; while [ "$i" -le 10000 ]; do printf '100644 blob %s\tf%05d.txt\n' "$blob" "$i"; i=$((i+1)); done; } | crafted

# Sem os arquivos obrigatórios.
new missing-files
printf '# Só um README\n' > README.md
commit

# Commit vazio.
new empty
git commit -q --allow-empty -m fixture
