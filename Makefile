# HELP
# This will output the help for each task
.PHONY: help

help: ## This help.
    @awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

.DEFAULT_GOAL := help

THIS_FILE := $(lastword $(MAKEFILE_LIST))
PHP_VERSION ?= "8.1"
PROJECT_NAME := "$$(basename `pwd` | cut -d. -f1 )"

%:
	@echo ""
all:
	@echo ""
cli:
	docker run --rm -it \
        -v $$(pwd):/srv/${PROJECT_NAME} \
		-w /srv/${PROJECT_NAME} \
		--user "$$(id -u):$$(id -g)" \
        --name ${PROJECT_NAME}_cli \
    php:$(PHP_VERSION)-cli-ext $(filter-out $@,$(MAKECMDGOALS))
build:
	@if [ "$$(docker images -q php:${PHP_VERSION}-cli-ext 2>/dev/null)" = "" ]; then \
        docker build -t php:${PHP_VERSION}-cli-ext -f docker/php-cli-ext/Dockerfile .; \
    fi
unittest-pg:
	$(MAKE) -s build
	docker network create ${PROJECT_NAME} 2>/dev/null || true
	docker run --rm -d  \
        -e POSTGRES_PASSWORD=888888 \
        -v $$(pwd)/tests/db/postgres:/docker-entrypoint-initdb.d \
        --name ${PROJECT_NAME}_postgres10 \
        --network ${PROJECT_NAME} \
        postgres:10 || true
	@while [ "$$( docker exec -it ${PROJECT_NAME}_postgres10 pg_isready > /dev/null && echo 1 || echo 0 )" -eq "0" ]; do \
       	echo "Awaiting port postgres10 to be ready" ; \
       	sleep 1; \
	done
	sleep 5
	docker run --rm -it \
        -v $$(pwd):/srv/${PROJECT_NAME} \
		-w /srv/${PROJECT_NAME} \
		-e DBTYPE=postgres \
		--user "$$(id -u):$$(id -g)" \
        --name ${PROJECT_NAME}_cli \
        --network ${PROJECT_NAME} \
	php:$(PHP_VERSION)-cli-ext vendor/bin/phpunit --verbose --debug tests 
unittest-mariadb:
	$(MAKE) -s build
	docker network create ${PROJECT_NAME} 2>/dev/null || true
	docker run --rm -d \
        -e MYSQL_ROOT_PASSWORD=888888 \
        -v $$(pwd)/tests/db/mysql:/docker-entrypoint-initdb.d \
        --network ${PROJECT_NAME} \
        --name ${PROJECT_NAME}_mariadb10 \
        mariadb:10 || true
	@while [ "$$( docker exec -it ${PROJECT_NAME}_mariadb10 mysqladmin ping --user=root --password=888888 -h localhost > /dev/null && echo 1 || echo 0 )" -eq "0" ]; do \
       	echo "Awaiting port mariadb10 to be ready" ; \
       	sleep 1; \
	done
	sleep 5
	docker run --rm -it \
        -v $$(pwd):/srv/${PROJECT_NAME} \
		-w /srv/${PROJECT_NAME} \
		-e DBTYPE=mariadb \
		--user "$$(id -u):$$(id -g)" \
        --name ${PROJECT_NAME}_cli \
        --network ${PROJECT_NAME} \
	php:$(PHP_VERSION)-cli-ext vendor/bin/phpunit --verbose --debug tests 
unittest-mysql:
	$(MAKE) -s build
	docker run --rm -d \
        -e MYSQL_ROOT_PASSWORD=888888 \
        -p 3357:3357 \
        -v $$(pwd)/tests/db/mysql:/docker-entrypoint-initdb.d \
        --network ${PROJECT_NAME} \
        --name ${PROJECT_NAME}_mysql57 \
        mysql:5.7 || true
	@while [ "$$( docker exec -it ${PROJECT_NAME}_mysql57 mysqladmin ping --user=root --password=888888 -h localhost > /dev/null && echo 1 || echo 0 )" -eq "0" ]; do \
       	echo "Awaiting port mysql57 to be ready" ; \
       	sleep 1; \
	done
	docker run --rm -d \
        -e MYSQL_ROOT_PASSWORD=888888 \
        -v $$(pwd)/tests/db/mysql:/docker-entrypoint-initdb.d \
        --network ${PROJECT_NAME} \
        --name ${PROJECT_NAME}_mysql80 \
        mysql:8.0 || true
	@while [ "$$( docker exec -it ${PROJECT_NAME}_mysql80 mysqladmin ping --user=root --password=888888 -h localhost > /dev/null && echo 1 || echo 0 )" -eq "0" ]; do \
       	echo "Awaiting port mysql80 to be ready" ; \
       	sleep 1; \
	done
	sleep 10
	docker run --rm -it \
	    -v $$(pwd):/srv/${PROJECT_NAME} \
		-w /srv/${PROJECT_NAME} \
		-e DBTYPE=mysql \
		--user "$$(id -u):$$(id -g)" \
        --name ${PROJECT_NAME}_cli \
        --network ${PROJECT_NAME} \
	php:$(PHP_VERSION)-cli-ext vendor/bin/phpunit --verbose --debug tests 
unittest-clean:
	docker stop ${PROJECT_NAME}_postgres10 2>/dev/null || true
	docker stop ${PROJECT_NAME}_mariadb10 2>/dev/null || true
	docker stop ${PROJECT_NAME}_mysql57 2>/dev/null|| true
	docker stop ${PROJECT_NAME}_mysql80 2>/dev/null || true 
composer-install:
	docker run --rm -it \
        -v $$(pwd):/srv/${PROJECT_NAME} \
        -w /srv/${PROJECT_NAME} \
        -e COMPOSER_HOME="/srv/${PROJECT_NAME}/.composer" \
        --user $$(id -u):$$(id -g) \
    composer composer install --no-plugins --no-scripts --prefer-dist -v
composer-update:
	docker run --rm -it \
        -v $$(pwd):/srv/${PROJECT_NAME} \
        -w /srv/${PROJECT_NAME} \
        -e COMPOSER_HOME="/srv/${PROJECT_NAME}/.composer" \
        --user $$(id -u):$$(id -g) \
	composer composer update --no-plugins --no-scripts  --prefer-dist -v
composer:
	docker run --rm -it \
        -v $$(pwd):/srv/${PROJECT_NAME} \
        -w /srv/${PROJECT_NAME} \
        -e COMPOSER_HOME="/srv/${PROJECT_NAME}/.composer" \
        --user $$(id -u):$$(id -g) \
    composer composer $(filter-out $@,$(MAKECMDGOALS))
