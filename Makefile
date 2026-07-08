SRC     := $(CURDIR)
TMP     := /tmp/42_camagru
COMPOSE := docker compose -p camagru

# Dossiers de code bind-montés dans les conteneurs (= ce qu'il faut resync à chaud)
CODE    := app public config database

.PHONY: up down re logs ps psql shell clean fclean sync watch dev

up:
	rm -rf $(TMP)
	mkdir -p $(TMP)
	tar -C $(SRC) --exclude=.git --exclude=node_modules -cf - . | tar -C $(TMP) -xf -
	cd $(TMP) && $(COMPOSE) up -d --build
	@echo "[up] conteneurs démarrés depuis $(TMP)"
	@echo "  Camagru -> http://localhost:8080/"
	@echo "  MailHog -> http://localhost:8025/"
	@echo "  Dev     -> lance 'make watch' dans un autre terminal pour sync les changements"

down:
	$(COMPOSE) down

re: down up

sync:
	rsync -a --delete $(addprefix $(SRC)/,$(CODE)) $(TMP)/
	@echo "[sync] code resynchronisé -> $(TMP)"

watch:
	@echo "[watch] surveillance de $(CODE). Ctrl-C pour arrêter."
	@while inotifywait -r -q -e modify,create,delete,move $(addprefix $(SRC)/,$(CODE)); do \
		rsync -a --delete $(addprefix $(SRC)/,$(CODE)) $(TMP)/ ; \
		echo "[watch] sync $$(date +%H:%M:%S)" ; \
	done

dev: up watch

logs:
	$(COMPOSE) logs -f

ps:
	$(COMPOSE) ps

psql:
	$(COMPOSE) exec db psql -U camagru -d camagru

shell:
	$(COMPOSE) exec web bash

clean:
	$(COMPOSE) down -v

php_error:
	$(COMPOSE) exec -T web tail -50 /var/log/apache2/error.log

php_access:
	$(COMPOSE) exec -T web tail -50 /var/log/apache2/access.log

suppr_logs:
	$(COMPOSE) exec -T web sh -c 'rm -f /var/log/apache2/*.log'

fclean:
	-$(COMPOSE) down -v --rmi all
	rm -rf $(TMP)
	@echo "[fclean] tout supprimé"
