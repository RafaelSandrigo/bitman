# Segurança

O Bitman distribui **conteúdo não confiável**. Este documento lista as ameaças,
como cada uma é mitigada e onde a mitigação é verificada. Uma mitigação sem teste
é considerada **inexistente**.

## Fronteiras de confiança

| Origem do dado | Confiança | Tratamento |
|---|---|---|
| Entrada do usuário (formulários, URL, ref) | Nenhuma | FormRequest + value objects com validação estrita |
| Tudo que vem do Git (URL, refs, commits, nomes, modos, conteúdo) | Nenhuma | Lido apenas como dados. É revalidado no Laravel mesmo depois de passar pela sandbox |
| Resposta da git-sandbox | Nenhuma | A sandbox pode ter sido comprometida pelo repositório; o Laravel aplica a política inteira de novo |
| Arquivos do jogo (HTML/CSS/JS) | Nenhuma | Nunca executados no servidor; no navegador, só em iframe sandbox de outra origem |
| Mensagens `postMessage` vindas do iframe | Nenhuma | Conferência de `event.source` + schema Zod |
| Score enviado | Baixa | Sessão + limites + anti-replay + sinalização. A limitação é aceita e documentada |
| Thumbnail enviada | Nenhuma | Decodificada e re-codificada em WebP, sem metadados |

## Ameaças e mitigações

### Operações Git (git-sandbox)

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| G1 | Command injection via URL/ref | Comandos sempre como **array de argumentos** (`proc_open` sem shell). `RepositoryUrl` e `GitRef` como value objects: a ref casa com `^[A-Za-z0-9._/-]{1,100}$`, não começa com `-` e não contém `..`. A sandbox revalida as mesmas regras; `--branch=<ref>`, `--` antes da URL e `--end-of-options` nos demais comandos. O Laravel revalida URL e ref lidas do banco antes de cada busca | `RepositoryUrlTest`, `GitRefTest`, `SubmitGameTest`, `ImportRepositoryTest` e o harness `tests/sandbox/run.php` (27 requisições hostis) |
| G2 | Protocolos perigosos (`file://`, `ext::`, `ssh`, `git://`) | Somente `https`. `GIT_ALLOW_PROTOCOL=https`, `-c protocol.allow=never -c protocol.https.allow=always`, além da validação da URL | Harness da sandbox: cada protocolo recusado na requisição, e `file://`/`ext::` recusados pelo próprio git mesmo passando direto (`ext::` nunca executa) |
| G3 | SSRF / acesso à rede interna (LAN do servidor, outros projetos) | Allowlist de hosts (`github.com`, `gitlab.com`, `codeberg.org`) no value object **e** na rede: a sandbox fica numa rede `internal: true`, e sua única saída é o `egress-proxy`, que só aceita `CONNECT` para esses hosts na porta 443. URL sem credenciais, sem porta, sem IP literal. `http.followRedirects=false` (redirect vira `fetch_failed`/`redirected`) | `RepositoryUrlTest` e `SubmitGameTest` (VO), `tests/infra/sandbox-egress.sh` (rede: allowlist, LAN, porta, app sem acesso à sandbox), `HttpGitSourceFetcherTest` (código `redirected`). Sem fixture de redirect real |
| G4 | Hooks e configuração maliciosa | Repositório *bare* criado pela sandbox. `GIT_CONFIG_NOSYSTEM=1`, `GIT_CONFIG_GLOBAL=/dev/null`, `core.hooksPath=/dev/null`, `credential.helper=` e ambiente mínimo em cada processo. Hooks nunca são transferidos | Revisão de `docker/git-sandbox/src/GitSandbox.php` (configuração fixa, independente do compose) |
| G5 | Symlinks, submodules e CVEs de checkout | **Não há checkout.** Árvore lida com `git ls-tree` e blobs com `git cat-file`. Sem `--recurse-submodules`. `transfer.fsckObjects=true` recusa árvores forjadas (`..`, `.git`). Modos `120000`/`160000` e `.gitmodules` bloqueiam a versão | Fixtures `symlink`, `symlink-extra`, `submodule`, `executable`, `dotdot-tree`, `dotgit-tree` (`SandboxFixturesTest`) |
| G6 | Path traversal | Os caminhos de armazenamento são montados com **nossos** IDs, nunca com nomes vindos do Git: quarentena em `{versionId}/{sha256}`. Só os paths exatos da allowlist são extraídos. Paths hostis bloqueiam a versão e aparecem escapados (`\xNN`) para o admin | `RepositoryPolicyTest` (`SafePaths`), `ImportRepositoryTest`, fixture `hostile-names`; `ArtifactStore` na Fase 5 |
| G7 | Exaustão de recursos | Clone `--depth=1 --filter=blob:limit=<max>` (blobs grandes ficam no servidor; `GIT_NO_LAZY_FETCH=1` impede que sejam buscados depois), timeout total de 60 s, teto de saída por comando, no máximo 10.000 entradas, `tmpfs` de 256 MB, limites de CPU/memória/pids, teto de 8 MiB na resposta lida pelo Laravel, uma busca por vez | Fixtures `huge`, `too-many`, `way-too-many`; harness (timeout); `HttpGitSourceFetcherTest` (resposta acima do teto) |
| G8 | Comprometimento da própria sandbox | Container mínimo, não-root, rootfs read-only, `cap_drop: ALL`, sem acesso a DB, Redis ou volumes, autenticada por token (`SANDBOX_TOKEN`, fail closed se vazio). Só o worker da fila alcança a sandbox, e ele roda como `www-data`. O Laravel não confia na resposta: cada blob é conferido contra o objeto Git da árvore, e a política inteira é reaplicada. Erros cruzam a fronteira só como códigos. Imagem com `git` atualizado (rebuild periódico) | `tests/infra/sandbox-egress.sh` (token, rede), `HttpGitSourceFetcherTest` (respostas hostis), `RepositorySnapshotTest` (blob trocado) |

