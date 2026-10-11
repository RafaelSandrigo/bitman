# 0008 — Auditoria append-only síncrona

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
A auditoria precisa responder quem fez o quê, quando e sobre qual estado. Um registro
perdido ou alterado invalida o fluxo de aprovação.

## Decisão
Os eventos de domínio são tratados por um listener **síncrono**, que grava em
`audit_entries` na **mesma transação** da mudança de estado. Cada registro guarda o
ator, a ação, a entidade, um snapshot do estado analisado (commit, hash), o motivo,
o IP e a data. Um trigger no PostgreSQL impede `UPDATE` e `DELETE`.

## Consequências
- Se a auditoria falhar, a ação também falha. Isso é intencional.
- A tabela cresce indefinidamente, o que é aceitável na escala do projeto.
