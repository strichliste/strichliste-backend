# Executables
DOCKER      := docker
DOCKER_COMP := docker compose
PHP         := php

# OS Detection
ifeq ($(OS),Windows_NT)
    PLATFORM := windows
else
    UNAME := $(shell uname -s)
    ifeq ($(UNAME),Darwin)
        PLATFORM := mac
    else
        PLATFORM := linux
    endif
endif

.DEFAULT_GOAL := help
.PHONY: help up prod down logs sh test e2e lint cs cs-fix phpstan rector rector-fix prettier prettier-fix tls tls-linux tls-mac tls-windows

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-12s\033[0m %s\n", $$1, $$2}'

## —— Stack ——
up: ## Build and start the dev stack (then open https://localhost)
	@$(DOCKER_COMP) up -d --build --wait

prod: ## Build and start the production stack (requires APP_SECRET in .env)
	@$(DOCKER_COMP) -f compose.yaml -f compose.prod.yaml up -d --build --wait

down: ## Stop the stack
	@$(DOCKER_COMP) down

logs: ## Tail the app container logs
	@$(DOCKER_COMP) logs -f app

sh: ## Open a shell in the app container
	@$(DOCKER_COMP) exec app sh

## —— Quality ——
test: ## Run the PHPUnit suite (local PHP)
	@$(PHP) vendor/bin/phpunit

e2e: ## Run the Playwright end-to-end suite (own sqlite db, boots its own server)
	@npx playwright test

lint: ## Run the twig/yaml/container lints (local PHP)
	@$(PHP) bin/console lint:twig templates
	@$(PHP) bin/console lint:yaml config translations
	@$(PHP) bin/console lint:container

cs: ## Check code style (php-cs-fixer, no changes)
	@$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix code style (php-cs-fixer)
	@$(PHP) vendor/bin/php-cs-fixer fix

phpstan: ## Static analysis (level 6, no baseline)
	@$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

rector: ## Preview rector transforms (dry-run)
	@$(PHP) vendor/bin/rector process --dry-run

rector-fix: ## Apply rector transforms
	@$(PHP) vendor/bin/rector process

prettier: ## Check css/js/twig formatting (prettier, no changes)
	@npx prettier --check assets templates

prettier-fix: ## Format css/js/twig (prettier)
	@npx prettier --write assets templates

## —— TLS ——
tls: tls-$(PLATFORM) ## Trust Caddy's local CA so https://localhost shows no warning

tls-linux: ## Trust Caddy TLS Authority for Linux
	@$(DOCKER_COMP) cp app:/data/caddy/pki/authorities/local/root.crt /tmp/strichliste-root.crt
	@sudo cp /tmp/strichliste-root.crt /usr/local/share/ca-certificates/strichliste-root.crt && sudo update-ca-certificates

tls-mac: ## Trust Caddy TLS Authority for Mac
	@$(DOCKER_COMP) cp app:/data/caddy/pki/authorities/local/root.crt /tmp/strichliste-root.crt
	@sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain /tmp/strichliste-root.crt

tls-windows: ## Trust Caddy TLS Authority for Windows
	@$(DOCKER_COMP) cp app:/data/caddy/pki/authorities/local/root.crt %TEMP%/root.crt && certutil -addstore -f "ROOT" %TEMP%/root.crt
