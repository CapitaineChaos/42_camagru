# Protection CSRF

Le jeton anti-CSRF vit en session (`$_SESSION['csrf_token']`), généré une fois
(32 octets aléatoires) et réutilisé pour toute la session.

`Core/Csrf.php` :

```php
public static function token(): string   // le jeton de session (créé au besoin)
public static function field(): string   // <input type="hidden" name="csrf_token" ...>
public static function check(mixed $token): bool   // comparaison à temps constant (hash_equals)
```

## L'attaque sans jeton

Rejouer la requête d'un formulaire du site depuis un autre site, en s'appuyant
sur le cookie que le navigateur attache automatiquement. Déroulé sur une version
sans jeton :

1. L'attaquant relève le formulaire visé sur Camagru. Exemple : suppression d'un
   montage, `POST /photo/delete` avec un champ `id`. Sans protection, c'est le
   seul paramètre à fournir.

2. Il recrée ce formulaire sur son propre site, champs en caché, valeurs
   choisies, et le fait s'envoyer au chargement :

   ```html
   <!-- page sur evil.example -->
   <form action="https://camagru.local/photo/delete" method="post">
       <input type="hidden" name="id" value="42">
   </form>
   <script>document.forms[0].submit()</script>
   ```

3. Il attire une victime connectée sur cette page (lien, pub, iframe).

4. Le formulaire s'auto-soumet. Le navigateur de la victime attache le cookie de
   session, comme pour toute requête vers `camagru.local`.

5. Le serveur reçoit un POST authentifié et supprime le montage 42 au nom de la
   victime. L'`id` est deviné ou relevé ailleurs ; rien n'est affiché à la
   victime.

Le cookie n'est ni volé ni lu, la page non plus : l'action est déclenchée en
laissant le navigateur fournir l'authentification.

Le jeton casse l'étape 2 : le formulaire forgé n'a pas de `csrf_token` valide, et
l'attaquant ne peut pas le lire, la same-origin policy interdisant de lire une
page Camagru depuis `evil.example`. `check()` échoue, réponse 403.

`SameSite=Lax` sur le cookie (`Core/Session.php`) bloque en amont l'envoi du
cookie sur un POST cross-site. Le jeton est la barrière indépendante du
navigateur.

## Vérification : automatique

Centralisée dans le routeur. Toute requête POST est contrôlée avant d'atteindre
le contrôleur ; jeton absent ou invalide, réponse 403.

`Core/Router.php` :

```php
if ($httpMethod === 'POST' && !Csrf::check($_POST['csrf_token'] ?? null)) {
    (new ErrorController())->forbidden('Security token invalid or expired. Reload the page and try again.');
    return;
}
```

Rien à appeler dans le contrôleur : le formulaire doit être en POST et porter le
jeton.

## Comparaison à temps constant (`hash_equals`)

`check()` compare le jeton reçu avec `hash_equals`, pas avec `==`/`===`.

`==` s'arrête au premier octet qui diffère :

```
"Xxxxxxxx…"  → faux dès l'octet 1
"aXxxxxxx…"  → faux à l'octet 2
```

La durée dépend du nombre d'octets corrects en tête. En mesurant le temps de
réponse sur un grand nombre d'essais, le secret se reconstitue octet par octet
(attaque temporelle).

`hash_equals` compare tous les octets à chaque appel, sans court-circuit ; sa
durée est indépendante du contenu.

Comparaison d'un secret (jeton CSRF, jeton de session, hash, signature) :
`hash_equals`. Mot de passe : `password_verify`.

L'attaque temporelle est peu réaliste ici, le jeton étant en session et régénéré
à chaque session.

## Protéger son propre formulaire

### 1 : Émettre le jeton dans la vue

Placer `Csrf::field()` à l'intérieur du `<form>` :

```php
<form method="post" action="/mon-action">
    <?= \App\Core\Csrf::field() ?>
    <p class="field flex-vt tight">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" required>
    </p>
    <p class="flex-hz"><button type="submit">Envoyer</button></p>
</form>
```

`Csrf::field()` génère le champ caché `csrf_token`, nom relu par le routeur dans
`$_POST`.

### 2 : Déclarer la route en POST

Dans `config/routes.php` :

```php
$router->post('/mon-action', [MonController::class, 'traiter']);
```

Le routeur vérifie le jeton pour cette route comme pour les autres.

## Points d'attention

- Uniquement POST. Une requête GET n'est pas vérifiée ; une action modifiant
  l'état (création, suppression, like) ne passe pas en GET.
- Le routeur lit `$_POST`. Un envoi `fetch`/AJAX transmet le jeton dans le corps
  au format formulaire (`FormData` ou `application/x-www-form-urlencoded`), pas
  en JSON : sinon `$_POST['csrf_token']` est vide et la requête est rejetée.

  ```js
  const data = new FormData(form); // récupère aussi le champ caché csrf_token
  fetch('/mon-action', { method: 'POST', body: data });
  ```

- Upload de fichier : `enctype="multipart/form-data"` remplit `$_POST` pour les
  champs non-fichiers, le champ caché fonctionne tel quel.
- Jeton par session, pas par formulaire. Tous les formulaires d'une même session
  partagent le même jeton.
