SRC_DIR="src/"
SRC_FILES= $(shell find $(SRC_DIR) -name "*.php")

dist/varnish-plus-cli.phar: vendor box.json.dist tools/box bin/varnish-plus-cli.php $(SRC_FILES) composer.lock
	./tools/box compile --quiet

tools/box:
	wget --directory-prefix=tools --quiet https://github.com/humbug/box/releases/download/3.6.0/box.phar
	mv tools/box.phar tools/box
	chmod +x tools/box

tools/php-cs-fixer:
	wget --directory-prefix=tools --quiet https://cs.sensiolabs.org/download/php-cs-fixer-v2.phar
	mv tools/php-cs-fixer-v2.phar tools/php-cs-fixer
	chmod +x tools/php-cs-fixer

tools/phpstan:
	wget --directory-prefix=tools --quiet https://github.com/phpstan/phpstan-shim/raw/0.10.5/phpstan
	chmod +x tools/phpstan

phpcs: vendor tools/php-cs-fixer tools/phpstan
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
