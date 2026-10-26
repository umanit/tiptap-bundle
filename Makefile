.DEFAULT_GOAL := help

.PHONY: help install fix full_static_analyze phpstan phpcs phparkitect twigcs rector eslint phpunit vitest playground

help: ## Lists the available targets
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

install: ## Installs the PHP and JavaScript dependencies
	@composer install
	@cd assets && yarn install

# phpcbf exits with 1 when it fixed something, hence the leading dash
fix: ## Applies the automatic fixes (PHPCBF, Twig CS Fixer, Rector, ESLint)
	@echo "Running phpcbf..."
	@-php vendor/bin/phpcbf --standard=phpcs.xml.dist
	@echo "Running Twig CS Fixer..."
	@php vendor/bin/twig-cs-fixer lint --fix
	@echo "Running Rector..."
	@php vendor/bin/rector process
	@echo "Running ESLint..."
	@cd assets && yarn lint --fix

full_static_analyze: phpstan phpcs phparkitect twigcs rector eslint phpunit vitest ## Runs every check, stops at the first failure

phpstan: ## Runs PHPStan
	@echo "Running phpstan..."
	@php vendor/bin/phpstan -n analyze

phpcs: ## Runs PHPCS
	@echo "Running phpcs..."
	@php vendor/bin/phpcs --standard=phpcs.xml.dist

phparkitect: ## Runs PHPArkitect
	@echo "Running PHPArkitect..."
	@php vendor/bin/phparkitect check --config=phparkitect.php

twigcs: ## Runs Twig CS Fixer
	@echo "Running Twig CS Fixer..."
	@php vendor/bin/twig-cs-fixer lint

rector: ## Runs Rector (dry run)
	@echo "Running Rector..."
	@php vendor/bin/rector process --dry-run

eslint: ## Runs ESLint
	@echo "Running ESLint..."
	@cd assets && yarn lint

phpunit: ## Runs PHPUnit
	@php vendor/bin/phpunit

vitest: ## Runs Vitest
	@echo "Running Vitest..."
	@cd assets && yarn test

playground: ## Installs, builds and serves the playground on http://127.0.0.1:8765 (PORT=… to change it)
	@$(MAKE) -C playground install build
	@$(MAKE) -C playground serve
