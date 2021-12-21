.PHONY: ${TARGETS}
.DEFAULT_GOAL := help

DIR := ${CURDIR}
QA_IMAGE := jakzal/phpqa:latest

help:
	@echo "\033[33mUsage:\033[0m"
	@echo "  make [command]"
	@echo ""
	@echo "\033[33mAvailable commands:\033[0m"
	@echo "$$(grep -hE '^\S+:.*##' $(MAKEFILE_LIST) | sort | sed -e 's/:.*##\s*/:/' -e 's/^\(.\+\):\(.*\)/  \\033[32m\1\\033[m:\2/' | column -c2 -t -s :)"

cs-fix: ## CS Fix with php-cs-fixer
	@docker run --rm -v $(DIR):/project -w /project $(QA_IMAGE) php-cs-fixer fix -vvv

cs-lint: ## CS Lint with php-cs-fixer
	@docker run --rm -v $(DIR):/project -w /project $(QA_IMAGE) php-cs-fixer fix --dry-run -vvv --diff

phpstan: ## Run PHPStan
	@docker run --rm -v $(DIR):/project -w /project $(QA_IMAGE) phpstan analyze

static: cs-lint phpstan

test: static ## Launch all tests
	@vendor/bin/phpunit
