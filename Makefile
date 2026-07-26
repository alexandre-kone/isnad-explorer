SHELL := /bin/bash
.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
SYMFONY        := symfony
CONSOLE        := $(SYMFONY) console
COMPOSER       := $(SYMFONY) composer
PHPUNIT        := vendor/bin/phpunit

# Port d'écoute du serveur web local
PORT ?= 8000

.PHONY: help
help: ## Affiche cette aide
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

## —— Cible principale ——————————————————————————————————————————————————

.PHONY: start
start: docker-up wait-db serve ## Lance Docker (Postgres, Mailpit) puis le serveur Symfony

.PHONY: stop
stop: serve-stop docker-down ## Arrête le serveur Symfony puis les conteneurs

.PHONY: restart
restart: stop start ## Redémarre tout

## —— Docker ————————————————————————————————————————————————————————————

.PHONY: docker-up
docker-up: ## Démarre les conteneurs en arrière-plan
	$(DOCKER_COMPOSE) up -d --wait

.PHONY: docker-down
docker-down: ## Arrête et supprime les conteneurs
	$(DOCKER_COMPOSE) down --remove-orphans

.PHONY: docker-logs
docker-logs: ## Suit les logs des conteneurs
	$(DOCKER_COMPOSE) logs -f

.PHONY: db-ui
db-ui: ## Affiche l'URL et les identifiants d'Adminer (inspection de Postgres)
	@port=$$($(DOCKER_COMPOSE) port adminer 8080 2>/dev/null | cut -d: -f2); \
	if [ -z "$$port" ]; then echo "Adminer n'est pas lancé — faire 'make docker-up'."; exit 1; fi; \
	echo "Adminer   http://127.0.0.1:$$port"; \
	echo "  Système     PostgreSQL"; \
	echo "  Serveur     database"; \
	echo "  Utilisateur $$($(DOCKER_COMPOSE) exec -T database printenv POSTGRES_USER | tr -d '\r\n')"; \
	echo "  Mot de passe$$(printf '\t')$$($(DOCKER_COMPOSE) exec -T database printenv POSTGRES_PASSWORD | tr -d '\r\n')"; \
	echo "  Base        $$($(DOCKER_COMPOSE) exec -T database printenv POSTGRES_DB | tr -d '\r\n')"; \
	echo; \
	echo "  Postgres ne contient que le schema (migrations). Les donnees de dev"; \
	echo "  sont dans SQLite — meme URL, choisir le systeme « SQLite » :"; \
	echo "    Base      /db/data_dev.db   (ou /db/data_test.db)"; \
	echo "    Les champs Serveur / Utilisateur / Mot de passe restent vides."

.PHONY: db-open
db-open: ## Ouvre Adminer dans le navigateur
	@port=$$($(DOCKER_COMPOSE) port adminer 8080 2>/dev/null | cut -d: -f2); \
	if [ -z "$$port" ]; then echo "Adminer n'est pas lancé — faire 'make docker-up'."; exit 1; fi; \
	echo "Ouverture de http://127.0.0.1:$$port"; \
	xdg-open "http://127.0.0.1:$$port" >/dev/null 2>&1 &

.PHONY: mail-ui
mail-ui: ## Affiche l'URL de Mailpit
	@port=$$($(DOCKER_COMPOSE) port mailer 8025 2>/dev/null | cut -d: -f2); \
	if [ -z "$$port" ]; then echo "Mailpit n'est pas lancé — faire 'make docker-up'."; exit 1; fi; \
	echo "Mailpit   http://127.0.0.1:$$port"

.PHONY: docker-purge
docker-purge: ## Supprime les conteneurs ET le volume Postgres (données perdues)
	$(DOCKER_COMPOSE) down --remove-orphans --volumes

