# Base de données

## 1 : Schéma de la base de données

Le fichier `database/schema.sql` contient le schéma PostgreSQL : création des
tables et des relations entre elles.

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
- `CREATE INDEX` : Crée un index pour accélérer certaines requêtes.

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
- `IF NOT EXISTS` : Conditionne l'exécution à l'absence de l'objet ciblé.
- `IF EXISTS` : Conditionne l'exécution à l'existence de l'objet ciblé.
- `ON DELETE CASCADE` : Propage une suppression aux lignes dépendantes.
- `ON UPDATE CASCADE` : Propage une mise à jour aux lignes dépendantes.

## 2 : Se connecter et inspecter la base

Base PostgreSQL exposée par le service `db` (conteneur `camagru-db`, nom fixé par `DB_CONTAINER` dans `.env`). Le nom de
la base et le rôle viennent de `.env` (`DB_NAME`, `DB_USER`) ; dans le conteneur,
ils sont dans `POSTGRES_DB` et `POSTGRES_USER`.

### A : Connexion

Via le Makefile :

```sh
make psql
```

Équivalent direct :

```sh
# Connexion locale dans le conteneur : aucun mot de passe demandé
docker exec -it camagru-db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
```

Invite `camagru=#` : session ouverte. `\q` pour quitter.

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

Avec restriction des colonnes et des lignes :

```sql
SELECT id, username, email, verified FROM users ORDER BY id LIMIT 20;
```

En une ligne sans ouvrir de session (`-c` exécute puis rend la main) :

```sh
docker exec camagru-db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "SELECT id, username FROM users;"'
```

## 3 : Lire et écrire depuis une page

### A : Connexion

`Core/Database` ouvre une connexion unique pour toute la requête HTTP :

```php
self::$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```

| Option | Effet |
|--------|-------|
| `ERRMODE_EXCEPTION` | une erreur SQL lève une `PDOException` |
| `FETCH_ASSOC` | les lignes arrivent en tableaux indexés par nom de colonne |
| `EMULATE_PREPARES => false` | la préparation est faite par PostgreSQL |

Aucun contrôleur ni aucune vue n'appelle `Database::pdo()` : le constructeur de
`Core/Model` récupère la connexion, et les modèles en héritent.

```php
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }
}
```

### B : Requête préparée

Les valeurs ne sont jamais concaténées dans le SQL. Marqueurs nommés, valeurs
passées à `execute()` :

```php
$stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username');
$stmt->execute(['username' => $username]);
```

Seules les valeurs sont paramétrables. Un nom de table ou de colonne ne peut pas
l'être ; s'il varie, il est choisi dans une liste fermée écrite dans le code,
jamais lu dans la requête HTTP :

```php
$colonnes = ['created_at', 'username'];
$tri = in_array($demande, $colonnes, true) ? $demande : 'created_at';
$stmt = $this->db->prepare("SELECT ... ORDER BY {$tri} DESC");
```

### C : Types des paramètres

`pdo_pgsql` transmet chaque paramètre sous forme de texte, sans type déclaré.
PostgreSQL déduit le type du contexte (colonne comparée, `LIMIT`, `OFFSET`) et
convertit le texte. Un entier passé à `execute()` arrive ainsi en `'6'` et
devient un entier côté serveur, `LIMIT` et `OFFSET` compris ; `null` est
transmis comme `NULL`.

`execute([...])` lie chaque valeur en chaîne, sauf `null`. Un booléen PHP y
devient `'1'` pour `true` et `''` pour `false` ; PostgreSQL accepte `'1'` comme
booléen et refuse la chaîne vide. Un booléen se lie donc par `bindValue()` avec
`PDO::PARAM_BOOL`, que le pilote transmet en `'t'` ou `'f'` :

```php
$stmt->bindValue('actif', $actif, PDO::PARAM_BOOL);
$stmt->bindValue('id', $id, PDO::PARAM_INT);
$stmt->execute();
```

Avec `pdo_pgsql`, `PDO::PARAM_INT` et `PDO::PARAM_NULL` produisent le même envoi
que `execute()` : ils indiquent le type attendu sans modifier la requête
transmise. Une requête qui lie une valeur par `bindValue()` lie toutes les
autres de la même façon, puis appelle `execute()` sans argument : un tableau
passé à `execute()` remplace les liaisons faites auparavant.

