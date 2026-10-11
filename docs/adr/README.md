# Architecture Decision Records

Um ADR registra uma decisão **difícil de reverter**: o contexto, a decisão e as consequências.
Um ADR não é editado depois de aceito. Para mudar uma decisão, cria-se outro ADR que
**substitui** o anterior, e o antigo recebe o status `Substituído por NNNN`.

Para criar um, copie [template.md](template.md) para `NNNN-titulo-curto.md`.

| # | Decisão | Status |
|---|---|---|
| [0001](0001-laravel-13.md) | Laravel 13 em vez de 12 | Aceito |
| [0002](0002-monolito-modular.md) | Monólito modular com DDD pragmático | Aceito |
| [0003](0003-git-sandbox.md) | Operações Git em serviço sandbox, sem checkout | Aceito |
| [0004](0004-isolamento-dos-jogos.md) | Jogos em outra origem, iframe sandbox e postMessage | Aceito |
| [0005](0005-autenticacao-e-autorizacao.md) | Sanctum SPA + roles/permissions + Policies nas Actions | Aceito |
| [0006](0006-aprovacao-vinculada-ao-hash.md) | Aprovação vinculada ao hash, sem herança entre versões | Aceito |
| [0007](0007-postgresql-redis.md) | PostgreSQL + Redis | Aceito |
| [0008](0008-auditoria-append-only.md) | Auditoria append-only síncrona | Aceito |
| [0009](0009-laravel-boost-restrito.md) | Laravel Boost com restrições (sem viés comercial, sem Tinker MCP) | Aceito |
| [0010](0010-infraestrutura-homelab.md) | Infraestrutura no servidor compartilhado (Cloudflare + Traefik + deploy-hub) | Aceito |