.PHONY: wait-db
wait-db: ## Attend que Postgres accepte les connexions
	@printf 'En attente de la base de données'
	@for i in $$(seq 1 30); do \
		if $(DOCKER_COMPOSE) exec -T database pg_isready -q 2>/dev/null; then \
			echo ' OK'; exit 0; \
		fi; \
		printf '.'; sleep 1; \
	done; \
	echo ' échec : la base ne répond pas'; exit 1

## —— Serveur Symfony ———————————————————————————————————————————————————

.PHONY: serve
serve: ## Démarre le serveur web Symfony en arrière-plan
	$(SYMFONY) server:start -d --port=$(PORT)
	@echo "Application disponible sur $$($(SYMFONY) var:export SYMFONY_DEFAULT_ROUTE_URL 2>/dev/null || echo http://127.0.0.1:$(PORT))"

.PHONY: serve-fg
serve-fg: ## Démarre le serveur web Symfony au premier plan (Ctrl+C pour quitter)
	$(SYMFONY) server:start --port=$(PORT)

.PHONY: serve-stop
serve-stop: ## Arrête le serveur web Symfony
	-$(SYMFONY) server:stop

.PHONY: serve-logs
serve-logs: ## Suit les logs du serveur et de l'application
	$(SYMFONY) server:log

## —— Application ———————————————————————————————————————————————————————

.PHONY: install
install: ## Installe les dépendances PHP et les assets
	$(COMPOSER) install
	$(CONSOLE) importmap:install

.PHONY: cc
cc: ## Vide le cache Symfony
	$(CONSOLE) cache:clear

.PHONY: db-sync
db-sync: ## [dev/SQLite] Aligne var/data_dev.db sur les entités
	$(CONSOLE) doctrine:schema:update --force

# Sur SQLite, ni « --if-exists » (getListDatabasesSQL) ni database:create
# (getCreateDatabaseSQL) ne sont supportés : le fichier est créé d'office par
# schema:update, et on tolère l'échec du drop s'il n'existe pas encore.
.PHONY: db-reset
db-reset: ## [dev/SQLite] Recrée la base de dev vide depuis les entités
	-$(CONSOLE) doctrine:database:drop --force
	$(CONSOLE) doctrine:schema:update --force

## —— Migrations (Postgres uniquement) ——————————————————————————————————
# L'env dev est câblé sur SQLite par config/packages/dev/doctrine.yaml.
# Les migrations ciblent la plateforme de prod (Postgres), donc on force
# APP_ENV=prod pour que %env(resolve:DATABASE_URL)% reprenne la main, et on
# pointe sur le conteneur lancé par make docker-up.

PG_PORT = $(shell $(DOCKER_COMPOSE) port database 5432 2>/dev/null | cut -d: -f2)
PG_PASS = $(shell $(DOCKER_COMPOSE) exec -T database printenv POSTGRES_PASSWORD 2>/dev/null | tr -d '\r\n')
PG_ENV  = APP_ENV=prod DATABASE_URL='postgresql://app:$(PG_PASS)@127.0.0.1:$(PG_PORT)/app?serverVersion=16&charset=utf8'

# Le « @ » évite d'imprimer le DSN (donc le mot de passe) dans le terminal.
.PHONY: migration
migration: ## [Postgres] Génère une migration par diff contre le conteneur
	@$(PG_ENV) php bin/console doctrine:migrations:diff --no-interaction

.PHONY: migrate
migrate: ## [Postgres] Applique les migrations sur le conteneur
	@$(PG_ENV) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: migrate-status
migrate-status: ## [Postgres] Affiche l'état des migrations
	@$(PG_ENV) php bin/console doctrine:migrations:status

.PHONY: fixtures
fixtures: ## Charge les fixtures Doctrine
	$(CONSOLE) doctrine:fixtures:load --no-interaction

## —— Tests —————————————————————————————————————————————————————————————

.PHONY: test
test: ## Lance la suite PHPUnit (WebTestCase + Panther)
	$(PHPUNIT)
