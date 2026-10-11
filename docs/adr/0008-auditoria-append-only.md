# 0008 — Auditoria append-only síncrona

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
A auditoria precisa responder quem fez o quê, quando e sobre qual estado. Um registro
perdido ou alterado invalida o fluxo de aprovação.

## Decisão
Os eventos de domínio que implementam `Audit\Domain\Auditable` são tratados por um listener
**síncrono**, que grava em `audit_entries` na **mesma transação** da mudança de estado. Os outros
módulos só conhecem esse contrato: cada evento declara seu `AuditRecord`.

Cada registro guarda:
- o ator (nulo no console);
- a ação (`contexto.verbo`, ex.: `identity.role-granted`) e a entidade;
- um snapshot do estado analisado (role, commit, hash);
- o motivo;
- o IP (nulo fora de uma requisição HTTP);
- a data.

Um trigger no PostgreSQL impede `UPDATE`, `DELETE` e `TRUNCATE`. A FK do ator é `RESTRICT`, então um
usuário que já agiu não pode ser apagado.

## Consequências
- Se a auditoria falhar, a ação também falha. Isso é intencional.
- A tabela cresce indefinidamente, o que é aceitável na escala do projeto.
