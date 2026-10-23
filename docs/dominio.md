# Domínio

Modelo de domínio do Bitman: a linguagem que usamos, os agregados, os estados
e as regras que nunca podem ser quebradas. Cada invariante listada aqui **tem
pelo menos um teste automatizado** que a referencia pelo código (ex.: `I5`).

## Linguagem ubíqua

O código é escrito em inglês e a interface e a documentação em português. Esta
tabela é o dicionário entre os dois. Não invente sinônimos.

| Termo (PT) | Código | Significado |
|---|---|---|
| Jogo | `Game` | O produto exibido no catálogo. Tem slug, metadados e no máximo uma versão publicada. |
| Dono do jogo | `owner` | Usuário com role `developer` que submeteu o jogo. Só submete; nunca aprova. |
| Autor | `authorName` | Nome exibido como criador. É texto livre e pode diferir do dono. |
| Versão | `GameVersion` | Um estado do repositório (commit) importado para análise. Numerada sequencialmente por jogo. |
| Solicitação de importação | `GameVersion` em `import_requested` | Pedido para buscar o repositório. Ainda não houve nenhum acesso à rede. |
| Portão 0 — Autorização Git | `authorizeImport` / `rejectImport` | O admin autoriza o sistema a acessar o repositório. |
| Portão 1 — Aprovação do commit | `approveCommit` / `rejectCommit` | O admin confirma que *este commit* é a fonte a analisar. |
| Portão 2 — Aprovação de arquivo | `approveFile` / `rejectFile` | O admin aprova o conteúdo exato (hash) de um arquivo. |
| Publicação | `publish` | O admin torna a versão executável, depois de verificados os hashes. |
| Relatório de validação | `ValidationReport` | Resultado das regras de repositório: violações bloqueantes e arquivos ignorados. |
| Arquivo da versão | `VersionFile` | Um arquivo candidato (path permitido + SHA-256 + tamanho) de uma versão. |
| Quarentena | `QuarantineStore` | Armazenamento dos blobs ainda não publicados. Nunca é servido. |
| Artefato | `ArtifactStore` | Cópia imutável dos arquivos de uma versão publicada. |
| Origem de jogos | `games origin` | Host separado (`bitman-games.sandrigo.com.br`) que serve apenas artefatos publicados. |
| Sessão de jogo | `GameSession` | Uma partida iniciada por um usuário numa versão publicada. Aceita no máximo um score. |
| Pontuação | `Score` | Resultado enviado ao final de uma sessão. |
| Ranking | `Leaderboard` | O melhor score de cada jogador em um jogo. |
| Registro de auditoria | `AuditEntry` | Fato imutável: quem fez o quê, quando, sobre qual estado. |

## Contextos (bounded contexts)

| Contexto | Responsabilidade | Tipo |
|---|---|---|
| **Versioning** | Ciclo de vida da versão: portões, arquivos, hashes, publicação | Núcleo (core) |
| **Ingestion** | Obter o repositório via sandbox e aplicar a política de repositório | Suporte |
| **Catalog** | Jogo, submissão, slug, thumbnail, listagem pública, bloqueio | Suporte |
| **Play** | Sessões, scores e ranking | Suporte |
| **Identity** | Usuários, autenticação, roles e permissões | Genérico |
| **Audit** | Registro append-only das ações | Genérico |

Os contextos se comunicam por **eventos de domínio** e por **IDs**, nunca alterando
diretamente os dados de outro contexto. Detalhes em [arquitetura.md](arquitetura.md).

## Agregados

### User (Identity)

- Campos: `id` (ULID), `name`, `email` (único, em minúsculas), `email_verified_at` e a senha (hash).
- Toda conta nasce com a role `player`, que é permanente.
- `grantRole(role, actor)` / `revokeRole(role, actor)` são os únicos meios de mudar roles. Eles recusam role repetida,
  remoção de `player`, remoção de role ausente e roles acima de `player` para quem não confirmou o e-mail.
  Cada mudança registra `RoleGranted` / `RoleRevoked`, que são auditáveis.
- Pelo painel só se promove ou rebaixa `developer`. `admin` só é concedido pelo console (`user:grant-role`).

