.DEFAULT_GOAL := help

COMPOSER ?= composer

.PHONY: help install lint cs-fix stan rector rector-fix deps test test-unit coverage infection audit ci

help: ## Show available targets
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-18s %s\n", $$1, $$2}'

install: ## Install dependencies
	$(COMPOSER) install

lint: ## Check composer.json and coding style
	$(COMPOSER) validate --strict
	$(COMPOSER) normalize:check
	$(COMPOSER) cs:check

cs-fix: ## Fix coding style and normalize composer.json
	$(COMPOSER) normalize
	$(COMPOSER) cs:fix

stan: ## Run PHPStan (level max)
	$(COMPOSER) stan

rector: ## Check Rector rules
	$(COMPOSER) rector:check

rector-fix: ## Apply Rector rules
	$(COMPOSER) rector:fix

deps: ## Analyse Composer dependencies
	$(COMPOSER) deps

test: ## Run the test suite
	$(COMPOSER) test

test-unit: ## Run unit tests
	$(COMPOSER) test:unit

coverage: ## Run tests with coverage
	$(COMPOSER) test:coverage

infection: ## Run mutation testing with Infection (needs pcov or Xdebug and the Infection PHAR)
	$(COMPOSER) infection

audit: ## Run composer audit
	$(COMPOSER) audit

ci: ## Run local checks close to CI
	$(COMPOSER) ci
	$(COMPOSER) audit
