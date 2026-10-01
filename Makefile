MAKEFLAGS += --no-print-directory

SRC     := $(CURDIR)
DFILES   := -f docker-compose.yml
COMPOSE  = docker compose -p camagru $(DFILES)

export CAMAGRU_UID := $(shell id -u)
export CAMAGRU_GID := $(shell id -g)

WEB     := camagru-web
DB      := camagru-db

VENV    := $(SRC)/scripts/.venv
PY      := $(VENV)/bin/python

DB_USER := $(shell cat $(SRC)/secrets/db_user 2>/dev/null)
# .env est en NOM=valeur : make le lit comme des affectations (DB_NAME, ...)
-include $(SRC)/.env
PSQL    := psql -U $(DB_USER) -d $(DB_NAME)

.PHONY: up down re logs ps psql shell clean fclean watch-apache watch-db dev seed venv draw secrets admin env php_error php_access php_log


# --force-recreate : podman-compose ne recrée un conteneur que si sa
# configuration compose change
up: env secrets
	$(COMPOSE) up -d --build --force-recreate
	@$(MAKE) admin
	@echo "[up] Conteneurs démarrés"
	@echo "  Camagru -> http://localhost:8080/"
	@echo "  MailHog -> http://localhost:8025/"

env:
	@test -f $(SRC)/.env || { echo "[env] .env absent" >&2; exit 1; }

down:
	$(COMPOSE) down

re: down fclean up

watch-apache:
	@./scripts/watch.sh --apache $(WEB)

watch-db:
	@./scripts/watch.sh --db $(DB) $(SRC)/camagru/database

dev: DFILES += -f docker-compose.override.yml
dev: up
	@echo "[dev] Mode développeur activé"

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

psql:
	$(COMPOSE) exec db $(PSQL)

secrets:
	@./scripts/credentials.py $(ARGS)

admin:
	@./scripts/admin.sh $(WEB) $(DB)

$(VENV): scripts/requirements.txt
	python3 -m venv $(VENV)
	$(PY) -m pip install --quiet --upgrade pip
	$(PY) -m pip install --quiet -r scripts/requirements.txt
	@touch $(VENV)

venv: $(VENV)

seed: $(VENV)
	@$(PY) scripts/seed.py $(ARGS)

bash-web:
	$(COMPOSE) exec -u www-data web bash

bash-db:
	$(COMPOSE) exec db bash

clean:
	$(COMPOSE) down -v

fclean:
	-$(COMPOSE) down -v --rmi all
	@echo "[fclean] tout a été supprimé"
