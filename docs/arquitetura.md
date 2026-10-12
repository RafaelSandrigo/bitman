# Arquitetura

Visão técnica do Bitman. As regras de negócio estão em [dominio.md](dominio.md)
e as ameaças e mitigações em [seguranca.md](seguranca.md).

## Visão geral

Monólito modular em Laravel 13 (API) com SPA em React + TypeScript. Três peças
ficam **fora** do monólito por motivo de segurança:

1. **git-sandbox**: o único componente que fala com repositórios Git externos.
2. **egress-proxy**: a única saída de rede da sandbox, que libera apenas os hosts Git permitidos.
3. **games origin**: servidor estático em outro host, que só serve artefatos publicados.

Nome do projeto na infraestrutura: **`bitman`** (prefixo de containers, routers e services).

```mermaid
flowchart LR
    subgraph Navegador
        SPA[SPA React<br/>bitman.sandrigo.com.br]
        IFR[iframe sandbox<br/>bitman-games.sandrigo.com.br]
        SPA <-- postMessage --> IFR
    end

    CF[Cloudflare<br/>TLS + túnel] --> TRAEFIK[Traefik compartilhado<br/>prod-network]
    SPA --> CF
    IFR --> CF

    subgraph bitman_front[prod-network: só os serviços de entrada]
        WEB[bitman-web<br/>nginx: SPA + proxy /api]
        GAMES[bitman-games<br/>nginx estático, somente leitura]
    end

    subgraph bitman_internal[internal: sem internet]
        APP[app<br/>Laravel php-fpm]
        QUEUE[queue<br/>filas ingestion / default]
        PG[(postgres)]
        RD[(redis)]
    end

    subgraph bitman_sandbox[sandbox: sem internet]
        SBX[git-sandbox<br/>sem DB, Redis ou volumes]
        PROXY[egress-proxy<br/>allowlist de hosts Git]
    end

    TRAEFIK --> WEB & GAMES
    WEB --> APP
    APP --> PG & RD
    QUEUE --> PG & RD
    QUEUE -- HTTP + token --> SBX
    SBX -- CONNECT --> PROXY
    PROXY -- HTTPS :443 --> GIT[(GitHub / GitLab / Codeberg)]
    QUEUE -- escreve --> VOL_Q[(quarentena)]
    APP -- lê --> VOL_Q
    APP -- publica: escreve uma vez --> VOL_A[(artefatos)]
    VOL_A -- somente leitura --> GAMES
```

## Infraestrutura

Ela segue as convenções do servidor compartilhado ([ADR 0010](adr/0010-infraestrutura-homelab.md)):

- **Borda:** navegador → Cloudflare (o TLS termina aqui) → túnel → Traefik v3 compartilhado → `bitman-web` / `bitman-games`.
  Não há `ports:`, nem certificados, nem outro proxy de borda.
- **Domínios:** hub em `bitman.sandrigo.com.br`, jogos em `bitman-games.sandrigo.com.br`. São subdomínios de
  primeiro nível porque o certificado universal da Cloudflare cobre só `*.sandrigo.com.br`. O túnel já
  encaminha `*.sandrigo.com.br` ao Traefik, que roteia pelo `Host` declarado nas labels dos containers.
  **Não há configuração na Cloudflare.**
- **Um ambiente só:** não existe ambiente de desenvolvimento separado. O projeto é desenvolvido direto no
  servidor, em `/mnt/Dados/prog/prod/bitman`, num único `docker-compose.yml` (`name: bitman`), sempre
  operado pelo `Makefile`. Até o lançamento vale o **modo pré-lançamento** (abaixo).
- **Dados:** volumes Docker nomeados (PostgreSQL, Redis, quarentena, artefatos, thumbnails e `node_modules` do frontend), nunca
  bind mount em `/mnt/Dados` (é NTFS, onde `chmod` não tem efeito). Os segredos ficam no `.env` da raiz, ignorado
  pelo git e entregue aos containers por `env_file` e, montado como `backend/.env`, somente leitura (padrão Carphook).
  Só o `.env.example` é versionado.
- **Testes:** rodam dentro dos containers, no banco `bitman_test` do mesmo PostgreSQL. O `phpunit.xml` força esse banco,
  e um teste-trava (`tests/Feature/TestEnvironmentTest.php`) falha se algum dia apontar para outro.
- **Deploy (a partir do lançamento):** GitHub Actions (CI verde em `main`) → `POST https://deploy.sandrigo.com.br/hooks/deploy-bitman`
  → `deploy-hub/scripts/deploy-bitman.sh` (executado só depois que o Rafael aprova o hash).

