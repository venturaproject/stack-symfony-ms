.PHONY: help up down test test-identity test-notification phpstan phpstan-identity phpstan-notification cs-check cs-check-identity cs-check-notification deptrac deptrac-identity deptrac-notification audit audit-identity audit-notification quality

COMPOSE = docker compose -f compose.dev.yml

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-24s %s\n", $$1, $$2}'

up: ## Start the local development stack
	$(COMPOSE) up --pull always -d --build

down: ## Stop the local development stack
	$(COMPOSE) down

test: test-identity test-notification ## Run all service tests

test-identity: ## Run Identity tests
	$(COMPOSE) exec -T identity php bin/phpunit

test-notification: ## Run Notification tests
	$(COMPOSE) exec -T notification php bin/phpunit

phpstan: phpstan-identity phpstan-notification ## Run PHPStan on all services

phpstan-identity: ## Run Identity PHPStan
	$(COMPOSE) exec -T identity composer phpstan

phpstan-notification: ## Run Notification PHPStan
	$(COMPOSE) exec -T notification composer phpstan

cs-check: cs-check-identity cs-check-notification ## Check PHP formatting on all services

cs-check-identity: ## Check Identity PHP formatting
	$(COMPOSE) exec -T identity composer cs-check

cs-check-notification: ## Check Notification PHP formatting
	$(COMPOSE) exec -T notification composer cs-check

deptrac: deptrac-identity deptrac-notification ## Check architecture rules on all services

deptrac-identity: ## Check Identity architecture rules
	$(COMPOSE) exec -T identity composer deptrac

deptrac-notification: ## Check Notification architecture rules
	$(COMPOSE) exec -T notification composer deptrac

audit: audit-identity audit-notification ## Audit Composer dependencies in all services

audit-identity: ## Audit Identity Composer dependencies
	$(COMPOSE) exec -T identity composer audit

audit-notification: ## Audit Notification Composer dependencies
	$(COMPOSE) exec -T notification composer audit

quality: test phpstan cs-check deptrac audit ## Run all tests and quality checks
