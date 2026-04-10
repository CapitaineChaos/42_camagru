.ONESHELL:
SHELL := /bin/bash
.SHELLFLAGS := -eu -o pipefail -c
MAKEFLAGS += --no-print-directory

USER_UID := $(shell id -u)
USER_GID := $(shell id -g)

COMPOSE     = USER_UID=$(USER_UID) USER_GID=$(USER_GID) docker compose -f ./docker-compose.yaml -p camagru
COMPOSE_DEV = $(COMPOSE) -f ./docker-compose.dev.yaml
SERVICES    := $(shell $(COMPOSE) config --services 2>/dev/null)
TARGETS	 	:= stop start down up restart logs rebuild history
SVC_TARGETS := $(foreach s,$(SERVICES), stop-$s start-$s down-$s up-$s restart-$s logs-$s rebuild-$s)

.PHONY: all up down build logs ps clean re fclean certs frontend frontend-dev git-fix seed $(SVC_TARGETS)

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
	@$(COMPOSE_DEV) up -d
	@echo "[dev] running - scss watch + no-cache active"

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

fclean:
	@echo "[fclean] stopping and removing all containers..."
	-docker stop $$(docker ps -aq) 2>/dev/null || true
	-docker rm -f $$(docker ps -aq) 2>/dev/null || true
	@echo "[fclean] removing all images..."
	-docker rmi -f $$(docker images -aq) 2>/dev/null || true
	@echo "[fclean] removing all volumes..."
	-docker volume rm $$(docker volume ls -q) 2>/dev/null || true
	@echo "[fclean] removing all custom networks..."
	-docker network rm $$(docker network ls --filter type=custom -q) 2>/dev/null || true
	@echo "[fclean] pruning builder cache..."
	-docker builder prune -af 2>/dev/null || true
	@echo "[fclean] removing certificates..."
	rm -f infra/nginx/certs/selfsigned.crt infra/nginx/certs/selfsigned.key
	@echo "[fclean] done"

list:
	@echo "Available targets:"
	@echo $(SERVICES) | tr ' ' '\n' | sed 's/^/  - /'
	@echo "Per-service targets:"
	@echo $(TARGETS) | tr ' ' '\n' | sed 's/^/  - /'

test:
	@echo "[test] requête vers https://localhost:8443/"
	@curl -skS -I https://localhost:8443/ || true
	
# --- Git SSH identity fix (auto-detects from remote + ~/.ssh/config) ---
git-fix:
	@bash scripts/git-fix-remote.sh

seed:
	@bash scripts/seed.sh

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
$(foreach s,$(SERVICES),$(eval history-$s: ; @docker image history `docker inspect camagru-$s --format '{{.Config.Image}}' 2>/dev/null` --format "{{.CreatedAt}}: {{.CreatedBy}}"))