| Role | Permissões |
|---|---|
| `player` | — (joga e aparece nos rankings, com e-mail confirmado) |
| `developer` | `games.submit` |
| `admin` | todas: `games.submit`, `games.manage`, `versions.authorize-import`, `versions.approve-commit`, `files.review`, `versions.publish`, `games.block`, `scores.moderate`, `users.manage-roles` e `audit.view` |

### Game (Catalog)

- Campos: `id` (ULID), `slug` (único), `name`, `description`, `authorName`,
  `thumbnailPath`, `ownerId`, `repositoryUrl`, `defaultRef`, `status`,
  `publishedVersionId` e as datas.
- Status: `submitted` → `published` ⇄ `blocked`. Também existe `rejected`, quando a
  primeira versão é rejeitada; o dono pode solicitar uma nova importação (ainda não usado: [melhorias M8](melhorias.md)).
- `publishedVersionId` aponta a versão no ar. O Catalog reage aos eventos do Versioning na mesma
  transação: `VersionPublished` → `recordPublication()` (jogo `published`, `firstPublishedAt` na primeira vez,
  slug imutável dali em diante) e `VersionWithdrawn` → `clearPublication()` (jogo fica sem versão jogável).
- `block()` (só de `published`, motivo obrigatório, `catalog.game-blocked`) e `unblock()` (`catalog.game-unblocked`,
  permissão `games.block`). O bloqueio tira a versão do ar dentro da transação; o desbloqueio volta a servi-la
  a partir do `archive` **depois** do commit, conferindo de novo cada hash. Jogo bloqueado não recebe publicação.
- **Jogável (I9):** `published` **e** `publishedVersionId` não nulo. Só esses aparecem em `GET /api/v1/games`
  e `GET /api/v1/games/{slug}`; qualquer outro estado responde 404.
- O slug é gerado a partir do nome (`Pac-Man Clássico` → `pac-man-classico`, com sufixo `-2`, `-3`… se já
  existir) e pode ser editado pelo admin **até a primeira publicação** (`changeSlug`, auditado como
  `catalog.slug-changed`). Depois disso é imutável (URIs públicas não quebram). Formato: 2 a 60 caracteres,
  minúsculas e números separados por hífens.
- `Game::submit()` é a única forma de criar um jogo. Ele registra `GameSubmitted` (auditado como
  `catalog.game-submitted`), e o Versioning reage criando a versão 1 em `import_requested` (auditada como
  `versioning.import-requested`), na mesma transação.
- A URL do repositório e a ref são value objects do kernel compartilhado (`RepositoryUrl` e `GitRef`):
  só `https`, hosts `github.com`, `gitlab.com` e `codeberg.org`, sem credenciais, porta, query ou fragmento,
  e a URL é normalizada (`https://github.com/ada/snake.git` → `https://github.com/ada/snake`).

### GameVersion (Versioning) — agregado central

Contém `VersionFile[]` e o histórico de decisões. Campos principais: `number`,
`status`, `repositoryUrl` (snapshot), `requestedRef`, `commitSha`, `validationReport`,
`requestedBy`, `fetchedAt`, decisões de cada portão (quem, quando, motivo) e `publicId`
(UUID usado no caminho público do artefato).

```mermaid
stateDiagram-v2
    [*] --> import_requested: dono/admin solicita importação
    import_requested --> import_rejected: Portão 0 — admin rejeita
    import_requested --> fetching: Portão 0 — admin autoriza
    fetching --> fetch_failed: erro, timeout ou limite excedido
    fetching --> validation_failed: relatório com violação bloqueante
    fetching --> awaiting_commit_review: commit resolvido + relatório sem violações
    awaiting_commit_review --> commit_rejected: Portão 1 — admin rejeita
    awaiting_commit_review --> awaiting_file_review: Portão 1 — admin aprova (sem violações bloqueantes)
    awaiting_file_review --> rejected: admin rejeita arquivo obrigatório ou a versão
    awaiting_file_review --> published: admin publica (todos aprovados + hashes conferem)
    published --> superseded: outra versão do jogo é publicada
    published --> withdrawn: admin retira a versão
    import_rejected --> [*]
    fetch_failed --> [*]
    validation_failed --> [*]
    commit_rejected --> [*]
    rejected --> [*]
    superseded --> [*]
    withdrawn --> [*]
```

