# Base de données

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

Base PostgreSQL exposée par le service `db` (conteneur `camagru-db`). Le nom de
la base est dans `.env` (`DB_NAME`), le rôle dans `secrets/db_user` : le
Makefile lit les deux pour construire la commande.

### A : Connexion

Via le Makefile :

```sh
make psql
```

Équivalent direct :

```sh
# Être déjà à l'intérieur du conteneur vaut authentification
docker exec -it camagru-db psql -U "$(cat secrets/db_user)" -d camagru
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

Restreindre les colonnes et les lignes plutôt que tout charger :

```sql
SELECT id, username, email, verified FROM users ORDER BY id LIMIT 20;
```

En une ligne sans ouvrir de session (`-c` exécute puis rend la main) :

```sh
docker exec camagru-db psql -U "$(cat secrets/db_user)" -d camagru -c "SELECT id, username FROM users;"
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
| `ERRMODE_EXCEPTION` | une erreur SQL lève une `PDOException` au lieu de passer inaperçue |
| `FETCH_ASSOC` | les lignes arrivent en tableaux associatifs, pas en doublons index + nom |
| `EMULATE_PREPARES => false` | la préparation est faite par PostgreSQL, pas simulée par PHP |

Aucun contrôleur ni aucune vue n'appelle `Database::pdo()` : c'est le
constructeur de `Core/Model` qui la récupère, et les modèles en héritent.

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
l'être ; s'il varie, il vient d'une liste fermée écrite dans le code :

```php
foreach (['likes', 'comments', 'reports', 'images'] as $table) {
    $colonne = $table === 'images' ? 'id' : 'image_id';
    $this->db->prepare("DELETE FROM {$table} WHERE {$colonne} = :id")->execute(['id' => $id]);
}
```

### C : bindValue quand le type compte

`execute([...])` envoie tout en chaîne. PostgreSQL refuse une chaîne là où il
attend un entier : `LIMIT` et `OFFSET` demandent donc `bindValue()` avec le
type.

```php
$stmt->bindValue('viewer', $viewerId, $viewerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
$stmt->bindValue('limit', $limit, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
```

Un paramètre qui peut être `null` se lie en `PARAM_NULL` : c'est le cas du
lecteur anonyme dans la galerie.

### D : Récupérer le résultat

| Méthode | Retour | Usage |
|---------|--------|-------|
| `fetch()` | une ligne, ou `false` | recherche par identifiant |
| `fetchAll()` | toutes les lignes | liste |
| `fetchColumn()` | la première colonne de la première ligne | compte, identifiant, nom de fichier |
| `rowCount()` | nombre de lignes touchées | savoir si un `INSERT`/`DELETE` a fait quelque chose |

`fetch()` renvoie `false` et non `null` quand il n'y a rien : les modèles
normalisent avant de rendre la main.

```php
return $stmt->fetch() ?: null;
```

`RETURNING` évite un aller-retour après une insertion :

```php
$stmt = $this->db->prepare(
    'INSERT INTO images (user_id, filename) VALUES (:user_id, :filename) RETURNING id'
);
$stmt->execute(['user_id' => $userId, 'filename' => $filename]);

return (int) $stmt->fetchColumn();
```

### E : Booléens

`pdo_pgsql` rend les booléens sous forme de chaînes `'t'` et `'f'`. `'f'` est
une chaîne non vide, donc vraie en PHP : un test direct est toujours vrai.

```php
if ($user['verified']) { ... }              // vrai même pour 'f'
if (Pg::bool($user['verified'])) { ... }    // correct
```

`Core/Pg::bool()` accepte `true`, `'t'`, `'1'` et `1`.

### F : Compteurs et drapeaux

Les compteurs de la galerie sont des sous-requêtes, une par colonne, plutôt
qu'un `GROUP BY` sur trois jointures :

