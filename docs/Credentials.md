# Gestion des credentials

Deux familles de credentials :

| Famille | Contenu | Emplacement |
|---------|---------|-------------|
| Infrastructure | rôle PostgreSQL, compte admin d'installation | `.env`, ignoré par git |
| Utilisateur | mots de passe des comptes, jetons de mail | table `users`, table `password_resets` |

Aucun credential n'est écrit dans le dépôt : ni dans `.env.example`, où ils sont
laissés vides, ni dans `config/settings.php`, ni dans `database/schema.sql`. La
feuille d'évaluation exige qu'ils soient dans `.env`.

## 1 : Credentials d'infrastructure

| Variable de `.env` | Usage |
|--------------------|-------|
| `DB_USER`, `DB_PASSWORD` | rôle PostgreSQL avec lequel l'application se connecte |
| `ADMIN_USER`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | compte admin Camagru, sans rapport avec le rôle PostgreSQL |

`make up` lance d'abord `scripts/check-env.sh`, qui arrête la commande si `.env`
manque ou si une variable requise est vide : noms des conteneurs, rôle
PostgreSQL, compte admin.

`web` reçoit tout `.env` dans son environnement (`env_file`) ; `config.php` lit
les variables par `getenv()` et arrête le démarrage sur une variable absente ou
vide :

```php
define('DB_USER', $env('DB_USER'));
define('DB_PASS', $env('DB_PASSWORD'));
```

`db` ne reçoit que `POSTGRES_DB`, `POSTGRES_USER` et `POSTGRES_PASSWORD`,
interpolés par compose depuis `DB_NAME`, `DB_USER` et `DB_PASSWORD`. Ces valeurs
sont visibles dans l'environnement des conteneurs, donc dans un `docker inspect`.

Le compte admin est créé une seule fois, à l'initialisation de la base, par
`database/admin.sh`, que l'entrypoint PostgreSQL joue après le schéma.
`db` reçoit pour cela `ADMIN_USER`, `ADMIN_EMAIL` et `ADMIN_PASSWORD`. Le script
insère le compte, vérifié, et sa ligne dans `admins` ; pgcrypto hache le mot de
passe en bcrypt (`crypt(…, gen_salt('bf', 10))`, préfixe `$2a$`), que
`password_verify` vérifie comme un hash de `password_hash`.

## 2 : Politique de mot de passe

Le seul paramètre de la politique, `auth.password_min_length`, est dans
`config/settings.php` :

```php
'auth' => [
    'password_min_length' => 8,
    'token_bytes'         => 32,
    'verification_ttl'    => 86400,
    'password_reset_ttl'  => 86400,
],
```

Les règles sont dans `Core/Password::errors()`, qui renvoie la liste des
contraintes non satisfaites :

| Règle | Contrôle |
|-------|----------|
| Longueur | `strlen($password) >= auth.password_min_length` |
| Au moins une lettre | `preg_match('/[A-Za-z]/', $password)` |
| Au moins un chiffre | `preg_match('/\d/', $password)` |

```php
$errors = array_merge($errors, Password::errors($password));
```

