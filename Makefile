# HELP
# This will output the help for each task
.PHONY: help

help: ## This help.
    @awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

.DEFAULT_GOAL := help

THIS_FILE := $(lastword $(MAKEFILE_LIST))
PHP_VERSION ?= "7.4"
COMPOSER_VERSION ?= "2.0.8"

%:
	@echo ""
all:
	@echo ""
run:
	docker run --rm -it \
        -v $$(pwd):/srv/$$(basename "`pwd`") \
		-w /srv/$$(basename "`pwd`") \
		--user "$$(id -u):$$(id -g)" \
        --name $$(basename "`pwd`")_cli \
    php:$(PHP_VERSION)-cli $(filter-out $@,$(MAKECMDGOALS))
unittest:
	docker run --rm -it \
        -v $$(pwd):/srv/$$(basename "`pwd`") \
		-w /srv/$$(basename "`pwd`") \
		--user "$$(id -u):$$(id -g)" \
        --name $$(basename "`pwd`")_cli \
    php:$(PHP_VERSION)-cli vendor/bin/phpunit --verbose --debug tests
composer-install:
	docker run --rm -it \
        -v $$(pwd):/srv/$$(basename "`pwd`") \
        -w /srv/$$(basename "`pwd`") \
        -e COMPOSER_HOME="/srv/$$(basename "`pwd`")/.composer" \
        --user $$(id -u):$$(id -g) \
    composer:${COMPOSER_VERSION} composer install --no-plugins --no-scripts --prefer-dist -v
composer-update:
	docker run --rm -it \
        -v $$(pwd):/srv/$$(basename "`pwd`") \
        -w /srv/$$(basename "`pwd`") \
        -e COMPOSER_HOME="/srv/$$(basename "`pwd`")/.composer" \
        --user $$(id -u):$$(id -g) \
	composer:${COMPOSER_VERSION} composer update --no-plugins --no-scripts  --prefer-dist -v
composer:
	docker run --rm -it \
        -v $$(pwd):/srv/$$(basename "`pwd`") \
        -w /srv/$$(basename "`pwd`") \
        -e COMPOSER_HOME="/srv/$$(basename "`pwd`")/.composer" \
        --user $$(id -u):$$(id -g) \
    composer:${COMPOSER_VERSION} composer $(filter-out $@,$(MAKECMDGOALS))
