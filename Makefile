.PHONY: install clean

install:
	composer install

clean:
	vendor/bin/php-cs-fixer fix .
