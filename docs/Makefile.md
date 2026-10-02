# Makefile

`make` lit un fichier `Makefile`, qui décrit des cibles, ce dont elles dépendent
et les commandes qui les produisent. Il compare les dates de modification des
fichiers et n'exécute que les commandes nécessaires. Ce document décrit GNU
make (version 4.4).

## 1 : Règle

```make
cible: prerequis1 prerequis2
	commande 1
	commande 2
```

| Partie | Rôle |
|--------|------|
| cible | fichier à produire, ou nom d'une action |
| prérequis | fichiers ou cibles à mettre à jour avant la cible |
| recette | commandes shell, une par ligne |

Chaque ligne de recette commence par une tabulation. Des espaces à la place
produisent l'erreur `missing separator`.

`make` sans argument traite la première cible du fichier. `make cible` traite
la cible nommée ; plusieurs cibles se traitent dans l'ordre donné
(`make clean all`).

## 2 : Dates et reconstruction

`make` exécute la recette d'une cible dans trois cas :

- la cible n'existe pas ;
- un prérequis est plus récent que la cible (date de modification) ;
- un prérequis a lui-même été reconstruit pendant cette exécution.

Les prérequis sont traités d'abord, récursivement. Une cible à jour affiche
`make: 'cible' is up to date.`

`touch fichier` met la date de modification à l'heure courante sans changer le
contenu, et crée le fichier s'il n'existe pas. Il sert dans deux sens :

| Usage | Effet |
|-------|-------|
| `touch source`, puis `make` | force la reconstruction de ce qui dépend de `source` |
| `touch $@` en fin de recette | rend la cible plus récente que ses prérequis |

Le second usage s'applique à une cible dont la date ne change pas d'elle-même,
comme un dossier : la date d'un dossier ne change qu'à l'ajout ou au retrait
d'une entrée à sa racine. Sans `touch`, la recette se relancerait à chaque
appel.

Un fichier vide créé par `touch` peut aussi servir de témoin (*stamp*) pour une
action qui ne produit pas de fichier :

```make
.installe: requirements.txt
	pip install -r requirements.txt
	touch $@
```

Un prérequis placé après `|` est d'ordre seul : `make` le crée avant la cible
s'il manque, et ignore sa date. Il convient à un dossier de sortie, dont la date
change à chaque fichier ajouté :

```make
build/%.o: src/%.c | build
	cc -c $< -o $@

build:
	mkdir -p $@
```

## 3 : Recettes

### Un shell par ligne

Chaque ligne de recette s'exécute dans un nouveau shell (`/bin/sh` par défaut,
réglable par la variable `SHELL`). Un `cd` ou une variable shell ne passe pas à
la ligne suivante :

```make
faux:
	cd src
	ls          # liste le dossier de départ

juste:
	cd src && ls
```

Une commande longue se coupe par `\` en fin de ligne ; les lignes jointes forment
une seule commande, donc un seul shell.

### `.ONESHELL` et `.SHELLFLAGS`

```make
.ONESHELL:
.SHELLFLAGS := -ec
```

`.ONESHELL:` fait exécuter chaque recette entière par un seul shell : un `cd`
ou une variable shell reste valable jusqu'à la fin de la recette. Deux cibles
différentes s'exécutent toujours dans deux shells distincts. La directive vaut
pour tout le Makefile.

Avec `.ONESHELL`, `make` ne voit que le code de sortie de la dernière commande
de la recette : une commande qui échoue au milieu laisse la suite s'exécuter,
et la cible est considérée comme réussie. `.SHELLFLAGS` contient les options
passées au shell, `-c` par défaut ; `-ec` ajoute `-e`, qui arrête le shell à la
première commande en échec et rétablit l'arrêt de `make`.

Les préfixes `@`, `-` et `+` ne sont lus que sur la première ligne, et
s'appliquent alors à toute la recette : un `@` en tête rend la recette entière
silencieuse. Sur les lignes suivantes, `make` retire ces caractères sans les
appliquer.

### Préfixes de ligne

| Préfixe | Effet |
|---------|-------|
| `@` | n'affiche pas la commande avant de l'exécuter |
| `-` | ignore l'échec de la commande ; `make` continue |
| `+` | exécute la ligne même avec `make -n` |

Par défaut, `make` affiche chaque commande puis l'exécute, et s'arrête à la
première commande qui renvoie un code non nul.

### `$` et `$$`

`make` développe `$(...)` et `$x` avant de passer la ligne au shell. Un `$`
destiné au shell s'écrit `$$` :

```make
afficher:
	@echo $$HOME          # variable du shell
	@for f in *.c; do echo $$f; done
