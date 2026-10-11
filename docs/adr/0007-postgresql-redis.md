# 0007 — PostgreSQL + Redis

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
Precisamos de integridade forte (índices parciais, triggers, concorrência no envio
de score) e de fila, cache e rate limit confiáveis.

## Decisão
PostgreSQL como banco principal (inclusive nos testes de feature) e Redis para
fila, cache e rate limit.

## Consequências
- O índice parcial garante uma única versão publicada por jogo, e os triggers tornam a auditoria append-only.
- Os testes de feature dependem do container PostgreSQL. O SQLite não é usado, para não mascarar diferenças de comportamento.