### D : Récupérer le résultat

| Méthode | Retour | Usage |
|---------|--------|-------|
| `fetch()` | une ligne, ou `false` | recherche par identifiant |
| `fetchAll()` | toutes les lignes | liste |
| `fetchColumn()` | la première colonne de la première ligne | compte, identifiant, nom de fichier |
| `rowCount()` | nombre de lignes touchées | savoir si un `INSERT`/`DELETE` a fait quelque chose |

`fetch()` renvoie `false` en l'absence de ligne ; les modèles convertissent
cette valeur en `null` :

```php
return $stmt->fetch() ?: null;
```

`RETURNING` évite un aller-retour après une insertion :

```php
$stmt = $this->db->prepare(
    'INSERT INTO xxx (user_id, nom) VALUES (:user_id, :nom) RETURNING id'
);
$stmt->execute(['user_id' => $userId, 'nom' => $nom]);

return (int) $stmt->fetchColumn();
```

### E : Types des résultats

En PHP 8.3, version de l'image `web`, `pdo_pgsql` convertit trois types de
colonnes :

| Type PostgreSQL | Valeur PHP |
|-----------------|------------|
| `boolean` | `bool` |
| `smallint`, `integer`, `bigint` (PHP 64 bits) | `int` |
| `bytea` | flux (`resource`) |

Les autres types (`text`, `varchar`, `numeric`, `timestamptz`) arrivent en
chaînes. Un booléen se teste donc directement (`if ($user['verified'])`), et un
`count(*)`, de type `bigint`, arrive en entier.

`Core/Pg::bool()` accepte aussi `'t'` et `'1'`, formes que renvoient les
fonctions `pg_fetch_*` de l'extension `pgsql`, mais que `pdo_pgsql` ne produit
pas.

### F : Compteurs et drapeaux