```

### `#`

Hors recette, `#` commence un commentaire jusqu'à la fin de la ligne. Dans une
recette, la ligne est passée telle quelle au shell : `# texte` est un
commentaire shell, affiché par `make` comme toute commande, sauf sous `@`.

Un commentaire en fin de définition de variable laisse les espaces qui le
précèdent dans la valeur :

```make
DOSSIER = build # sortie
# DOSSIER vaut "build " : $(DOSSIER)/a.o donne "build /a.o"
```

## 4 : Variables

| Affectation | Effet |
|-------------|-------|
| `A = valeur` | développée à chaque usage (affectation récursive) |
| `A := valeur` | développée une fois, à la lecture de la ligne |
| `A ?= valeur` | affectée seulement si `A` n'est pas déjà définie |
| `A += valeur` | ajoute à la suite, séparée par une espace |
| `A != commande` | résultat de la commande shell, calculé à la lecture |

La différence entre `=` et `:=` porte sur le moment du calcul :

```make
D := tot
E = $(D)
D := tard
# $(E) vaut "tard" : E est calculée à l'usage
```

Avec `=`, une valeur `$(shell ...)` relance la commande à chaque usage de la
variable. `:=` l'exécute une fois.

Une variable se lit par `$(A)` ou `${A}`. Sans parenthèses, `$A` ne lit qu'un
nom d'un seul caractère : `$AB` vaut `$(A)B`.

### Origine des valeurs

| Source | Priorité |
|--------|----------|
| ligne de commande : `make A=x` | remplace la valeur du Makefile |
| Makefile | remplace la valeur de l'environnement |
| environnement du shell | valeur par défaut d'une variable non définie |

`override A = x` dans le Makefile l'emporte sur la ligne de commande.
`export A` transmet la variable à l'environnement des commandes de recette.

### Variable propre à une cible

```make
debug: CFLAGS += -g
debug: all
```

La valeur s'applique pendant le traitement de `debug` et de tous ses prérequis :
`make debug` compile `all` avec `-g`, `make all` sans.

## 5 : Variables automatiques

Disponibles dans la recette, elles désignent les éléments de la règle en cours :

| Variable | Valeur |
|----------|--------|
| `$@` | la cible |
| `$<` | le premier prérequis |
| `$^` | tous les prérequis, sans doublons |
| `$?` | les prérequis plus récents que la cible |
| `$*` | la partie capturée par `%` dans une règle générique |
| `$(@D)`, `$(@F)` | dossier et nom de fichier de la cible |

```make
prog: main.o util.o
	cc $^ -o $@
```

## 6 : Règles génériques

`%` remplace n'importe quelle chaîne non vide, la même des deux côtés :

```make
%.o: %.c
	cc -c $< -o $@
```

Cette règle produit `main.o` à partir de `main.c`, `util.o` à partir de
`util.c`. GNU make contient aussi des règles implicites intégrées
(`%.o: %.c` avec `$(CC)` et `$(CFLAGS)`) ; `make -r` ou `MAKEFLAGS += -r` les
désactive.

Remplacement de suffixe sur une liste :

```make
SRCS = main.c util.c
OBJS = $(SRCS:.c=.o)        # main.o util.o
```

## 7 : Cibles spéciales