### Modo pré-lançamento

Enquanto não há usuários reais:

| Item | Pré-lançamento | No lançamento (ver [plano.md](plano.md#checklist-de-lançamento)) |
|---|---|---|
| Acesso ao hub | Autenticação básica no Traefik (`BITMAN_BASIC_AUTH`) + `X-Robots-Tag: noindex` | Removidos |
| Código PHP | Bind mount de `./backend` (mudança vale na hora) | Imagem com o código embutido, sem dependências de dev |
| Frontend | Container `frontend` com `vite build --watch` gerando `frontend/dist` | Build feito na imagem do `bitman-web` |
| OPcache | Revalida timestamps | `validate_timestamps=0` |

O host de jogos **não** tem autenticação básica: o navegador não pede senha para recursos de um iframe isolado de
outra origem, e esse host só serve jogos publicados, em caminhos UUID não adivinháveis, com `noindex`.

## Containers

| Serviço | Papel | Redes | Observações |
|---|---|---|---|
| `bitman-web` | nginx: serve `frontend/dist` e as thumbnails (`/media/thumbnails`, volume `media` somente leitura) e encaminha `/api`, `/sanctum` e `/up` para o php-fpm | prod-network, internal | SPA e API na **mesma origem** (cookies Sanctum sem CORS). CSP do hub e IP real via `CF-Connecting-IP` |
| `bitman-games` | nginx estático da origem de jogos | prod-network | Monta o subdiretório `served` do volume de artefatos, somente leitura. Só serve `/{uuid}/{index.html,style.css,game.js}`; o resto é 404 |
| `app` | Laravel (php-fpm 8.5) | internal, egress | Imagem `bitman-php` (construída só por este serviço) |
| `queue` | `queue:work` (filas `ingestion` e `default`) | internal, egress, sandbox | É o único que fala com a sandbox |
| `scheduler` | `schedule:work` | internal, egress | — |
| `frontend` | `vite build --watch` (pré-lançamento) | egress | `node_modules` em volume próprio (binários da imagem alpine) |
| `postgres` | PostgreSQL 17 (bancos `bitman` e `bitman_test`) | internal | — |
| `redis` | Redis 8.2 com senha: fila, cache, sessão e rate limit | internal | — |
| `git-sandbox` | Busca repositórios e devolve um manifesto (stub até a Fase 3) | sandbox | Usuário não-root, rootfs read-only, `tmpfs` de 256 MB, `cap_drop: ALL`, `no-new-privileges`, limites de CPU, memória e pids |
| `egress-proxy` | tinyproxy: só `CONNECT` na 443 para `github.com`, `gitlab.com` e `codeberg.org` | sandbox, egress | O único caminho da sandbox para a internet. Bloqueia a LAN e os outros projetos |

Os containers ficam com os nomes `bitman-<serviço>-1`. As redes do projeto são `internal` (`internal: true`: banco e
cache sem internet), `egress` (saída para a internet: SMTP e npm) e `sandbox` (`internal: true`, com saída só pelo proxy).
Só `bitman-web` e `bitman-games` entram na `prod-network`. Todo container tem limite de CPU e memória.

**Atrás da Cloudflare:** o nginx `bitman-web` obtém o IP real do cliente por `CF-Connecting-IP` (`set_real_ip_from` só para
redes privadas) e o repassa ao PHP como `REMOTE_ADDR`, o que é essencial para o rate limit. Como o acesso público é sempre
HTTPS e o trecho Traefik → nginx é HTTP interno, o nginx informa `HTTPS=on` ao PHP.

## Backend: módulos e camadas

```
backend/app/
├── Modules/
│   ├── Identity/
│   ├── Catalog/
│   ├── Versioning/
│   ├── Ingestion/
│   ├── Play/
│   └── Audit/
└── Shared/              # kernel compartilhado: eventos de domínio, exceção base, RepositoryUrl e GitRef (Domain/Git)
```

Cada módulo usa as mesmas camadas, e só cria as que precisa:

```
Modules/Versioning/
├── Domain/           # Modelos-agregado, enums de estado, value objects, eventos, exceções, portas (interfaces)
├── Application/      # Actions (um caso de uso = uma classe), DTOs de entrada
├── Infrastructure/   # Adaptadores das portas (sandbox HTTP, filesystem), jobs, listeners
└── Http/             # Controllers finos, FormRequests, API Resources, rotas do módulo
```

### Regra de dependência

```
Http ──► Application ──► Domain ◄── Infrastructure
```

- `Domain` não conhece `Http`, `Infrastructure`, `Request`, `Auth` nem facades de I/O.
- `Application` depende do `Domain` e das **portas** declaradas nele; nunca de um adaptador concreto.
- Um módulo só usa de outro módulo os seus **eventos**, **IDs** e **contratos públicos**
  (ex.: `Catalog` reage a `VersionPublished`; ele não altera `GameVersion`).
- As regras são verificadas por **testes de arquitetura do Pest** (`tests/Arch`), que
  quebram o build quando violadas.

### Decisões de design (e por que são simples)

| Tema | Decisão | Motivo |
|---|---|---|
| Persistência | Modelos Eloquent **são** os agregados. Não há Repository Pattern sobre o Eloquent | KISS: o Eloquent já é a camada de persistência e a abstração extra não paga o custo |
| Transições de estado | Apenas por métodos de intenção (`$version->approveCommit($admin, $reason)`), que validam a transição e emitem o evento. `status` não é *fillable* | Mantém as invariantes dentro do agregado (padrão State via enum com mapa de transições) |
| Portas e adaptadores | Só onde há I/O externo ou não determinístico: `GitSourceFetcher`, `QuarantineStore`, `ArtifactStore`, `ThumbnailProcessor`, `Clock` | Permite testar o domínio sem rede nem disco e trocar a infraestrutura depois |
| Casos de uso | Actions invocáveis (`AuthorizeImport`, `ApproveCommit`, `ApproveFile`, `PublishVersion`, `SubmitScore`...) | SRP. Qualquer entrada (HTTP, CLI, job) passa pelo mesmo caminho |
| Autorização | Gate **dentro da Action** (não só no controller): permissão do enum `Permission`, ou Policy quando depende do recurso. Rotas admin sempre no grupo `/api/v1/admin` | Uma nova rota não consegue "esquecer" a autorização (I12) |
| Política de repositório | Lista de regras `RepositoryRule` (Specification) compostas em `RepositoryPolicy` | Open/Closed: uma nova regra é uma nova classe, testada isoladamente |
| Eventos de domínio | O agregado registra (`RecordsDomainEvents`); a Action persiste e publica (`DomainEventPublisher`) dentro da transação | O domínio não depende do dispatcher, e os listeners síncronos participam do mesmo commit |
| Auditoria | Eventos que implementam `Audit\Domain\Auditable` → listener **síncrono** que grava `audit_entries` na mesma transação | A auditoria não se perde se a fila cair (I11), e os módulos não dependem do Audit além do contrato |
| Erros de domínio | Exceções `BusinessRuleViolation` com mensagem em pt-BR → HTTP 422, mapeadas só em `bootstrap/app.php` | Um único ponto de tradução domínio → HTTP |
| IDs | ULID nas entidades expostas; UUID v4 em `publicId` do artefato | Não enumeráveis e ordenáveis |
| API | JSON REST, `/api/v1`, respostas com API Resources. OpenAPI gerada a partir do código (Scramble) | Contrato documentado sem manutenção manual |

### API e autenticação

- Rotas em `/api/v1`. Cada módulo declara as suas em `Http/routes.php` (públicas) e `Http/admin-routes.php`
  (administrativas). O `routes/api.php` inclui as administrativas **só** dentro do grupo
  `/api/v1/admin` (`auth:sanctum` + `verified` + `role:admin`).
- Autenticação: Fortify headless em `/api/v1/auth` (`register`, `login`, `logout`, `forgot-password`, `reset-password`,
  `email/verify/{id}/{hash}`, `email/verification-notification`), mais `GET /api/v1/me` ([ADR 0011](adr/0011-autenticacao-com-fortify.md)).
- A SPA busca `/sanctum/csrf-cookie` antes da primeira requisição que altera estado e repete uma vez em caso de 419.
- Os e-mails saem pela fila `default`, com links para as rotas da SPA (`/verificar-email?link=…`, `/redefinir-senha?token=…`).
- Catálogo: `POST /api/v1/games` (submissão, multipart, `games.submit`) e `GET /api/v1/me/games`; no admin,
  `GET /api/v1/admin/submissions` e `PATCH /api/v1/admin/games/{id}/slug` (`games.manage`).
- Comunicação entre módulos: o Versioning escuta `Catalog\Domain\Events\GameSubmitted` (listener síncrono, mesma transação)
  para criar a versão 1. Nenhum módulo altera tabelas de outro.

## Fluxos principais

### Ingestão (Portão 0 → Portão 1)

```mermaid
sequenceDiagram
    actor Admin
    participant API as Laravel API
    participant Q as Worker (ingestion)
    participant S as git-sandbox
    participant G as Host Git

    Admin->>API: POST /admin/versions/{id}/authorize-import
    API->>API: Policy + transição import_requested → fetching + AuditEntry
    API->>Q: FetchRepositoryJob(versionId)
    Q->>S: POST /fetch {url, ref} (já validados pelos value objects)
    S->>G: git ls-remote / fetch --depth=1 (somente HTTPS, sem redirects)
    S-->>Q: manifesto: commitSha, árvore (path, modo, tamanho), blobs permitidos
    Q->>Q: Revalida tudo (zero trust também na sandbox): RepositoryPolicy → ValidationReport
    Q->>Q: Grava blobs na quarentena; calcula SHA-256; diff com a versão publicada
    Q->>API: transição fetching → awaiting_commit_review
    Admin->>API: POST /admin/versions/{id}/approve-commit
```

### Publicação

`PublishVersion` (em uma transação): verifica I6 lendo cada blob da quarentena e
recalculando o SHA-256 → grava em `artefatos/archive/{gameId}/versions/{n}/` (write-once)
→ copia para `artefatos/served/{publicId}/` → marca a versão anterior como `superseded`
e remove o diretório servido dela → emite `VersionPublished`. Bloquear um jogo ou
retirar uma versão remove o diretório `served` correspondente. O `archive` é permanente.

### Partida e score

```mermaid
sequenceDiagram
    actor U as Jogador
    participant SPA as SPA (hub)
    participant IF as iframe (games origin)
    participant API as Laravel API

    U->>SPA: abre /games/{slug}
    SPA->>API: POST /api/v1/games/{slug}/sessions
    API-->>SPA: {sessionId, sessionToken, playUrl}
    SPA->>IF: carrega playUrl (sandbox="allow-scripts")
    IF-->>SPA: postMessage {type: "game-over", score}
    SPA->>SPA: confere event.source e valida o schema (zod)
    SPA->>API: POST /api/v1/game-sessions/{id}/score {score, sessionToken}
    API->>API: I10 + limites + rate limit + sinalização de anomalia
    API-->>SPA: posição no ranking
```

O jogo **não tem acesso à rede** (`connect-src 'none'`). Toda comunicação passa pela
SPA. Contrato para desenvolvedores: [protocolo-do-jogo.md](protocolo-do-jogo.md).

## Frontend

React 19 + TypeScript (strict) + Vite.

| Necessidade | Escolha |
|---|---|
| Rotas | React Router |
| Estado do servidor | TanStack Query |
| Validação de respostas da API e de mensagens do iframe | Zod (zero trust também nas fronteiras do cliente) |
| Formulários | React Hook Form + Zod |
| Estilo | Tailwind CSS |
| Testes | Vitest + Testing Library + MSW. Playwright para os fluxos críticos E2E |
| Qualidade | oxlint + `tsc --noEmit` |

Organização por feature, com dependências apenas `features → shared`:

```
frontend/src/
├── app/            # roteamento, providers, layouts, guards de rota por role
├── features/
│   ├── auth/
│   ├── catalog/    # listagem e página do jogo
│   ├── play/       # host do iframe, bridge postMessage, sessão
│   ├── ranking/
│   ├── submissions/  # área do desenvolvedor
│   └── admin/      # filas de revisão, portões, visualizador de arquivos, auditoria
└── shared/         # cliente HTTP, componentes de UI, utilitários
```

Os guards de rota no frontend servem só para UX. **A autorização real é sempre do backend.**

## Dados (visão inicial)

```mermaid
erDiagram
    users ||--o{ games : "é dono de"
    games ||--o{ game_versions : possui
    game_versions ||--o{ version_files : contém
    version_files ||--o{ file_reviews : "decisões"
    games ||--o{ game_sessions : ""
    game_versions ||--o{ game_sessions : ""
    users ||--o{ game_sessions : joga
    game_sessions ||--o| scores : "no máximo 1"
    users ||--o{ audit_entries : "ator"
```

Restrições no banco reforçam o domínio: `unique(game_id, number)`,
`unique(scores.game_session_id)`, índice parcial que garante uma única versão
`published` por jogo e um trigger no PostgreSQL que impede `UPDATE`/`DELETE` em
`audit_entries` e `file_reviews`.