Estados terminais nunca voltam. Para tentar de novo, cria-se uma **nova versão**
(`RequestNewImport`, pela URL e ref atuais do jogo, de volta ao Portão 0). Quem pede é o
**dono** (reenvio em "Meus jogos", com a permissão de submeter) ou o admin; jogo bloqueado não
recebe versão nova. O dono vê a versão mais recente de cada jogo (`GET /api/v1/me/versions`):
o estado e, quando parou, o motivo — violações da política, motivo da rejeição do Portão 0 ou a
mensagem da falha da busca —, nunca quem decidiu nem a lista de arquivos ignorados. Um jogo tem
**no máximo uma versão em andamento** (`import_requested`, `fetching`,
`awaiting_commit_review`, `awaiting_file_review`), garantido por índice parcial no banco.
A partir de `awaiting_commit_review`, `commit_sha` e o relatório são obrigatórios (`CHECK`, I13).

**Publicação** (`versions.publish`): `assertPublishable()` (em `awaiting_file_review`, todos os arquivos
aprovados) → `publish()` grava o `publicId` (UUID novo, o único nome do diretório servido) e registra
`versioning.version-published` com o hash de cada arquivo. A anterior vai para `superseded` (`supersede()`)
**antes**, porque o índice parcial só admite uma `published` por jogo (I8). `withdraw()` exige motivo.

**"Novo" nas filas do admin** (`status_seen_at`): toda transição passa por `moveTo()`. A feita por um
admin já nasce vista; a do sistema ou do dono (submissão, reenvio, fim da busca) fica nova até algum
admin exibir a versão (na fila ou no detalhe), desde que ela ainda esteja no estado exibido. Marcar
como vista é leitura, não decisão de portão: não gera `AuditEntry`. As abas mostram, por estado, o
total e se há novas (`GET /api/v1/admin/versions/summary`).

### VersionFile e decisões de arquivo

- Campos: `path` (um dos paths permitidos), `sha256`, `size`, `gitBlobSha`,
  `changeType` em relação à versão publicada (`added` | `modified` | `unchanged`)
  e `reviewStatus` (`pending` | `approved` | `rejected`).
- Toda decisão vira um registro append-only `FileReview` com
  (`versionFileId`, `sha256` analisado, `decision`, `reviewerId`, `reason`, `decidedAt`).
  Enquanto a versão está em `awaiting_file_review`, o admin pode mudar de ideia sobre uma
  aprovação; o status atual é o da última decisão. Como todo arquivo da versão é obrigatório,
  **rejeitar um arquivo rejeita a versão** (motivo: "Arquivo X rejeitado: …"), e aí não há
  mais volta. O admin também pode rejeitar a versão inteira, com motivo.
- A decisão leva o SHA-256 do conteúdo que o admin abriu: só há botão de aprovar depois de
  abrir o arquivo, e o conteúdo é conferido contra o hash do fetch antes de ser mostrado.
- Rejeição do commit ou da versão descarta a quarentena dela (o hash fica no banco).
  Com todos os arquivos aprovados, a versão continua em `awaiting_file_review`, pronta para
  a publicação (Fase 5).
- **Não há herança de aprovação entre versões.** Um arquivo `unchanged` mostra
  "aprovado anteriormente por X em Y" como informação, mas precisa de nova aprovação
  explícita ([ADR 0006](adr/0006-aprovacao-vinculada-ao-hash.md)).

### GameSession e Score (Play)

- `GameSession`: `id` (ULID), `userId`, `gameId`, `gameVersionId`, `tokenHash`,
  `status` (`open` → `scored` | `expired`), `startedAt`, `expiresAt` e `endedAt`.
  - `start()` só numa versão jogável (I9) e para conta com e-mail confirmado (A5). Gera um token de 256 bits; só o
    SHA-256 é guardado, e o token em claro vai uma única vez para a SPA (nunca para o jogo).
  - Começar uma partida expira a aberta anterior do mesmo jogo (`expired`): no máximo uma `open` por jogador e jogo
    (índice parcial). Depois de `expiresAt` (2 h), a sessão continua `open` no banco, mas não aceita score.
  - `recordScore()` aplica I10, nesta ordem: o token confere e a sessão é do jogador (senão, "sessão inválida", sem
    revelar mais nada); está `open` (`scored` → replay, 409); dentro do prazo; durou o mínimo (5 s).
