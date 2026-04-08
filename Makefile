.ONESHELL:
SHELL := /bin/bash
.SHELLFLAGS := -eu -o pipefail -c
MAKEFLAGS += --no-print-directory

USER_UID := $(shell id -u)
USER_GID := $(shell id -g)

COMPOSE     = USER_UID=$(USER_UID) USER_GID=$(USER_GID) docker compose -f ./docker-compose.yaml -p camagru
SERVICES    := $(shell $(COMPOSE) config --services 2>/dev/null)
TARGETS	 	:= stop start down up restart logs rebuild
SVC_TARGETS := $(foreach s,$(SERVICES), stop-$s start-$s down-$s up-$s restart-$s logs-$s rebuild-$s)

.PHONY: all up down build logs ps clean re fclean certs frontend frontend-dev git-fix $(SVC_TARGETS)

all: up

certs:
	sh infra/nginx/generate-cert.sh

# --- Frontend ---
frontend:
	@$(MAKE) -C app/frontend build

frontend-dev:
	@$(MAKE) -C app/frontend dev &

frontend-install:
	@$(MAKE) -C app/frontend install

frontend-clean:
	@$(MAKE) -C app/frontend clean

# --- Docker ---
up: certs
	@$(COMPOSE) up -d

dev: certs frontend-dev
	@$(COMPOSE) up -d
	@echo "[dev] running - scss watch active"

down:
	@$(COMPOSE) down

start:
	@$(COMPOSE) start

stop:
	@$(COMPOSE) stop

restart:
	@$(COMPOSE) restart

build: certs
	@$(COMPOSE) up -d --build

logs:
	@$(COMPOSE) logs -f

ps:
	@$(COMPOSE) ps

clean:
	@$(COMPOSE) down -v --rmi local

fclean: clean
	@echo "[fclean] suppression des certificats..."
	rm -f infra/nginx/certs/selfsigned.crt infra/nginx/certs/selfsigned.key
	@echo "[fclean] suppression du cache builder..."
	docker builder prune -af
	@echo "[fclean] suppression système..."
	docker system prune -af --volumes
	@echo "[fclean] terminé"

list:
	@echo "Available targets:"
	@echo $(SERVICES) | tr ' ' '\n' | sed 's/^/  - /'
	@echo "Per-service targets:"
	@echo $(TARGETS) | tr ' ' '\n' | sed 's/^/  - /'

# --- Git SSH identity fix (auto-detects from remote + ~/.ssh/config) ---
git-fix:
	@bash scripts/git-fix-remote.sh

re: fclean build

# ======================== Per-service targets =================
# Usage : make stop-backend, make up-frontend, make logs-db, make rebuild-backend ...

$(foreach s,$(SERVICES),$(eval stop-$s:    ; @$(COMPOSE) stop $s))
$(foreach s,$(SERVICES),$(eval start-$s:   ; @$(COMPOSE) start $s))
$(foreach s,$(SERVICES),$(eval down-$s:    ; @$(COMPOSE) rm -fs $s))
$(foreach s,$(SERVICES),$(eval up-$s:      ; @$(COMPOSE) up -d $s))
$(foreach s,$(SERVICES),$(eval restart-$s: ; @$(COMPOSE) restart $s))
$(foreach s,$(SERVICES),$(eval logs-$s:    ; @$(COMPOSE) logs -f --tail=50 $s))
$(foreach s,$(SERVICES),$(eval rebuild-$s: ; @$(COMPOSE) up -d --build $s))
