# 0010 — Infraestrutura no servidor compartilhado (Cloudflare + Traefik + deploy-hub)

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
O projeto roda no mesmo servidor que os demais projetos de `/mnt/Dados/prog/prod`, com borda,
roteamento e deploy já padronizados (referências: `deploy-hub/README.md`,
`garagem-saas/docker-compose.prod.yml` e `portifolio/docker-compose.yml`).

## Decisão
- Borda: Cloudflare (TLS) → túnel → Traefik v3 compartilhado (entrypoint `web`). Não há portas publicadas,
  certificados nem proxy de borda próprio.
- Prefixo `bitman` em containers, routers, services e no `name:` do compose. Só `bitman-web` e
  `bitman-games` entram na rede externa `prod-network`. Todo o resto fica em redes internas do projeto.
- Hosts: `bitman.sandrigo.com.br` (hub) e `bitman-games.sandrigo.com.br` (jogos), ambos de primeiro nível,
  para serem cobertos pelo certificado universal da Cloudflare.
- **Um único ambiente** (decisão do Rafael, 2026-10-11): sem ambiente de dev separado. O projeto é desenvolvido
  direto no servidor com um único `docker-compose.yml`, em **modo pré-lançamento** (autenticação básica no Traefik,
  `noindex`, código por bind mount). Os testes rodam nos containers, no banco `bitman_test`.
- Dados em volumes Docker nomeados. Segredos no `.env` da raiz do projeto, ignorado pelo git e injetado via `env_file`, como no Carphook
  (decisão do Rafael). Só o `.env.example` é versionado.
- **Este diretório é produção.** Toda execução é restrita ao projeto ([convencoes.md](../convencoes.md#operação-segura-no-servidor-de-produção)).
- CI no GitHub Actions. O deploy segue o fluxo do deploy-hub (`deploy-bitman.sh` + hook + hash aprovado pelo Rafael).
- E-mail: Resend via SMTP, como no CPO. O Bitman usa API key própria, com permissão só de envio, e remetente
  `nao-responda@bitman.sandrigo.com.br`. Sem a chave, `MAIL_MAILER=log`.

## Atualização (2026-10-22): produção por imagens do commit
O "um único ambiente" continua, mas a produção deixou de montar o código: `app`, `queue`, `scheduler`, `bitman-web`,
`bitman-games`, `git-sandbox` e `egress-proxy` rodam imagens geradas de um `git archive` do commit (`make release`), e
o desenvolvimento usa os serviços `test` e `frontend` do perfil `dev`, com bind mount. O deploy-hub só faz `git fetch`
e `make release REF=origin/main`, sem tocar na árvore de trabalho. Mudanças sem commit nunca vão para produção
(decisão do Rafael; detalhes em [implantacao.md](../implantacao.md)).

## Consequências
- O projeto convive com os demais sem colisão de nomes ou portas, e sem expor nada além dos dois hosts.
- Os testes não podem depender da Cloudflare. Os comportamentos de borda (IP real, headers e cache) são
  verificados por testes de configuração e por um teste de fumaça pós-deploy.
- Nada é configurado na Cloudflare: o túnel encaminha `*.sandrigo.com.br` ao Traefik, e o roteamento vem das labels.
  As garantias de borda (sem cache, sem reescrita) vêm dos headers da origem e são verificadas pelo teste de fumaça.
