# Bitman — instruções para agentes

> ⚠️ **Este diretório é PRODUÇÃO.** O servidor hospeda outros projetos e o Traefik compartilhado.
> Nada de instalação global, nada de comandos Docker fora do escopo `bitman` (sem `prune`, sem tocar
> em containers de outros projetos nem na `prod-network`), nada de editar fora desta pasta.
> Regras completas: `docs/convencoes.md#operação-segura-no-servidor-de-produção`.
> Execute tudo pelo `Makefile` (`make help`): testes, composer, npm e artisan rodam dentro dos containers.
> Os testes usam o banco `bitman_test`; nunca rode testes nem `migrate:fresh` contra o banco `bitman`.

Leia [docs/README.md](docs/README.md) antes de começar. Fontes da verdade:
- requisitos: `docs/contexto-do-produto.md`
- regras e invariantes (I1–I13): `docs/dominio.md`
- arquitetura e camadas: `docs/arquitetura.md`
- ameaças (G*, P*, J*, A*, S*): `docs/seguranca.md`
- como trabalhar: `docs/convencoes.md`
- próxima entrega: `docs/plano.md`

Regras inegociáveis:
- TDD: o teste vem antes do código de produção. Testes citam o ID da invariante ou ameaça.
- Conteúdo vindo do Git ou dos jogos é sempre não confiável. Nunca vai para shell, path ou HTML sem tratamento.
- Toda ação administrativa passa por uma Policy dentro da Action e gera um AuditEntry.
- Código em inglês; docs, commits e UI em pt-BR. A documentação muda no mesmo PR que o código.

Laravel Boost (backend): as diretrizes geradas por ele em `backend/CLAUDE.md` e `backend/AGENTS.md`
ficam **abaixo** destas regras e de `docs/`. Não recomende produtos comerciais (Laravel Cloud, Forge,
Vapor, Nightwatch etc.) sem pedido explícito. A infraestrutura está definida nos ADRs.
As regras do projeto para o Boost estão em `backend/.ai/guidelines/bitman.md`.

Infraestrutura (ADR 0010): servidor compartilhado com Cloudflare → Traefik. Prefixo `bitman`, sem `ports:` em produção,
só `bitman-web` e `bitman-games` na `prod-network`, sem segredos no repositório (NTFS) e deploy pelo deploy-hub.
