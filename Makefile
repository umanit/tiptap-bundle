.PHONY: full_static_analyze phpstan phpcs twigcs rector phpunit

full_static_analyze: phpstan phpcs twigcs rector

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

phpunit:
	@php vendor/bin/phpunit