| Cible | Effet |
|-------|-------|
| `.PHONY: clean all` | ces cibles sont des actions : leur recette s'exécute toujours, même si un fichier `clean` existe |
| `.DEFAULT_GOAL := all` | cible traitée par `make` sans argument |
| `.DELETE_ON_ERROR:` | supprime la cible si sa recette échoue, pour ne pas laisser un fichier incomplet plus récent que ses prérequis |
| `.SILENT:` | équivaut à `@` sur toutes les lignes |
| `.ONESHELL:` | une recette entière dans un seul shell ; à associer à `.SHELLFLAGS := -ec` |

Sans `.PHONY`, un fichier nommé `clean` dans le dossier rend la cible `clean`
toujours à jour : sa recette ne s'exécute plus.

## 8 : Fonctions

| Fonction | Résultat |
|----------|----------|
| `$(wildcard src/*.c)` | fichiers existants correspondant au motif |
| `$(shell commande)` | sortie de la commande, sauts de ligne remplacés par des espaces |
| `$(patsubst %.c,%.o,$(SRCS))` | remplacement par motif |
| `$(subst a,b,texte)` | remplacement littéral |
| `$(addprefix build/,$(OBJS))` | préfixe ajouté à chaque mot |
| `$(notdir src/a.c)`, `$(dir src/a.c)` | `a.c`, `src/` |
| `$(filter %.c,$(FICHIERS))` | mots qui correspondent au motif |
| `$(foreach v,liste,texte)` | `texte` développé pour chaque mot de `liste` |
| `$(if cond,alors,sinon)` | `alors` si `cond` est non vide |
| `$(error message)` | arrête `make` avec le message |
| `$(warning message)`, `$(info message)` | affiche le message et continue |

Les fonctions se développent à la lecture du fichier ou au lancement de la
recette, avant l'exécution des commandes. Un contrôle qui demande des boucles
et des messages s'écrit plus lisiblement dans un script shell appelé par la
recette.

## 9 : Inclusion et conditions

```make
include config.mk       # erreur si le fichier manque
-include .env           # ignoré si le fichier manque
```

Un fichier `.env` au format `NOM=valeur` est une suite d'affectations valides
pour `make` : `-include .env` en fait des variables du Makefile.

```make
ifeq ($(MODE),debug)
CFLAGS += -g
else
CFLAGS += -O2
endif

ifdef VERBOSE
...
endif
```

## 10 : Options

| Option | Effet |
|--------|-------|
| `-n` | affiche les commandes sans les exécuter |
| `-B` | reconstruit toutes les cibles, dates ignorées |
| `-j 4` | exécute jusqu'à 4 recettes en parallèle |
| `-k` | continue les autres cibles après un échec |
| `-s` | silencieux, comme `@` partout |
| `-C dossier` | se place dans `dossier` avant de lire le Makefile |
| `-f fichier` | lit un autre fichier que `Makefile` |
| `-p` | affiche toutes les règles et variables, implicites comprises |
| `-q` | n'exécute rien ; code de retour 0 si tout est à jour |
| `--debug=b` | affiche chaque cible examinée et indique celles à reconstruire |

`MAKEFLAGS` contient les options de l'exécution en cours et se transmet aux
`make` lancés par les recettes. Une affectation dans le Makefile applique des
options sans les taper :

```make
MAKEFLAGS += --no-print-directory    # retire les lignes "Entering directory"
MAKEFLAGS += -r                      # désactive les règles implicites
```

Un appel récursif s'écrit `$(MAKE) -C sous-dossier`. `$(MAKE)` reprend le même
exécutable et transmet `MAKEFLAGS` ; avec `make -n`, les lignes qui contiennent
`$(MAKE)` s'exécutent quand même, pour que le sous-`make` affiche à son tour ses
commandes.

## 11 : Noms standard

### Cibles

Les GNU Coding Standards (chapitre « Makefile Conventions ») fixent les noms de
cibles que la plupart des projets reprennent :

