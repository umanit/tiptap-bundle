.DEFAULT_GOAL := build

PORT ?= 8765

.PHONY: install build serve smoke

install:
	@composer install
	@npm install
	@php bin/console importmap:install

build:
	@npm run build

serve:
	@php -S 127.0.0.1:$(PORT) -t public

# Requests every page of a running server: a 200 proves that the bundle installs with both pipelines.
smoke:
	@for path in / /assetmapper /encore; do \
		code=$$(curl -sL -o /dev/null -w '%{http_code}' "http://127.0.0.1:$(PORT)$$path"); \
		echo "$$path -> $$code"; \
		[ "$$code" = "200" ] || exit 1; \
	done
