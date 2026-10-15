.PHONY: full_static_analyze phpstan phpcs phparkitect twigcs rector eslint phpunit vitest

full_static_analyze: phpstan phpcs phparkitect twigcs rector eslint phpunit vitest

phpstan:
	@echo "Running phpstan..."
	@php vendor/bin/phpstan -n analyze

phpcs:
	@echo "Running phpcs..."
	@php vendor/bin/phpcs --standard=phpcs.xml.dist

phparkitect:
	@echo "Running PHPArkitect..."
	@php vendor/bin/phparkitect check --config=phparkitect.php

twigcs:
	@echo "Running Twig CS Fixer..."
	@php vendor/bin/twig-cs-fixer lint

rector:
	@echo "Running Rector..."
	@php vendor/bin/rector process --dry-run

eslint:
	@echo "Running ESLint..."
	@cd assets && yarn lint

phpunit:
	@php vendor/bin/phpunit

vitest:
	@echo "Running Vitest..."
	@cd assets && yarn test
