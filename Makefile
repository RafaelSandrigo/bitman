# Bitman — comandos do projeto.
# ESTE DIRETÓRIO É PRODUÇÃO: todos os alvos atuam só no projeto "bitman" (docs/convencoes.md).
# Nada de "docker ... prune", nada global, nada em containers de outros projetos.

COMPOSE := docker compose
# Desenvolvimento (perfil "dev"): código por bind mount. Produção roda as imagens do "make release".
PHP     := $(COMPOSE) run --rm -T test
NODE    := $(COMPOSE) run --rm -T --no-deps -e NPM_CONFIG_UPDATE_NOTIFIER=false frontend
# Commit que o "make release" coloca no ar (o deploy-hub usa origin/main).
REF     ?= HEAD

.DEFAULT_GOAL := help
.PHONY: help init release up down restart ps logs build sh migrate artisan composer npm \
        test test-back test-front test-infra test-e2e lint stan check

help: ## Lista os comandos
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-12s %s\n", $$1, $$2}'

init: ## Primeira instalação: cria os diretórios dos volumes (dono www-data) e instala as dependências
	$(COMPOSE) run --rm --no-deps -T --entrypoint sh app -c 'mkdir -p /var/bitman/artifacts/served /var/bitman/artifacts/archive /var/bitman/quarantine && chown -R www-data:www-data /var/bitman/artifacts /var/bitman/quarantine && chmod 755 /var/bitman/artifacts /var/bitman/artifacts/served /var/bitman/artifacts/archive'
	$(PHP) composer install
	$(NODE) npm ci

release: ## Deploy: imagens do commit REF (padrão HEAD), migrations e troca dos containers (docs/implantacao.md)
	@git rev-parse --verify --quiet "$(REF)^{commit}" >/dev/null || { echo "release: ref inválida: $(REF)"; exit 1; }
	@# A orquestração (este Makefile e o compose) vem da árvore de trabalho: precisa ser a do commit.
	@git diff --quiet "$(REF)" -- docker-compose.yml Makefile || { echo "release: docker-compose.yml ou Makefile diferem de $(REF); faça commit ou atualize o diretório"; exit 1; }
	rm -rf .release && mkdir .release && git archive "$(REF)" | tar -x -C .release
	BITMAN_BUILD=$$(git rev-parse --short "$(REF)") $(COMPOSE) build app bitman-web bitman-games git-sandbox egress-proxy
	@# Migrations com a imagem nova antes da troca: se falharem, a versão atual continua no ar.
	$(COMPOSE) run --rm -T app php artisan migrate --force
	$(COMPOSE) run --rm -T app php artisan db:seed --class=RolesAndPermissionsSeeder --force
	$(COMPOSE) up -d --wait --remove-orphans
	@echo "release: $$(git rev-parse --short "$(REF)") no ar"

up: ## Sobe a stack do Bitman com as imagens do último release
	$(COMPOSE) up -d --wait

down: ## Para a stack (preserva os volumes/dados)
	$(COMPOSE) down

restart: ## Recria os containers (necessário após alterar o .env)
	$(COMPOSE) up -d --force-recreate --wait

ps: ## Estado dos containers do projeto
	$(COMPOSE) ps

logs: ## Logs (ex.: make logs s=app)
	$(COMPOSE) logs -f --tail=100 $(s)

build: ## Reconstrói a imagem de desenvolvimento (as de produção saem do make release)
	$(COMPOSE) build test

sh: ## Shell no container da aplicação
	$(COMPOSE) exec app sh

migrate: ## Executa as migrations e sincroniza roles/permissões com o domínio
	$(COMPOSE) exec -T app php artisan migrate --force
	$(COMPOSE) exec -T app php artisan db:seed --class=RolesAndPermissionsSeeder --force

artisan: ## php artisan (ex.: make artisan c="route:list")
	$(COMPOSE) exec -T app php artisan $(c)

composer: ## composer (ex.: make composer c="require pacote")
	$(PHP) composer $(c)

npm: ## npm no frontend (ex.: make npm c="install pacote")
	$(NODE) npm $(c)

test: test-back test-front test-infra test-e2e ## Todos os testes

test-back: ## Testes do backend (banco bitman_test)
	$(PHP) ./vendor/bin/pest --colors=always

test-front: ## Testes do frontend
	$(NODE) npm test

test-e2e: ## E2E no Chromium (Playwright) contra uma stack descartável "bitman-e2e" (docs/convencoes.md#testes)
	sh tests/e2e/run.sh

test-infra: ## Testes de infraestrutura (imagens de produção, origem de jogos, thumbnails, jogo de exemplo, fixtures e egress da sandbox)
	sh tests/infra/release-images.sh
	sh tests/infra/games-origin.sh
	sh tests/infra/example-game.sh
	sh tests/infra/hub-media.sh
	sh tests/infra/sandbox-fixtures.sh
	sh tests/infra/sandbox-egress.sh

lint: ## Pint (checagem) + oxlint + typecheck
	$(PHP) ./vendor/bin/pint --test
	$(NODE) sh -c 'npm run lint && npm run typecheck'

stan: ## Análise estática (Larastan)
	$(PHP) ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M

check: lint stan test ## Tudo que o CI roda

