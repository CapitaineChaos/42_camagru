# Lexique

Termes regroupés par thème.

## Sessions et cookies

| Terme | Définition |
|-------|------------|
| Session | Ensemble de données côté serveur associé à un visiteur, identifié par un cookie. |
| Identifiant de session | Chaîne aléatoire désignant la session ; seule donnée qui circule, le contenu reste sur le serveur. |
| Cookie | Petite valeur déposée par le serveur et renvoyée par le navigateur à chaque requête sur le même domaine. |
| `PHPSESSID` | Nom par défaut du cookie de session en PHP ; remplacé ici par `camagru_session`. |
| `HttpOnly` | Attribut de cookie interdisant sa lecture par JavaScript. |
| `SameSite` | Attribut de cookie contrôlant son envoi lors de requêtes venant d'un autre site. `Lax` : envoyé sur navigation directe, bloqué sur POST cross-site. |
| `Secure` | Attribut de cookie limitant sa transmission aux connexions HTTPS. |
| Cookie de session | Cookie sans date d'expiration (`lifetime = 0`), effacé à la fermeture du navigateur. |
| Vol de session | Usurpation obtenue en récupérant l'identifiant de session d'un autre utilisateur. |
| Fixation de session | Attaque où l'attaquant impose un identifiant de session connu à la victime. Contrée en régénérant l'identifiant à la connexion. |
| `session_regenerate_id` | Fonction PHP qui remplace l'identifiant de session ; avec `true`, l'ancien est supprimé. |
| Rotation d'identifiant | Régénération périodique de l'identifiant, même sans reconnexion, pour réduire la durée de vie d'un identifiant volé. |
| Expiration d'inactivité | Destruction de la session après un délai sans requête (`session.lifetime`). |
| `gc_maxlifetime` | Durée après laquelle PHP peut ramasser les fichiers de session ; le ramassage est opportuniste, d'où le contrôle explicite du délai. |

## CSRF

| Terme | Définition |
|-------|------------|
| CSRF (Cross-Site Request Forgery) | Attaque où un site tiers déclenche une requête vers l'application en réutilisant la session de la victime déjà connectée. |
| Synchronizer token | Défense anti-CSRF : un jeton secret stocké en session est inséré dans chaque formulaire, puis comparé à la soumission. Un site tiers ne connaît pas le jeton. |
| Requête cross-site | Requête émise depuis un autre domaine que celui de l'application. |
| Origine | Triplet schéma + domaine + port qui sert de frontière de sécurité au navigateur. |
| Champ caché | `<input type="hidden">` transportant une valeur non affichée, ici le jeton CSRF. |

## XSS et injections

| Terme | Définition |
|-------|------------|
| XSS (Cross-Site Scripting) | Injection de code (souvent JavaScript) dans une page, exécuté par le navigateur des autres visiteurs. |
| XSS stocké | Charge enregistrée en base (commentaire, pseudo) puis rejouée à chaque affichage. |
| XSS réfléchi | Charge renvoyée immédiatement dans la réponse, typiquement depuis un paramètre d'URL. |
| Échappement | Conversion des caractères spéciaux en entités pour qu'ils s'affichent au lieu d'être interprétés. |
| `htmlspecialchars` | Fonction PHP d'échappement HTML (`<`, `>`, `&`, guillemets). |
| Contexte d'échappement | Endroit où la donnée est insérée (texte HTML, attribut, JavaScript, URL) ; chaque contexte demande un échappement différent. |
| Injection SQL | Attaque insérant du SQL malveillant via une entrée utilisateur non isolée. |
| Requête préparée | Requête SQL où les valeurs sont envoyées séparément du texte de la requête, ce qui empêche l'injection SQL. |
| Validation en entrée | Refus des données non conformes à leur format attendu (email, longueur, type). |

## Mots de passe et cryptographie

| Terme | Définition |
|-------|------------|
| Hachage | Transformation à sens unique d'une donnée ; un mot de passe haché n'est pas réversible. |
| Sel (salt) | Valeur aléatoire ajoutée avant hachage pour que deux mots de passe identiques produisent des empreintes différentes. |
| bcrypt | Algorithme de hachage de mots de passe lent et paramétrable, utilisé par `PASSWORD_DEFAULT`. |
| Coût (cost) | Paramètre réglant la lenteur de bcrypt ; plus il est élevé, plus une attaque par force brute coûte cher. |
| `password_hash` / `PASSWORD_DEFAULT` | Hachage de mot de passe avec algorithme robuste (bcrypt) et sel automatique. |
| `password_verify` | Compare un mot de passe en clair au hash stocké, sel et coût lus dans le hash lui-même. |
| Force brute | Essai systématique de mots de passe jusqu'à trouver le bon. |
| Table arc-en-ciel | Table d'empreintes précalculées ; rendue inutile par le sel. |
| Jeton (token) | Chaîne aléatoire imprévisible servant de preuve (vérification de compte, CSRF, réinitialisation). |
| `random_bytes` | Fonction PHP produisant des octets aléatoires cryptographiquement sûrs. |
| `hash_equals` | Comparaison de deux chaînes à temps constant, pour éviter les attaques temporelles. |
| Attaque temporelle | Déduction d'un secret en mesurant le temps de réponse d'une comparaison. |
| HTTPS | HTTP chiffré via TLS. |

