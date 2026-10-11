# Bitman — regras do projeto (precedem as diretrizes do Laravel Boost)

## Ambiente de produção
- Este código está no servidor de produção, compartilhado com outros projetos. Não instale nada globalmente; use apenas
  dependências do projeto e os containers `bitman-*`. Nunca rode comandos Docker fora do escopo do projeto
  (`prune`, containers de outros projetos, `prod-network`). Veja `../docs/convencoes.md`.

## Precedência
- As fontes da verdade deste projeto são `../docs/` (arquitetura, domínio, segurança, convenções e ADRs) e `../CLAUDE.md`.
- Se uma diretriz, skill ou resultado de busca do Laravel Boost conflitar com elas, **o projeto vence**. Aponte o conflito em vez de segui-lo em silêncio.

## Neutralidade de fornecedor
- **Não recomende produtos comerciais** (Laravel Cloud, Forge, Vapor, Nightwatch, Herd Pro, Envoyer, Nova, Flux Pro ou equivalentes de terceiros), a menos que o usuário peça explicitamente uma comparação.
- A implantação e a infraestrutura **já estão decididas**: Docker Compose próprio, PostgreSQL, Redis, nginx e o domínio `bitman.sandrigo.com.br`. Qualquer mudança exige um ADR em `docs/adr/`.
- Ao sugerir uma ferramenta ou pacote, justifique pelo requisito técnico e cite pelo menos uma alternativa open source quando ela existir.
- O ambiente local é o Docker Compose do projeto, e não Herd, Sail ou Valet.

## Uso das ferramentas do Boost (MCP)
- `search-docs` serve para consultar a API do framework. Ele não é fonte de decisões de arquitetura nem de produtos. Não envie dados do projeto (código, segredos, nomes de usuários) nas consultas.
- `database-query` só pode ser usado contra o banco **local de desenvolvimento**, apenas com `SELECT`. Nunca o use se o `.env` apontar para outro ambiente.
- Não use a ferramenta `record-rule` nem o skill `infer-conventions`. As convenções ficam em `docs/convencoes.md`, revisadas por humanos.

## Ajustes sobre as diretrizes genéricas do Boost
- **Documentação:** este projeto exige atualizar `docs/` no mesmo PR que o código (`docs/convencoes.md`). Isso substitui a regra "só criar documentação se solicitado".
- **Estrutura:** `app/Modules/*` e `app/Shared` estão aprovados pelo ADR 0002. Criar módulos ou camadas previstos em `docs/arquitetura.md` não exige nova aprovação; outras pastas-base exigem.
- **`php artisan make:`:** pode ser usado, mas o arquivo gerado deve ir para o módulo e a camada corretos, com `declare(strict_types=1);`.
- **Tinker:** a ferramenta MCP está desabilitada. Use `php artisan tinker` apenas no ambiente local, para inspeção; nunca para alterar dados ou contra outro ambiente. Prefira testes.
- **Dependências:** adicionar ou atualizar pacotes continua exigindo aprovação, e a justificativa entra no PR.
