#!/usr/bin/env bash
#
# deploy-bitman.sh — script de deploy hash-aprovado do projeto bitman.
#
# Versionado em bitman/deploy/ para revisão; o operador o copia para
# deploy-hub/scripts/deploy-bitman.sh e aprova o SHA-256 com
# ~/.local/share/deploy-hub/bin/approve.sh bitman (docs/implantacao.md do bitman).
#
# ESTE ARQUIVO É O ARTEFATO AUDITADO. Qualquer edição invalida a aprovação.
# Caminho fixo, zero parâmetros externos, zero interpolação de dado vindo da
# requisição HTTP. Roda no HOST como sandrigo (grupo docker).
#
# Por que só "git fetch" e não "git pull": o diretório do bitman é também onde
# se desenvolve (ADR 0010). O deploy não mexe na árvore de trabalho: o
# "make release" gera as imagens de um "git archive" de origin/main, então
# mudanças locais sem commit nunca vão para produção. A sequência de deploy
# (build, migrations, troca dos containers) vive no Makefile do projeto, para
# que mudar o processo não exija reaprovar este script.
set -euo pipefail

# Caminho absoluto e fixo do projeto — nunca recebido como parâmetro.
readonly PROJECT_DIR="/mnt/Dados/prog/prod/bitman"

# Deploy key dedicada (somente leitura no GitHub), fora de $HUB (NTFS).
readonly DEPLOY_KEY="/home/sandrigo/.local/share/deploy-hub/keys/bitman_deploy_key"

if [[ ! -f "${DEPLOY_KEY}" ]]; then
  echo "ERRO: deploy key nao encontrada em ${DEPLOY_KEY}." >&2
  echo "Gere um par de chaves dedicado, cadastre a PUBLICA como Deploy Key" >&2
  echo "(sem 'Allow write access') no repositorio RafaelSandrigo/bitman" >&2
  echo "no GitHub, e coloque a PRIVADA neste caminho com permissao 600." >&2
  exit 1
fi

cd "${PROJECT_DIR}"

echo "==> Buscando origin/main (git fetch, sem tocar na arvore de trabalho)"
GIT_SSH_COMMAND="ssh -i ${DEPLOY_KEY} -o IdentitiesOnly=yes" \
  git fetch --no-tags origin main

# O compose usa name: bitman; nao forcar -p aqui (criaria uma stack paralela).
echo "==> Release de origin/main"
make release REF=origin/main

echo "==> Deploy do bitman concluido"
