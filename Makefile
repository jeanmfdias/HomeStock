SHELL := /bin/bash
COMPOSE_DEV := docker compose -f docker-compose.yml
COMPOSE_PROD := docker compose -f docker-compose.prod.yml
PHP := $(COMPOSE_DEV) exec php
CONSOLE := $(PHP) bin/console

.PHONY: help up down restart logs sh build install migrate fixtures test test-backend test-frontend lint stan backup prod-build prod-up prod-down

help:
	@echo "Targets:"
	@echo "  up              Start dev stack (php, nginx, node)"
	@echo "  down            Stop dev stack"
	@echo "  restart         Restart dev stack"
	@echo "  logs            Tail logs"
	@echo "  sh              Open shell in php container"
	@echo "  build           Build dev images"
	@echo "  install         composer install + npm install"
	@echo "  migrate         Run Doctrine migrations"
	@echo "  fixtures        Load Doctrine fixtures"
	@echo "  test            Run all tests (backend + frontend)"
	@echo "  test-backend    PHPUnit"
	@echo "  test-frontend   Vitest"
	@echo "  lint            php-cs-fixer + eslint"
	@echo "  stan            phpstan"
	@echo "  backup          Backup SQLite DB to ./backups"
	@echo "  prod-build      Build production image"
	@echo "  prod-up         Start production stack"
	@echo "  prod-down       Stop production stack"

up:
	$(COMPOSE_DEV) up -d

down:
	$(COMPOSE_DEV) down

restart: down up

logs:
	$(COMPOSE_DEV) logs -f --tail=100

sh:
	$(PHP) sh

build:
	$(COMPOSE_DEV) build

install:
	$(PHP) composer install
	$(COMPOSE_DEV) exec node npm install

migrate:
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

fixtures:
	$(CONSOLE) doctrine:fixtures:load --no-interaction

test: test-backend test-frontend

test-backend:
	$(PHP) vendor/bin/phpunit

test-frontend:
	$(COMPOSE_DEV) exec node npm run test -- --run

lint:
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(COMPOSE_DEV) exec node npm run lint

stan:
	$(PHP) vendor/bin/phpstan analyse

backup:
	@mkdir -p backups
	@TS=$$(date +%Y%m%d-%H%M%S); \
	$(PHP) sh -c "sqlite3 /app/var/data/data.db \".backup '/app/var/data/backup-$$TS.db'\""; \
	docker cp homestock-php:/app/var/data/backup-$$TS.db backups/; \
	$(PHP) rm /app/var/data/backup-$$TS.db; \
	echo "Backup written to backups/backup-$$TS.db"

prod-build:
	$(COMPOSE_PROD) build

prod-up:
	$(COMPOSE_PROD) up -d

prod-down:
	$(COMPOSE_PROD) down