`Password::errors()` a trois appelants : `AuthController::register`,
`PasswordController::reset` (qui ajoute l'égalité avec la confirmation) et
`PrefsController::account` (qui n'appelle que si un nouveau mot de passe est
fourni).

Côté vue, `minlength`, `pattern` (généré par `Password::pattern()`, mêmes
classes ASCII que le serveur) et un rappel de la règle sous le champ. L'attribut HTML ne
vaut que pour l'affichage : un POST peut arriver sans passer par la page. La
longueur est lue depuis `settings.php` des deux côtés,
pour que le message affiché et le contrôle serveur ne divergent pas.

Hors périmètre : liste de mots de passe interdits, historique, expiration.

## 3 : Stockage

```php
password_hash($password, PASSWORD_DEFAULT)
```

`PASSWORD_DEFAULT` désigne bcrypt. Le coût par défaut est 10 en PHP 8.3,
version de l'image `web`, et 12 à partir de PHP 8.4.

Chaque appel de `password_hash()` tire un sel aléatoire de 16 octets, propre au
hash produit. Deux comptes ayant le même mot de passe obtiennent des hash
différents, et un changement de mot de passe produit un nouveau sel.

Le sel est écrit en clair dans la chaîne produite, avec l'algorithme et le
coût, et l'ensemble est stocké dans `users.password`.

```
$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy
 2y                      algorithme : bcrypt
    10                   coût : 2^10 itérations
       N9qo8uLOickgx2ZMRZoMye     sel, 22 caractères
                             IjZAgcfl7p92ldGxad68LJZdL17lhWy   hash, 31 caractères
```

Le sel rend inutilisables les tables d'empreintes précalculées et oblige à
attaquer chaque hash séparément. La résistance à la force brute vient du coût.

### Coût

bcrypt répète son calcul interne 2^coût fois : chaque point de coût double la
durée d'un hash. Mesures sur un poste de 42, un cœur, PHP 8.5 :

| Coût | Itérations | Durée d'un hash |
|------|------------|-----------------|
| 8 | 256 | 20 ms |
| 10 | 1 024 | 47 ms |
| 12 | 4 096 | 186 ms |
| 14 | 16 384 | 745 ms |

Le serveur calcule un hash par connexion : 47 ms passent inaperçus pour
l'utilisateur.

Un attaquant qui a copié la table `users` possède les hash. Un hash ne se
déchiffre pas : l'attaquant prend un mot de passe candidat, le hache avec le sel
et le coût lus dans la chaîne, compare le résultat au hash stocké, puis passe au
candidat suivant. Chaque essai lui coûte la durée d'un hash. Pour un
dictionnaire d'un milliard de candidats, sur un cœur :

| Fonction | Durée d'un essai | Un milliard d'essais |
|----------|------------------|----------------------|
| SHA-256 | 0,14 µs | 2 min 20 s |
| bcrypt, coût 10 | 47 ms | 1 an et 6 mois |
| bcrypt, coût 12 | 186 ms | 5 ans et 11 mois |

Ces durées valent pour un seul compte : le sel propre à chaque hash oblige à
recommencer pour le compte suivant. Plusieurs machines ou des cartes graphiques
les divisent ; bcrypt limite le gain des cartes graphiques par ses accès
répétés à une table de 4 Kio en mémoire.

Le coût se choisit le plus haut possible tant qu'une connexion reste rapide et
que le serveur tient la charge. PHP l'a porté de 10 à 12 en version 8.4, pour
suivre la vitesse des processeurs.

Le coût est écrit dans chaque hash : `password_verify()` vérifie un ancien hash
avec son propre coût, et relever le coût laisse les comptes existants
fonctionner. `password_needs_rehash($hash, PASSWORD_DEFAULT)` indique, à la
connexion, qu'un hash a été produit avec d'autres paramètres ; le mot de passe
saisi permet alors de le recalculer.

Une valeur secrète commune à tous les mots de passe, gardée hors de la base et
ajoutée avant le hachage, s'appelle un poivre (*pepper*). Le projet n'en
utilise pas.

La colonne `users.password` est en `VARCHAR(255)` : assez large pour une sortie
bcrypt de 60 caractères et pour un changement d'algorithme par défaut dans une
version ultérieure de PHP.

Le mot de passe en clair n'est jamais écrit, ni en base, ni en log, ni en
session. La session ne porte que `id`, `username` et `is_admin`.

## 4 : Vérification à la connexion

L'identifiant de connexion est le pseudo. `findByUsername()` compare en
minuscules (`lower(username) = lower(:username)`) : `Alice` se connecte aussi
en tapant `alice`. Un index unique sur `lower(username)` interdit deux pseudos
qui ne diffèrent que par la casse.

Les règles du pseudo sont dans `Core/Username` : 3 à 50 caractères parmi
`A-Z a-z 0-9 _ . -`, le premier étant une lettre ou un chiffre.
`Username::errors()` les applique côté serveur (inscription, préférences,
`/register/available`) et `Username::pattern()` en donne la version HTML.

```php
$user = (new User())->findByUsername($username);

if ($user === null || !password_verify($password, $user['password'])) {
    // 'Invalid credentials.'
}
```

L'adresse ne sert qu'aux envois de mail et à la demande de réinitialisation.
`Email::normalize()` la passe en minuscules à chaque lecture d'un formulaire
(inscription, préférences, mot de passe oublié) : une boîte mail correspond à un
seul compte, quelle que soit la casse saisie.

`password_verify` relit le sel et le coût depuis le hash stocké, et compare en
temps constant. Un compte inexistant et un mot de passe faux produisent le même
message : un visiteur ne peut pas en déduire si le pseudo existe.

Après authentification, deux contrôles s'appliquent :

| Contrôle | Message |
|----------|---------|
| `verified` faux | `Account not verified. Check your email to activate it.` |
| `suspended` vrai | `This account is suspended.` |

Puis `session_regenerate_id(true)` avant d'écrire `$_SESSION['user']`.

## 5 : Changement de mot de passe par un utilisateur connecté

`PrefsController::account` exige le mot de passe courant avant toute
modification d'identité ou de mot de passe :

```php
if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $user['password'])) {
    $errors[] = 'Current password is wrong.';
}
```

Le champ nouveau mot de passe est facultatif : laissé vide, seuls le pseudo et
l'adresse sont mis à jour.

## 6 : Jetons de mail

Les deux jetons sont générés de la même façon :

```php
$token = bin2hex(random_bytes((int) Settings::get('auth.token_bytes')));
```

32 octets du générateur cryptographique, rendus en 64 caractères hexadécimaux.

| Usage | Stockage | Durée | Invalidation |
|-------|----------|-------|--------------|
| Vérification de compte | `users.verification_token`, en clair | `verification_ttl`, 86400 s | `markVerified()` met la colonne à `NULL` |
| Réinitialisation | `password_resets.token_hash`, SHA-256 du jeton | `password_reset_ttl`, 86400 s | `markUsed()` pose `used_at` |

Le jeton de réinitialisation n'est jamais stocké tel quel : la base garde
`hash('sha256', $token)`, et la recherche hashe la valeur reçue avant de
comparer. Une lecture de la table ne permet donc pas de forger un lien.

`PasswordReset::create()` appelle d'abord `deleteForUser()` : un compte n'a
qu'une demande en cours, et une nouvelle demande remplace la précédente.

`findValid()` combine trois conditions :

```sql
WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > now()
```

Les durées sont calculées par PostgreSQL (`now() + make_interval(secs => :ttl)`) :
l'horloge de référence est celle de la base.

## 7 : Énumération de comptes

`PasswordController::sendReset` répond la même phrase que l'adresse existe ou
non :

```php
private const CONFIRMATION = 'If an account matches this address, a reset link has been sent.';
```

Le mail n'est envoyé que si le compte existe, mais la réponse HTTP est
identique. Même principe au login avec `Invalid credentials.`

L'inscription distingue le cas : `An account already exists with
this email or username.` L'information est nécessaire pour que l'utilisateur
comprenne le refus ; elle confirme en contrepartie qu'une adresse est inscrite.

## 8 : Écarts connus

- `users.verification_token` est stocké en clair, contrairement au jeton de
  réinitialisation. Une lecture de la table permet d'activer un compte en
  attente. Portée limitée : le jeton devient `NULL` à la vérification.
- Aucune limitation du nombre de tentatives de connexion. Rien ne ralentit un
  essai automatisé sur `/login`, hors le coût de bcrypt.
- Le coût bcrypt est celui de `PASSWORD_DEFAULT`, soit 10. Le relever se fait
  par un troisième argument de `password_hash`.