```sql
SELECT i.id, i.filename, i.created_at, i.user_id, u.username,
       (SELECT count(*) FROM likes l WHERE l.image_id = i.id)    AS likes,
       (SELECT count(*) FROM comments c WHERE c.image_id = i.id) AS comments,
       CAST(EXISTS (SELECT 1 FROM likes l
                    WHERE l.image_id = i.id
                      AND l.user_id = :viewer) AS INTEGER)       AS liked
FROM images i JOIN users u ON u.id = i.user_id
ORDER BY i.created_at DESC, i.id DESC
LIMIT :limit OFFSET :offset
```

`EXISTS` s'arrête à la première ligne trouvée, là où un `count(*)` les parcourt
toutes. Le `CAST(... AS INTEGER)` évite le problème des booléens de la section
précédente : la vue teste `(int) $image['liked'] === 1`.

`JOIN` quand la ligne liée est garantie, `LEFT JOIN` quand elle peut manquer.
Un compte supprimé laisse ses commentaires derrière lui, sans auteur :

```sql
SELECT c.image_id, c.comment, c.created_at, u.username
FROM comments c LEFT JOIN users u ON u.id = c.user_id
WHERE c.image_id IN (?,?,?)
```

### G : Liste d'identifiants

`IN` ne prend pas un tableau : il faut autant de marqueurs que de valeurs,
générés puis passés en positionnel.

```php
$marques = implode(',', array_fill(0, count($imageIds), '?'));
$stmt = $this->db->prepare("... WHERE c.image_id IN ({$marques}) ...");
$stmt->execute($imageIds);
```

Une requête par page de galerie, pas une par montage : les commentaires sont
ensuite regroupés par image en PHP.

### H : Pagination

Le modèle fournit le total et la tranche, le contrôleur calcule les bornes :

```php
$parPage = max(1, (int) Settings::get('gallery.per_page', 6));
$total   = $images->count();
$pages   = max(1, (int) ceil($total / $parPage));
$page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

$liste = $images->page($parPage, ($page - 1) * $parPage, $this->viewerId());
```

`$_GET['page']` est borné des deux côtés : une valeur absurde donne la première
ou la dernière page, jamais une erreur SQL.

Le tri porte sur deux colonnes, `ORDER BY i.created_at DESC, i.id DESC` : deux
montages créés dans la même seconde auraient sinon un ordre indéterminé, et
certaines lignes apparaîtraient deux fois d'une page à l'autre.

### I : Transactions

Plusieurs écritures qui doivent tenir ou échouer ensemble :

```php
$this->db->beginTransaction();

try {
    // ... plusieurs requêtes
    $this->db->commit();
} catch (Throwable $e) {
    $this->db->rollBack();
    throw $e;
}
```

`SELECT ... FOR UPDATE` verrouille la ligne lue jusqu'à la fin de la
transaction, pour qu'une suppression concurrente ne passe pas entre la
vérification et l'effacement :

```sql
SELECT filename FROM images WHERE id = :id AND user_id = :user_id FOR UPDATE
```

### J : Contrôle de propriété

La vérification se fait dans la clause `WHERE`, pas dans une condition PHP
après coup :

```php
public function delete(int $id, int $userId): ?string
```

Sans ligne correspondante, la requête ne renvoie rien et la méthode rend `null`.
Le contrôleur n'a pas à comparer un `user_id` lui-même.

### K : Erreurs

Avec `ERRMODE_EXCEPTION`, une violation de contrainte lève une `PDOException`.
Elle ne sert pas de message utilisateur : les contraintes courantes (unicité
d'un pseudo, longueur) sont validées en PHP avant l'écriture, et l'exception
reste le filet pour ce qui a échappé à la validation. `display_errors` est à
`Off` côté conteneur, la trace part dans le log Apache.

### L : Trajet complet

```
GalleryController::gallery()
    Image::count()            total des montages
    Image::page()             une tranche, triée, avec compteurs et drapeaux
    Comment::forImages()      les commentaires des identifiants de la page
    $this->view('gallery', [...])
        la vue lit $images, $commentaires, $page, $pages
        et échappe chaque valeur affichée
```

Aucune requête n'est émise depuis la vue : tout ce qu'elle affiche a été
préparé par le contrôleur.
