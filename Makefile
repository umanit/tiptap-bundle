.PHONY: full_static_analyze phpstan phpcs twigcs rector eslint phpunit

full_static_analyze: phpstan phpcs twigcs rector eslint phpunit

phpstan:
	@echo "Running phpstan..."
	@php vendor/bin/phpstan -n analyze

phpcs:
	@echo "Running phpcs..."
	@php vendor/bin/phpcs --standard=phpcs.xml.dist

twigcs:
	@echo "Running Twig CS Fixer..."
	@php vendor/bin/twig-cs-fixer lint

rector:
	@echo "Running Rector..."
	@php vendor/bin/rector process --dry-run

eslint:
	@echo "Running ESLint..."
	@cd assets && yarn eslint src purgecss-safelist.js

phpunit:
	@php vendor/bin/phpunit
