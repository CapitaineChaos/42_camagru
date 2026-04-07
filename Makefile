.ONESHELL:
SHELL := /bin/bash
MAKEFLAGS += --no-print-directory

USER_UID := $(shell id -u)
USER_GID := $(shell id -g)

COMPOSE     = USER_UID=$(USER_UID) USER_GID=$(USER_GID) docker compose -f ./docker-compose.yaml -p camagru
SERVICES    := $(shell $(COMPOSE) config --services 2>/dev/null)
SVC_TARGETS := $(foreach s,$(SERVICES), stop-$s start-$s down-$s up-$s restart-$s logs-$s rebuild-$s)

.PHONY: all up down build logs ps clean re fclean certs $(SVC_TARGETS)

all: up

certs:
	sh infra/nginx/generate-cert.sh

up: certs
	@$(COMPOSE) up -d

down:
	@$(COMPOSE) down

build: certs
	@$(COMPOSE) up -d --build

logs:
	@$(COMPOSE) logs -f

ps:
	@$(COMPOSE) ps

clean:
	@$(COMPOSE) down -v --rmi local

fclean: clean
	@docker system prune -f

re: clean build

# ======================== Per-service targets =================
# Usage : make stop-backend, make up-frontend, make logs-db, make rebuild-backend …

$(foreach s,$(SERVICES),$(eval stop-$s:    ; @$(COMPOSE) stop $s))
$(foreach s,$(SERVICES),$(eval start-$s:   ; @$(COMPOSE) start $s))
$(foreach s,$(SERVICES),$(eval down-$s:    ; @$(COMPOSE) rm -fs $s))
$(foreach s,$(SERVICES),$(eval up-$s:      ; @$(COMPOSE) up -d $s))
$(foreach s,$(SERVICES),$(eval restart-$s: ; @$(COMPOSE) restart $s))
$(foreach s,$(SERVICES),$(eval logs-$s:    ; @$(COMPOSE) logs -f --tail=20 $s))
$(foreach s,$(SERVICES),$(eval rebuild-$s: ; @$(COMPOSE) up -d --build $s))