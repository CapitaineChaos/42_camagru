# Gestion des credentials

Deux familles, traitées séparément :

| Famille | Contenu | Emplacement |
|---------|---------|-------------|
| Infrastructure | rôle PostgreSQL, compte admin d'installation | `secrets/`, un fichier par valeur |
| Utilisateur | mots de passe des comptes, jetons de mail | table `users`, table `password_resets` |

Aucun credential n'est écrit dans `.env`, dans `config/settings.php`, ni dans
`database/schema.sql`.

## 1 : Secrets d'infrastructure

Un fichier par valeur, sous `secrets/`, ignoré par git :

| Fichier | Usage |
|---------|-------|
| `db_user`, `db_password` | rôle PostgreSQL avec lequel l'application se connecte |
| `admin_user`, `admin_email`, `admin_password` | compte admin Camagru, sans rapport avec le rôle PostgreSQL |

Lecture par `Core/Secret::read()`, qui cherche `/run/secrets/<nom>` puis
`secrets/<nom>` à la racine du dépôt, rejette un nom hors `[a-z0-9_]`, coupe le
retour à la ligne final et lève si le fichier manque ou est vide.

```php
define('DB_USER', \App\Core\Secret::read('db_user'));
define('DB_PASS', \App\Core\Secret::read('db_password'));
```

`docker-compose.yml` monte `./secrets` sur `/run/secrets` en lecture seule dans
`web` et `db`. PostgreSQL lit `POSTGRES_USER_FILE` et `POSTGRES_PASSWORD_FILE` :
le mot de passe n'apparaît pas dans l'environnement du conteneur, donc pas dans
un `inspect`.

`make secrets` demande les identifiants et tire les deux mots de passe
(`openssl rand -base64`). Un fichier déjà rempli n'est pas remplacé.

Permissions : `db_user` et `db_password` en 644, lus par l'uid 70 du conteneur
PostgreSQL ; les `admin_*` en 600, lus par PHP qui tourne sous l'uid de
l'utilisateur.

Le compte admin est créé par `database/admin.php` (`make admin`), qui hashe le
mot de passe côté PHP et fait un `ON CONFLICT (email) DO UPDATE` : relancer le
script change le mot de passe au lieu d'échouer.

## 2 : Politique de mot de passe

Une seule valeur, dans `config/settings.php` :

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
| Au moins une lettre | `preg_match('/\p{L}/u', $password)` |
| Au moins un chiffre | `preg_match('/\d/', $password)` |

```php
$errors = array_merge($errors, Password::errors($password));
```

Trois appelants, une seule politique : `AuthController::register`,
`PasswordController::reset` (qui ajoute l'égalité avec la confirmation) et
`PrefsController::account` (qui n'appelle que si un nouveau mot de passe est
fourni).

Côté vue, `minlength` et un rappel de la règle sous le champ. L'attribut HTML ne
vaut que pour l'affichage : un POST peut arriver sans passer par la page. La
longueur est lue depuis `settings.php` des deux côtés, jamais écrite en dur,
pour que le message affiché et le contrôle serveur ne divergent pas.

Hors périmètre : liste de mots de passe interdits, historique, expiration.

## 3 : Stockage

```php
password_hash($password, PASSWORD_DEFAULT)
```

`PASSWORD_DEFAULT` désigne bcrypt, coût 10, sel généré automatiquement et
embarqué dans la chaîne produite. La colonne `users.password` est en
`VARCHAR(255)` : assez large pour une sortie bcrypt de 60 caractères et pour un
changement d'algorithme par défaut dans une version ultérieure de PHP.

Le mot de passe en clair n'est jamais écrit, ni en base, ni en log, ni en
session. La session ne porte que `id`, `username` et `is_admin`.

## 4 : Vérification à la connexion

L'identifiant de connexion est le pseudo, pas l'adresse :

```php
$user = (new User())->findByUsername($username);

if ($user === null || !password_verify($password, $user['password'])) {
    // 'Invalid credentials.'
}
```

L'adresse ne sert qu'aux envois de mail et à la demande de réinitialisation.

`password_verify` relit le sel et le coût depuis le hash stocké, et compare en
temps constant. Un compte inexistant et un mot de passe faux produisent le même
message, ce qui ne dit pas à un visiteur si le pseudo est pris.

Deux contrôles suivent, après authentification réussie :

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

Deux usages, même génération :

```php
$token = bin2hex(random_bytes((int) Settings::get('auth.token_bytes', 32)));
```

32 octets du générateur cryptographique, rendus en 64 caractères hexadécimaux.

| Usage | Stockage | Durée | Invalidation |
|-------|----------|-------|--------------|
| Vérification de compte | `users.verification_token`, en clair | `verification_ttl`, 86400 s | `markVerified()` met la colonne à `NULL` |
| Réinitialisation | `password_resets.token_hash`, SHA-256 du jeton | `password_reset_ttl`, 86400 s | `markUsed()` pose `used_at` |

Le jeton de réinitialisation n'est jamais stocké tel quel : la base garde
`hash('sha256', $token)`, et la recherche hashe la valeur reçue avant de
comparer. Une lecture de la table ne permet donc pas de forger un lien.

`PasswordReset::create()` appelle `deleteForUser()` d'abord : une seule demande
vivante par compte, une nouvelle demande annule la précédente.

`findValid()` filtre sur trois conditions à la fois :

```sql
WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > now()
```

Les durées sont calculées côté base (`now() + make_interval(secs => :ttl)`),
pas côté PHP : l'horloge de référence est celle de PostgreSQL.

## 7 : Énumération de comptes

`PasswordController::sendReset` répond la même phrase que l'adresse existe ou
non :

```php
private const CONFIRMATION = 'If an account matches this address, a reset link has been sent.';
```

Le mail n'est envoyé que si le compte existe, mais la réponse HTTP est
identique. Même principe au login avec `Invalid credentials.`

L'inscription, elle, distingue explicitement : `An account already exists with
this email or username.` L'information est nécessaire pour que l'utilisateur
comprenne le refus ; elle confirme en contrepartie qu'une adresse est inscrite.

## 8 : Écarts connus

- `users.verification_token` est stocké en clair, contrairement au jeton de
  réinitialisation. Une lecture de la table permet d'activer un compte en
  attente. Portée limitée : le jeton devient `NULL` à la vérification.
- Aucune limitation du nombre de tentatives de connexion. Rien ne ralentit un
  essai automatisé sur `/login`, hors le coût de bcrypt.
- `make hash PASS='...'` fait passer un mot de passe en clair par la ligne de
  commande, donc par l'historique du shell.
- Le coût bcrypt est celui de `PASSWORD_DEFAULT`, soit 10. Le relever se fait
  par un troisième argument de `password_hash`.