## Base de données

| Terme | Définition |
|-------|------------|
| PDO (PHP Data Objects) | Couche d'accès aux bases de données de PHP. |
| DSN (Data Source Name) | Chaîne de connexion décrivant la base à joindre (hôte, port, nom). |
| `EMULATE_PREPARES` | Option PDO : à `false`, la préparation est réellement effectuée par le serveur de base de données et non simulée par PHP. |
| `ERRMODE_EXCEPTION` | Option PDO faisant lever une exception sur erreur SQL au lieu d'un code silencieux. |
| Marqueur nommé | Emplacement `:nom` dans une requête préparée, rempli à l'exécution. |
| Transaction | Groupe de requêtes validé d'un bloc (`COMMIT`) ou annulé entièrement (`ROLLBACK`). |
| Contrainte `UNIQUE` | Interdit deux lignes portant la même valeur (email, pseudo). |
| Clé étrangère | Colonne référençant la clé primaire d'une autre table. |
| `ON DELETE CASCADE` | Suppression automatique des lignes filles quand la ligne parente disparaît. |
| Index | Structure accélérant la recherche sur une ou plusieurs colonnes. |
| `ON CONFLICT` | Clause PostgreSQL décidant quoi faire quand un `INSERT` viole une contrainte d'unicité (ignorer ou mettre à jour). |
| Migration | Modification du schéma appliquée à une base existante sans perdre les données. |

## HTTP et routage

| Terme | Définition |
|-------|------------|
| `GET` / `POST` | Méthodes HTTP : `GET` lit une ressource, `POST` soumet une action qui modifie l'état. |
| Front controller | Point d'entrée unique (`public/index.php`) par lequel passent toutes les requêtes. |
| Route | Association d'une méthode et d'un chemin à une méthode de contrôleur. |
| Redirection | Réponse HTTP (`Location`) demandant au navigateur d'aller vers une autre URL. |
| 403 Forbidden | Code HTTP : requête comprise mais accès refusé. |
| 404 Not Found | Code HTTP : ressource inexistante. |
| En-tête (header) | Métadonnée d'une requête ou d'une réponse HTTP, sous forme `Nom: valeur`. |
| `Content-Type` | En-tête annonçant le format du corps (`text/html`, `image/jpeg`) ; c'est lui qui décide comment le navigateur interprète la réponse. |
| `Cache-Control` | En-tête réglant la mise en cache d'une réponse et sa durée. |
| `DocumentRoot` | Dossier exposé par le serveur web ; ici `public/`, pour que le code applicatif reste hors de portée des URL. |
| Same-origin policy | Règle du navigateur interdisant à une page de lire la réponse d'une autre origine. |
| CORS (Cross-Origin Resource Sharing) | En-têtes par lesquels un serveur autorise certaines origines à lire ses réponses ; un assouplissement de la same-origin policy, pas une protection. |
| Preflight | Requête `OPTIONS` envoyée par le navigateur pour demander l'autorisation avant une requête cross-origin non simple. |
| `Content-Security-Policy` | En-tête listant les sources de scripts et styles autorisées ; filet de sécurité contre le XSS. |
| Clickjacking | Attaque affichant le site dans une iframe invisible pour détourner les clics de la victime. |

## Comptes et contrôle d'accès

| Terme | Définition |
|-------|------------|
| Authentification | Établir qui est l'utilisateur (connexion). |
| Autorisation | Décider ce qu'un utilisateur authentifié a le droit de faire. |
| Vérification de compte | Confirmation de l'adresse email par un lien à jeton avant d'autoriser la connexion. |
| Réinitialisation de mot de passe | Envoi d'un jeton à usage unique et daté permettant de choisir un nouveau mot de passe. |
| Énumération de comptes | Fuite permettant de savoir si une adresse est inscrite, via un message d'erreur trop précis. |
| Privilège admin | Droit supplémentaire porté par la table `admins`, vérifié à chaque requête sensible. |
| Principe du moindre privilège | N'accorder que les droits strictement nécessaires. |

## Secrets et configuration

| Terme | Définition |
|-------|------------|
| Variable d'environnement | Valeur fournie au processus par son environnement d'exécution, hors du code source. |
| `.env` | Fichier de configuration de déploiement (URL, hôte, port) chargé au démarrage ; ne contient aucun credential. |
| Secret | Credential stocké dans un fichier dédié sous `secrets/`, ignoré par git. |
| `/run/secrets` | Emplacement où les fichiers de `secrets/` sont montés en lecture seule dans les conteneurs. |
| `POSTGRES_PASSWORD_FILE` | Variable lue par l'image PostgreSQL : le mot de passe est pris dans un fichier au lieu de l'environnement du conteneur. |
| Credential en dur | Identifiant ou mot de passe écrit dans le code ou le schéma SQL, donc publié avec le dépôt. |
| Rotation | Remplacement périodique d'un secret, ou après suspicion de fuite. |
