MAKEFLAGS += --no-print-directory

SRC     := $(CURDIR)
TMP     := /tmp/42_camagru
COMPOSE := docker compose -p camagru

DATA    := $(TMP)/.data
export CAMAGRU_DATA := $(DATA)
# Tester si c'est podman
PODMAN  := $(shell docker --version 2>/dev/null | grep -qi podman && echo 1)
export CAMAGRU_UID  := $(if $(PODMAN),33,$(shell id -u))
export CAMAGRU_GID  := $(if $(PODMAN),0,$(shell id -g))

WATCH_CODE := camagru/app camagru/config camagru/public
WATCH_DB   := camagru/database
RSYNC   := rsync -a --delete --exclude=.git --exclude=node_modules --exclude='camagru/database/.schema.sql.*' --exclude='camagru/storage/images' --exclude='/.data' --exclude='scripts/.venv'

WEB     := camagru-web

VENV    := $(SRC)/scripts/.venv
PY      := $(VENV)/bin/python

DB_USER := $(shell cat $(SRC)/secrets/db_user 2>/dev/null)
DB_NAME := $(shell sed -n 's/^DB_NAME=//p' $(SRC)/.env 2>/dev/null)
PSQL    := psql -U $(DB_USER) -d $(DB_NAME)

.PHONY: up down re logs ps psql db-apply db-reset shell clean data-clean fclean sync watch-code watch-db dev seed user venv draw secrets admin env php_error php_access php_log


# g+w sur la source aussi : rsync -a recopie les permissions à chaque sync
up: env secrets sync
	mkdir -p $(DATA)/postgres $(DATA)/images
	# only the directories: apache creates files there but rewrites none, and the
	# ones it already wrote are not ours. The source is included because rsync -a
	# carries the permissions over on every sync.
	find $(DATA)/images $(SRC)/camagru/storage $(TMP)/camagru/storage -type d -exec chmod g+w {} +
	cd $(TMP) && $(COMPOSE) up -d --build
# 	@$(MAKE) admin
	@echo "[up] Conteneurs démarrés depuis $(TMP)"
	@echo "  Camagru -> http://localhost:8080/"
	@echo "  MailHog -> http://localhost:8025/"

env:
	@test -f $(SRC)/.env || { echo "[env] .env absent" >&2; exit 1; }

down:
	$(COMPOSE) down

re: down fclean up

sync:
	mkdir -p $(TMP)
	$(RSYNC) $(SRC)/ $(TMP)/
	@echo "[sync] code resynchronisé -> $(TMP)"

# docker logs plutôt que compose logs : podman-compose vomit une trace python
# à chaque Ctrl-C. || true évite le « Error 130 » de make, et --line-buffered
# fait sortir grep ligne par ligne au lieu d'attendre 4 ko.
# le « |$$ » fait passer toutes les lignes, seules les correspondances sont colorées
watch-apache:
	@docker logs -f $(WEB) 2>&1 \
		| grep --line-buffered --color=always -E \
		  "PHP (Fatal error|Parse error|Warning|Notice|Deprecated)|\[error\]|\" (4|5)[0-9][0-9] |$$" || true

watch-apache-errors:
	@docker logs -f $(WEB) 2>&1 \
		| grep --line-buffered -E "PHP (Fatal error|Parse error|Warning|Notice|Deprecated)|\[error\]" || true

watch-apache-access:
	@docker logs -f $(WEB) 2>&1 | grep --line-buffered -E 'HTTP/1\.[01]"' || true

watch-code:
	@echo "[watch-code] Surveillance code active. Ctrl-C pour arrêter."
	@trap 'exit 0' INT TERM ; \
	while inotifywait -r -q -e modify,create,delete,move $(addprefix $(SRC)/,$(WATCH_CODE)) >/dev/null; do \
		$(MAKE) sync ; \
		echo "[watch-code] sync $$(date +%H:%M:%S)" ; \
	done

watch-db:
	@echo "[watch-db] Surveillance DB active. Ctrl-C pour arrêter."
	@trap 'exit 0' INT TERM ; \
	while inotifywait -r -q -e close_write,move,create,delete $(addprefix $(SRC)/,$(WATCH_DB)) >/dev/null; do \
		$(MAKE) db-apply ; \
		echo "[watch-db] db-apply $$(date +%H:%M:%S)" ; \
	done

dev: up
	@echo "[dev] watchers actifs. Ctrl-C pour arrêter."
	@trap 'kill 0 2>/dev/null; exit 0' INT TERM ; \
	$(MAKE) watch-code & \
	$(MAKE) watch-db & \
	wait

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
	@$(MAKE) admin

secrets:
	@./scripts/credentials.py $(ARGS)

admin: sync
	@$(COMPOSE) exec -T web php database/admin.php

$(VENV): scripts/requirements.txt
	python3 -m venv $(VENV)
	$(PY) -m pip install --quiet --upgrade pip
	$(PY) -m pip install --quiet -r scripts/requirements.txt
	@touch $(VENV)

venv: $(VENV)

# make user ARGS="alice [alice@camagru.local] [Sunflower42]"
user: $(VENV)
	@test -n "$(ARGS)" || { echo 'Usage: make user ARGS="alice [email] [password]"' >&2; exit 1; }
	@$(PY) scripts/user.py $(ARGS)

seed: $(VENV)
	@$(PY) scripts/seed.py $(ARGS)

bash-web:
	$(COMPOSE) exec web bash

bash-db:
	$(COMPOSE) exec db bash

# postgres writes as uid 70 in 0700 dirs: only a root container can remove them
data-clean:
	@test -d $(DATA) || exit 0; \
	docker run --rm -v $(DATA):/data postgres:16-alpine rm -rf /data/postgres /data/images

# -v drops the volumes
clean:
	$(COMPOSE) down -v
	@$(MAKE) data-clean

fclean:
	-$(COMPOSE) stop
	@$(MAKE) data-clean
	-$(COMPOSE) down -v --rmi all
	rm -rf $(TMP)
	@echo "[fclean] tout a été supprimé"
