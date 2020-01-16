.PHONY: ${TARGETS}

DIR := ${CURDIR}
QA_IMAGE := jakzal/phpqa:php7.3-alpine

cs-fix:
	@docker run --rm -v $(DIR):/project -w /project $(QA_IMAGE) php-cs-fixer fix --diff-format udiff -vvv

cs-lint:
	@docker run --rm -v $(DIR):/project -w /project $(QA_IMAGE) php-cs-fixer fix --diff-format udiff --dry-run -vvv

static: cs-lint

test: static
	@vendor/bin/phpunit
