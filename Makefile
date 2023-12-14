SRC_DIR="src/"
SRC_FILES= $(shell find $(SRC_DIR) -name "*.php")

dist/varnish-plus-cli.phar: vendor box.json.dist tools/box bin/varnish-plus-cli.php $(SRC_FILES) composer.lock
	./tools/box compile --quiet

tools/box:
	wget --directory-prefix=tools --quiet https://github.com/humbug/box/releases/download/3.16.0/box.phar
	mv tools/box.phar tools/box
	chmod +x tools/box

tools/php-cs-fixer:
	wget --directory-prefix=tools --quiet https://github.com/php-cs-fixer/php-cs-fixer/releases/download/v3.41.1/php-cs-fixer.phar
	mv tools/php-cs-fixer.phar tools/php-cs-fixer
	chmod +x tools/php-cs-fixer

tools/phpstan:
	wget --directory-prefix=tools --quiet https://github.com/phpstan/phpstan/releases/download/1.10.50/phpstan.phar
	mv tools/phpstan.phar tools/phpstan
	chmod +x tools/phpstan

phpcs: vendor tools/box tools/php-cs-fixer tools/phpstan
	composer validate --strict --no-check-lock
	tools/box validate
	tools/php-cs-fixer fix --dry-run --stop-on-violation -v
	tools/phpstan analyze --level=7 --no-progress bin/ src/

vendor:
	composer install --optimize-autoloader --no-dev --no-suggest --quiet

fix-cs: tools/php-cs-fixer
	tools/php-cs-fixer fix -v

dist: dist/varnish-plus-cli.phar

clean:
	rm -Rf tools/ dist/ vendor/

.PHONY: clean phpcs fix-cs vendor
