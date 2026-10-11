# 0009 — Laravel Boost com restrições

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
O Laravel Boost (v2.10) injeta diretrizes e skills no contexto dos agentes de IA e expõe ferramentas MCP.
A análise do pacote instalado mostrou:

1. A diretriz `deployments` diz que o Laravel Cloud é "the fastest way to deploy". Ela é sempre incluída e já foi alvo de críticas públicas como publicidade dentro do agente.
2. A integração `cloud` vem **ligada por padrão** (`boost.json` → `cloud: true`) e instala o skill `deploying-to-cloud`, cujo gatilho é amplo ("deployment, shipping, ... billing").
3. Com `laravel/nightwatch` instalado, o install registra o MCP remoto `nightwatch.laravel.com` (padrão `true`).
4. A ferramenta MCP **Tinker** (execução de PHP arbitrário) é registrada, embora não apareça na tabela da documentação oficial.
5. `search-docs` envia as consultas para `boost.laravel.com`, e o auditor de skills consulta `skills.laravel.cloud`.
6. As diretrizes genéricas conflitam com o projeto: "só crie documentação se pedido", "não crie pastas-base" e o incentivo ao uso de tinker.
7. *Project rules* e `infer-conventions` criariam uma segunda fonte de convenções (`.ai/rules`), paralela a `docs/`.
8. O browser logs watcher injeta script nas respostas `web` e cria uma rota sem CSRF. O Boost só fica ativo com `APP_ENV=local` **ou** `APP_DEBUG=true`.

## Decisão
- `boost.json` com `cloud: false`, `nightwatch: false` e `sail: false`, sem depender dos padrões do pacote.
- `config/boost.php`:
  - exclui as diretrizes `deployments`, `herd` e `sail` e os skills `deploying-to-cloud` e `infer-conventions`;
  - remove as ferramentas MCP `Tinker`, `RecordRule` e `BrowserLogs`;
  - desliga *project rules* e o browser logs watcher.
- `.ai/guidelines/bitman.md` define a precedência de `docs/`, a neutralidade de fornecedor e os ajustes sobre as diretrizes genéricas. `.ai/guidelines/deployments/core.md` substitui a diretriz de implantação.
- Teste `tests/Unit/BoostGuidelinesTest.php` falha se a recomendação comercial ou as ferramentas removidas voltarem após um `boost:install` ou `boost:update`.
- Sem `boost:update` automático no `post-update-cmd` do Composer: a atualização é manual e o diff é revisado no PR.
- Em produção: `composer install --no-dev` e `APP_DEBUG=false`, para que o Boost nunca carregue.

## Consequências
- O agente ainda recebe as boas práticas úteis (PHP, Laravel, Pint, testes) sem viés comercial.
- Cada atualização do Boost exige rodar os testes e revisar o diff de `CLAUDE.md`.