| Cible | Rôle |
|-------|------|
| `all` | construit le programme ; première cible du fichier, donc cible par défaut |
| `install` | copie le programme et ses fichiers dans `$(DESTDIR)$(prefix)` |
| `uninstall` | retire ce que `install` a copié |
| `clean` | supprime les fichiers produits par la construction |
| `distclean` | `clean`, plus les fichiers produits par la configuration (`configure`) |
| `mostlyclean` | `clean` en gardant les fichiers longs à reconstruire |
| `maintainer-clean` | `distclean`, plus les fichiers que seuls les mainteneurs régénèrent |
| `check` | lance les tests |
| `installcheck` | lance les tests sur la version installée |
| `dist` | produit l'archive de distribution |

Les projets de l'école 42 imposent leur propre jeu : `$(NAME)` (le binaire),
`all`, `clean` (fichiers objets), `fclean` (`clean` et le binaire), `re`
(`fclean` puis `all`).

### Variables

`make` définit des variables utilisées par ses règles intégrées. Valeurs par
défaut de GNU make 4.4 :

| Variable | Défaut | Contenu |
|----------|--------|---------|
| `CC` | `cc` | compilateur C |
| `CXX` | `g++` | compilateur C++ |
| `CFLAGS` | vide | options du compilateur C (`-Wall -Wextra -Werror -g`) |
| `CPPFLAGS` | vide | options du préprocesseur (`-I include`, `-D NOM`) |
| `LDFLAGS` | vide | options de l'éditeur de liens (`-L lib`) |
| `LDLIBS` | vide | bibliothèques (`-lm`), placées après les objets |
| `AR`, `ARFLAGS` | `ar`, `-rv` | création d'archives statiques `.a` |
| `RM` | `rm -f` | suppression sans erreur sur un fichier absent |

Les règles intégrées les combinent ainsi :

```make
%.o: %.c
	$(CC) $(CFLAGS) $(CPPFLAGS) -c -o $@ $<

%: %.o
	$(CC) $(LDFLAGS) $^ $(LDLIBS) -o $@
```

Un Makefile qui emploie ces noms reste modifiable depuis la ligne de commande
(`make CC=clang CFLAGS=-O2`) comme tout autre projet. `LDLIBS` vient après les
objets parce que l'éditeur de liens ne cherche dans une bibliothèque que les
symboles manquants au moment où il la lit.

Variables d'installation : `prefix` (défaut `/usr/local`), `bindir`
(`$(prefix)/bin`), `DESTDIR` (racine provisoire, utilisée pour empaqueter).

Variables tenues par `make` :

| Variable | Contenu |
|----------|---------|
| `MAKECMDGOALS` | cibles demandées sur la ligne de commande |
| `CURDIR` | dossier courant, après un éventuel `-C` |
| `MAKEFILE_LIST` | fichiers lus, `include` compris |
| `MAKE` | commande de l'exécutable `make` en cours |
| `.RECIPEPREFIX` | caractère qui introduit une recette, tabulation par défaut |

## 12 : Pièges

- **Parallélisme.** Avec `-j`, les prérequis d'une même cible s'exécutent dans
  n'importe quel ordre. `re: fclean all` peut compiler pendant le nettoyage ; un
  ordre imposé s'écrit dans la recette :

  ```make
  re:
  	$(MAKE) fclean
  	$(MAKE) all
  ```

- **Fichier oublié dans les prérequis.** Un en-tête `.h` absent des prérequis
  d'un `.o` ne déclenche pas de recompilation quand il change. `cc -MMD` génère
  des fichiers `.d` de dépendances, à inclure par `-include $(OBJS:.o=.d)`.
- **Joker sur des fichiers à créer.** `$(wildcard *.o)` ne liste que les
  fichiers présents au moment de la lecture ; une liste de cibles se calcule à
  partir des sources (`$(SRCS:.c=.o)`).

## 13 : Astuce de débogage

Une règle générique affiche la valeur de n'importe quelle variable :

```make
print-%:
	@echo '$*=$($*)'
```

`make print-CFLAGS` affiche `CFLAGS=...`, valeur calculée comprise.