Un compteur associé à chaque ligne (nombre d'éléments liés) s'écrit en
sous-requête corrélée, une par colonne :

```sql
SELECT p.id, p.nom,
       (SELECT count(*) FROM enfants e WHERE e.parent_id = p.id) AS nb_enfants
FROM parents p
```

Avec plusieurs compteurs, un `GROUP BY` sur plusieurs jointures multiplie les
lignes avant le comptage : chaque compteur est alors faux, sauf à écrire
`count(DISTINCT ...)`. Les sous-requêtes restent indépendantes.

Un drapeau (l'utilisateur courant a-t-il une ligne liée ?) s'écrit avec
`EXISTS`, qui s'arrête à la première ligne trouvée là où `count(*)` les parcourt
toutes. `EXISTS (...)` rend un `boolean`, reçu en `bool` PHP.

`JOIN` convient quand la ligne liée existe toujours, `LEFT JOIN` quand elle peut
manquer, par exemple quand la clé étrangère est en `ON DELETE SET NULL` : les
colonnes de la table jointe valent alors `NULL`.

### G : Liste d'identifiants

`IN` attend une liste de valeurs : la requête porte autant de marqueurs que de
valeurs, générés puis passés en positionnel.

```php
$marques = implode(',', array_fill(0, count($ids), '?'));
$stmt = $this->db->prepare("SELECT ... WHERE parent_id IN ({$marques})");
$stmt->execute($ids);
```

Une liste vide produit `IN ()`, refusé par PostgreSQL : la méthode renvoie un
tableau vide sans exécuter la requête.

Une seule requête couvre ainsi tous les éléments d'une page. Le regroupement par élément se fait ensuite en PHP.

### H : Pagination

Le modèle fournit le total (`count(*)`) et une tranche (`LIMIT`, `OFFSET`). Le
contrôleur calcule les bornes et ramène le numéro de page demandé dans
l'intervalle valide :

```php
$parPage = max(1, (int) Settings::get('xxx.per_page'));
$pages   = max(1, (int) ceil($total / $parPage));
$page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
$offset  = ($page - 1) * $parPage;
```

Une valeur absurde dans `$_GET['page']` donne la première ou la dernière page,
jamais une erreur SQL.

Le tri d'une liste paginée porte sur une combinaison unique de colonnes, par
exemple `ORDER BY created_at DESC, id DESC`. Avec un tri sur une seule colonne
non unique, l'ordre des ex aequo est indéterminé, et une même ligne peut
apparaître sur deux pages.

### I : Suppressions en cascade

Le comportement d'une suppression se déclare dans le schéma, sur chaque clé
étrangère :

| Clause | Effet sur les lignes qui référencent la ligne supprimée |
|--------|-----------------------------------------------------------|
| `ON DELETE CASCADE` | supprimées |
| `ON DELETE SET NULL` | conservées, la colonne passe à `NULL` (colonne sans `NOT NULL`) |
| aucune | la suppression échoue tant qu'il en reste |

Le code PHP ne supprime donc pas lui-même les lignes dépendantes. `RETURNING`
rend dans la même requête les colonnes de la ligne supprimée, par exemple un nom
de fichier à effacer ensuite du disque :

```sql
DELETE FROM xxx WHERE id = :id RETURNING filename
```

### J : Contrôle de propriété

La propriété d'une ligne se vérifie dans la clause `WHERE` de la requête qui
la modifie :

```sql
DELETE FROM xxx WHERE id = :id AND user_id = :user_id RETURNING filename
```

Si la ligne n'existe pas ou appartient à un autre compte, la requête ne touche
rien et ne renvoie rien ; la méthode rend `null`. Le contrôleur ne compare pas
lui-même de `user_id`, et aucune fenêtre ne sépare la vérification de
l'écriture.

### K : Index et contraintes particuliers

Un index porte sur des colonnes, ou sur une expression calculée à partir
d'elles. Déclaré `UNIQUE`, il interdit deux lignes qui donnent la même valeur.

| Forme | Exemple | Effet |
|-------|---------|-------|
| index sur expression | `CREATE UNIQUE INDEX ON xxx (lower(nom))` | `Nom` et `nom` sont des doublons ; une requête en `WHERE lower(nom) = lower(:nom)` utilise l'index |
| paire non ordonnée | `CREATE UNIQUE INDEX ON paires (least(a, b), greatest(a, b))` | `(1, 2)` et `(2, 1)` donnent la même clé : une seule ligne par paire, quel que soit le sens |
| index partiel | `CREATE INDEX ON xxx (user_id) WHERE traite_le IS NULL` | ne contient que les lignes de la condition ; plus petit, utilisé par les requêtes qui reprennent la même condition |
| contrainte `CHECK` | `CHECK (a <> b)` | refuse à l'écriture toute ligne qui ne vérifie pas l'expression |

Une requête n'utilise un index sur expression que si elle contient la même
expression : `WHERE nom = :nom` ne s'en sert pas.

### L : Recherche par motif

`LIKE` compare à un motif où `%` remplace une suite de caractères et `_` un seul
caractère ; `ILIKE`, propre à PostgreSQL, ignore la casse. Une saisie
utilisateur insérée dans un motif est d'abord échappée, sinon un `_` ou un `%`
tapé par l'utilisateur agit comme joker :

```php
$motif = '%' . addcslashes($saisie, '\\%_') . '%';
$stmt = $this->db->prepare('SELECT ... WHERE nom ILIKE :motif');
$stmt->execute(['motif' => $motif]);
```

`\` est le caractère d'échappement par défaut de `LIKE` en PostgreSQL.

### M : Erreurs

Avec `ERRMODE_EXCEPTION`, une violation de contrainte lève une `PDOException`.
Les contraintes courantes (unicité d'un pseudo, longueur) sont validées en PHP
avant l'écriture, pour produire un message lisible ; l'exception couvre ce qui a
échappé à la validation. `display_errors` est à
`Off` côté conteneur, la trace part dans le log Apache.

### N : Ajouter une méthode de modèle

1. Choisir le modèle de la table principale de la requête dans
   `app/Models/`, ou créer une classe `final` qui hérite de `Core\Model`.
2. Écrire une méthode publique typée, nommée d'après ce qu'elle fait.
3. Préparer la requête avec des marqueurs nommés ; lier par `bindValue()` les
   entiers de `LIMIT` et `OFFSET` et les valeurs qui peuvent être `null`.
4. Rendre un résultat normalisé : `fetch() ?: null`, `fetchAll()`,
   `(int) fetchColumn()`, ou un booléen tiré de `rowCount()`.
5. Appeler la méthode depuis le contrôleur.