- `Score`: `gameSessionId` (único), `userId`, `gameId`, `gameVersionId`, `value`
  (ver limite abaixo), `flagged` + `flagReason`, ocultação (`hiddenById`, `hiddenAt`, `hideReason`) e `createdAt`.
  - Sinalizado (S2) quando o jogo já tem 20+ scores visíveis e o valor passa de p99 × 3 (`AnomalyPolicy`). Continua
    no ranking até um admin decidir.
  - `hide()` (motivo obrigatório, `play.score-hidden`) e `restore()` (`play.score-restored`), com `scores.moderate`.
    O valor nunca muda.
  - Jogo bloqueado ou versão retirada durante a partida: o score não entra. Versão substituída durante a partida:
    vale, com a versão em que foi jogado.
- O ranking por jogo é o melhor `value` visível (não oculto) por usuário, considerando todas as versões do
  jogo, com a versão exibida ao lado. Empates ficam na mesma posição (1, 1, 3); entre eles, quem chegou primeiro
  aparece antes. Mostra as 100 primeiras posições.

### GuestPlay (Play)

Partida de quem não tem conta ou ainda não confirmou o e-mail.

- `GuestPlay`: `id` (ULID), `gameId`, `gameVersionId`, `tokenHash`, `startedAt`, `expiresAt`, `value`, `endedAt`,
  `claimExpiresAt` e `claimedScoreId` (único).
- `start()` só numa versão jogável (I9), sem conta. O token segue as mesmas regras da `GameSession`.
- `recordScore()` aplica as regras de I10 que não dependem de conta: o token confere, ainda sem score (senão 409),
  dentro do prazo e com a duração mínima. O valor fica **pendente**: não cria `Score` nem entra no ranking.
  O prazo para salvar é de 24 h (`play.guest_claim_ttl_seconds`).
- `claim()` exige uma conta com e-mail confirmado (A5), o mesmo token e o prazo de salvar ainda aberto. Cria uma
  `GameSession` já `scored`, com o início e o fim reais da partida, e o `Score` por ela, com a sinalização de anomalia
  de sempre (S2). Salva uma única vez, para uma única conta (`claimed_score_id` único, 409 na segunda tentativa).
- Jogo bloqueado ou versão retirada entre a partida e o salvamento: o score não entra.

### Score: limite técnico

Não há score máximo por jogo. O único teto é o maior inteiro que **todas** as camadas
representam sem perda:

| Camada | Maior inteiro exato |
|---|---|
| PostgreSQL `bigint` | 9.223.372.036.854.775.807 (2⁶³−1) |
| PHP `int` (64 bits) | 9.223.372.036.854.775.807 |
| JavaScript `Number` (jogo, `postMessage`, SPA, `JSON.parse`) | **9.007.199.254.740.991** (2⁵³−1, `Number.MAX_SAFE_INTEGER`) |

O teto é, portanto, `Score::MAX = 9_007_199_254_740_991` (cerca de 9 quatrilhões). Acima
disso, o JavaScript arredonda o valor em silêncio, e o score registrado não seria o
que o jogo calculou. O valor é armazenado em `bigint`, validado como inteiro em
`0..Score::MAX` na SPA (`Number.isSafeInteger`) e na API, e o ranking só usa `MAX()`,
nunca soma, então não há risco de overflow. Valores fora da faixa são **rejeitados**,
e não truncados.

## Política de repositório (MVP)

Ela é aplicada pelo domínio (`RepositoryPolicy`) sobre o manifesto devolvido pela
sandbox. É uma lista de regras independentes (padrão *Specification*), e cada regra
produz zero ou mais violações. Os valores ficam em `config/ingestion.php`.

