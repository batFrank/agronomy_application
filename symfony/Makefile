PHP_CONTAINER=symfony-base-php
FRONTEND_CONTAINER=symfony-base-frontend
USER_ID:=$(shell id -u)
GROUP_ID:=$(shell id -g)

# Every target is an action, not a file. Undeclared, a target whose name matches a
# top-level path (e.g. a `test/` directory) is "up to date" and its recipe never runs.
.PHONY: start stop destroy build rebuild restart install \
	composer-install composer-update composer-require composer-req composer-require-dev \
	migration-init migration-migrate migration-status \
	fix-code-project fix-code-staged \
	test test-all test-parallel cleanup-test-dbs cleanup-test-dbs-dry-run \
	clean-cache bash console phpstan \
	frontend-install frontend-dev frontend-offline frontend-build frontend-storybook \
	frontend-test frontend-test-coverage frontend-sync frontend-bash \
	deploy-install deploy jwt-generate git-hooks setup-project

start:
	UID=${USER_ID} GID=${GROUP_ID} docker compose up -d --remove-orphans

stop:
	UID=${USER_ID} GID=${GROUP_ID} docker compose stop

destroy:
	UID=${USER_ID} GID=${GROUP_ID} docker compose down --remove-orphans

build:
	UID=${USER_ID} GID=${GROUP_ID} docker compose build

rebuild:
	UID=${USER_ID} GID=${GROUP_ID} docker compose build --pull --force-rm --no-cache
	make install
	make start

restart:
	make stop
	make start

install:
	make stop
	make build
	make start
	make git-hooks
	make composer-install
	make frontend-install
	make jwt-generate
	make migration-init
	make migration-migrate
	docker exec ${PHP_CONTAINER} bin/console app:create-user soporte@grupogestionet.com 12345678 --admin

composer-install:
	docker exec ${PHP_CONTAINER} composer install

composer-update:
	docker exec ${PHP_CONTAINER} composer update

composer-require:
	docker exec ${PHP_CONTAINER} composer require $(filter-out $@,$(MAKECMDGOALS))

composer-req:
	docker exec ${PHP_CONTAINER} composer require $(filter-out $@,$(MAKECMDGOALS))

composer-require-dev:
	docker exec ${PHP_CONTAINER} composer require --dev $(filter-out $@,$(MAKECMDGOALS))

migration-init:
	docker exec ${PHP_CONTAINER} php bin/console doctrine:migrations:sync-metadata-storage

migration-migrate:
	docker exec ${PHP_CONTAINER} php bin/console doctrine:migrations:migrate

migration-status:
	docker exec ${PHP_CONTAINER} php bin/console doctrine:migrations:status

fix-code-project:
	docker exec ${PHP_CONTAINER} bash -c "./vendor/bin/php-cs-fixer fix --allow-risky=yes --config=.php-cs-fixer.dist.php"

fix-code-staged:
	@FILES=$$(git status -s | grep -e '^[AM]' | cut -c4- | grep '^backend/' | sed 's|^backend/||' | tr '\n' ' '); \
	if [ -n "$$FILES" ]; then \
	   docker exec ${PHP_CONTAINER} bash -c "./vendor/bin/php-cs-fixer --verbose --config=.php-cs-fixer.dist.php fix --allow-risky=yes --path-mode=intersection $$FILES"; \
	else \
	   echo "No hay archivos PHP staged en el directorio backend"; \
	fi

test:
	docker exec -e XDEBUG_MODE=off ${PHP_CONTAINER} ./vendor/bin/phpunit --stop-on-failure --stop-on-error

test-all:
	docker exec -e XDEBUG_MODE=off ${PHP_CONTAINER} ./vendor/bin/phpunit

test-parallel:
	docker exec -e XDEBUG_MODE=off ${PHP_CONTAINER} ./vendor/bin/paratest --processes=auto
	make cleanup-test-dbs

cleanup-test-dbs:
	docker exec ${PHP_CONTAINER} php bin/console app:cleanup-test-databases --silent --force

cleanup-test-dbs-dry-run:
	docker exec ${PHP_CONTAINER} php bin/console app:cleanup-test-databases --dry-run

clean-cache:
	@rm -rf var/cache/*
	@if [ -d vendor ]; then docker exec ${PHP_CONTAINER} ./bin/console cache:warmup; fi

bash:
	docker exec -it ${PHP_CONTAINER} bash

console:
	docker exec ${PHP_CONTAINER} symfony console  $(filter-out $@,$(MAKECMDGOALS))

phpstan:
	docker exec -it ${PHP_CONTAINER} php -d memory_limit=512M vendor/bin/phpstan analyse -c ./phpstan.dist.neon src tests

frontend-install:
	docker compose exec $(FRONTEND_CONTAINER) bun install

frontend-dev:
	docker compose exec -it $(FRONTEND_CONTAINER) sh -c "bun run vite --mode=dev --host"

frontend-offline:
	docker compose exec -it $(FRONTEND_CONTAINER) sh -c "START_MOCK_SERVER=true bun run vite --mode=dev --host"

frontend-build:
	docker compose exec $(FRONTEND_CONTAINER) sh -c "bun run build --client=$${VITE_CLIENT:-default} --mode=production"

frontend-storybook:
	docker compose exec -it $(FRONTEND_CONTAINER) sh -c "VITE_CLIENT=$${VITE_CLIENT:-default} bun run storybook --host 0.0.0.0"

frontend-test:
	docker compose exec $(FRONTEND_CONTAINER) sh -c "bun run test:run"

frontend-test-coverage:
	docker compose exec $(FRONTEND_CONTAINER) sh -c "bun run test:coverage"

frontend-sync:
	git subtree pull --prefix=frontend vue-upstream main --squash

frontend-bash:
	docker exec -it $(FRONTEND_CONTAINER) sh

deploy-install:
	cd scripts/deploy && npm ci

deploy:
	@if ! command -v node > /dev/null 2>&1; then \
	   echo "Node.js no está instalado en la máquina local y el script de despliegue lo necesita"; \
	   exit 1; \
	fi
	@if [ ! -d scripts/deploy/node_modules ]; then \
	   echo "Faltan las dependencias del script de despliegue. Ejecuta: make deploy-install"; \
	   exit 1; \
	fi
	cd scripts/deploy && node deploy.js

jwt-generate:
	mkdir -p backend/config/jwt
	openssl genrsa -out backend/config/jwt/private.pem 4096
	openssl rsa -in backend/config/jwt/private.pem -pubout -out backend/config/jwt/public.pem

git-hooks:
	bash ./scripts/setup-git-hooks.sh

setup-project:
	bash ./scripts/setup-project.sh $(filter-out $@,$(MAKECMDGOALS))

# Handle arguments after the target
%:
	@:
