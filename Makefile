SRC     := $(CURDIR)
TMP     := /tmp/42_camagru
COMPOSE := docker compose -p camagru

DATA    := $(TMP)/.data
export CAMAGRU_DATA := $(DATA)
export CAMAGRU_UID  := $(shell id -u)
export CAMAGRU_GID  := $(shell id -g)

WATCH_CODE := camagru/app camagru/config camagru/public
WATCH_DB   := camagru/database
RSYNC   := rsync -a --delete --exclude=.git --exclude=node_modules --exclude='camagru/database/.schema.sql.*' --exclude='camagru/storage/images' --exclude='/.data'

.PHONY: up down re logs ps psql db-apply db-reset hash shell clean data-clean fclean sync watch-code watch-db dev seed

up:
	mkdir -p $(TMP) $(DATA)/postgres $(DATA)/images
	$(RSYNC) $(SRC)/ $(TMP)/
	cd $(TMP) && $(COMPOSE) up -d --build
	@echo "[up] Conteneurs démarrés depuis $(TMP)"
	@echo "  Camagru -> http://localhost:8080/"
	@echo "  MailHog -> http://localhost:8025/"

down:
	$(COMPOSE) down

re: down up

sync:
	mkdir -p $(TMP)
	$(RSYNC) $(SRC)/ $(TMP)/
	@echo "[sync] code resynchronisé -> $(TMP)"

watch-code:
	@echo "[watch-code] Surveillance code active. Ctrl-C pour arrêter."
	@while inotifywait -r -q -e modify,create,delete,move $(addprefix $(SRC)/,$(WATCH_CODE)) >/dev/null; do \
		$(MAKE) --no-print-directory sync ; \
		echo "[watch-code] sync $$(date +%H:%M:%S)" ; \
	done

watch-db:
	@echo "[watch-db] Surveillance DB active. Ctrl-C pour arrêter."
	@while inotifywait -r -q -e close_write,move,create,delete $(addprefix $(SRC)/,$(WATCH_DB)) >/dev/null; do \
		$(MAKE) --no-print-directory db-reset ; \
		echo "[watch-db] db-reset $$(date +%H:%M:%S)" ; \
	done

dev: up
	@$(MAKE) --no-print-directory watch-code & \
	code_pid=$$! ; \
	trap 'kill $$code_pid 2>/dev/null || true' INT TERM EXIT ; \
	$(MAKE) --no-print-directory watch-db

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

psql:
	$(COMPOSE) exec db psql -U camagru -d camagru

db-apply: sync
	$(COMPOSE) exec -T db psql -U camagru -d camagru < $(TMP)/camagru/database/schema.sql

db-reset: sync
	reset_sql=$$(mktemp $(TMP)/db-reset.XXXXXX.sql); \
	printf '%s\n' 'SELECT pg_advisory_lock(424242);' 'DROP SCHEMA public CASCADE;' 'CREATE SCHEMA public;' > $$reset_sql; \
	cat $(TMP)/camagru/database/schema.sql >> $$reset_sql; \
	printf '%s\n' 'SELECT pg_advisory_unlock(424242);' >> $$reset_sql; \
	$(COMPOSE) exec -T db psql -v ON_ERROR_STOP=1 -U camagru -d camagru < $$reset_sql

# peuple l'app par HTTP, comme le ferait un visiteur : make seed ARGS="-n 3"
seed:
	@./scripts/seed.py $(ARGS)

hash:
	@test -n "$(PASS)" || (echo "Usage: make hash PASS='motdepasse'" && exit 1)
	@php -r 'echo password_hash($$argv[1], PASSWORD_DEFAULT), PHP_EOL;' '$(PASS)'

shell-web:
	$(COMPOSE) exec web bash

shell-db:
	$(COMPOSE) exec db bash

# postgres writes as uid 70 in 0700 dirs: only a root container can remove them
data-clean:
	@test -d $(DATA) || exit 0; \
	docker run --rm -v $(DATA):/data postgres:16-alpine rm -rf /data/postgres /data/images

# down -v drops the volumes, not their bind targets
clean:
	$(COMPOSE) down -v
	@$(MAKE) --no-print-directory data-clean

php_error:
	$(COMPOSE) exec -T web tail -50 /var/log/apache2/error.log

php_access:
	$(COMPOSE) exec -T web tail -50 /var/log/apache2/access.log

suppr_logs:
	$(COMPOSE) exec -T web sh -c 'rm -f /var/log/apache2/*.log'

fclean:
	-$(COMPOSE) stop
	@$(MAKE) --no-print-directory data-clean
	-$(COMPOSE) down -v --rmi all
	rm -rf $(TMP)
	@echo "[fclean] tout supprimé"
