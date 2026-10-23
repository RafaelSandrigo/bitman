# Plano de desenvolvimento

Entrega incremental em **fatias verticais**: cada fase entrega backend, frontend,
testes e documentação de algo utilizável. A ordem segue o risco: a fundação e a
segurança da ingestão vêm antes das funcionalidades sociais.

Status: ⬜ não iniciada · 🟨 em andamento · ✅ concluída

## Fase 0 — Fundação ✅

**Objetivo:** ambiente reprodutível e esteira de qualidade antes de qualquer regra de negócio.

- ✅ Laravel Boost instalado com proteções contra recomendações comerciais ([ADR 0009](adr/0009-laravel-boost-restrito.md)).
- ✅ `docker-compose.yml` único (`name: bitman`) com `bitman-web`, `bitman-games`, `app`, `queue`, `scheduler`,
  `frontend`, `postgres`, `redis`, `git-sandbox` (stub até a Fase 3) e `egress-proxy`; redes `internal`, `egress` e `sandbox`;
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
  O "esqueci a senha" não revela se a conta existe. Envio pelo Resend via SMTP, como no CPO.
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

## Fase 2 — Catálogo e submissão ✅

- ✅ Módulos `Catalog` e `Versioning`. Agregados `Game` e `GameVersion`; value objects `Slug` (Catalog) e
  `RepositoryUrl` (G1–G3) e `GitRef` (G1) no kernel compartilhado, porque Catalog, Versioning e Ingestion os usam.
- ✅ Submissão pelo `developer` (`POST /api/v1/games`): nome, descrição, autor, thumbnail (A4), URL e ref. Cria
  o `Game` (`submitted`) e, pelo evento `GameSubmitted`, a `GameVersion` 1 (`import_requested`), na mesma transação
  e com os dois registros de auditoria. Limite de 10 submissões por hora por usuário.
- ✅ Thumbnail: porta `ThumbnailProcessor` + adaptador GD (PNG/JPEG/WebP pelo conteúdo, até 2 MB, de 64 a 4096 px,
  re-codificada em WebP de até 800 px, sem metadados e com nome aleatório), servida pelo `bitman-web` em
  `/media/thumbnails` a partir do volume `media`.
- ✅ "Meus jogos" (`GET /api/v1/me/games`): o dono acompanha o status, somente leitura.
- ✅ Admin: fila de submissões, com edição de slug até a primeira publicação (auditada). Permissão `games.manage`.
- ✅ Frontend: área do developer (`/desenvolvedor/jogos` e `/desenvolvedor/enviar`) e `/admin/submissoes`.

**Aceite:** ✅ uma URL inválida, de host fora da allowlist ou com credenciais é rejeitada na submissão, sem criar nada.
✅ Nenhuma chamada de rede nem job ocorre (I2): `SubmitGameTest` com `Http::assertNothingSent()` e `Queue::assertNothingPushed()`.

## Fase 3 — Ingestão segura (Portão 0) ✅

- ✅ Serviço `git-sandbox` (`docker/git-sandbox`): `POST /fetch` autenticado por token (G8), clone **bare** raso
  (`--depth=1 --no-tags --single-branch --filter=blob:limit=<max>`), árvore lida com `ls-tree` e blobs com `cat-file`,
  sem checkout, com G1–G8. Uma única busca resolve o commit (o `ls-remote` separado do plano original virou redundante).
  Só devolve códigos de erro: a saída do git traz texto do servidor remoto, que é hostil.
- ✅ Porta `GitSourceFetcher` + `HttpGitSourceFetcher` (resposta tratada como hostil: teto de tamanho, JSON estrito,
  paths em base64, só os blobs pedidos e cada blob conferido contra o objeto Git da árvore) + `FakeGitSourceFetcher`.
