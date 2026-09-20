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

DB_USER := $(shell cat $(SRC)/secrets/db_user 2>/dev/null)
DB_NAME := $(shell sed -n 's/^DB_NAME=//p' $(SRC)/.env 2>/dev/null)
PSQL    := psql -U $(DB_USER) -d $(DB_NAME)

.PHONY: up down re logs ps psql db-apply db-reset hash shell clean data-clean fclean sync watch-code watch-db dev seed secrets admin env

env:
	@test -f $(SRC)/.env || { echo "[env] .env absent" >&2; exit 1; }

up: env secrets
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
	$(COMPOSE) exec db $(PSQL)

db-apply: sync
	$(COMPOSE) exec -T db $(PSQL) < $(TMP)/camagru/database/schema.sql

db-reset: sync
	reset_sql=$$(mktemp $(TMP)/db-reset.XXXXXX.sql); \
	printf '%s\n' 'SELECT pg_advisory_lock(424242);' 'DROP SCHEMA public CASCADE;' 'CREATE SCHEMA public;' > $$reset_sql; \
	cat $(TMP)/camagru/database/schema.sql >> $$reset_sql; \
	printf '%s\n' 'SELECT pg_advisory_unlock(424242);' >> $$reset_sql; \
	$(COMPOSE) exec -T db $(PSQL) -v ON_ERROR_STOP=1 < $$reset_sql
	@$(MAKE) --no-print-directory admin

secrets:
	@mkdir -p $(SRC)/secrets
	@set -e; \
	demande() { \
		fichier=$(SRC)/secrets/$$1; \
		test -s $$fichier && return 0; \
		test -t 0 || { echo "[secrets] secrets/$$1 absent, make secrets demande à être lancé depuis un terminal" >&2; exit 1; }; \
		printf '%s [%s] : ' "$$2" "$$3"; read -r reponse; \
		printf '%s' "$${reponse:-$$3}" > $$fichier; \
	}; \
	demande db_user     "Rôle Postgres de l'application" 'test'; \
	demande admin_user  "Login de l'admin Camagru"       'test'; \
	demande admin_email "Email de l'admin Camagru"       'test@test.local'
	@test -s $(SRC)/secrets/db_password    || openssl rand -base64 24 | tr -d '\n=/+' > $(SRC)/secrets/db_password
	@test -s $(SRC)/secrets/admin_password || openssl rand -base64 18 | tr -d '\n=/+' > $(SRC)/secrets/admin_password
	@chmod 644 $(SRC)/secrets/db_user $(SRC)/secrets/db_password
	@chmod 600 $(SRC)/secrets/admin_user $(SRC)/secrets/admin_email $(SRC)/secrets/admin_password
	@echo "[secrets] $(SRC)/secrets prêt"
	@echo "  postgres  $$(cat $(SRC)/secrets/db_user) / $$(cat $(SRC)/secrets/db_password)"
	@echo "  admin     $$(cat $(SRC)/secrets/admin_user) <$$(cat $(SRC)/secrets/admin_email)> / $$(cat $(SRC)/secrets/admin_password)"

admin: sync
	@$(COMPOSE) exec -T web php database/admin.php

# peuple l'app par HTTP, comme le ferait un visiteur : make seed ARGS="-n 3"
seed:
	@./scripts/seed.py $(ARGS)

bash-web:
	$(COMPOSE) exec web bash

bash-db:
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
	$(COMPOSE) exec -T web tail -50 /var/log/apache2/error.log /var/log/apache2/php_error.log

php_access:
	$(COMPOSE) exec -T web tail -50 /var/log/apache2/access.log

suppr_logs:
	$(COMPOSE) exec -T web sh -c 'rm -f /var/log/apache2/*.log'

fclean:
	-$(COMPOSE) stop
	@$(MAKE) --no-print-directory data-clean
	-$(COMPOSE) down -v --rmi all
	rm -rf $(TMP)
	@echo "[fclean] tout a été supprimé"
