# Bitman — comandos do projeto.
# ESTE DIRETÓRIO É PRODUÇÃO: todos os alvos atuam só no projeto "bitman" (docs/convencoes.md).
# Nada de "docker ... prune", nada global, nada em containers de outros projetos.

COMPOSE := docker compose
PHP     := $(COMPOSE) run --rm -T app
NODE    := $(COMPOSE) run --rm -T --no-deps -e NPM_CONFIG_UPDATE_NOTIFIER=false frontend

.DEFAULT_GOAL := help
.PHONY: help init up down restart ps logs build sh migrate artisan composer npm \
        test test-back test-front test-infra lint stan check basic-auth

help: ## Lista os comandos
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-12s %s\n", $$1, $$2}'

init: ## Primeira instalação: cria os diretórios dos volumes (dono www-data) e instala as dependências
	$(COMPOSE) run --rm --no-deps -T --entrypoint sh app -c 'mkdir -p /var/bitman/artifacts/served /var/bitman/artifacts/archive /var/bitman/quarantine && chown -R www-data:www-data /var/bitman/artifacts /var/bitman/quarantine && chmod 755 /var/bitman/artifacts /var/bitman/artifacts/served /var/bitman/artifacts/archive'
	$(COMPOSE) run --rm --no-deps -T app composer install
	$(NODE) npm ci

up: ## Sobe a stack do Bitman
	$(COMPOSE) up -d --build --wait

down: ## Para a stack (preserva os volumes/dados)
	$(COMPOSE) down

restart: ## Recria os containers (necessário após alterar o .env)
	$(COMPOSE) up -d --force-recreate --wait

ps: ## Estado dos containers do projeto
	$(COMPOSE) ps

logs: ## Logs (ex.: make logs s=app)
	$(COMPOSE) logs -f --tail=100 $(s)

build: ## Reconstrói as imagens do projeto
	$(COMPOSE) build

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

test: test-back test-front test-infra ## Todos os testes

test-back: ## Testes do backend (banco bitman_test)
	$(PHP) ./vendor/bin/pest --colors=always

test-front: ## Testes do frontend
	$(NODE) npm test

test-infra: ## Testes de infraestrutura (origem de jogos, thumbnails, fixtures e egress da sandbox)
	sh tests/infra/games-origin.sh
	sh tests/infra/hub-media.sh
	sh tests/infra/sandbox-fixtures.sh
	sh tests/infra/sandbox-egress.sh

lint: ## Pint (checagem) + oxlint + typecheck
	$(PHP) ./vendor/bin/pint --test
	$(NODE) sh -c 'npm run lint && npm run typecheck'

stan: ## Análise estática (Larastan)
	$(PHP) ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M

check: lint stan test ## Tudo que o CI roda

basic-auth: ## Gera a linha do BITMAN_BASIC_AUTH (make basic-auth USER=rafael PASS='senha')
	@$(COMPOSE) run --rm --no-deps -T app php -r 'echo "BITMAN_BASIC_AUTH='"'"'".$$argv[1].":".password_hash($$argv[2], PASSWORD_BCRYPT)."'"'"'\n";' "$(USER)" "$(PASS)"
