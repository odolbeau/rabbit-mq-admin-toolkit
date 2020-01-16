.PHONY: install clean

install:
	composer install

test:
	vendor/bin/phpunit ./tests

clean:
	vendor/bin/php-cs-fixer fix .
