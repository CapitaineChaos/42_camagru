# Création de la base de données

## 1 : Schema de la base de données

Le fichier `database/schema.sql` contient le schéma de la base de données utilisé pour créer les tables et les relations entre elles.

Langage : SQL
Système : PostgreSQL

### A : Types de données
- `SERIAL` : Entier auto-incrémenté.
- `VARCHAR(n)` : Chaîne de caractères de longueur variable, avec `n` = longueur max.
- `TEXT` : Chaîne de caractères de longueur variable, sans limite imposée.
- `TIMESTAMP` : Date et heure sans fuseau horaire.
- `TIMESTAMPTZ` : Date et heure avec fuseau horaire.
- `BOOLEAN` : Valeur booléenne.

### B : Commandes SQL
- `CREATE TABLE` : Crée une nouvelle table.
- `INSERT INTO` : Insère des données dans une table.
- `ALTER TABLE` : Modifie la structure d'une table existante.
- `DROP TABLE` : Supprime une table existante.
- `SELECT` : Récupère des données depuis une ou plusieurs tables.
- `UPDATE` : Met à jour des données existantes dans une table.
- `DELETE` : Supprime des lignes dans une table.
- `CREATE INDEX` : Crée un index pour améliorer certaines requêtes.

### C : Contraintes SQL
- `PRIMARY KEY` : Définit la clé primaire de la table.
- `FOREIGN KEY` : Définit une clé étrangère pour établir une relation entre deux tables.
- `REFERENCES` : Spécifie la table et la colonne référencées par une clé étrangère.
- `NOT NULL` : Empêche les valeurs nulles dans une colonne.
- `DEFAULT` : Définit une valeur par défaut pour une colonne.
- `UNIQUE` : Garantit que les valeurs d'une colonne sont uniques.
- `CHECK` : Définit une règle pour vérifier les valeurs d'une colonne.

### D : Clauses SQL
- `WHERE` : Filtre les lignes selon une condition.
- `ORDER BY` : Trie les résultats selon une ou plusieurs colonnes.
- `GROUP BY` : Regroupe les résultats selon une ou plusieurs colonnes.
- `HAVING` : Filtre les groupes selon une condition.
- `JOIN` : Combine les lignes de deux tables selon une condition.
- `IF` : Conditionne l'exécution d'une commande selon une condition.
- `IF NOT EXISTS` : Conditionne l'exécution à l'absence de l'objet ciblé.
- `IF EXISTS` : Conditionne l'exécution à l'existence de l'objet ciblé.
- `ON DELETE CASCADE` : Propage une suppression aux lignes dépendantes.
- `ON UPDATE CASCADE` : Propage une mise à jour aux lignes dépendantes.

## 2 : Se connecter et inspecter la base

Base PostgreSQL exposée par le service `db` (conteneur `camagru-db`), utilisateur et base `camagru`.

### A : Connexion

Via le Makefile :

```sh
make psql
```

Équivalent direct :

```sh
docker exec -it camagru-db psql -U camagru -d camagru
```

Invite `camagru=#` = session ouverte. `\q` pour quitter.

### B : Lister les tables

```
\dt
```

`\dt+` ajoute la taille et le propriétaire. `\d <table>` affiche la structure d'une table (colonnes, types, index, contraintes) :

```
\d users
```

### C : Afficher une table

```sql
SELECT * FROM users;
```

Restreindre les colonnes et les lignes plutôt que tout charger :

```sql
SELECT id, username, email, verified FROM users ORDER BY id LIMIT 20;
```

En une ligne sans ouvrir de session (`-c` exécute puis rend la main) :

```sh
docker exec camagru-db psql -U camagru -d camagru -c "SELECT id, username FROM users;"
```