| Regra (classe) | Código | Tipo |
|---|---|---|
| `index.html`, `style.css` e `game.js` existem na raiz (`RequiredFilesPresent`) | `required_file_missing` | Bloqueante |
| Os arquivos obrigatórios são blobs regulares (modo `100644`): não podem ser symlink (`120000`), executável (`100755`), submodule (`160000`) nem diretório (`RequiredFilesAreRegular`) | `required_file_not_regular` | Bloqueante |
| Nenhum submodule no repositório: sem `.gitmodules` e sem entradas `160000` (`NoSubmodules`) | `submodule` | Bloqueante |
| Nenhum symlink no repositório (`NoSymlinks`) | `symlink` | Bloqueante |
| Cada arquivo obrigatório tem no máximo `max_file_bytes` (padrão 1 MiB). Um blob acima do limite nem é baixado (`RequiredFilesWithinSize`) | `file_too_large` | Bloqueante |
| Os arquivos obrigatórios são texto UTF-8 válido, sem bytes nulos, e o conteúdo foi entregue (`RequiredFilesAreText`) | `not_text`, `content_missing` | Bloqueante |
| Total de entradas na árvore ≤ `max_tree_entries` (padrão 500) (`TreeWithinEntryLimit`) | `too_many_entries` | Bloqueante |
| Os paths não contêm `..`, `.`, `.git`, barra inicial, `\`, caracteres de controle, segmentos vazios nem UTF-8 inválido (`SafePaths`) | `unsafe_path` | Bloqueante |
| Qualquer outro arquivo é listado como **ignorado** (✗) e nunca entra na versão | — | Informativo |

Somente os paths da allowlist são extraídos. Os demais aparecem no relatório apenas
como nome, tamanho e modo (no máximo `max_tree_entries` itens, com o total); o conteúdo
deles não é baixado para a aplicação. Paths vindos do Git são bytes arbitrários: no
relatório, bytes de controle e UTF-8 inválido aparecem escapados como `\xNN`.

### Fetch (`GitSourceFetcher`)

- Antes de qualquer regra, o snapshot confere que cada blob entregue é exatamente o objeto
  Git que a árvore aponta (hash `blob {tamanho}\0{conteúdo}` e tamanho). Uma sandbox
  comprometida não consegue trocar o conteúdo de um arquivo.
- A falha é um código (`FetchFailure`): `ref_not_found`, `repository_unavailable`, `redirected`,
  `timeout`, `too_large`, `too_many_entries`, `invalid_objects`, `sandbox_unavailable`,
  `invalid_response`, `internal`… A versão vai para `fetch_failed` com o código, e o admin vê a
  mensagem correspondente. Nenhum texto do servidor remoto chega à aplicação.
- Com violação bloqueante, a versão termina em `validation_failed`, com o commit e o relatório,
  e nada vai para a quarentena (`CHECK` no banco: esse estado exige relatório `blocked`). Assim
  nenhuma versão bloqueada chega ao Portão 1 (I3) e o jogo não fica travado: o dono corrige o
  repositório e reenvia.

## Invariantes

| ID | Regra |
|---|---|
| I1 | Somente um usuário com a permissão correspondente (role `admin`) executa transições de portão, publicação, retirada e bloqueio. Um admin pode fazer isso inclusive nos jogos de que é dono. |
| I2 | Nenhum acesso de rede ao repositório acontece antes da autorização do Portão 0. |
| I3 | O Portão 1 só pode ser aprovado se o `ValidationReport` não tiver violações bloqueantes. |
| I4 | Arquivos só podem ser avaliados com a versão em `awaiting_file_review`. |
| I5 | Uma aprovação de arquivo vale para o par (arquivo, `sha256`). Hash diferente significa aprovação inexistente. |
| I6 | A publicação exige que todos os arquivos obrigatórios estejam aprovados **e** que o SHA-256 recalculado a partir da quarentena seja igual ao hash aprovado. |
| I7 | O artefato publicado é escrito uma única vez e nunca é sobrescrito. Se o destino já existe, a operação falha. |
| I8 | Um jogo tem no máximo uma versão `published`. Publicar uma nova move a anterior para `superseded` na mesma transação. |
| I9 | Só jogos `published` (não bloqueados), com versão `published`, aparecem na listagem e podem ser executados ou receber sessões. |
| I10 | Uma sessão aceita no máximo um score, deve estar `open`, não expirada, pertencer ao usuário autenticado (com o token dela) e ter durado pelo menos `min_session_seconds`. Testes: `GameSessionTest`, `ScoreConcurrencyTest`. |
| I11 | Toda decisão de portão, publicação, retirada e bloqueio gera um `AuditEntry` na mesma transação, com ator, data, estado analisado (commit/hash) e motivo. O motivo é obrigatório nas rejeições. |
| I12 | Um usuário sem role `admin` (incluindo o dono do jogo) não consegue executar nenhuma ação de I1, qualquer que seja o endpoint ou o caminho usado. |
| I13 | O que é analisado e publicado é exatamente o conteúdo obtido no fetch daquela versão (quarentena), vinculado ao `commitSha` resolvido. O repositório nunca é consultado de novo para a mesma versão. |
