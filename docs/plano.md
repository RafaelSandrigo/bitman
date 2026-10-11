# Plano de desenvolvimento

Entrega incremental em **fatias verticais**: cada fase entrega backend, frontend,
testes e documentação de algo utilizável. A ordem segue o risco: a fundação e a
segurança da ingestão vêm antes das funcionalidades sociais.

Status: ⬜ não iniciada · 🟨 em andamento · ✅ concluída

## Fase 0 — Fundação ⬜

**Objetivo:** ambiente reprodutível e esteira de qualidade antes de qualquer regra de negócio.

- ✅ Laravel Boost instalado com proteções contra recomendações comerciais ([ADR 0009](adr/0009-laravel-boost-restrito.md)).

- Docker Compose (`base`, `dev`, `prod`) com os serviços, as redes e as portas de
  [arquitetura.md](arquitetura.md#containers): `bitman-web`, `bitman-games`, `bitman-app` (PHP 8.5),
  `bitman-queue`, `bitman-scheduler`, `bitman-postgres`, `bitman-redis`, `bitman-git-sandbox` (stub),
  `bitman-egress-proxy` e `bitman-mailpit`. Healthchecks e labels do Traefik só no `prod`.
- Backend: Pest, Larastan, Pint, Sanctum, spatie/laravel-permission
  e Scramble. Estrutura `app/Modules` e `app/Shared`. Testes `arch()` das camadas.
- Frontend: React Router, TanStack Query, Zod, React Hook Form, Tailwind, Vitest,
  Testing Library e MSW. Estrutura `app/ features/ shared/`. Proxy do Vite para a API.
- `Makefile`: `up`, `down`, `test`, `check`, `fresh`.
- CI (GitHub Actions): lint, análise estática e testes do backend (com PostgreSQL),
  do frontend e build das imagens. Workflow `deploy` no padrão do garagem-saas (dispara o deploy-hub).
- `deploy-hub/scripts/deploy-bitman.sh` + registro em `hooks.json.tmpl` (rascunho; ativado na Fase 8).
- Remover o scaffold padrão (welcome, assets do Vite).

**Aceite:** `make up && make check` passa numa máquina limpa, e o CI está verde.

## Fase 1 — Identidade, acesso e auditoria ⬜

- Registro, login e logout (Sanctum SPA), com rate limit no login.
- Verificação de e-mail (obrigatória para jogar e submeter) e recuperação de senha. Mailpit em dev (A5).
- Roles `player` (padrão), `developer` e `admin`, mais permissões granulares.
- Comando `php artisan user:grant-role {email} {role}` (bootstrap do primeiro admin).
- Cadastro aberto (role `player`). Somente o admin promove ou rebaixa `developer` pelo painel.
- Módulo Audit: `audit_entries` append-only (trigger), listener síncrono e consulta por entidade.
- Frontend: telas de auth, layout público e admin, guards de rota.

**Aceite:** I12 coberta por um teste genérico que percorre as rotas administrativas.
A concessão de role gera um `AuditEntry`.

## Fase 2 — Catálogo e submissão ⬜

- Agregado `Game`. Value objects `Slug`, `RepositoryUrl` (G1–G3) e `GitRef`.
- Submissão pelo `developer`: nome, descrição, autor, thumbnail (A4), URL e ref. Cria
  o `Game` (`submitted`) e a `GameVersion` 1 (`import_requested`).
- "Meus jogos": o dono acompanha o status, somente leitura.
- Admin: fila de submissões, com edição de slug antes da publicação.

**Aceite:** uma URL inválida, de host fora da allowlist ou com credenciais é rejeitada
na submissão. Nenhuma chamada de rede ocorre (I2).

## Fase 3 — Ingestão segura (Portão 0) ⬜

- Serviço `git-sandbox`: `ls-remote` + `fetch --depth=1` em repositório bare, leitura
  da árvore e dos blobs sem checkout, com todos os endurecimentos G1–G8.
- Porta `GitSourceFetcher` + adaptador HTTP + `FakeGitSourceFetcher`.
- `RepositoryPolicy` com as regras de [dominio.md](dominio.md#política-de-repositório-mvp) (TDD, uma regra por classe).
- Admin: autorizar ou rejeitar a importação (Portão 0) → `FetchRepositoryJob` →
  quarentena → `ValidationReport` → diff com a versão publicada.
- Repositórios-fixture maliciosos (symlink, submodule, arquivo gigante, nomes
  hostis, executável, redirect) nos testes de integração.

**Aceite:** todas as fixtures maliciosas resultam em violação bloqueante ou
`fetch_failed`, nunca em erro não tratado. O commit fica registrado.

## Fase 4 — Revisão (Portões 1 e 2) ⬜

- Tela do Portão 1: repositório, ref, commit, arquivos encontrados (✓/✗), violações
  e diff. Aprovar fica desabilitado quando há violação bloqueante (I3).
- Tela do Portão 2: visualizador de cada arquivo como texto (J5), hash, tipo de
  mudança e aprovação/rejeição com motivo, vinculada ao hash (I5).
- Histórico de decisões por arquivo e por versão.

**Aceite:** a rejeição de um arquivo obrigatório rejeita a versão. A auditoria
responde "quem aprovou qual hash, quando".

## Fase 5 — Publicação e execução isolada ⬜

- `PublishVersion` (I6–I8): verifica os hashes, grava o `archive` write-once, cria
  `served/{publicId}` e marca a versão anterior como `superseded`.
- Retirar versão e bloquear/desbloquear jogo (J6).
- nginx `games` com CSP e headers (J1–J3) e testes de headers.
- Página inicial pública (cards de jogos publicados) e página `/games/{slug}` com o
  iframe sandbox.
- Histórico de versões do jogo no admin.

**Aceite:** E2E — submeter → autorizar → aprovar commit → aprovar arquivos →
publicar → jogo visível e executável. Uma versão não publicada nunca é acessível
pela origem de jogos.

## Fase 6 — Partidas, scores e ranking ⬜

- `GameSession` com token de uso único, expiração e duração mínima (I10).
- Bridge `postMessage` na SPA (J4) e envio de score (S1, S2).
- Ranking por jogo (melhor score por jogador), histórico do usuário e cache Redis
  invalidado a cada novo score.
- Admin: ver e ocultar scores sinalizados.
- Jogo de exemplo em um repositório público, seguindo o [protocolo](protocolo-do-jogo.md).

**Aceite:** reenviar o mesmo score (replay) falha, e envios concorrentes resultam em
exatamente um registro. A limitação dos scores aparece na UI do ranking.

## Fase 7 — Painel administrativo e auditoria ⬜

- Painel com filas por estado (Portão 0, 1, 2, prontas para publicar) e filtros.
- Linha do tempo de auditoria por jogo, versão e usuário, respondendo todas as
  perguntas da seção *Auditoria* do [contexto](contexto-do-produto.md#auditoria).

**Aceite:** cada pergunta da lista de auditoria tem uma tela ou consulta que a responde.

## Fase 8 — Endurecimento e implantação ⬜

- Revisão de segurança completa contra [seguranca.md](seguranca.md).
- `docs/implantacao.md`: labels do Traefik para `bitman` e `bitman-games`, segredos em `~/.local/share/bitman/`, ativação do hook do deploy-hub, backups dos volumes
  (PostgreSQL e artefatos) e rotação de logs.
- Teste de fumaça pós-deploy: headers de segurança e hash dos arquivos servidos (C1, C2).
- Observabilidade mínima: logs estruturados, alertas de `fetch_failed` e de scores sinalizados.

## Fora do escopo do MVP

Assets adicionais (imagens, áudio, fontes); repositórios privados; build de jogos;
validação autoritativa de score; multiplayer; comentários e avaliações.

## Decisões de produto

| Questão | Decisão (2026-10-09) |
|---|---|
| Um admin pode aprovar um jogo do qual é dono? | **Sim.** A ação é auditada normalmente |
| O ranking considera todas as versões ou só a atual? | **Todas**, exibindo a versão de cada score |
| Quem pode se cadastrar? | **Qualquer pessoa**, como `player`. Só o admin promove um usuário a `developer` |
| Score máximo | Não há limite por jogo. O teto técnico é `9.007.199.254.740.991` (ver [dominio.md](dominio.md#score-limite-técnico)) |
| Domínio de produção do hub | `bitman.sandrigo.com.br` |
| Domínio da origem de jogos | `bitman-games.sandrigo.com.br`: subdomínio, com o risco aceito documentado no [ADR 0004](adr/0004-isolamento-dos-jogos.md) |
| Isolamento do Git | Serviço sandbox permanente + egress proxy com allowlist ([ADR 0003](adr/0003-git-sandbox.md)) |
| Infraestrutura e deploy | Servidor compartilhado: Cloudflare → Traefik, deploy-hub e GitHub Actions ([ADR 0010](adr/0010-infraestrutura-homelab.md)) |
| Cadastro | E-mail + senha, com verificação de e-mail e recuperação de senha |

## Pendências com o Rafael

Ações que dependem de acesso ou de decisão externa. Nenhuma bloqueia o início da Fase 0.

| Pendência | Necessária até |
|---|---|
| Provedor SMTP de produção (e-mails de verificação e de senha) | Fase 8 |
| Service Token do Cloudflare Access, `X-Deploy-Token` e aprovação do hash do `deploy-bitman.sh` | Fase 8 |
| Garantir que nenhum sistema em `*.sandrigo.com.br` emita cookies com `Domain=.sandrigo.com.br` | Fase 8 |
