# Convenções de desenvolvimento

## Operação segura no servidor de produção

O repositório fica em `/mnt/Dados/prog/prod/`, no **mesmo servidor** dos outros projetos em produção
(garagem-saas, Carphook, portifolio, CPO, sandboxn8n, deploy-hub) e do Traefik compartilhado.
Qualquer comando roda em produção. Regras, para pessoas e agentes de IA:

1. **Nada global.** Não instale nem atualize nada no sistema: sem `apt`/`pacman`, `composer global`,
   `npm -g`, `pip install` fora de venv, nem mudança de versão do PHP ou do Node do host. Dependências
   são do projeto (`vendor/`, `node_modules/`) ou ficam dentro das imagens Docker do projeto.
2. **Docker só no escopo do projeto.** Use sempre `docker compose` neste diretório (`name: bitman`) ou os
   alvos do `Makefile`. **Proibido:** `docker system|image|volume|network|builder prune`, `docker rm`/`stop`/`restart`/`kill`
   em containers sem prefixo `bitman-`, criar/remover/alterar a rede `prod-network`, mexer no Traefik e
   `docker compose down -v` sem confirmação explícita (apaga os dados do projeto).
3. **Nada fora da pasta do projeto.** Não edite arquivos de outros projetos nem do sistema (`/etc`, systemd,
   crontab, firewall). Ler outros projetos como referência é permitido. A exceção é `deploy-hub/scripts/deploy-bitman.sh`
   e o registro do hook, feitos uma vez e aprovados pelo Rafael.
4. **Sem portas no host.** Nenhum serviço usa `ports:`; o acesso é pelo Traefik ou por `docker compose exec`.
   Use os alvos do `Makefile` (`make help`), que já trabalham só no projeto `bitman`.
5. **Recursos limitados.** Todo container tem limites de CPU e memória para não degradar os vizinhos.
   Builds e testes pesados não rodam em paralelo com deploys.
6. **Na dúvida, pergunte antes.** Qualquer ação que possa afetar outro projeto, o Docker do host ou o
   servidor exige confirmação explícita.

## Idioma

- **Código** (classes, métodos, variáveis, tabelas, rotas): inglês, seguindo a
  [linguagem ubíqua](dominio.md#linguagem-ubíqua).
- **Documentação, mensagens de commit e textos de interface**: português (pt-BR).

## Princípios

| Princípio | Como aplicamos |
|---|---|
| **TDD** | Red → Green → Refactor. O teste vem antes do código de produção, principalmente em `Domain` e `Application` |
| **DDD** | Linguagem ubíqua, agregados com métodos de intenção, value objects, eventos de domínio e contextos com fronteiras claras ([dominio.md](dominio.md)) |
| **SOLID** | Uma Action por caso de uso (S). Regras de repositório extensíveis por nova classe (O). Portas pequenas e específicas (I). O domínio depende de interfaces, não de adaptadores (D) |
| **KISS / YAGNI** | Sem abstração "para o futuro". Uma porta só existe se há I/O externo ou necessidade real de teste |
| **Fail fast** | Value objects inválidos lançam exceção na construção. Um objeto inválido não existe |
| **Imutabilidade** | Value objects e DTOs são `readonly`. Registros de auditoria e decisões são append-only |

## Testes

| Nível | Ferramenta | O que cobre |
|---|---|---|
| Unitário | Pest | Value objects, transições de estado, regras de política e cálculo de ranking. Sem banco e sem framework, quando possível |
| Feature | Pest + PostgreSQL | Endpoints HTTP, autorização (403 por role), transações, eventos e auditoria |
| Arquitetura | Pest `arch()` | Regras de dependência entre camadas e módulos, sem `dd`/`dump`, `strict_types` |
| Integração | Pest + container | git-sandbox contra repositórios-fixture; headers do nginx `games` |
| Frontend | Vitest + Testing Library + MSW | Componentes, hooks, schemas Zod e bridge `postMessage` |
| E2E | Playwright | Poucos fluxos críticos: submeter → aprovar → publicar → jogar → score no ranking |

- Cada invariante (`I1`…`I13`) e cada mitigação de [seguranca.md](seguranca.md) tem
  um teste que cita o ID no nome ou na descrição.
- Para portas, use **fakes** em memória (`FakeGitSourceFetcher`) em vez de mocks
  frágeis. Use mocks só para verificar interações que importam.
- Não há meta de cobertura global. `Domain` e `Application` devem ficar perto de 100%,
  e o teste de mutação (`pest --mutate`) roda nos módulos `Versioning` e `Ingestion`.

## Qualidade automatizada

| Backend | Frontend |
|---|---|
| Pint (PSR-12 + preset Laravel) | oxlint |
| Larastan nível máximo viável (começando em 8) | `tsc --noEmit` com `strict` |
| Pest (unit, feature, arch) | Vitest |

Tudo isso roda no CI e localmente via `make check`. **PR com CI vermelho não é mesclado.**

## Código

- `declare(strict_types=1);` em todo arquivo PHP.
- Controllers só fazem: FormRequest → DTO → Action → Resource. Sem regra de negócio.
- Exceções de domínio têm nome do negócio (`CommitHasBlockingViolations`) e são
  mapeadas para HTTP em um único lugar.
- Sem `$request->all()`, sem `Model::unguard()`, sem `DB::raw` com entrada externa.
- No frontend, todo dado que chega de fora (API, `postMessage`) passa por um schema
  Zod antes de ser usado. Sem `any`.

## Git

- Trunk-based: branches curtas a partir de `main` (`feat/…`, `fix/…`, `docs/…`, `chore/…`).
- [Conventional Commits](https://www.conventionalcommits.org/pt-br/) em português:
  `feat(versioning): aprovação de arquivo vinculada ao hash`.
- Commits pequenos e coesos. O teste e a implementação podem ir juntos.

## Definição de pronto (DoD)

Uma entrega está pronta quando:

1. Os critérios de aceite da fase ([plano.md](plano.md)) são atendidos.
2. Os testes foram escritos antes ou junto e o CI está verde.
3. As regras de autorização e auditoria aplicáveis estão cobertas por teste.
4. A documentação afetada foi atualizada no mesmo PR, e há um ADR se houve decisão difícil de reverter.
5. Não há TODO sem issue associada.
6. Toda melhoria percebida e deixada para depois está registrada em [melhorias.md](melhorias.md).
