# Sécurité

## Cookie de session

Configuré dans `camagru/public/index.php` avant `session_start()`.

| Flag | Valeur | Rôle |
|------|--------|------|
| `httponly` | `true` | Cookie inaccessible au JavaScript (limite le vol de session via XSS). |
| `samesite` | `Lax` | Cookie non envoyé sur les requêtes POST cross-site. |
| `secure` | auto | `true` si la requête est en HTTPS (port 443 ou `$_SERVER['HTTPS']`), `false` en HTTP local. |

`session_regenerate_id(true)` est appelé à la connexion (`AuthController::login`) pour empêcher la fixation de session.

## Protection CSRF

Pattern synchronizer token, implémenté dans `camagru/app/Core/Csrf.php`.

- Un jeton unique par session, généré à la première utilisation via `random_bytes(32)`.
- `Csrf::field()` insère le champ caché `csrf_token` dans un formulaire.
- `Csrf::check()` compare le jeton soumis avec `hash_equals()` (temps constant).

La vérification est centralisée dans `Router::dispatch` : toute requête POST sans jeton valide reçoit une réponse `403`. Les nouvelles routes POST sont donc protégées sans code supplémentaire.

Formulaires portant le jeton :

- `camagru/app/Views/auth/login.php`
- `camagru/app/Views/auth/register.php`
- déconnexion, dans `camagru/app/Views/layout.php`

## Déconnexion

`POST /logout` uniquement (`camagru/config/routes.php`). La route `GET /logout` a été retirée pour empêcher une déconnexion forcée via `<img src="/logout">`. Le lien de déconnexion soumet un formulaire POST portant le jeton CSRF.

## Base de données

- Toutes les requêtes passent par des requêtes préparées PDO (`camagru/app/Models/`).
- `PDO::ATTR_EMULATE_PREPARES => false` : préparation réelle côté serveur.
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`.

## Mots de passe

- `password_hash()` avec `PASSWORD_DEFAULT` au stockage.
- `password_verify()` à la connexion.
- Longueur minimale de 8 caractères validée à l'inscription.

## XSS

Toutes les variables interpolées dans les vues sont échappées avec `htmlspecialchars()`.

## Vérification de compte

Inscription suivie d'un email de confirmation contenant un jeton (`random_bytes(32)`). La connexion est refusée tant que le compte n'est pas vérifié (`AuthController::login`).

## Contrôle d'accès

- `Router::requireAuth` : routes réservées aux utilisateurs authentifiés (redirection vers `/login`).
- `Router::requireAdmin` : routes réservées aux administrateurs (réponse `403`).

## Lexique

| Terme | Définition |
|-------|------------|
| CSRF (Cross-Site Request Forgery) | Attaque où un site tiers déclenche une requête vers l'application en réutilisant la session de la victime déjà connectée. |
| Synchronizer token | Défense anti-CSRF : un jeton secret stocké en session est inséré dans chaque formulaire, puis comparé à la soumission. Un site tiers ne connaît pas le jeton. |
| XSS (Cross-Site Scripting) | Injection de code (souvent JavaScript) dans une page, exécuté par le navigateur des autres visiteurs. |
| Session | Ensemble de données côté serveur associé à un visiteur, identifié par un cookie (`PHPSESSID`). |
| Fixation de session | Attaque où l'attaquant impose un identifiant de session connu à la victime. Contrée en régénérant l'identifiant à la connexion. |
| `HttpOnly` | Attribut de cookie interdisant sa lecture par JavaScript. |
| `SameSite` | Attribut de cookie contrôlant son envoi lors de requêtes venant d'un autre site. `Lax` : envoyé sur navigation directe, bloqué sur POST cross-site. |
| `Secure` | Attribut de cookie limitant sa transmission aux connexions HTTPS. |
| HTTPS | HTTP chiffré via TLS. |
| Jeton (token) | Chaîne aléatoire imprévisible servant de preuve (vérification de compte, CSRF). Généré ici avec `random_bytes`. |
| `random_bytes` | Fonction PHP produisant des octets aléatoires cryptographiquement sûrs. |
| `hash_equals` | Comparaison de deux chaînes à temps constant, pour éviter les attaques temporelles. |
| Attaque temporelle | Déduction d'un secret en mesurant le temps de réponse d'une comparaison. |
| `password_hash` / `PASSWORD_DEFAULT` | Hachage de mot de passe avec algorithme robuste (bcrypt) et sel automatique. |
| Hachage | Transformation à sens unique d'une donnée ; un mot de passe haché n'est pas réversible. |
| Sel (salt) | Valeur aléatoire ajoutée avant hachage pour que deux mots de passe identiques produisent des empreintes différentes. |
| PDO (PHP Data Objects) | Couche d'accès aux bases de données de PHP. |
| Requête préparée | Requête SQL où les valeurs sont envoyées séparément du texte de la requête, ce qui empêche l'injection SQL. |
| Injection SQL | Attaque insérant du SQL malveillant via une entrée utilisateur non isolée. |
| `EMULATE_PREPARES` | Option PDO : à `false`, la préparation est réellement effectuée par le serveur de base de données et non simulée par PHP. |
| DSN (Data Source Name) | Chaîne de connexion décrivant la base à joindre (hôte, port, nom). |
| 403 Forbidden | Code HTTP : requête comprise mais accès refusé. |
| 404 Not Found | Code HTTP : ressource inexistante. |
| Redirection | Réponse HTTP (`Location`) demandant au navigateur d'aller vers une autre URL. |
