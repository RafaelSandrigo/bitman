# Plano de desenvolvimento

Entrega incremental em **fatias verticais**: cada fase entrega backend, frontend,
testes e documentação de algo utilizável. A ordem segue o risco: a fundação e a
segurança da ingestão vêm antes das funcionalidades sociais.

Status: ⬜ não iniciada · 🟨 em andamento · ✅ concluída

## Fase 0 — Fundação ✅

**Objetivo:** ambiente reprodutível e esteira de qualidade antes de qualquer regra de negócio.

- ✅ Laravel Boost instalado com proteções contra recomendações comerciais ([ADR 0009](adr/0009-laravel-boost-restrito.md)).
- ✅ `docker-compose.yml` único (`name: bitman`) com `bitman-web`, `bitman-games`, `app`, `queue`, `scheduler`,
  `frontend`, `postgres`, `redis`, `git-sandbox` (stub) e `egress-proxy`; redes `internal`, `egress` e `sandbox`;
  limites de recursos; healthchecks; labels do Traefik com autenticação básica (pré-lançamento).
- ✅ Imagem PHP 8.5 (intl, pdo_pgsql, redis, pcntl, zip, bcmath, opcache) com Composer.
- ✅ nginx do hub (SPA + API na mesma origem, CSP, IP real) e da origem de jogos (CSP `sandbox`, `no-store, no-transform`, allowlist de arquivos).
- ✅ Backend: Pest 5, Larastan (nível 8), Pint (`strict_types`), Sanctum (modo SPA), spatie/laravel-permission e Scramble.
  Testes de arquitetura das camadas e módulos. Teste-trava do banco de testes. Scaffold web removido.
- ✅ Frontend: React Router, TanStack Query, Zod, React Hook Form, Tailwind, Vitest, Testing Library e MSW.
  Estrutura `app/ features/ shared/`. Cliente HTTP que valida toda resposta com Zod.
- ✅ Testes de infraestrutura: `tests/infra/games-origin.sh` e `tests/infra/sandbox-egress.sh`.
- ✅ `Makefile` (`make help`) restrito ao projeto.
- ✅ CI no GitHub Actions: backend, frontend e infra.

**Aceite:** `make check` passa no servidor, e o CI está verde.

## Fase 1 — Identidade, acesso e auditoria ✅

- ✅ Cadastro, login e logout com Fortify headless em `/api/v1/auth` ([ADR 0011](adr/0011-autenticacao-com-fortify.md)).
  Rate limit no login por e-mail + IP (A2) e em todas as rotas de autenticação.
- ✅ Verificação de e-mail e recuperação de senha, com e-mails em pt-BR enviados pela fila e links para a SPA (A5).
  O "esqueci a senha" não revela se a conta existe. Envio pelo Resend via SMTP, como no CPO (falta a API key).
- ✅ Usuários com ULID. Roles `player` (padrão), `developer` e `admin` como enums do domínio, com permissões granulares
  sincronizadas pelo seeder (`make migrate`).
- ✅ Comando `php artisan user:grant-role {email} {role}` (bootstrap do primeiro admin), auditado com ator nulo.
- ✅ Cadastro aberto como `player`. Só o admin promove ou rebaixa `developer` pelo painel, e só quem confirmou o e-mail.
- ✅ Módulo Audit: `audit_entries` append-only (trigger contra `UPDATE`/`DELETE`/`TRUNCATE`), contrato `Auditable`,
  listener síncrono na transação da Action, IP do cliente e consulta por entidade com paginação por cursor.
- ✅ Frontend:
  - entrar, criar conta, esqueci a senha, redefinir senha e confirmar e-mail;
  - aviso para quem não confirmou o e-mail;
  - painel admin (usuários e auditoria);
  - guards de rota, com `?next=` restrito a caminhos internos.

**Aceite:** ✅ I12 coberta por um teste genérico que percorre todas as rotas administrativas (401/403).
✅ A concessão de role gera um `AuditEntry` na mesma transação, e não o gera se a transação falhar.
✅ Fluxo verificado em produção (cadastro → e-mail → confirmação → login/logout → CSRF), e a conta de teste foi removida.

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
  `served/{publicId}` e marca a versão anterior como `superseded`. Arquivos com `0644` e diretórios
  com `0755`: o worker do nginx `bitman-games` precisa conseguir lê-los (descoberto no teste de infra da Fase 0).
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

- Executar o [checklist de lançamento](#checklist-de-lançamento).
- Revisão de segurança completa contra [seguranca.md](seguranca.md).
- `docs/implantacao.md`: labels do Traefik para `bitman` e `bitman-games`, segredos no `.env` da raiz (fora do git), ativação do hook do deploy-hub, backups dos volumes
  (PostgreSQL e artefatos) e rotação de logs.
- Teste de fumaça pós-deploy: headers de segurança e hash dos arquivos servidos (C1, C2).
- Observabilidade mínima: logs estruturados, alertas de `fetch_failed` e de scores sinalizados.

## Checklist de lançamento

Executado quando o Bitman sair do pré-lançamento:

- [ ] Remover os middlewares `bitman-auth` e `bitman-noindex` das labels do `bitman-web` e o `noindex` do `index.html`.
- [ ] Imagem PHP de produção com o código embutido, `composer install --no-dev` (o Boost sai junto) e `opcache.validate_timestamps=0`.
- [ ] Build do frontend dentro da imagem do `bitman-web`; remover o serviço `frontend` (watch).
- [ ] Conferir `APP_ENV=production` e `APP_DEBUG=false`.
- [ ] Ativar o deploy pelo deploy-hub (script, hook, tokens e aprovação do hash).
- [ ] Teste de fumaça pós-deploy (headers e hash dos arquivos servidos).
- [ ] Backups dos volumes `postgres_data` e `artifacts`.
- [ ] Resend configurado (`MAIL_MAILER=smtp` e `MAIL_PASSWORD`) e um e-mail real de verificação recebido. Depois, remover o `MAIL_LOG_CHANNEL`.

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
| Envio de e-mail | Resend via SMTP (`smtp.resend.com:587`), como no CPO. Remetente `nao-responda@bitman.sandrigo.com.br` |

## Pendências com o Rafael

Ações que dependem de acesso ou de decisão externa. Nenhuma bloqueia o início da Fase 0.

| Pendência | Necessária até |
|---|---|
| Resend: verificar o domínio `bitman.sandrigo.com.br` e criar uma API key própria do Bitman (só envio) para o `MAIL_PASSWORD`. Até lá, `MAIL_MAILER=log` e os e-mails aparecem em `make logs s=queue` | Antes do lançamento |
| Criar a própria conta no hub e virar admin: `make artisan c="user:grant-role seu@email admin"` (precisa do e-mail confirmado) | Fase 2 |
| Service Token do Cloudflare Access, `X-Deploy-Token` e aprovação do hash do `deploy-bitman.sh` | Fase 8 |
| Garantir que nenhum sistema em `*.sandrigo.com.br` emita cookies com `Domain=.sandrigo.com.br` | Fase 8 |
