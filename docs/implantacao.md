# Implantação

Como o Bitman chega à produção no servidor compartilhado ([ADR 0010](adr/0010-infraestrutura-homelab.md)).

## Modelo: um diretório, imagens do commit

O diretório `/mnt/Dados/prog/prod/bitman` é ao mesmo tempo onde se desenvolve e de onde a produção é operada.
O que separa os dois é **o que roda em cada container**:

| Serviços | Imagem | Código |
|---|---|---|
| `app`, `queue`, `scheduler` | `bitman-php:release` (target `prod`) | Embutido, de um commit. `composer --no-dev`, sem composer, código de dono root, `opcache.validate_timestamps=0` |
| `bitman-web` | `bitman-web:release` | SPA compilada na imagem (sem source maps) e configuração do nginx |
| `bitman-games` | `bitman-games:release` | Configuração do nginx (CSP sandbox, J1–J3). Os jogos vêm do volume `artifacts` |
| `git-sandbox`, `egress-proxy` | `:release` | Do mesmo commit |
| `test`, `frontend` (perfil `dev`) | `bitman-php:dev` e `node:22-alpine` | Bind mount de `./backend` e `./frontend`. Só rodam sob demanda (`make test`, `make composer`, `make npm`) |

Consequência: **editar um arquivo aqui não muda a produção.** Uma mudança só vai ao ar com um release, e só se
estiver commitada. Para ver uma mudança no app de verdade: commit → push → CI → deploy (ou `make release` à mão).

## `make release`

```bash
make release              # commit atual (HEAD)
make release REF=origin/main
```

1. Confere que `REF` é um commit e que `docker-compose.yml` e `Makefile` da árvore de trabalho são iguais aos do
   commit (a orquestração vem daqui, então precisa ser a mesma). Se não forem, para com uma mensagem.
2. `git archive REF` em `./.release` (ignorado pelo git): só o que está versionado, nada de `.env`, `vendor` ou
   mudanças locais.
3. Constrói as imagens de produção a partir de `./.release`.
4. Roda as migrations e o seeder de roles **com a imagem nova, antes da troca**. Se falharem, os containers atuais
   continuam no ar.
5. `docker compose up -d --wait --remove-orphans`: troca os containers e espera os healthchecks.

O commit do release (`BITMAN_BUILD`) entra no bundle e no `/version.json` da SPA. Uma aba aberta antes do
deploy compara os dois ao ganhar foco e a cada 5 minutos, e mostra **Há uma nova versão do Bitman — Recarregar**
(M10). Ela nunca recarrega sozinha.

Os caches do Laravel (`config`, `route` e `event`) são gerados ao subir cada container
(`docker/php/entrypoint.prod.sh`), porque dependem do `.env`, que só existe em tempo de execução.

O teste `tests/infra/release-images.sh` (no `make test-infra` e no CI) constrói as imagens com os mesmos
Dockerfiles e confere essas propriedades.

## Deploy automático (deploy-hub)

```
push em main → CI verde → workflow "deploy" → POST https://deploy.sandrigo.com.br/hooks/deploy-bitman
  (CF-Access-Client-Id/Secret + X-Deploy-Token)
  → deploy-hub/scripts/deploy-bitman.sh (só roda com o SHA-256 aprovado)
  → git fetch origin main (sem tocar na árvore de trabalho) → make release REF=origin/main
```

- O script e o trecho do hook ficam versionados em [`deploy/`](../deploy/) para revisão. A cópia que roda é a do
  deploy-hub, aprovada pelo hash.
- A sequência do deploy mora no `make release`, não no script: mudar o processo não exige reaprovar o hash. O
  script só faz `git fetch` com a deploy key e chama o Makefile.
- O workflow (`.github/workflows/deploy.yml`) só dispara depois do CI verde em `main` e **pula o deploy** (com
  aviso) enquanto os segredos não estiverem cadastrados.
- O hub responde só o veredito do hash (`200 iniciado`). Uma falha no build ou nas migrations **não reprova o
  pipeline**: chega pelo Telegram do deploy-hub e pela linha `FALHA_DEPLOY` em `deploy-hub/logs/deploy-hub.log`.

### Ativação (passos do Rafael)

Cada passo está no runbook do `deploy-hub/README.md`. Os que dependem de acesso externo:

1. **Deploy key** somente leitura:
   `ssh-keygen -t ed25519 -f ~/.local/share/deploy-hub/keys/bitman_deploy_key -N ""` e cadastrar a chave
   **pública** em GitHub › RafaelSandrigo/bitman › Settings › Deploy keys, **sem** "Allow write access".
2. **Script:** `cp deploy/deploy-bitman.sh ../deploy-hub/scripts/deploy-bitman.sh`.
3. **Hook:** acrescentar o conteúdo de `deploy/hooks.json.fragment` à lista de `deploy-hub/hooks.json.tmpl` e
   recriar o container do webhook.
4. **Token do hook:** definir `X_DEPLOY_TOKEN_BITMAN` em `~/.local/share/deploy-hub/.env`.
5. **Cloudflare Access:** Access Application para o path `/hooks/deploy-bitman`, com Service Token próprio.
6. **Segredos no GitHub** (Settings › Secrets and variables › Actions): `CF_ACCESS_CLIENT_ID`,
   `CF_ACCESS_CLIENT_SECRET` e `DEPLOY_TOKEN_BITMAN` (o mesmo valor do passo 4).
7. **Aprovar o hash:** ler o script e rodar `~/.local/share/deploy-hub/bin/approve.sh bitman`.
8. **Testar:** GitHub › Actions › deploy › *Run workflow* e conferir o 🚀 / ✅ no Telegram.

## Tarefas agendadas

Rodam no container `scheduler` (`schedule:work`) e registram em JSON no `stderr` (`make logs s=scheduler`).

| Comando | Quando | O que faz | Alerta |
|---|---|---|---|
| `bitman:reconcile-served` | de hora em hora | Confere o `served/` contra as versões no ar e corrige a partir do archive (M12) | `served.restore_failed` (error) e exit 1 |
| `bitman:expire-sessions` | a cada 15 min | Expira sessões vencidas e apaga partidas de visitante sem score para salvar (M14) | — |
| `bitman:admin-digest` | 12h UTC | E-mail ao admin com as versões esperando decisão (M3) | — |

Os e-mails saem pela fila `default`, processada pelo container `queue`.

## Reverter

- **Caminho normal:** `git revert` do commit problemático e push. O revert passa pelo CI e pelo deploy como qualquer
  mudança.
- **Emergência:** `make release REF=<sha-anterior>`, desde que `docker-compose.yml` e `Makefile` não tenham mudado
  desde aquele commit (o release confere isso).
- Migrations **não são desfeitas** por nenhum dos dois. Uma migration destrutiva precisa de plano de volta próprio.

## A fazer (Fase 8)

- Teste de fumaça pós-deploy pela URL pública: headers de segurança e hash dos arquivos servidos (C1, C2).
- Backups dos volumes `postgres_data`, `artifacts` e `media`.
- Rotação de logs e alertas (`fetch_failed`, scores sinalizados).
