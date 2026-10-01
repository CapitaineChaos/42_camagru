MAKEFLAGS += --no-print-directory

SRC     := $(CURDIR)
PODMAN  := $(shell docker --version 2>/dev/null | grep -qi podman && echo 1)
DFILES   := -f docker-compose.yml $(if $(PODMAN),-f docker-compose.podman.yml)
COMPOSE  = docker compose -p camagru $(DFILES)

export CAMAGRU_UID := $(shell id -u)
export CAMAGRU_GID := $(shell id -g)

VENV    := $(SRC)/scripts/.venv
PY      := $(VENV)/bin/python

-include $(SRC)/.env

.PHONY: up down re logs ps psql shell clean fclean watch-apache watch-db dev seed venv env php_error php_access php_log

# --force-recreate : sinon podman-compose garderait un conteneur dont seule l'image a changé
up: env
	$(COMPOSE) up -d --build --force-recreate
	@echo "[up] Conteneurs démarrés"
	@echo "  Camagru -> http://localhost:8080/"
	@echo "  MailHog -> http://localhost:8025/"

env:
	$(if $(wildcard $(SRC)/.env),,$(error .env absent : cp .env.example .env puis le remplir))
	$(foreach v,WEB_CONTAINER DB_CONTAINER MAILHOG_CONTAINER DB_USER DB_PASSWORD ADMIN_USER ADMIN_EMAIL ADMIN_PASSWORD,$(if $($(v)),,$(error $(v) vide dans .env)))

down:
	$(COMPOSE) down

re: down fclean up

watch-apache:
	@./scripts/watch.sh --apache $(WEB_CONTAINER)

watch-db:
	@./scripts/watch.sh --db $(DB_CONTAINER) $(SRC)/camagru/database

dev: DFILES += -f docker-compose.override.yml
dev: up
	@echo "[dev] Mode développeur activé"

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

psql:
	$(COMPOSE) exec db psql -U $(DB_USER) -d $(DB_NAME)

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
