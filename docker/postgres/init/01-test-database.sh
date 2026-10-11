#!/bin/sh
# Banco separado para os testes automatizados: nunca usar o banco da aplicação.
set -eu
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  -c "CREATE DATABASE bitman_test OWNER \"$POSTGRES_USER\";"