### Integridade da publicação

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| P1 | Conteúdo muda entre a análise e a publicação (TOCTOU) | Aprovação vinculada ao SHA-256 (I5): a decisão leva o hash do conteúdo exibido, e hash diferente é recusado. O conteúdo exibido é conferido contra o hash do fetch (quarentena adulterada → 409, nunca mostrada). A publicação recalcula o hash da quarentena (I6), e o desbloqueio, o do archive | `ReviewGatesTest` (hash diferente, quarentena adulterada), `PublicationTest` (quarentena e archive adulterados) |
| P2 | Sobrescrita de versão publicada | Escrita write-once (I7: criação exclusiva, nunca sobrescreve) e índice parcial de versão publicada única (I8) | `ArtifactStoreTest`, `PublicationTest` (archive existente, duas publicadas) |
| P3 | Dono aprova o próprio conteúdo ou escala privilégio | Roles/permissions + Policy dentro da Action (I12). `status` não é *fillable* | Teste de feature: **todo** endpoint administrativo retorna 403 para `developer` e `player` |
| P4 | Adulteração da auditoria | Tabela append-only com trigger no PostgreSQL | Teste de banco |

### Execução dos jogos (navegador)

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| J1 | XSS no hub / roubo de sessão | Jogos servidos em origem separada do hub ([ADR 0004](adr/0004-isolamento-dos-jogos.md)). `iframe sandbox="allow-scripts"` **sem** `allow-same-origin` (origem opaca). O nginx `games` também envia o header `Content-Security-Policy: sandbox allow-scripts`, de modo que o jogo continua isolado mesmo se aberto direto numa aba. Cookies do hub com prefixo `__Host-`, `HttpOnly`, `Secure`, `SameSite=Lax` | `GamePage.test.tsx` (atributos do iframe), `tests/infra/games-origin.sh` (headers); E2E em navegador: [melhorias M11](melhorias.md) |
| J2 | Exfiltração de dados / chamadas externas | CSP na origem de jogos: `default-src 'none'; script-src <games-origin>; style-src <games-origin>; img-src <games-origin> data:; connect-src 'none'; form-action 'none'; base-uri 'none'; frame-ancestors <hub-origin>` | Teste de headers do nginx `games` |
| J3 | Navegação do topo, popups, phishing | Sandbox sem `allow-top-navigation`, `allow-popups`, `allow-forms` e `allow-modals`. `Permissions-Policy` negando câmera, microfone, geolocalização etc. A UI do hub delimita visualmente o conteúdo de terceiros | E2E |
| J4 | Mensagens forjadas para o hub | A SPA aceita apenas mensagens com `event.source === iframe.contentWindow` e `event.origin === 'null'` (origem opaca) que passem no schema Zod. Apenas um `game-over` por sessão. O hub nunca envia nada ao jogo: token e dados do jogador ficam na SPA | `bridge.test.ts`, `GamePlayer.test.tsx` |
| J5 | Admin "executa" ou é enganado pelo código ao revisar | O conteúdo chega como string JSON (`Cache-Control: no-store`) e o visualizador o mostra como **texto escapado** pelo React, linha a linha, nunca em `innerHTML` ou iframe. Caracteres bidirecionais e invisíveis ("Trojan Source", CVE-2021-42574) aparecem marcados como `⟦U+202E⟧`, com aviso. Sem realce de sintaxe por enquanto ([melhorias](melhorias.md)) | `VersionPage.test.tsx` (conteúdo hostil e Trojan Source), `ReviewGatesTest` |
| J6 | Versão não aprovada sendo servida | Só `artifacts/served/{publicId}` é exposto, e só a publicação o cria (com o `rename` atômico de um staging que o nginx não serve). Bloqueio, retirada e substituição removem o diretório dentro da transação. A SPA só põe no iframe uma URL `{origem de jogos}/{UUID}/index.html` | `PublicationTest`, `EndToEndPublicationTest`, `config.test.ts`, `GamePage.test.tsx`, `tests/infra/games-origin.sh` |
| J7 | Mineração / consumo de CPU no cliente | **Risco residual aceito.** Mitigado pela revisão humana e pela possibilidade de bloqueio imediato | — |

