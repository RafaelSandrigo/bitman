# 0005 — Sanctum SPA + roles/permissions + Policies nas Actions

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
O requisito diz que a autorização deve ser baseada em permissões, e não na
existência de uma rota. O dono do jogo nunca pode aprovar nada.

## Decisão
- Autenticação por **Sanctum em modo SPA** (cookie de sessão + CSRF), com SPA e API na mesma origem. Não há tokens no `localStorage`.
- `spatie/laravel-permission` com roles `player`, `developer` e `admin` e permissões granulares (`versions.authorize-import`, `versions.approve-commit`, `files.review`, `versions.publish`, `games.block`...).
  Roles e permissões são enums do domínio (`Identity\Domain\Role` e `Permission`); o seeder sincroniza o banco a cada deploy.
  Cada permissão nasce na fase que a usa. Na Fase 1: `games.submit`, `users.manage-roles` e `audit.view`; na Fase 2: `games.manage`; na Fase 3:
  `versions.authorize-import` (Portão 0, nova importação e consulta das versões); na Fase 4: `versions.approve-commit`
  (Portão 1) e `files.review` (Portão 2: conteúdo, decisão por arquivo e rejeição da versão).
- Autorização **dentro das Actions**, pelo Gate: `authorize(Permission::X)` quando basta a permissão, e uma Policy
  quando a regra depende do recurso (ex.: dono do jogo). Assim qualquer entrada (HTTP, CLI, job) é autorizada.
- Todas as rotas administrativas são declaradas dentro de um único grupo `/api/v1/admin`
  (`auth:sanctum` + `verified` + `role:admin`), em `routes/api.php`. Essa é uma segunda barreira.
- Autenticação com Fortify headless ([ADR 0011](0011-autenticacao-com-fortify.md)).

## Consequências
- Não existe uma rota administrativa que funcione sem a checagem.
- Um teste genérico (`tests/Feature/Security/AdminRoutesAuthorizationTest.php`) percorre **todas** as rotas
  administrativas, inclusive as das próximas fases, e garante 401 para visitante e 403 para os papéis não-admin.
- A exceção consciente é o comando de console `user:grant-role` (bootstrap do primeiro admin). Ele não passa
  pelo Gate porque exige shell no servidor, e a concessão é auditada com ator nulo.