- ✅ `RepositoryPolicy` com as 8 regras de [dominio.md](dominio.md#política-de-repositório-mvp), uma classe cada (TDD).
- ✅ Admin › Importações: autorizar ou rejeitar (com motivo) no Portão 0 → `FetchRepositoryJob` (fila `ingestion`) →
  quarentena (`{versionId}/{sha256}`) → `ValidationReport` → diff com a versão publicada → `awaiting_commit_review`
  (ou `validation_failed`, ver abaixo).
  Detalhe da versão com o relatório, os arquivos e os ignorados. Nova importação depois de falha ou rejeição.
- ✅ Violação bloqueante → `validation_failed` (terminal, sem quarentena), em vez de parar no Portão 1.
  "Meus jogos" mostra ao dono o estado da versão mais recente e o motivo (violações, rejeição do Portão 0
  ou falha da busca), com **Enviar novamente**: nova versão, de volta à autorização do admin.
- ✅ Repositórios-fixture (`tests/sandbox`): válido, tag, symlink (obrigatório e extra), submodule, executável, arquivo
  gigante, binário, nomes hostis, árvores forjadas com `..` e `.git`, árvore grande e acima do teto, sem obrigatórios e
  commit vazio. Gerados e buscados pela imagem real da sandbox, sem rede; o manifesto de cada um é um golden versionado.
- ✅ O worker da fila e o scheduler rodam como `www-data` (processam conteúdo hostil); os volumes `quarantine` e
  `artifacts` passaram a ter dono `www-data`.

**Aceite:** ✅ toda fixture maliciosa termina em violação bloqueante ou `fetch_failed`, nunca em erro não tratado
(`SandboxFixturesTest`, sobre os manifestos que a sandbox real gerou; `tests/infra/sandbox-fixtures.sh` confere o golden).
✅ O commit fica registrado (`commit_sha` + auditoria `versioning.repository-fetched`; `CHECK` no banco a partir do Portão 1).
Redirect: `http.followRedirects=false` e o código `redirected` são testados, mas não há fixture de redirect real (exigiria
um servidor HTTPS falso dentro da allowlist).

## Fase 4 — Revisão (Portões 1 e 2) ✅

- ✅ Portão 1 (`versions.approve-commit`): repositório, ref, commit, arquivos encontrados (✓) e ignorados (✗),
  violações e tipo de mudança de cada arquivo (o diff em relação à versão publicada). Aprovar fica desabilitado
  com violação bloqueante, e a Action também recusa (I3). Rejeitar exige motivo e descarta a quarentena.
- ✅ Portão 2 (`files.review`): visualizador de cada arquivo como texto (J5), com os caracteres bidirecionais e
  invisíveis marcados; hash, tamanho, tipo de mudança e "aprovado anteriormente por X" (ADR 0006, sem herança).
  A decisão leva o hash do conteúdo exibido (I5); rejeitar arquivo ou versão exige motivo (I11).
- ✅ `file_reviews` append-only (trigger no banco); histórico por arquivo e decisões de cada portão no detalhe da versão.
- ✅ Admin › **Versões** (antes "Importações"): filas dos três portões e das versões que pararam. O dono vê o motivo
  da rejeição em qualquer portão e pode reenviar.
- ✅ Abas de Versões com o total de cada estado e a tag **Novo** quando há versão que nenhum admin viu no estado atual.

**Aceite:** ✅ a rejeição de um arquivo obrigatório rejeita a versão (`ReviewGatesTest`). ✅ A auditoria
responde "quem aprovou qual hash, quando": `versioning.file-approved` com ator, data, `path` e `sha256`.

## Fase 5 — Publicação e execução isolada ✅

- ✅ `PublishVersion` (I6–I8, `versions.publish`): recalcula o SHA-256 de cada arquivo da quarentena, grava o
  `archive` write-once, cria `served/{publicId}` por `rename` atômico (diretórios `0755`, arquivos `0644`), tira a
  versão anterior do ar (`superseded`) e desfaz o que gravou se algo falhar antes do commit. Detalhes em
  [arquitetura.md](arquitetura.md#publicação).
- ✅ Retirar versão (`versions.publish`) e bloquear/desbloquear jogo (`games.block`), com motivo (J6). O desbloqueio
  volta a servir a partir do archive, conferindo os hashes de novo.
- ✅ nginx `games` com CSP e headers (J1–J3): feito na Fase 0, com `tests/infra/games-origin.sh`; conferido em produção
  pela URL pública (404 e `Cache-Control: no-store, no-transform` pela Cloudflare).
- ✅ Página inicial pública (cards dos jogos publicados) e `/games/{slug}` com o iframe `sandbox="allow-scripts"`
  (sem `allow-same-origin`, sem `allow`, sem Referer). A SPA só carrega uma URL `{origem de jogos}/{UUID}/index.html`.
- ✅ Admin: página do jogo (no ar ou não, bloqueio, histórico de versões), aba **Jogos** (publicados e bloqueados),
  publicar e retirar no detalhe da versão. O dono vê "Jogar" e o motivo de um bloqueio.

**Aceite:** ✅ fluxo completo pela API — submeter → autorizar → aprovar commit → aprovar arquivos → publicar → jogo no
catálogo com os arquivos servidos idênticos ao conteúdo aprovado (`EndToEndPublicationTest`). ✅ Uma versão não publicada
nunca é acessível pela origem de jogos: só a publicação cria o diretório servido, e retirada, bloqueio e substituição o
removem (`PublicationTest`). O E2E num navegador de verdade (Playwright) ficou para depois ([melhorias M11](melhorias.md)).

## Fase 6 — Partidas, scores e ranking ✅

- ✅ Contexto **Play**: `GameSession` com token de uso único (só o SHA-256 é guardado), prazo de 2 h e duração
  mínima de 5 s (I10). Começar uma partida encerra a anterior do mesmo jogo (índice parcial: uma aberta por jogador e
  jogo). Só conta com e-mail confirmado joga valendo score (A5); visitante joga sem sessão.
- ✅ Bridge `postMessage` na SPA (J4): só `event.source === iframe.contentWindow` com origem opaca, schema Zod e um
  único `game-over` por partida. Score inválido é rejeitado no hub, nunca arredondado.
- ✅ Envio do score (S1, S2): sessão travada na transação + `unique(game_session_id)`; inteiro JSON em
  `0..Number.MAX_SAFE_INTEGER` (string, fração e `1e3` são rejeitados); rate limit por jogador e jogo; jogo bloqueado
  ou versão retirada durante a partida não pontua. Score acima do p99 × 3 (com 20+ scores) é **sinalizado**.
- ✅ Ranking por jogo (melhor score por jogador, todas as versões, empates na mesma posição) em cache (Redis em
  produção), invalidado depois do commit de cada score registrado, ocultado ou devolvido. Histórico em
  **Minhas partidas**.
- ✅ Admin › **Scores** (`scores.moderate`): sinalizados, ocultos e todos; ocultar do ranking com motivo e devolver,
  auditados (`play.score-hidden`, `play.score-restored`).
- ✅ Jogo de exemplo (`examples/snake`) seguindo o [protocolo](protocolo-do-jogo.md#jogo-de-exemplo), conferido por
  `tests/infra/example-game.sh`. Falta só publicá-lo num repositório público e passá-lo pelos portões.

**Aceite:** ✅ reenviar o mesmo score (replay) falha com 409 (`GameSessionTest`), e envios concorrentes resultam em
exatamente um registro: `ScoreConcurrencyTest` dispara 6 processos (`pcntl_fork`), cada um com a própria conexão ao
PostgreSQL, no mesmo instante. ✅ A limitação dos scores aparece na UI do ranking (`GamePlayer.test.tsx`).

## Fase 7 — Painel administrativo e auditoria ✅

- ✅ **Painel** (`/admin`, página inicial da administração): cards das filas — Portão 0, Portão 1, Portão 2, prontas
  para publicar e paradas (busca falhou ou reprovada) — com o total e as versões novas, e link direto para cada fila.
  `GET /admin/versions/summary` passou a trazer `meta.ready_to_publish` (escopo `GameVersion::readyToPublish`, que espelha
  `allFilesApproved()`).
- ✅ **Filtros das filas** em Admin › Versões: busca por jogo, slug ou solicitante (`search`, sem curingas do `LIKE`) e
  "só as prontas para publicar" (`ready=1`). Fila, busca e recorte ficam na URL.
- ✅ **Linhas do tempo de auditoria** por jogo (`game_id`, que inclui as versões e os scores dele) e por usuário
  (`user_id`, o que aconteceu com ele e o que ele fez), além de filtro por ação e por ator. Cada entrada liga à tela do
  jogo ou da versão. Detalhes e o mapa pergunta → resposta em [arquitetura.md](arquitetura.md#consulta-da-auditoria).

**Aceite:** ✅ cada pergunta da seção *Auditoria* do [contexto](contexto-do-produto.md#auditoria) é respondida pela
linha do tempo do jogo, e `EndToEndPublicationTest` confere as respostas (quem, quando, URL, commit, arquivo, hash, versão
e URI) depois do fluxo completo.

## Fase 8 — Endurecimento e implantação 🟨

- ✅ **Deploy** ([implantacao.md](implantacao.md)): produção por imagens geradas de um commit (`make release`), com o
  código embutido e migrations antes da troca dos containers; desenvolvimento no perfil `dev`. Script do deploy-hub e
  trecho do hook em `deploy/`, workflow `deploy` depois do CI verde. `tests/infra/release-images.sh` confere as imagens.
  Falta a ativação pelo Rafael (deploy key, tokens, Cloudflare Access e aprovação do hash).
- Executar o [checklist de lançamento](#checklist-de-lançamento).
- Revisão de segurança completa contra [seguranca.md](seguranca.md).
- `docs/implantacao.md`: labels do Traefik para `bitman` e `bitman-games`, segredos no `.env` da raiz (fora do git), ativação do hook do deploy-hub, backups dos volumes
  (PostgreSQL e artefatos) e rotação de logs.
- Teste de fumaça pós-deploy: headers de segurança e hash dos arquivos servidos (C1, C2).
- Observabilidade mínima: alerta de `fetch_failed` (os logs em JSON e o alerta de scores sinalizados já entraram).

### Melhorias entregues na Fase 8

| Item | Entrega | Onde |
|---|---|---|
| Confirmação do score | Painel do Bitman sobre o jogo com o resultado (registrado, posição, recorde, recusado). Visitante e e-mail não confirmado jogam numa partida de visitante; o painel avisa que não estão logados e oferece cadastro e login, e o score é salvo na conta depois da confirmação | [arquitetura.md](arquitetura.md#partida-de-visitante), [dominio.md](dominio.md#guestplay-play), A10 |
| M19 | Apresentação do Bitman na home, antes da lista, com o CTA **Jogar agora** (primeiro jogo, ou a lista se não houver nenhum) | `HomePage.tsx` |
| M20 | Score sinalizado fica fora do ranking (e do p99) até o admin decidir: **Manter no ranking** (`play.score-kept`) ou ocultar. A aba padrão da moderação passa a ser **Em revisão** | [dominio.md](dominio.md#gamesession-e-score-play), S2 |
| M21 | Card **Scores em revisão** no painel (`GET /admin/scores/summary`) e e-mail a quem tem `scores.moderate` quando um score é sinalizado, no máximo um por jogo por hora. O e-mail não cita o jogador | `NotifyModeratorsOfFlaggedScore`, S2 |
| M18 | **Seja Desenvolvedor**: no menu e na home, o usuário com e-mail confirmado pede a role `developer` com nome, perfil do GitHub e apresentação. O admin aprova ou rejeita com motivo em **Pedidos de dev**, com card no painel. Tudo auditado | [dominio.md](dominio.md#developerrequest-identity), A11 |
| M12 | `bitman:reconcile-served`, de hora em hora: tira do ar o que está servido sem versão no ar e devolve ao ar, só a partir do archive e com os hashes conferidos, o que deveria estar servido (ou está diferente). Logs `served.*` e exit 1 em falha | [arquitetura.md](arquitetura.md#publicação), J6 |
| Logs | Canal `stderr` em JSON estruturado por padrão (`JsonFormatter`) | [convencoes.md](convencoes.md#logs) |
| M10 | Aviso **Há uma nova versão do Bitman — Recarregar**: `version.json` com o commit do release, comparado com o do bundle ao focar a aba e a cada 5 minutos | [implantacao.md](implantacao.md#make-release) |

## Checklist de lançamento

Executado quando o Bitman sair do pré-lançamento:

- [x] Remover o middleware `bitman-auth` (autenticação básica) das labels do `bitman-web` — antecipado em 13/10/2026.
- [ ] Remover o middleware `bitman-noindex` das labels do `bitman-web` e o `noindex` do `index.html`.
- [x] Imagem PHP de produção com o código embutido, `composer install --no-dev` (o Boost sai junto) e `opcache.validate_timestamps=0` (2026-10-22).
- [x] Build do frontend dentro da imagem do `bitman-web`; o serviço `frontend` passou ao perfil `dev`, sem watch (2026-10-22).
- [x] Conferir `APP_ENV=production` e `APP_DEBUG=false` (conferido no `.env` em 2026-10-22).
- [ ] Ativar o deploy pelo deploy-hub: script e hook prontos em `deploy/`; faltam deploy key, tokens, Cloudflare Access e aprovação do hash ([implantacao.md](implantacao.md#ativação-passos-do-rafael)).
- [ ] Teste de fumaça pós-deploy (headers e hash dos arquivos servidos).
- [ ] Backups dos volumes `postgres_data`, `artifacts` e `media`.
- [x] Resend configurado (`MAIL_MAILER=smtp` e `MAIL_PASSWORD`) e um e-mail real de verificação recebido (2026-10-11).
- [ ] Remover o `MAIL_LOG_CHANNEL` do `.env`.

## Fora do escopo do MVP

Assets adicionais (imagens, áudio, fontes); repositórios privados; build de jogos;
validação autoritativa de score; multiplayer; comentários e avaliações.

## Decisões de produto

| Questão | Decisão (2026-10-09) |
|---|---|
| Um admin pode aprovar um jogo do qual é dono? | **Sim.** A ação é auditada normalmente |
| O ranking considera todas as versões ou só a atual? | **Todas**, exibindo a versão de cada score |
| Quem pode se cadastrar? | **Qualquer pessoa**, como `player`. Só o admin promove um usuário a `developer`, diretamente ou aprovando o pedido "Seja Desenvolvedor" (M18) |
| Score máximo | Não há limite por jogo. O teto técnico é `9.007.199.254.740.991` (ver [dominio.md](dominio.md#score-limite-técnico)) |
| Domínio de produção do hub | `bitman.sandrigo.com.br` |
| Domínio da origem de jogos | `bitman-games.sandrigo.com.br`: subdomínio, com o risco aceito documentado no [ADR 0004](adr/0004-isolamento-dos-jogos.md) |
| Isolamento do Git | Serviço sandbox permanente + egress proxy com allowlist ([ADR 0003](adr/0003-git-sandbox.md)) |
| Infraestrutura e deploy | Servidor compartilhado: Cloudflare → Traefik, deploy-hub e GitHub Actions ([ADR 0010](adr/0010-infraestrutura-homelab.md)) |
| Cadastro | E-mail + senha, com verificação de e-mail e recuperação de senha |
| Envio de e-mail | Resend via SMTP (`smtp.resend.com:587`), como no CPO. Remetente `nao-responda@bitman.sandrigo.com.br` (configurado em 2026-10-11) |
| Thumbnail na submissão | Obrigatória (os cards do catálogo dependem dela) |

## Pendências com o Rafael

Ações que dependem de acesso ou de decisão externa. Nenhuma bloqueia o início da Fase 0.

| Pendência | Necessária até |
|---|---|
| Virar admin (a conta já existe e o e-mail está confirmado): `make artisan c="user:grant-role seu@email admin"` | Para usar o Portão 0 (Fase 3) |
| Service Token do Cloudflare Access, `X-Deploy-Token` e aprovação do hash do `deploy-bitman.sh` | Fase 8 |
| Garantir que nenhum sistema em `*.sandrigo.com.br` emita cookies com `Domain=.sandrigo.com.br` | Fase 8 |
