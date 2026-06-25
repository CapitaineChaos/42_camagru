SRC     := $(CURDIR)
TMP     := /tmp/42_camagru
COMPOSE := docker compose -p camagru

.PHONY: up down re logs ps psql shell clean fclean

# Le home 42 est sur NFS : les bind-mounts cassent en podman rootless.
# Donc on copie TOUT le projet sur un disque local (/tmp) et on lance depuis là.
# `make up` écrase la copie /tmp à chaque fois (= prend en compte tes modifs).
up:
	rm -rf $(TMP)
	mkdir -p $(TMP)
	tar -C $(SRC) --exclude=.git --exclude=node_modules -cf - . | tar -C $(TMP) -xf -
	cd $(TMP) && $(COMPOSE) up -d --build
	@echo "[up] lancé depuis $(TMP) -> http://localhost:8080/"

down:
	$(COMPOSE) down

re: down up

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

# Client psql sur la base
psql:
	$(COMPOSE) exec db psql -U camagru -d camagru

# Shell dans le container web
shell:
	$(COMPOSE) exec web bash

# Stoppe + supprime le volume de la base (reset des données)
clean:
	$(COMPOSE) down -v

php_error:
	$(COMPOSE) exec -T web tail -50 /var/log/apache2/error.log

php_access:
	$(COMPOSE) exec -T web tail -50 /var/log/apache2/access.log

suppr_logs:
	$(COMPOSE) exec -T web sh -c 'rm -f /var/log/apache2/*.log'

# Nuke total : containers + volumes + images du projet + la copie /tmp
fclean:
	-$(COMPOSE) down -v --rmi all
	rm -rf $(TMP)
	@echo "[fclean] tout supprimé"
