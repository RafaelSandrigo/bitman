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
| G1 | Command injection via URL/ref | Comandos sempre como **array de argumentos** (sem shell). `RepositoryUrl` e `GitRef` como value objects: a ref casa com `^[A-Za-z0-9._/-]{1,100}$`, não começa com `-` e não contém `..`. Uso de `--end-of-options` | Testes unitários dos VOs com payloads maliciosos |
| G2 | Protocolos perigosos (`file://`, `ext::`, `ssh`, `git://`) | Somente `https`. `GIT_ALLOW_PROTOCOL=https`, `-c protocol.allow=never -c protocol.https.allow=always` | Teste da sandbox com cada protocolo |
| G3 | SSRF / acesso à rede interna (LAN do servidor, outros projetos) | Allowlist de hosts (`github.com`, `gitlab.com`, `codeberg.org`) no value object **e** na rede: a sandbox fica numa rede `internal: true`, e sua única saída é o `bitman-egress-proxy`, que só aceita `CONNECT` para esses hosts na porta 443. URL sem credenciais, sem porta, sem IP literal. `http.followRedirects=false` | Testes do VO, teste de integração com redirect e teste de que a sandbox não alcança `bitman-postgres` nem um IP da LAN |
| G4 | Hooks e configuração maliciosa | Repositório *bare* criado pela sandbox. `GIT_CONFIG_NOSYSTEM=1`, `GIT_CONFIG_GLOBAL=/dev/null`, `core.hooksPath=/dev/null`. Hooks nunca são transferidos | Teste de integração |
| G5 | Symlinks, submodules e CVEs de checkout | **Não há checkout.** Árvore lida com `git ls-tree` e blobs com `git cat-file`. Sem `--recurse-submodules`. Modos `120000`/`160000` bloqueiam a versão | Repositórios-fixture maliciosos |
| G6 | Path traversal | Os caminhos de armazenamento são montados com **nossos** IDs, nunca com nomes vindos do Git. Só os paths exatos da allowlist são extraídos | Testes da `RepositoryPolicy` e do `ArtifactStore` |
| G7 | Exaustão de recursos | `fetch --depth=1 --filter=blob:limit=<max>`, timeout total, `tmpfs` com tamanho fixo, limites de CPU/memória/pids no container, teto no tamanho da resposta, uma busca por vez por worker | Teste com repositório-fixture grande |
| G8 | Comprometimento da própria sandbox | Container mínimo, não-root, rootfs read-only, `cap_drop: ALL`, sem acesso a DB, Redis ou volumes e autenticada por token. Imagem com `git` atualizado (rebuild periódico) | Revisão do compose + CI de imagem |

### Integridade da publicação

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| P1 | Conteúdo muda entre a análise e a publicação (TOCTOU) | Aprovação vinculada ao SHA-256 (I5). A publicação recalcula o hash da quarentena (I6) | Teste: adulterar o blob na quarentena faz a publicação falhar |
| P2 | Sobrescrita de versão publicada | Escrita write-once (I7) e índice parcial de versão publicada única (I8) | Testes de domínio e de banco |
| P3 | Dono aprova o próprio conteúdo ou escala privilégio | Roles/permissions + Policy dentro da Action (I12). `status` não é *fillable* | Teste de feature: **todo** endpoint administrativo retorna 403 para `developer` e `player` |
| P4 | Adulteração da auditoria | Tabela append-only com trigger no PostgreSQL | Teste de banco |

### Execução dos jogos (navegador)

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| J1 | XSS no hub / roubo de sessão | Jogos servidos em origem separada do hub ([ADR 0004](adr/0004-isolamento-dos-jogos.md)). `iframe sandbox="allow-scripts"` **sem** `allow-same-origin` (origem opaca). O nginx `games` também envia o header `Content-Security-Policy: sandbox allow-scripts`, de modo que o jogo continua isolado mesmo se aberto direto numa aba. Cookies do hub com prefixo `__Host-`, `HttpOnly`, `Secure`, `SameSite=Lax` | Teste E2E dos atributos e dos headers |
| J2 | Exfiltração de dados / chamadas externas | CSP na origem de jogos: `default-src 'none'; script-src <games-origin>; style-src <games-origin>; img-src <games-origin> data:; connect-src 'none'; form-action 'none'; base-uri 'none'; frame-ancestors <hub-origin>` | Teste de headers do nginx `games` |
| J3 | Navegação do topo, popups, phishing | Sandbox sem `allow-top-navigation`, `allow-popups`, `allow-forms` e `allow-modals`. `Permissions-Policy` negando câmera, microfone, geolocalização etc. A UI do hub delimita visualmente o conteúdo de terceiros | E2E |
| J4 | Mensagens forjadas para o hub | A SPA aceita apenas mensagens com `event.source === iframe.contentWindow` que passem no schema Zod. Apenas um `game-over` por sessão | Testes unitários da bridge |
| J5 | Admin "executa" código malicioso ao revisar | O visualizador mostra o arquivo como **texto escapado** (realce de sintaxe), nunca em `innerHTML` ou iframe | Teste de componente |
| J6 | Versão não aprovada sendo servida | Só `artefatos/served/{publicId}` é exposto. Bloqueio e retirada removem o diretório | Teste de feature |
| J7 | Mineração / consumo de CPU no cliente | **Risco residual aceito.** Mitigado pela revisão humana e pela possibilidade de bloqueio imediato | — |