### Borda (Cloudflare + Traefik)

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| C1 | A Cloudflare altera o conteúdo servido (Rocket Loader, Email Obfuscation, beacon do Web Analytics etc.), de modo que o navegador executa algo diferente do que o admin aprovou | Três camadas, sem configuração no painel da Cloudflare: **(1)** `bitman-games` envia `Cache-Control: no-store, no-transform`, que pede à borda para não reescrever a resposta (comportamento documentado pela Cloudflare para Email Obfuscation e relatado para as demais injeções); **(2)** a CSP do jogo (`script-src` só da origem de jogos, sem `'unsafe-inline'`) bloqueia qualquer script injetado: no pior caso o jogo quebra, mas não executa código de terceiros; **(3)** o teste de fumaça pós-deploy baixa os arquivos pela URL pública e compara o SHA-256 com o hash aprovado. Se ele falhar, aí sim se cria uma *Configuration Rule* para o host | Teste de headers do nginx + teste de fumaça pós-deploy |
| C2 | Cache da Cloudflare continua servindo um jogo bloqueado ou retirado (`.js` e `.css` entram no cache da Cloudflare por padrão) | `bitman-games` responde com `Cache-Control: no-store` (a Cloudflare não armazena respostas `no-store`), então cada acesso chega à origem. O bloqueio remove o diretório `served`, e a origem passa a responder 404 na hora. Versões reprovadas **nunca** chegam ao `served`. Uma Cache Rule com Edge TTL que ignore a origem anularia isso, por isso nenhuma deve ser criada para `*.sandrigo.com.br` | Teste de headers + teste de fumaça (bloquear → 404 pela URL pública) |
| C3 | Rate limit inútil porque todo tráfego chega com o IP do proxy | `real_ip_header CF-Connecting-IP` aceito só a partir da rede do Traefik + `TrustProxies` restrito | Teste de feature com headers forjados vindos de fora da rede confiável |
| C4 | Cookie de sessão sem `Secure`, porque a origem fala HTTP com o Traefik | `X-Forwarded-Proto` confiável + `SESSION_SECURE_COOKIE=true` + prefixo `__Host-` | Teste de configuração |

