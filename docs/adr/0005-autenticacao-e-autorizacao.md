# 0005 — Sanctum SPA + roles/permissions + Policies nas Actions

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
O requisito diz que a autorização deve ser baseada em permissões, e não na
existência de uma rota. O dono do jogo nunca pode aprovar nada.

## Decisão
- Autenticação por **Sanctum em modo SPA** (cookie de sessão + CSRF), com SPA e API na mesma origem. Não há tokens no `localStorage`.
- `spatie/laravel-permission` com roles `player`, `developer` e `admin` e permissões granulares (`versions.authorize-import`, `versions.approve-commit`, `files.review`, `versions.publish`, `games.block`...).
- Policies invocadas **dentro das Actions**, para que qualquer entrada (HTTP, CLI, job) seja autorizada.

## Consequências
- Não existe uma rota administrativa que funcione sem a checagem.
- Um teste genérico percorre as rotas administrativas e garante 403 para os papéis não-admin.