### Borda (Cloudflare + Traefik)

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| C1 | A Cloudflare altera o conteúdo servido (Rocket Loader, Email Obfuscation, beacon do Web Analytics etc.), de modo que o navegador executa algo diferente do que o admin aprovou | Três camadas, sem configuração no painel da Cloudflare: **(1)** `bitman-games` envia `Cache-Control: no-store, no-transform`, que pede à borda para não reescrever a resposta (comportamento documentado pela Cloudflare para Email Obfuscation e relatado para as demais injeções); **(2)** a CSP do jogo (`script-src` só da origem de jogos, sem `'unsafe-inline'`) bloqueia qualquer script injetado: no pior caso o jogo quebra, mas não executa código de terceiros; **(3)** o teste de fumaça pós-deploy baixa os arquivos pela URL pública e compara o SHA-256 com o hash aprovado. Se ele falhar, aí sim se cria uma *Configuration Rule* para o host | Teste de headers do nginx + teste de fumaça pós-deploy |
| C2 | Cache da Cloudflare continua servindo um jogo bloqueado ou retirado (`.js` e `.css` entram no cache da Cloudflare por padrão) | `bitman-games` responde com `Cache-Control: no-store` (a Cloudflare não armazena respostas `no-store`), então cada acesso chega à origem. O bloqueio remove o diretório `served`, e a origem passa a responder 404 na hora. Versões reprovadas **nunca** chegam ao `served`. Uma Cache Rule com Edge TTL que ignore a origem anularia isso, por isso nenhuma deve ser criada para `*.sandrigo.com.br` | Teste de headers + teste de fumaça (bloquear → 404 pela URL pública) |
| C3 | Rate limit inútil porque todo tráfego chega com o IP do proxy | `real_ip_header CF-Connecting-IP` aceito só a partir da rede do Traefik + `TrustProxies` restrito | Teste de feature com headers forjados vindos de fora da rede confiável |
| C4 | Cookie de sessão sem `Secure`, porque a origem fala HTTP com o Traefik | `X-Forwarded-Proto` confiável + `SESSION_SECURE_COOKIE=true` + prefixo `__Host-` | Teste de configuração |

### Contas, API e scores

| # | Ameaça | Mitigação | Verificação |
|---|---|---|---|
| A1 | CSRF | Sanctum SPA (cookie) + token CSRF + proteção de origem do Laravel 13 | Teste de feature |
| A2 | Força bruta em login | Rate limit por IP + e-mail | Teste de feature |
| A5 | Contas descartáveis inflando o ranking | Verificação de e-mail obrigatória antes de criar sessões de jogo. Reenvio de verificação com rate limit | Teste de feature |
| A3 | Mass assignment | FormRequest → DTO explícito, sem `$request->all()` | Revisão + Larastan |
| A4 | Thumbnail maliciosa (polyglot, SVG, bomba de descompressão) | Apenas PNG/JPEG/WebP verificados pelo conteúdo, limites de bytes e dimensões, re-codificação em WebP e nome aleatório | Testes com arquivos-fixture |
| S1 | Replay de score | Sessão de uso único (I10) e `unique(game_session_id)` | Teste de concorrência |
| S2 | Score forjado | Limites (inteiro em `0..Score::MAX`, sem truncar), duração mínima, rate limit por usuário e jogo, `flagged` para valores fora do padrão (ex.: acima do p99 × fator) | Testes de feature |

## Limitação explícita: scores

O jogo roda no navegador do jogador, então **qualquer score pode ser forjado** por
quem modificar o JavaScript ou chamar a API diretamente. O sistema dificulta,
limita e sinaliza fraudes, mas não as impede. Scores sinalizados continuam
registrados e podem ser ocultados do ranking pelo admin. Validação autoritativa
(replay de inputs no servidor) está fora do escopo do MVP.

## Checklist de PR com impacto em segurança

- [ ] A nova entrada externa passa por um value object ou FormRequest com validação estrita?
- [ ] A ação administrativa passa pela Policy dentro da Action e gera `AuditEntry`?
- [ ] Algum dado vindo do Git chega a um comando, caminho de arquivo ou HTML sem tratamento?
- [ ] Os headers e atributos de isolamento dos jogos continuam cobertos por testes?