### Operação no servidor de produção

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| O1 | Testes apagam ou poluem os dados reais (o projeto é desenvolvido no servidor de produção) | `phpunit.xml` força `DB_DATABASE=bitman_test` em `<env>` **e** `<server>` (o Laravel lê `$_SERVER` primeiro: só `<env>` não bastava, e o teste-trava pegou isso) | `tests/Feature/TestEnvironmentTest.php` |
| O2 | Exposição pública antes do lançamento | `X-Robots-Tag: noindex` no hub e no host de jogos (a autenticação básica do hub foi retirada em 13/10/2026; o acesso é controlado pelo login da aplicação) | `tests/infra/games-origin.sh`; verificação manual com `curl -I` |
| O3 | Um comando do projeto derruba os vizinhos | Regras de [convencoes.md](convencoes.md#operação-segura-no-servidor-de-produção), `Makefile` restrito ao projeto e limites de CPU/memória em todo container | Revisão de PR |

### Contas, API e scores

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| A1 | CSRF | Sanctum SPA (cookie) + token CSRF + proteção de origem do Laravel 13. Cookies host-only (`bitman.sandrigo.com.br`), `Secure`; sessão `HttpOnly` | Teste do cliente HTTP (cookie e repetição no 419) + verificação em produção (POST sem token → 419) |
| A2 | Força bruta em login | Rate limit de 5/min por e-mail + IP e 20/min por IP; 30/min por IP em todas as rotas de autenticação. A mesma mensagem para e-mail inexistente e senha errada | `AuthenticationTest` |
| A5 | Contas descartáveis inflando o ranking | Verificação de e-mail obrigatória antes de criar sessões de jogo, para salvar o score de uma partida de visitante e para receber roles acima de `player`. Reenvio de verificação com rate limit (6/min) | `EmailVerificationTest`, `DeveloperRoleTest`, `GameSessionTest`, `GuestPlayTest` |
| A10 | Score de visitante salvo por outra pessoa ou várias vezes | O token da partida só existe no navegador de quem jogou (resposta `no-store`, `localStorage` do hub, nunca no jogo: J1). Salvar exige o token, uma conta confirmada e o prazo de 24 h, e acontece uma única vez (`FOR UPDATE` + `unique(claimed_score_id)`). Partidas de visitante têm limite por IP (30/h e 20 envios/min). Um XSS no hub exporia o token, o mesmo risco do token da sessão | `GuestPlayTest`, `GuestPlay.test.tsx` |
| A6 | Enumeração de contas pelo "esqueci a senha" | Resposta idêntica para e-mail cadastrado, desconhecido ou pedido repetido | `PasswordResetTest` |
| A7 | Redirecionamento aberto após o login (`?next=`) e chamada arbitrária à API pelo link de verificação | `?next=` só aceita caminhos internos (`/…`, nunca `//…`). A SPA só chama links no formato exato da URL assinada de verificação | Testes do `LoginPage` e do `VerifyEmailPage` |
| A8 | Verificar o e-mail de outra conta | O link é assinado, expira em 60 min e só vale para o usuário logado a que pertence | `EmailVerificationTest` |
| A3 | Mass assignment | FormRequest → DTO explícito, sem `$request->all()` | Revisão + Larastan |
| A4 | Thumbnail maliciosa (polyglot, SVG, bomba de descompressão, metadados) | Triagem de tipo no FormRequest; depois o `ThumbnailProcessor` confere o conteúdo: só PNG/JPEG/WebP, até 2 MB, dimensões de 64 a 4096 px lidas do cabeçalho **antes** de decodificar, re-codificação em WebP (sem metadados nem conteúdo anexado) e nome aleatório. O hub serve só `/media/thumbnails/{32 hex}.webp`, com `Content-Type` fixo, `nosniff` e `CSP: default-src 'none'` | `ThumbnailProcessorTest`, `SubmitGameTest`, `tests/infra/hub-media.sh` |
| A9 | Abuso da submissão (CPU no processamento da imagem, inundação da fila de revisão) | 10 submissões por hora por usuário; só `developer` com e-mail confirmado submete | `SubmitGameTest` |
| S1 | Replay de score | Sessão de uso único (I10), travada (`FOR UPDATE`) na transação do envio, e `unique(game_session_id)` como última barreira. Replay responde 409 | `GameSessionTest`; `ScoreConcurrencyTest` (6 processos com conexões próprias enviando no mesmo instante → exatamente um registro) |
| S2 | Score forjado | Limites (inteiro JSON em `0..ScoreValue::MAX`, sem truncar; string, fração e `1e3` rejeitados), duração mínima, prazo da sessão, 120 partidas/h por jogador e jogo e 20 envios/min por jogador, `flagged` acima do p99 × 3 (com 20+ scores), fora do ranking até a revisão do admin (manter ou ocultar, auditados), com aviso no painel e por e-mail (um por jogo por hora). A sinalização não é exposta ao jogador: o score só não aparece no ranking | `GameSessionTest`, `ScoreRulesTest`, `ScoreModerationTest`, `FlaggedScoreAlertTest` |

## Limitação explícita: scores

O jogo roda no navegador do jogador, então **qualquer score pode ser forjado** por
quem modificar o JavaScript ou chamar a API diretamente. O sistema dificulta,
limita e sinaliza fraudes, mas não as impede. Scores sinalizados continuam
registrados e ficam fora do ranking até o admin mantê-los ou ocultá-los. Validação autoritativa
(replay de inputs no servidor) está fora do escopo do MVP.

## Checklist de PR com impacto em segurança

- [ ] A nova entrada externa passa por um value object ou FormRequest com validação estrita?
- [ ] A ação administrativa autoriza pelo Gate dentro da Action, gera `AuditEntry` e tem a rota no grupo `/api/v1/admin`?
- [ ] Algum dado vindo do Git chega a um comando, caminho de arquivo ou HTML sem tratamento?
- [ ] Os headers e atributos de isolamento dos jogos continuam cobertos por testes?
